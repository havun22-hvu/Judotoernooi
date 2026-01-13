<?php

namespace App\Services;

use App\Models\Poule;
use App\Models\Toernooi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for distributing variable (lft-kg) categories over blocks
 *
 * Unlike BlokMatVerdelingService which works with fixed category labels,
 * this service handles dynamic categories where pools are sorted by
 * age → weight and split at optimal boundaries.
 */
class VariabeleBlokVerdelingService
{
    /**
     * Check if toernooi has variable categories (lft-kg labels)
     */
    public function heeftVariabeleCategorieen(Toernooi $toernooi): bool
    {
        // Check poules for dynamic titles (contain " · " separator or "lft-kg")
        return $toernooi->poules()
            ->where(function ($q) {
                $q->where('titel', 'like', '%lft-kg%')
                    ->orWhere('titel', 'like', '% · %')
                    ->orWhere('leeftijdsklasse', 'like', '%lft-kg%')
                    ->orWhere('leeftijdsklasse', 'like', '%j · %');
            })
            ->exists();
    }

    /**
     * Generate distribution variants for variable categories
     *
     * Algorithm:
     * 1. Sort all pools by MIN age → MIN weight
     * 2. Calculate target matches per block
     * 3. Trial & error to find optimal split points
     * 4. At age boundaries, try different weight splits
     *
     * @param int $userVerdelingGewicht 0-100 (weight for equal distribution)
     * @return array ['varianten' => [...], 'stats' => [...]]
     */
    public function genereerVarianten(Toernooi $toernooi, int $userVerdelingGewicht = 50): array
    {
        $blokken = $toernooi->blokken->sortBy('nummer')->values();

        if ($blokken->isEmpty()) {
            throw new \RuntimeException('Geen blokken gevonden');
        }

        $numBlokken = $blokken->count();

        // Get all variable pools with their ranges
        $poules = $this->getVariabelePoules($toernooi);

        if ($poules->isEmpty()) {
            return ['varianten' => [], 'message' => 'Geen variabele poules gevonden'];
        }

        // Calculate target matches per block
        $totaalWedstrijden = $poules->sum('aantal_wedstrijden');
        $doelPerBlok = (int) ceil($totaalWedstrijden / $numBlokken);

        Log::info('VariabeleBlokVerdeling start', [
            'blokken' => $numBlokken,
            'poules' => $poules->count(),
            'totaal_wedstrijden' => $totaalWedstrijden,
            'doel_per_blok' => $doelPerBlok,
        ]);

        // Sort pools by age, then weight
        $gesorteerdePoules = $this->sorteerPoules($poules);

        // Generate variants with different split strategies
        $startTime = microtime(true);
        $varianten = [];
        $gezien = [];

        // Try multiple split strategies
        for ($strategie = 0; $strategie < 20; $strategie++) {
            $variant = $this->berekenVerdeling(
                $gesorteerdePoules,
                $blokken,
                $doelPerBlok,
                $strategie,
                $userVerdelingGewicht
            );

            // Hash to detect duplicates
            $hash = md5(json_encode($variant['toewijzingen']));
            if (!isset($gezien[$hash])) {
                $gezien[$hash] = true;
                $varianten[] = $variant;
            }

            // Stop after 3 seconds
            if (microtime(true) - $startTime > 3.0) {
                break;
            }
        }

        // Sort by score (lower = better)
        usort($varianten, fn($a, $b) => $a['totaal_score'] <=> $b['totaal_score']);

        // Take top 5
        $varianten = array_slice($varianten, 0, 5);

        // Add category groupings to each variant
        foreach ($varianten as &$variant) {
            $variant['categorie_groepen'] = $this->berekenCategorieGroepen(
                $gesorteerdePoules,
                $variant['toewijzingen'],
                $blokken
            );
        }

        $elapsed = round(microtime(true) - $startTime, 2);

        Log::info('VariabeleBlokVerdeling klaar', [
            'varianten' => count($varianten),
            'tijd_sec' => $elapsed,
            'beste_score' => $varianten[0]['totaal_score'] ?? 'N/A',
        ]);

        return [
            'varianten' => $varianten,
            'stats' => [
                'poules' => $poules->count(),
                'totaal_wedstrijden' => $totaalWedstrijden,
                'doel_per_blok' => $doelPerBlok,
                'tijd_sec' => $elapsed,
            ],
        ];
    }

