<?php

namespace App\Services\BlokVerdeling;

/**
 * Calculate quality scores for block distribution variants
 *
 * Verdeling: som van absolute % afwijkingen per blok (lager = beter)
 * Aansluiting: punten per overgang tussen gewichtscategorieën
 */
class BlokScoreCalculator
{
    /**
     * Calculate scores for a distribution variant
     *
     * @param array $toewijzingen category_key => blok_nummer
     * @param array $capaciteit blok_id => ['gewenst' => x, 'actueel' => y, 'ruimte' => z]
     * @param array $blokken Array of Blok models
     * @param array $perLeeftijd Grouped categories by age class
     * @param float $verdelingGewicht Weight for distribution score (0-1)
     * @param float $aansluitingGewicht Weight for continuity score (0-1)
     * @return array Score breakdown
     */
    public function berekenScores(
        array $toewijzingen,
        array $capaciteit,
        array $blokken,
        array $perLeeftijd,
        float $verdelingGewicht = 0.5,
        float $aansluitingGewicht = 0.5
    ): array {
        $verdelingResult = $this->berekenVerdelingScore($capaciteit, $blokken);
        $aansluitingResult = $this->berekenAansluitingScore($toewijzingen, $perLeeftijd);

        // Totaal score: gewogen som (lager = beter)
        $totaalScore = ($verdelingGewicht * $verdelingResult['score'])
            + ($aansluitingGewicht * $aansluitingResult['score']);

        return [
            'verdeling_score' => round($verdelingResult['score'], 1),
            'aansluiting_score' => $aansluitingResult['score'],
            'totaal_score' => round($totaalScore, 1),
            'max_afwijking_pct' => round($verdelingResult['max_afwijking'], 1),
            'overgangen' => $aansluitingResult['overgangen'],
            'aflopend' => $aansluitingResult['aflopend'],
            'blok_stats' => $verdelingResult['blok_stats'],
            'is_valid' => $verdelingResult['is_valid'],
            'gewichten' => [
                'verdeling' => round($verdelingGewicht * 100),
                'aansluiting' => round($aansluitingGewicht * 100),
            ],
        ];
    }

    /**
     * Calculate distribution score: SUM of absolute % deviations per block
     */
    private function berekenVerdelingScore(array $capaciteit, array $blokken): array
    {
        $score = 0;
        $maxAfwijking = 0;
        $blokStats = [];
        $isValid = true;

        foreach ($blokken as $blok) {
            $cap = $capaciteit[$blok->id];
            $gewenst = max(1, $cap['gewenst']);
            $afwijkingPct = abs(($cap['actueel'] - $gewenst) / $gewenst * 100);

            $score += $afwijkingPct;
            $maxAfwijking = max($maxAfwijking, $afwijkingPct);

            // HARD LIMIT: if any block exceeds threshold, variant is INVALID
            if ($afwijkingPct > BlokVerdelingConstants::MAX_AFWIJKING_PERCENTAGE) {
                $isValid = false;
            }

            $blokStats[$blok->nummer] = [
                'actueel' => $cap['actueel'],
                'gewenst' => $gewenst,
                'afwijking_pct' => round($afwijkingPct, 1),
            ];
        }

        return [
            'score' => $score,
            'max_afwijking' => $maxAfwijking,
            'blok_stats' => $blokStats,
            'is_valid' => $isValid,
        ];
    }

    /**
     * Calculate continuity score: points per transition between weight classes
     *
     * Zelfde blok = 0, +1 = 10, -1 = 20, +2 = 30, verder = 50+
     */
    private function berekenAansluitingScore(array $toewijzingen, array $perLeeftijd): array
    {
        $score = 0;
        $overgangen = 0;
        $aflopendCount = 0;

        foreach ($perLeeftijd as $leeftijd => $gewichten) {
            $vorigBlok = null;
            $eersteBlok = null;
            $laatsteBlok = null;

            foreach ($gewichten as $cat) {
                $key = $cat['leeftijd'] . '|' . $cat['gewicht'];
                $blokNr = $toewijzingen[$key] ?? null;

                if ($blokNr !== null) {
                    if ($eersteBlok === null) $eersteBlok = $blokNr;
                    $laatsteBlok = $blokNr;
                }

                if ($vorigBlok !== null && $blokNr !== null) {
                    $verschil = $blokNr - $vorigBlok;
                    $overgangen++;

                    $punten = $this->berekenOvergangPunten($verschil);
                    $score += $punten;
                }
                $vorigBlok = $blokNr;
            }

            // Check: gaat deze leeftijd AFLOPEND? (laatste blok < eerste blok)
            if ($eersteBlok !== null && $laatsteBlok !== null && $laatsteBlok < $eersteBlok) {
                $aflopendCount++;
                $score += BlokVerdelingConstants::AANSLUITING_AFLOPEND_PENALTY;
            }
        }

        return [
            'score' => $score,
            'overgangen' => $overgangen,
            'aflopend' => $aflopendCount,
        ];
    }

    /**
     * Calculate points for a single block transition
     */
    private function berekenOvergangPunten(int $verschil): int
    {
        return match (true) {
            $verschil === 0 => BlokVerdelingConstants::AANSLUITING_ZELFDE_BLOK,
            $verschil === 1 => BlokVerdelingConstants::AANSLUITING_VOLGEND_BLOK,
            $verschil === -1 => BlokVerdelingConstants::AANSLUITING_VORIG_BLOK,
            $verschil === 2 => BlokVerdelingConstants::AANSLUITING_TWEE_BLOKKEN,
            $verschil < -1 => BlokVerdelingConstants::AANSLUITING_VERDER + abs($verschil) * 10,
            default => BlokVerdelingConstants::AANSLUITING_VERDER + $verschil * 10,
        };
    }
}
