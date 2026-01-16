<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Simpele greedy poule-indeling service.
 *
 * Algoritme (zie docs/4-PLANNING/PLANNING_DYNAMISCHE_INDELING.md):
 * 1. Judoka's zijn al gesorteerd (door PouleIndelingService)
 * 2. Loop door judoka's, voeg toe aan poule als binnen limieten
 * 3. Merge kleine poules (< 4) met buren als mogelijk
 */
class DynamischeIndelingService
{
    private array $config = [
        'poule_grootte_voorkeur' => [5, 4, 6, 3],
        'clubspreiding' => true,
    ];

    /**
     * Get effectief gewicht: gewogen > ingeschreven > gewichtsklasse
     */
    private function getEffectiefGewicht($judoka): float
    {
        if ($judoka->gewicht_gewogen !== null) {
            return (float) $judoka->gewicht_gewogen;
        }
        if ($judoka->gewicht !== null) {
            return (float) $judoka->gewicht;
        }
        if ($judoka->gewichtsklasse && preg_match('/(\d+)/', $judoka->gewichtsklasse, $m)) {
            return (float) $m[1];
        }
        return 0.0;
    }

    /**
     * Bereken indeling met simpel greedy algoritme.
     *
     * @param Collection $judokas Gesorteerde judoka's (door caller)
     * @param int $maxLeeftijdVerschil Max jaren verschil in poule (uit config)
     * @param float $maxKgVerschil Max kg verschil in poule (uit config)
     * @param array $config Extra config (poule_grootte_voorkeur, clubspreiding)
     */
    public function berekenIndeling(
        Collection $judokas,
        int $maxLeeftijdVerschil = 2,
        float $maxKgVerschil = 3.0,
        array $config = []
    ): array {
        $this->config = array_merge($this->config, $config);

        if ($judokas->isEmpty()) {
            return $this->maakResultaat([], $judokas->count());
        }

        // Stap 1: Maak poules met greedy algoritme
        $poules = $this->maakPoulesGreedy($judokas, $maxKgVerschil, $maxLeeftijdVerschil);

        // Stap 2: Merge kleine poules (< 4 judoka's) met buren
        $poules = $this->mergeKleinePoules($poules, $maxKgVerschil, $maxLeeftijdVerschil);

        // Stap 3: Clubspreiding (optioneel)
        if ($this->config['clubspreiding'] && count($poules) > 1) {
            $poules = $this->pasClubspreidingToe($poules, $maxKgVerschil, $maxLeeftijdVerschil);
        }

        return $this->maakResultaat($poules, $judokas->count());
    }

    /**
     * Greedy algoritme: loop door gesorteerde judoka's, maak poules van max 5.
     */
    private function maakPoulesGreedy(Collection $judokas, float $maxKg, int $maxLeeftijd): array
    {
        $poules = [];
        $huidigePoule = [];
        $minGewicht = null;
        $maxGewicht = null;
        $minLeeftijd = null;
        $maxLeeftijdInPoule = null;

        $maxPouleGrootte = $this->config['poule_grootte_voorkeur'][0] ?? 5;

        foreach ($judokas as $judoka) {
            $gewicht = $this->getEffectiefGewicht($judoka);
            $leeftijd = $judoka->leeftijd ?? 0;

            if (empty($huidigePoule)) {
                // Eerste judoka in nieuwe poule
                $huidigePoule[] = $judoka;
                $minGewicht = $maxGewicht = $gewicht;
                $minLeeftijd = $maxLeeftijdInPoule = $leeftijd;
                continue;
            }

            // Bereken nieuwe ranges als we deze judoka toevoegen
            $nieuwMinGewicht = min($minGewicht, $gewicht);
            $nieuwMaxGewicht = max($maxGewicht, $gewicht);
            $nieuwMinLeeftijd = min($minLeeftijd, $leeftijd);
            $nieuwMaxLeeftijd = max($maxLeeftijdInPoule, $leeftijd);

            $gewichtVerschil = $nieuwMaxGewicht - $nieuwMinGewicht;
            $leeftijdVerschil = $nieuwMaxLeeftijd - $nieuwMinLeeftijd;

            // Check: past deze judoka in de huidige poule?
            $pastInPoule = $gewichtVerschil <= $maxKg
                && $leeftijdVerschil <= $maxLeeftijd
                && count($huidigePoule) < $maxPouleGrootte;

            if ($pastInPoule) {
                // Voeg toe aan huidige poule
                $huidigePoule[] = $judoka;
                $minGewicht = $nieuwMinGewicht;
                $maxGewicht = $nieuwMaxGewicht;
                $minLeeftijd = $nieuwMinLeeftijd;
                $maxLeeftijdInPoule = $nieuwMaxLeeftijd;
            } else {
                // Start nieuwe poule
                $poules[] = $this->maakPouleData($huidigePoule);
                $huidigePoule = [$judoka];
                $minGewicht = $maxGewicht = $gewicht;
                $minLeeftijd = $maxLeeftijdInPoule = $leeftijd;
            }
        }

        // Laatste poule opslaan
        if (!empty($huidigePoule)) {
            $poules[] = $this->maakPouleData($huidigePoule);
        }

        return $poules;
    }