    /**
     * Get all variable pools with calculated age/weight ranges
     */
    private function getVariabelePoules(Toernooi $toernooi): Collection
    {
        $huidigJaar = now()->year;

        return $toernooi->poules()
            ->with('judokas')
            ->where('type', '!=', 'kruisfinale')
            ->where('blok_vast', false)
            ->get()
            ->map(function ($poule) use ($huidigJaar) {
                $judokas = $poule->judokas;

                // Calculate age range
                $leeftijden = $judokas->pluck('geboortejaar')
                    ->filter()
                    ->map(fn($gj) => $huidigJaar - $gj);

                $minLeeftijd = $leeftijden->min() ?? 0;
                $maxLeeftijd = $leeftijden->max() ?? 0;

                // Calculate weight range
                $gewichten = $judokas->map(fn($j) => $this->getEffectiefGewicht($j))->filter();
                $minGewicht = $gewichten->min() ?? 0;
                $maxGewicht = $gewichten->max() ?? 0;

                // Key format: leeftijdsklasse|gewichtsklasse (compatible with BlokMatVerdelingService)
                $key = $poule->leeftijdsklasse . '|' . $poule->gewichtsklasse;

                return [
                    'id' => $poule->id,
                    'key' => $key,
                    'nummer' => $poule->nummer,
                    'titel' => $poule->titel,
                    'leeftijdsklasse' => $poule->leeftijdsklasse,
                    'gewichtsklasse' => $poule->gewichtsklasse,
                    'aantal_wedstrijden' => $poule->aantal_wedstrijden,
                    'blok_vast' => $poule->blok_vast,
                    'min_leeftijd' => $minLeeftijd,
                    'max_leeftijd' => $maxLeeftijd,
                    'min_gewicht' => $minGewicht,
                    'max_gewicht' => $maxGewicht,
                    // Sort keys
                    'sort_leeftijd' => $minLeeftijd * 1000 + $maxLeeftijd,
                    'sort_gewicht' => $minGewicht * 1000 + $maxGewicht,
                ];
            });
    }

    /**
     * Get effective weight: weighed > registered > weight class
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
     * Sort pools by age (primary) and weight (secondary)
     */
    private function sorteerPoules(Collection $poules): Collection
    {
        return $poules->sortBy([
            ['sort_leeftijd', 'asc'],
            ['sort_gewicht', 'asc'],
        ])->values();
    }

