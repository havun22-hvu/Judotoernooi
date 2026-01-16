<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Dynamische poule-indeling service.
 *
 * Gebruikt Python Greedy++ solver voor optimale indeling.
 * Zie: docs/4-PLANNING/PLANNING_DYNAMISCHE_INDELING.md
 * Solver: scripts/poule_solver.py
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
     * Bereken indeling via Python Greedy++ solver.
     *
     * @param Collection $judokas Judoka's in deze categorie
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

        // Roep Python solver aan
        $poules = $this->callPythonSolver($judokas, $maxKgVerschil, $maxLeeftijdVerschil);

        // Clubspreiding (optioneel, Python doet dit niet)
        if ($this->config['clubspreiding'] && count($poules) > 1) {
            $poules = $this->pasClubspreidingToe($poules, $maxKgVerschil, $maxLeeftijdVerschil);
        }

        return $this->maakResultaat($poules, $judokas->count());
    }

    /**
     * Roep Python Greedy++ solver aan.
     *
     * @param Collection $judokas
     * @param float $maxKg
     * @param int $maxLeeftijd
     * @return array Poules met judoka objecten
     */
    private function callPythonSolver(Collection $judokas, float $maxKg, int $maxLeeftijd): array
    {
        // Bouw input voor Python solver
        $judokaMap = [];
        $pythonInput = [
            'max_kg_verschil' => $maxKg,
            'max_leeftijd_verschil' => $maxLeeftijd,
            'poule_grootte_voorkeur' => $this->config['poule_grootte_voorkeur'],
            'judokas' => [],
        ];

        foreach ($judokas as $judoka) {
            $id = $judoka->id;
            $judokaMap[$id] = $judoka;
            $pythonInput['judokas'][] = [
                'id' => $id,
                'leeftijd' => $judoka->leeftijd ?? 0,
                'gewicht' => $this->getEffectiefGewicht($judoka),
                'band' => $this->bandNaarNummer($judoka->band ?? 'wit'),
                'club_id' => $judoka->club_id ?? 0,
            ];
        }

        // Roep Python aan
        $scriptPath = base_path('scripts/poule_solver.py');
        $pythonCmd = $this->findPython();

        if (!$pythonCmd || !file_exists($scriptPath)) {
            Log::warning('Python solver niet beschikbaar, fallback naar simpele indeling');
            return $this->simpleFallback($judokas, $maxKg, $maxLeeftijd);
        }

        $inputJson = json_encode($pythonInput);

        // Execute Python script
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = proc_open(
            [$pythonCmd, $scriptPath],
            $descriptors,
            $pipes,
            base_path('scripts')
        );

        if (!is_resource($process)) {
            Log::error('Kon Python proces niet starten');
            return $this->simpleFallback($judokas, $maxKg, $maxLeeftijd);
        }

        // Schrijf input en sluit stdin
        fwrite($pipes[0], $inputJson);
        fclose($pipes[0]);

        // Lees output
        $output = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0 || empty($output)) {
            Log::error('Python solver fout', ['exitCode' => $exitCode, 'stderr' => $stderr]);
            return $this->simpleFallback($judokas, $maxKg, $maxLeeftijd);
        }

        // Parse Python output
        $result = json_decode($output, true);

        if (!$result || !isset($result['success']) || !$result['success']) {
            Log::error('Python solver gaf ongeldige output', ['output' => $output]);
            return $this->simpleFallback($judokas, $maxKg, $maxLeeftijd);
        }

        // Converteer Python output naar PHP poules met judoka objecten
        $poules = [];
        foreach ($result['poules'] as $pythonPoule) {
            $judokasInPoule = [];
            foreach ($pythonPoule['judoka_ids'] as $id) {
                if (isset($judokaMap[$id])) {
                    $judokasInPoule[] = $judokaMap[$id];
                }
            }
            if (!empty($judokasInPoule)) {
                $poules[] = $this->maakPouleData($judokasInPoule);
            }
        }

        return $poules;
    }

    /**
     * Vind Python executable (python3 of python).
     */
    private function findPython(): ?string
    {
        // Windows
        if (PHP_OS_FAMILY === 'Windows') {
            $paths = ['python', 'python3', 'py'];
            foreach ($paths as $cmd) {
                exec("where $cmd 2>NUL", $output, $exitCode);
                if ($exitCode === 0) {
                    return $cmd;
                }
                $output = [];
            }
        }
        // Linux/Mac
        else {
            $paths = ['python3', 'python'];
            foreach ($paths as $cmd) {
                exec("which $cmd 2>/dev/null", $output, $exitCode);
                if ($exitCode === 0) {
                    return $cmd;
                }
                $output = [];
            }
        }
        return null;
    }

    /**
     * Simpele fallback als Python niet beschikbaar is.
     * Maakt poules van max 5 judoka's, gesorteerd op leeftijd en gewicht.
     */
    private function simpleFallback(Collection $judokas, float $maxKg, int $maxLeeftijd): array
    {
        $maxGrootte = $this->config['poule_grootte_voorkeur'][0] ?? 5;
        $gesorteerd = $judokas->sortBy([
            fn($a, $b) => ($a->leeftijd ?? 0) <=> ($b->leeftijd ?? 0),
            fn($a, $b) => $this->getEffectiefGewicht($a) <=> $this->getEffectiefGewicht($b),
        ])->values();

        $poules = [];
        $huidigePoule = [];
        $minGewicht = $maxGewicht = $minLft = $maxLft = null;

        foreach ($gesorteerd as $judoka) {
            $gewicht = $this->getEffectiefGewicht($judoka);
            $leeftijd = $judoka->leeftijd ?? 0;

            if (empty($huidigePoule)) {
                $huidigePoule[] = $judoka;
                $minGewicht = $maxGewicht = $gewicht;
                $minLft = $maxLft = $leeftijd;
                continue;
            }

            $nieuwMinGew = min($minGewicht, $gewicht);
            $nieuwMaxGew = max($maxGewicht, $gewicht);
            $nieuwMinLft = min($minLft, $leeftijd);
            $nieuwMaxLft = max($maxLft, $leeftijd);

            $past = ($nieuwMaxGew - $nieuwMinGew) <= $maxKg
                && ($nieuwMaxLft - $nieuwMinLft) <= $maxLeeftijd
                && count($huidigePoule) < $maxGrootte;

            if ($past) {
                $huidigePoule[] = $judoka;
                $minGewicht = $nieuwMinGew;
                $maxGewicht = $nieuwMaxGew;
                $minLft = $nieuwMinLft;
                $maxLft = $nieuwMaxLft;
            } else {
                $poules[] = $this->maakPouleData($huidigePoule);
                $huidigePoule = [$judoka];
                $minGewicht = $maxGewicht = $gewicht;
                $minLft = $maxLft = $leeftijd;
            }
        }

        if (!empty($huidigePoule)) {
            $poules[] = $this->maakPouleData($huidigePoule);
        }

        return $poules;
    }

    /**
     * Converteer band naar nummer (voor Python).
     */
    private function bandNaarNummer(?string $band): int
    {
        $mapping = ['wit' => 0, 'geel' => 1, 'oranje' => 2, 'groen' => 3, 'blauw' => 4, 'bruin' => 5, 'zwart' => 6];
        $lower = strtolower(explode(' ', $band ?? 'wit')[0]);
        return $mapping[$lower] ?? 0;
    }

    /**
     * Pas clubspreiding toe: probeer judoka's van dezelfde club te verdelen.
     */
    private function pasClubspreidingToe(array $poules, float $maxKg, int $maxLeeftijd): array
    {
        for ($p = 0; $p < count($poules); $p++) {
            $clubCount = [];
            foreach ($poules[$p]['judokas'] as $idx => $judoka) {
                $clubId = $judoka->club_id ?? 0;
                $clubCount[$clubId][] = $idx;
            }

            foreach ($clubCount as $clubId => $indices) {
                if (count($indices) <= 1) continue;

                for ($i = 1; $i < count($indices); $i++) {
                    $judokaIdx = $indices[$i];
                    $judoka = $poules[$p]['judokas'][$judokaIdx];

                    for ($q = 0; $q < count($poules); $q++) {
                        if ($q === $p) continue;

                        foreach ($poules[$q]['judokas'] as $kandidaatIdx => $kandidaat) {
                            if ($this->kanSwappen($poules[$p], $judokaIdx, $poules[$q], $kandidaatIdx, $maxKg, $maxLeeftijd)) {
                                if (($kandidaat->club_id ?? 0) !== $clubId) {
                                    $poules[$p]['judokas'][$judokaIdx] = $kandidaat;
                                    $poules[$q]['judokas'][$kandidaatIdx] = $judoka;
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
        $nieuw1 = $poule1['judokas'];
        $nieuw2 = $poule2['judokas'];
        $nieuw1[$idx1] = $poule2['judokas'][$idx2];
        $nieuw2[$idx2] = $poule1['judokas'][$idx1];

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
     * Bereken band range.
     */
    private function berekenBandRange(array $judokas): int
    {
        $niveaus = array_map(fn($j) => $this->bandNaarNummer($j->band ?? 'wit'), $judokas);
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
     * Bereken score op basis van poule_grootte_voorkeur.
     */
    private function berekenScore(array $poules): float
    {
        $score = 0;
        $voorkeur = $this->config['poule_grootte_voorkeur'] ?? [5, 4, 6, 3];

        foreach ($poules as $poule) {
            $grootte = count($poule['judokas']);

            if ($grootte <= 1) {
                $score += 100; // Orphan
            } elseif (in_array($grootte, $voorkeur)) {
                $index = array_search($grootte, $voorkeur);
                if ($index === 0) {
                    $score += 0;  // Eerste voorkeur
                } elseif ($index === 1) {
                    $score += 5;  // Tweede voorkeur
                } else {
                    $score += 40; // Rest van voorkeurlijst
                }
            } else {
                $score += 70; // Niet in voorkeurlijst
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