    /**
     * Merge kleine poules (< 4 judoka's) met aangrenzende poules.
     */
    private function mergeKleinePoules(array $poules, float $maxKg, int $maxLeeftijd): array
    {
        if (count($poules) < 2) {
            return $poules;
        }

        $gewijzigd = true;
        $maxIteraties = 20;
        $iteratie = 0;

        while ($gewijzigd && $iteratie < $maxIteraties) {
            $gewijzigd = false;
            $iteratie++;

            for ($i = 0; $i < count($poules); $i++) {
                $poule = $poules[$i];
                $aantal = count($poule['judokas']);

                // Skip als poule groot genoeg is
                if ($aantal >= 4) {
                    continue;
                }

                // Probeer te mergen met vorige poule
                if ($i > 0 && $this->kanMergen($poules[$i - 1], $poule, $maxKg, $maxLeeftijd)) {
                    $poules[$i - 1] = $this->mergeTweePoules($poules[$i - 1], $poule);
                    array_splice($poules, $i, 1);
                    $gewijzigd = true;
                    break;
                }

                // Probeer te mergen met volgende poule
                if ($i < count($poules) - 1 && $this->kanMergen($poule, $poules[$i + 1], $maxKg, $maxLeeftijd)) {
                    $poules[$i] = $this->mergeTweePoules($poule, $poules[$i + 1]);
                    array_splice($poules, $i + 1, 1);
                    $gewijzigd = true;
                    break;
                }
            }
        }

        return $poules;
    }

    /**
     * Check of twee poules samengevoegd kunnen worden binnen limieten.
     */
    private function kanMergen(array $poule1, array $poule2, float $maxKg, int $maxLeeftijd): bool
    {
        $gecombineerd = array_merge($poule1['judokas'], $poule2['judokas']);

        // Check grootte (max 6, of hoogste voorkeur)
        $maxGrootte = max($this->config['poule_grootte_voorkeur'] ?? [6]);
        if (count($gecombineerd) > $maxGrootte) {
            return false;
        }

        // Check gewicht
        $gewichten = array_map(fn($j) => $this->getEffectiefGewicht($j), $gecombineerd);
        if (max($gewichten) - min($gewichten) > $maxKg) {
            return false;
        }

        // Check leeftijd
        $leeftijden = array_map(fn($j) => $j->leeftijd ?? 0, $gecombineerd);
        if (max($leeftijden) - min($leeftijden) > $maxLeeftijd) {
            return false;
        }

        return true;
    }

    /**
     * Merge twee poules tot één.
     */
    private function mergeTweePoules(array $poule1, array $poule2): array
    {
        $gecombineerd = array_merge($poule1['judokas'], $poule2['judokas']);
        return $this->maakPouleData($gecombineerd);
    }