    /**
     * Calculate distribution for a specific strategy
     */
    private function berekenVerdeling(
        Collection $poules,
        $blokken,
        int $doelPerBlok,
        int $strategie,
        int $userVerdelingGewicht
    ): array {
        $numBlokken = $blokken->count();
        $blokkenArray = $blokken->values()->all();
        $toewijzingen = []; // key (leeftijdsklasse|gewichtsklasse) => blok_nummer

        // Initialize block capacities
        $capaciteit = [];
        foreach ($blokkenArray as $blok) {
            $capaciteit[$blok->id] = [
                'gewenst' => $doelPerBlok,
                'actueel' => 0,
            ];
        }

        // Strategy variations for finding different split points
        $spreadFactor = 1.0 + ($strategie % 5) * 0.05; // 1.0 - 1.20
        $startOffset = ($strategie % 3) * 0.1; // 0.0, 0.1, 0.2

        // Adjusted target per block for this strategy
        $adjustedDoel = (int) ($doelPerBlok * $spreadFactor);

        $huidigBlokIndex = 0;
        $wedstrijdenInBlok = 0;

        // Group pools by age boundary for smarter splitting
        $leeftijdGroepen = $this->groepeerOpLeeftijd($poules);

        foreach ($leeftijdGroepen as $leeftijd => $groep) {
            $groepWedstrijden = collect($groep)->sum('aantal_wedstrijden');

            // Check if this group fits in current block
            $nieuweActueel = $wedstrijdenInBlok + $groepWedstrijden;

            // Should we split this age group?
            if ($wedstrijdenInBlok > 0 && $nieuweActueel > $adjustedDoel * 1.15 && $huidigBlokIndex < $numBlokken - 1) {
                // Try to find optimal weight split within this age group
                $splitResult = $this->vindOptimaleWeightSplit(
                    $groep,
                    $adjustedDoel - $wedstrijdenInBlok,
                    $strategie
                );

                if ($splitResult['split_index'] > 0) {
                    // Put first part in current block
                    for ($i = 0; $i < $splitResult['split_index']; $i++) {
                        $poule = $groep[$i];
                        $blok = $blokkenArray[$huidigBlokIndex];
                        $toewijzingen[$poule['key']] = $blok->nummer;
                        $capaciteit[$blok->id]['actueel'] += $poule['aantal_wedstrijden'];
                        $wedstrijdenInBlok += $poule['aantal_wedstrijden'];
                    }

                    // Move to next block for remainder
                    $huidigBlokIndex = min($huidigBlokIndex + 1, $numBlokken - 1);
                    $wedstrijdenInBlok = 0;

                    // Put rest in new block
                    for ($i = $splitResult['split_index']; $i < count($groep); $i++) {
                        $poule = $groep[$i];
                        $blok = $blokkenArray[$huidigBlokIndex];
                        $toewijzingen[$poule['key']] = $blok->nummer;
                        $capaciteit[$blok->id]['actueel'] += $poule['aantal_wedstrijden'];
                        $wedstrijdenInBlok += $poule['aantal_wedstrijden'];
                    }
                    continue;
                }
            }

            // Check if we should move to next block before this group
            if ($wedstrijdenInBlok > 0 && $wedstrijdenInBlok >= $adjustedDoel * (0.9 + $startOffset) && $huidigBlokIndex < $numBlokken - 1) {
                $huidigBlokIndex++;
                $wedstrijdenInBlok = 0;
            }

            // Assign all pools in this age group to current block
            foreach ($groep as $poule) {
                $blok = $blokkenArray[$huidigBlokIndex];
                $toewijzingen[$poule['key']] = $blok->nummer;
                $capaciteit[$blok->id]['actueel'] += $poule['aantal_wedstrijden'];
                $wedstrijdenInBlok += $poule['aantal_wedstrijden'];
            }

            // After a complete age group, check if we should move to next block
            if ($wedstrijdenInBlok >= $adjustedDoel && $huidigBlokIndex < $numBlokken - 1) {
                $huidigBlokIndex++;
                $wedstrijdenInBlok = 0;
            }
        }

        // Calculate scores
        $scores = $this->berekenScores($capaciteit, $blokkenArray, $doelPerBlok, $userVerdelingGewicht);

        return [
            'toewijzingen' => $toewijzingen,
            'capaciteit' => $capaciteit,
            'scores' => $scores,
            'totaal_score' => $scores['totaal_score'],
            'strategie' => $strategie,
        ];
    }

    /**
     * Group pools by age (using min_leeftijd)
     */
    private function groepeerOpLeeftijd(Collection $poules): array
    {
        $groepen = [];

        foreach ($poules as $poule) {
            $key = $poule['min_leeftijd'] . '-' . $poule['max_leeftijd'];
            if (!isset($groepen[$key])) {
                $groepen[$key] = [];
            }
            $groepen[$key][] = $poule;
        }

        // Sort within each group by weight
        foreach ($groepen as &$groep) {
            usort($groep, fn($a, $b) => $a['sort_gewicht'] <=> $b['sort_gewicht']);
        }

        // Sort groups by age
        uksort($groepen, function ($a, $b) {
            $aMin = (int) explode('-', $a)[0];
            $bMin = (int) explode('-', $b)[0];
            return $aMin <=> $bMin;
        });

        return $groepen;
    }

