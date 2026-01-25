<?php

namespace App\Services\BlokVerdeling;

/**
 * Helper for placing categories in blocks with adjacency rules
 */
class BlokPlaatsingsHelper
{
    private BlokCapaciteitHelper $capaciteitHelper;

    public function __construct(BlokCapaciteitHelper $capaciteitHelper)
    {
        $this->capaciteitHelper = $capaciteitHelper;
    }

    /**
     * Find best block with strict adjacency rules: +1, -1, +2 (max!)
     *
     * @param int $vorigeBlokIndex Previous block index
     * @param int $wedstrijden Number of matches to place
     * @param array $capaciteit Current capacity state
     * @param array $blokken Array of Blok models
     * @param int $numBlokken Total number of blocks
     * @param int $aansluitingVariant Strategy variant (0-5)
     * @param float $verdelingGewicht Distribution weight (0-1)
     * @param float $randomFactor Random factor for variation (0-1)
     * @return int Best block index
     */
    public function vindBesteBlokMetAansluiting(
        int $vorigeBlokIndex,
        int $wedstrijden,
        array $capaciteit,
        array $blokken,
        int $numBlokken,
        int $aansluitingVariant,
        float $verdelingGewicht,
        float $randomFactor = 0.0
    ): int {
        $opties = $this->getAansluitingOpties($aansluitingVariant);
        $kandidaten = [];

        foreach ($opties as $offset) {
            $idx = $vorigeBlokIndex + $offset;
            if ($idx < 0 || $idx >= $numBlokken) continue;

            $blok = $blokken[$idx];
            if (!$this->capaciteitHelper->kanPlaatsen($capaciteit, $blok->id, $wedstrijden)) {
                continue;
            }

            $cap = $capaciteit[$blok->id];
            $gewenst = max(1, $cap['gewenst']);
            $nieuweActueel = $cap['actueel'] + $wedstrijden;

            // Score: combinatie van vulgraad en aansluiting
            $vulgraad = $nieuweActueel / $gewenst;
            $aansluitingPenalty = abs($offset) * 20;
            $score = ($vulgraad * 50 * $verdelingGewicht) + ($aansluitingPenalty * (1 - $verdelingGewicht));

            // Add random noise for variation
            $score += mt_rand(0, 100) * $randomFactor * 0.5;

            $kandidaten[] = ['idx' => $idx, 'score' => $score];
        }

        if (empty($kandidaten)) {
            return $this->capaciteitHelper->vindBlokMetMeesteRuimte($capaciteit, $blokken);
        }

        usort($kandidaten, fn($a, $b) => $a['score'] <=> $b['score']);

        // With random factor: sometimes pick 2nd or 3rd best
        if ($randomFactor > 0.7 && count($kandidaten) > 1) {
            return $kandidaten[1]['idx'];
        }
        if ($randomFactor > 0.9 && count($kandidaten) > 2) {
            return $kandidaten[2]['idx'];
        }

        return $kandidaten[0]['idx'];
    }

    /**
     * Find best block for variable pool (prioritize even distribution)
     */
    public function vindBesteBlokVoorVariabelePoule(
        int $wedstrijden,
        array $capaciteit,
        array $blokken,
        int $numBlokken
    ): int {
        $besteIndex = 0;
        $meesteRuimte = -PHP_INT_MAX;

        foreach ($blokken as $index => $blok) {
            if (!$this->capaciteitHelper->kanPlaatsen($capaciteit, $blok->id, $wedstrijden)) {
                continue;
            }

            $ruimte = $capaciteit[$blok->id]['ruimte'];
            if ($ruimte > $meesteRuimte) {
                $meesteRuimte = $ruimte;
                $besteIndex = $index;
            }
        }

        return $besteIndex;
    }

    /**
     * Get adjacency options based on strategy variant
     */
    private function getAansluitingOpties(int $variant): array
    {
        return match ($variant) {
            0 => [0, 1, -1, 2],   // Standaard: vooruit
            1 => [0, -1, 1, 2],   // Achteruit eerst
            2 => [0, 1, 2, -1],   // Vooruit, dan ver, dan terug
            3 => [1, 0, 2, -1],   // +1 eerst (spread out)
            4 => [0, 2, 1, -1],   // +2 als tweede optie
            5 => [1, -1, 0, 2],   // Wissel eerst
            default => BlokVerdelingConstants::AANSLUITING_OPTIES,
        };
    }
}