    /**
     * Pas clubspreiding toe: probeer judoka's van dezelfde club te verdelen.
     */
    private function pasClubspreidingToe(array $poules, float $maxKg, int $maxLeeftijd): array
    {
        // Voor elke poule, check voor club duplicaten
        for ($p = 0; $p < count($poules); $p++) {
            $clubCount = [];
            foreach ($poules[$p]['judokas'] as $idx => $judoka) {
                $clubId = $judoka->club_id ?? 0;
                $clubCount[$clubId][] = $idx;
            }

            // Voor clubs met meerdere judoka's, probeer te swappen
            foreach ($clubCount as $clubId => $indices) {
                if (count($indices) <= 1) continue;

                // Probeer tweede judoka te swappen naar andere poule
                for ($i = 1; $i < count($indices); $i++) {
                    $judokaIdx = $indices[$i];
                    $judoka = $poules[$p]['judokas'][$judokaIdx];

                    // Zoek swap kandidaat in andere poule
                    for ($q = 0; $q < count($poules); $q++) {
                        if ($q === $p) continue;

                        foreach ($poules[$q]['judokas'] as $kandidaatIdx => $kandidaat) {
                            if ($this->kanSwappen($poules[$p], $judokaIdx, $poules[$q], $kandidaatIdx, $maxKg, $maxLeeftijd)) {
                                // Check dat kandidaat niet zelfde club is
                                if (($kandidaat->club_id ?? 0) !== $clubId) {
                                    // Swap
                                    $poules[$p]['judokas'][$judokaIdx] = $kandidaat;
                                    $poules[$q]['judokas'][$kandidaatIdx] = $judoka;

                                    // Update poule data
                                    $poules[$p] = $this->maakPouleData($poules[$p]['judokas']);
                                    $poules[$q] = $this->maakPouleData($poules[$q]['judokas']);
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $poules;
    }

    /**
     * Check of swap beide poules binnen limieten houdt.
     */
    private function kanSwappen(array $poule1, int $idx1, array $poule2, int $idx2, float $maxKg, int $maxLeeftijd): bool
    {
        // Simuleer swap
        $nieuw1 = $poule1['judokas'];
        $nieuw2 = $poule2['judokas'];
        $nieuw1[$idx1] = $poule2['judokas'][$idx2];
        $nieuw2[$idx2] = $poule1['judokas'][$idx1];

        // Check beide poules
        return $this->pouleIsValid($nieuw1, $maxKg, $maxLeeftijd)
            && $this->pouleIsValid($nieuw2, $maxKg, $maxLeeftijd);
    }

    /**
     * Check of een poule binnen de limieten valt.
     */
    private function pouleIsValid(array $judokas, float $maxKg, int $maxLeeftijd): bool
    {
        if (empty($judokas)) return true;

        $gewichten = array_map(fn($j) => $this->getEffectiefGewicht($j), $judokas);
        $leeftijden = array_map(fn($j) => $j->leeftijd ?? 0, $judokas);

        return (max($gewichten) - min($gewichten)) <= $maxKg
            && (max($leeftijden) - min($leeftijden)) <= $maxLeeftijd;
    }

    /**
     * Maak poule data array met ranges.
     */
    private function maakPouleData(array $judokas): array
    {
        $gewichten = array_map(fn($j) => $this->getEffectiefGewicht($j), $judokas);
        $leeftijden = array_map(fn($j) => $j->leeftijd ?? 0, $judokas);

        $minGewicht = !empty($gewichten) ? min($gewichten) : 0;
        $maxGewicht = !empty($gewichten) ? max($gewichten) : 0;
        $minLeeftijd = !empty($leeftijden) ? min($leeftijden) : 0;
        $maxLeeftijd = !empty($leeftijden) ? max($leeftijden) : 0;

        return [
            'judokas' => $judokas,
            'leeftijd_range' => $maxLeeftijd - $minLeeftijd,
            'gewicht_range' => round($maxGewicht - $minGewicht, 1),
            'band_range' => $this->berekenBandRange($judokas),
            'leeftijd_groep' => $minLeeftijd == $maxLeeftijd ? "{$minLeeftijd}j" : "{$minLeeftijd}-{$maxLeeftijd}j",
            'gewicht_groep' => $minGewicht == $maxGewicht ? "{$minGewicht}kg" : "{$minGewicht}-{$maxGewicht}kg",
        ];
    }

    /**
     * Bereken band range (verschil tussen hoogste en laagste).
     */
    private function berekenBandRange(array $judokas): int
    {
        $bandVolgorde = ['wit' => 0, 'geel' => 1, 'oranje' => 2, 'groen' => 3, 'blauw' => 4, 'bruin' => 5, 'zwart' => 6];

        $niveaus = array_map(function($j) use ($bandVolgorde) {
            $band = strtolower(explode(' ', $j->band ?? 'wit')[0]);
            return $bandVolgorde[$band] ?? 0;
        }, $judokas);

        return !empty($niveaus) ? max($niveaus) - min($niveaus) : 0;
    }

    /**
     * Maak resultaat array.
     */
    private function maakResultaat(array $poules, int $totaalJudokas): array
    {
        $totaalIngedeeld = array_sum(array_map(fn($p) => count($p['judokas']), $poules));

        return [
            'poules' => $poules,
            'score' => $this->berekenScore($poules),
            'totaal_ingedeeld' => $totaalIngedeeld,
            'totaal_judokas' => $totaalJudokas,
            'aantal_poules' => count($poules),
            'params' => [],
            'stats' => $this->berekenStatistieken($poules),
        ];
    }

    /**
     * Bereken score (voor vergelijking varianten).
     */
    private function berekenScore(array $poules): float
    {
        $score = 0;
        $voorkeur = $this->config['poule_grootte_voorkeur'] ?? [5, 4, 6, 3];

        foreach ($poules as $poule) {
            $grootte = count($poule['judokas']);
            $positie = array_search($grootte, $voorkeur);

            // Penalty gebaseerd op positie in voorkeur (of 10 als niet in lijst)
            $score += $positie !== false ? $positie : 10;

            // Extra penalty voor heel kleine poules
            if ($grootte < 3) {
                $score += 5;
            }
        }

        return $score;
    }

    /**
     * Bereken statistieken.
     */
    private function berekenStatistieken(array $poules): array
    {
        if (empty($poules)) {
            return ['leeftijd_gem' => 0, 'leeftijd_max' => 0, 'gewicht_gem' => 0, 'gewicht_max' => 0];
        }

        $leeftijdRanges = array_column($poules, 'leeftijd_range');
        $gewichtRanges = array_column($poules, 'gewicht_range');

        return [
            'leeftijd_gem' => round(array_sum($leeftijdRanges) / count($poules), 1),
            'leeftijd_max' => max($leeftijdRanges),
            'gewicht_gem' => round(array_sum($gewichtRanges) / count($poules), 1),
            'gewicht_max' => max($gewichtRanges),
        ];
    }

    /**
     * Genereer varianten (voor backwards compatibility).
     * Retourneert alleen de standaard indeling.
     */
    public function genereerVarianten(Collection $judokas, array $config = []): array
    {
        $indeling = $this->berekenIndeling($judokas, 2, 3.0, $config);

        return [
            'varianten' => [$indeling],
            'tijdMs' => 0,
        ];
    }
}