    /**
     * Find optimal weight split point within an age group
     * Returns index where to split (0 = no split)
     */
    private function vindOptimaleWeightSplit(array $groep, int $beschikbareRuimte, int $strategie): array
    {
        if (count($groep) < 2) {
            return ['split_index' => 0, 'verschil' => PHP_INT_MAX];
        }

        $besteSplit = 0;
        $besteVerschil = PHP_INT_MAX;

        // Variation based on strategy
        $preferEarlySplit = ($strategie % 2 === 0);

        $cumulatief = 0;
        for ($i = 0; $i < count($groep) - 1; $i++) {
            $cumulatief += $groep[$i]['aantal_wedstrijden'];

            $verschil = abs($cumulatief - $beschikbareRuimte);

            // Prefer splits that get closer to target
            if ($verschil < $besteVerschil) {
                $besteVerschil = $verschil;
                $besteSplit = $i + 1;
            }

            // Early split preference for some strategies
            if ($preferEarlySplit && $cumulatief >= $beschikbareRuimte * 0.8) {
                break;
            }
        }

        // Only split if it improves distribution
        if ($besteVerschil > $beschikbareRuimte * 0.5) {
            return ['split_index' => 0, 'verschil' => PHP_INT_MAX];
        }

        return ['split_index' => $besteSplit, 'verschil' => $besteVerschil];
    }

    /**
     * Calculate quality scores for a distribution
     */
    private function berekenScores(array $capaciteit, array $blokken, int $doelPerBlok, int $userVerdelingGewicht): array
    {
        $verdelingScore = 0;
        $maxAfwijking = 0;
        $blokStats = [];

        foreach ($blokken as $blok) {
            $cap = $capaciteit[$blok->id];
            $gewenst = max(1, $doelPerBlok);
            $afwijkingPct = abs(($cap['actueel'] - $gewenst) / $gewenst * 100);

            $verdelingScore += $afwijkingPct;
            $maxAfwijking = max($maxAfwijking, $afwijkingPct);

            $blokStats[$blok->nummer] = [
                'actueel' => $cap['actueel'],
                'gewenst' => $gewenst,
                'afwijking_pct' => round($afwijkingPct, 1),
            ];
        }

        // Weight the distribution score
        $gewicht = $userVerdelingGewicht / 100.0;
        $totaalScore = $verdelingScore * $gewicht;

        return [
            'verdeling_score' => round($verdelingScore, 1),
            'totaal_score' => round($totaalScore, 1),
            'max_afwijking_pct' => round($maxAfwijking, 1),
            'blok_stats' => $blokStats,
            'is_valid' => $maxAfwijking <= 30, // Max 30% deviation allowed
        ];
    }

    /**
     * Calculate category groupings for display
     * Groups consecutive pools with same/similar age into categories with dynamic headers
     */
    private function berekenCategorieGroepen(Collection $poules, array $toewijzingen, $blokken): array
    {
        $groepen = [];

        foreach ($blokken as $blok) {
            $blokPoules = $poules->filter(fn($p) => ($toewijzingen[$p['key']] ?? null) === $blok->nummer);

            if ($blokPoules->isEmpty()) {
                continue;
            }

            // Group by age range
            $ageGroups = [];
            foreach ($blokPoules as $poule) {
                $ageKey = $poule['min_leeftijd'] . '-' . $poule['max_leeftijd'];
                if (!isset($ageGroups[$ageKey])) {
                    $ageGroups[$ageKey] = [
                        'poules' => [],
                        'min_leeftijd' => $poule['min_leeftijd'],
                        'max_leeftijd' => $poule['max_leeftijd'],
                        'min_gewicht' => PHP_INT_MAX,
                        'max_gewicht' => 0,
                        'wedstrijden' => 0,
                    ];
                }
                $ageGroups[$ageKey]['poules'][] = $poule;
                $ageGroups[$ageKey]['min_gewicht'] = min($ageGroups[$ageKey]['min_gewicht'], $poule['min_gewicht']);
                $ageGroups[$ageKey]['max_gewicht'] = max($ageGroups[$ageKey]['max_gewicht'], $poule['max_gewicht']);
                $ageGroups[$ageKey]['wedstrijden'] += $poule['aantal_wedstrijden'];
            }

            // Merge adjacent age groups if they overlap
            $mergedGroups = $this->mergeAdjacentAgeGroups(array_values($ageGroups));

            // Generate dynamic headers
            foreach ($mergedGroups as $group) {
                $minL = $group['min_leeftijd'];
                $maxL = $group['max_leeftijd'];
                $minG = round($group['min_gewicht'], 1);
                $maxG = round($group['max_gewicht'], 1);

                $leeftijdStr = $minL === $maxL ? "{$minL}j" : "{$minL}-{$maxL}j";
                $gewichtStr = $minG === $maxG ? "{$minG}kg" : "{$minG}-{$maxG}kg";

                $groepen[] = [
                    'blok_nummer' => $blok->nummer,
                    'header' => "{$leeftijdStr} · {$gewichtStr}",
                    'wedstrijden' => $group['wedstrijden'],
                    'poule_count' => count($group['poules']),
                    'leeftijd_range' => [$minL, $maxL],
                    'gewicht_range' => [$minG, $maxG],
                ];
            }
        }

        return $groepen;
    }

    /**
     * Merge adjacent age groups that overlap
     */
    private function mergeAdjacentAgeGroups(array $groups): array
    {
        if (empty($groups)) {
            return [];
        }

        // Sort by min age
        usort($groups, fn($a, $b) => $a['min_leeftijd'] <=> $b['min_leeftijd']);

        $merged = [$groups[0]];

        for ($i = 1; $i < count($groups); $i++) {
            $last = &$merged[count($merged) - 1];
            $current = $groups[$i];

            // Merge if ages overlap (max of last >= min of current)
            if ($last['max_leeftijd'] >= $current['min_leeftijd'] - 1) {
                $last['max_leeftijd'] = max($last['max_leeftijd'], $current['max_leeftijd']);
                $last['min_gewicht'] = min($last['min_gewicht'], $current['min_gewicht']);
                $last['max_gewicht'] = max($last['max_gewicht'], $current['max_gewicht']);
                $last['wedstrijden'] += $current['wedstrijden'];
                $last['poules'] = array_merge($last['poules'], $current['poules']);
            } else {
                $merged[] = $current;
            }
        }

        return $merged;
    }

    /**
     * Apply a variant to the database
     * Keys are in format: leeftijdsklasse|gewichtsklasse
     */
    public function pasVariantToe(Toernooi $toernooi, array $toewijzingen): void
    {
        DB::transaction(function () use ($toernooi, $toewijzingen) {
            foreach ($toewijzingen as $key => $blokNummer) {
                // Parse key: "leeftijdsklasse|gewichtsklasse"
                $parts = explode('|', $key);
                if (count($parts) !== 2) {
                    continue;
                }

                [$leeftijd, $gewicht] = $parts;

                $blok = $toernooi->blokken()->where('nummer', $blokNummer)->first();

                if ($blok) {
                    // Update all poules for this category (except pinned ones)
                    Poule::where('toernooi_id', $toernooi->id)
                        ->where('leeftijdsklasse', $leeftijd)
                        ->where('gewichtsklasse', $gewicht)
                        ->where('blok_vast', false)
                        ->update(['blok_id' => $blok->id]);
                }
            }

            $toernooi->update(['blokken_verdeeld_op' => now()]);
        });
    }
}
