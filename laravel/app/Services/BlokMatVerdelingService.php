<?php

namespace App\Services;

use App\Models\Blok;
use App\Models\Mat;
use App\Models\Poule;
use App\Models\Toernooi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BlokMatVerdelingService
{
    /**
     * Preference order for placing categories relative to previous weight
     * Same block (0), +1 block, +2 blocks, -1 block, +3 blocks
     */
    private array $blokVoorkeur = [0, 1, 2, -1, 3];

    /**
     * Generate distribution variants until we have 5 acceptable ones
     * Acceptable = max 25% deviation from gewenst per block (relaxed)
     *
     * @param int $userVerdelingGewicht User-provided weight for distribution (0-100)
     * @param int $userAansluitingGewicht User-provided weight for weight class continuity (0-100)
     * @return array ['varianten' => [...top 5...], 'huidige' => current state]
     */
    public function genereerVarianten(Toernooi $toernooi, int $userVerdelingGewicht = 50, int $userAansluitingGewicht = 50): array
    {
        $blokken = $toernooi->blokken->sortBy('nummer')->values();

        if ($blokken->isEmpty()) {
            throw new \RuntimeException('Geen blokken gevonden');
        }

        // Get all categories with their current assignments
        $categories = $this->getCategoriesMetToewijzing($toernooi);

        // Only distribute categories that are NOT pinned (blok_vast = false)
        // Pinned categories stay where they are
        $nietVerdeeld = $categories->filter(fn($cat) => $cat['blok_id'] === null && !$cat['blok_vast']);

        if ($nietVerdeeld->isEmpty()) {
            return ['varianten' => [], 'message' => 'Alle categorieën zijn al verdeeld'];
        }

        // Calculate base capacity WITH existing placements (respect what's already there)
        $baseCapaciteit = $this->berekenBlokCapaciteit($toernooi, $blokken);

        // Calculate totals for logging
        $totaalGewenst = array_sum(array_column($baseCapaciteit, 'gewenst'));
        $totaalActueel = array_sum(array_column($baseCapaciteit, 'actueel'));
        $gemiddeld = $totaalGewenst / count($baseCapaciteit);

        // Relaxed threshold: 25% of average OR 30 wedstrijden (whichever is higher)
        $maxAcceptabeleAfwijking = max(ceil($gemiddeld * 0.25), 30);

        // Group only NOT YET PLACED categories by leeftijd
        $perLeeftijd = $this->groepeerPerLeeftijd($nietVerdeeld);

        Log::info('Blokverdeling start', [
            'blokken' => $blokken->count(),
            'al_geplaatst' => $categories->count() - $nietVerdeeld->count(),
            'te_verdelen' => $nietVerdeeld->count(),
            'actueel_in_blokken' => $totaalActueel,
            'gemiddeld_per_blok' => round($gemiddeld),
        ]);

        // Keep generating until we have 5 acceptable unique variants
        $alleVarianten = [];
        $gezien = [];
        $poging = 0;
        $maxPogingen = 200;

        while ($poging < $maxPogingen) {
            $variant = $this->simuleerVerdeling(
                $perLeeftijd,
                $blokken,
                $baseCapaciteit,
                $categories,  // For anchor detection
                $poging,
                $userVerdelingGewicht,
                $userAansluitingGewicht
            );

            $variant['id'] = $poging + 1;
            $variant['poging'] = $poging;

            // Check for duplicates - sort keys for consistent hash
            $toewijzingenSorted = $variant['toewijzingen'];
            ksort($toewijzingenSorted);
            $hash = md5(json_encode($toewijzingenSorted));

            if (!isset($gezien[$hash])) {
                $gezien[$hash] = true;
                $alleVarianten[] = $variant;

                // Log first few attempts for debugging
                if ($poging < 5) {
                    Log::info("Variant #{$poging}", [
                        'max_afwijking' => $variant['scores']['max_afwijking'],
                        'breaks' => $variant['scores']['breaks'],
                        'blok_stats' => $variant['scores']['blok_stats'],
                    ]);
                }
            }

            $poging++;
        }

        // Sort all variants by score
        usort($alleVarianten, function($a, $b) {
            // First by max_afwijking (lower = better)
            $afwijkingDiff = $a['scores']['max_afwijking'] - $b['scores']['max_afwijking'];
            if ($afwijkingDiff !== 0) {
                return $afwijkingDiff;
            }
            // Then by breaks (lower = better)
            $breaksDiff = $a['scores']['breaks'] - $b['scores']['breaks'];
            if ($breaksDiff !== 0) {
                return $breaksDiff;
            }
            // Then by verdeling_score (lower = better)
            return $a['scores']['verdeling_score'] - $b['scores']['verdeling_score'];
        });

        // Take top 5 UNIQUE variants (different toewijzingen)
        $beste = [];
        $gezienHashes = [];
        foreach ($alleVarianten as $variant) {
            // Sort toewijzingen by key for consistent hash
            $toewijzingen = $variant['toewijzingen'];
            ksort($toewijzingen);
            $hash = md5(json_encode($toewijzingen));

            if (!isset($gezienHashes[$hash])) {
                $gezienHashes[$hash] = true;
                $beste[] = $variant;

                Log::debug("Variant toegevoegd aan top 5", [
                    'index' => count($beste),
                    'hash' => substr($hash, 0, 8),
                    'max_afwijking' => $variant['scores']['max_afwijking'],
                    'breaks' => $variant['scores']['breaks'],
                ]);

                if (count($beste) >= 5) {
                    break;
                }
            }
        }

        Log::info("Blokverdeling klaar na {$poging} pogingen", [
            'unieke_varianten' => count($alleVarianten),
            'beste_afwijking' => $beste[0]['scores']['max_afwijking'] ?? 'N/A',
        ]);

        // Return top 5 unique variants
        return ['varianten' => $beste];
    }

    /**
     * Calculate distribution weight based on attempt number
     * Use more variation to find different solutions
     */
    private function berekenVerdelingGewicht(int $poging, int $gevonden): float
    {
        // Create distinct weight patterns for variety
        $patterns = [
            0.95, 0.90, 0.85, 0.80, 0.75,  // High distribution focus
            0.70, 0.65, 0.60, 0.55, 0.50,  // Balanced
            0.92, 0.88, 0.82, 0.78, 0.72,  // Slightly different
            0.68, 0.62, 0.58, 0.52, 0.48,  // More aansluiting focus
        ];

        return $patterns[$poging % count($patterns)];
    }

    /**
     * Calculate random factor based on attempt number
     * Add variety through controlled randomness
     */
    private function berekenRandomFactor(int $poging): float
    {
        // Every 5th attempt: add some randomness
        if ($poging % 5 === 0) {
            return 0.05 + ($poging % 20) * 0.01;
        }

        // Every 10th attempt: more randomness
        if ($poging % 10 === 0) {
            return 0.15;
        }

        return 0.0;
    }

    /**
     * Simulate a distribution without saving to database
     * Uses a smarter approach: balance blocks while keeping weight classes together
     *
     * @param int $userVerdelingGewicht User-provided weight (0-100)
     * @param int $userAansluitingGewicht User-provided weight (0-100)
     */
    private function simuleerVerdeling(
        array $perLeeftijd,
        $blokken,
        array $baseCapaciteit,
        $alleCategorieen,
        int $seed,
        int $userVerdelingGewicht = 50,
        int $userAansluitingGewicht = 50
    ): array {
        $capaciteit = $baseCapaciteit;  // Copy
        $toewijzingen = [];  // category_key => blok_nummer
        $numBlokken = $blokken->count();

        // Use seed for reproducible randomness
        mt_srand($seed * 12345);

        // User weights: 0% = 0.0, 100% = 1.0 - NO exceptions!
        // Direct conversion - user intent is respected exactly
        $verdelingGewicht = $userVerdelingGewicht / 100.0;
        $aansluitingGewicht = $userAansluitingGewicht / 100.0;

        // Strict modes: 100% means ABSOLUTE priority
        $strictVerdeling = $userVerdelingGewicht === 100;
        $strictAansluiting = $userAansluitingGewicht === 100;

        // Only add tiny variation in middle range (20-80%), not at extremes
        if ($userVerdelingGewicht > 20 && $userVerdelingGewicht < 80) {
            $verdelingGewicht += ($seed % 5) * 0.01;  // max 0.04 variation
        }
        if ($userAansluitingGewicht > 20 && $userAansluitingGewicht < 80) {
            $aansluitingGewicht += ($seed % 5) * 0.01;
        }

        // Randomness only in middle range
        $randomFactor = ($userVerdelingGewicht > 20 && $userVerdelingGewicht < 80 && $seed % 3 === 0) ? 0.03 : 0.0;

        // Calculate total wedstrijden per leeftijd for smart distribution
        $leeftijdTotalen = [];
        foreach ($perLeeftijd as $leeftijd => $gewichten) {
            $leeftijdTotalen[$leeftijd] = array_sum(array_column($gewichten, 'wedstrijden'));
        }

        // Vary the order of processing based on seed for different results
        $leeftijdKeys = array_keys($leeftijdTotalen);
        $orderStrategy = $seed % 8;  // More strategies

        switch ($orderStrategy) {
            case 0:
                // Largest leeftijd first
                arsort($leeftijdTotalen);
                $leeftijdKeys = array_keys($leeftijdTotalen);
                break;
            case 1:
                // Smallest leeftijd first
                asort($leeftijdTotalen);
                $leeftijdKeys = array_keys($leeftijdTotalen);
                break;
            case 2:
                // Original order
                break;
            case 3:
                // Reverse original order
                $leeftijdKeys = array_reverse($leeftijdKeys);
                break;
            case 4:
                // Random shuffle with seed
                shuffle($leeftijdKeys);
                break;
            case 5:
                // Alphabetical
                sort($leeftijdKeys);
                break;
            case 6:
                // Reverse alphabetical
                rsort($leeftijdKeys);
                break;
            case 7:
                // Middle-out (alternate from middle)
                $mid = (int)(count($leeftijdKeys) / 2);
                $newOrder = [];
                for ($i = 0; $i <= $mid; $i++) {
                    if ($mid + $i < count($leeftijdKeys)) $newOrder[] = $leeftijdKeys[$mid + $i];
                    if ($mid - $i - 1 >= 0) $newOrder[] = $leeftijdKeys[$mid - $i - 1];
                }
                $leeftijdKeys = $newOrder;
                break;
        }

        // Assign starting blocks for each leeftijd - vary offset per seed
        $leeftijdStartBlok = [];
        $blokOffset = $seed % $numBlokken;  // Different starting point per seed

        foreach ($leeftijdKeys as $idx => $leeftijd) {
            // Check if this leeftijd has an anchor from already placed categories
            $ankerBlokIndex = $this->vindAnkerBlok($leeftijd, $alleCategorieen, $blokken);

            if ($ankerBlokIndex !== null) {
                // Start near where this leeftijd already has categories
                $leeftijdStartBlok[$leeftijd] = $ankerBlokIndex;
            } else {
                // Distribute starting points evenly, with seed-based offset
                $leeftijdStartBlok[$leeftijd] = ($idx + $blokOffset) % $numBlokken;
            }
        }

        // Process each leeftijd in the determined order
        foreach ($leeftijdKeys as $leeftijd) {
            if (!isset($perLeeftijd[$leeftijd])) continue;

            $gewichten = $perLeeftijd[$leeftijd];
            $vorigeBlokIndex = $leeftijdStartBlok[$leeftijd] ?? 0;

            // Vary weight order based on seed
            $weightStrategy = ($seed + array_search($leeftijd, $leeftijdKeys)) % 4;
            if ($weightStrategy === 1) {
                $gewichten = array_reverse($gewichten);  // Heavy first
            } elseif ($weightStrategy === 2) {
                // Start from middle
                $mid = (int)(count($gewichten) / 2);
                $reordered = [];
                for ($w = 0; $w < count($gewichten); $w++) {
                    $idx = ($mid + ($w % 2 === 0 ? $w/2 : -(($w+1)/2))) % count($gewichten);
                    if ($idx < 0) $idx += count($gewichten);
                    $reordered[] = $gewichten[(int)$idx];
                }
                $gewichten = $reordered;
            }
            // weightStrategy 0 and 3 = original order (light first)

            foreach ($gewichten as $cat) {
                $key = $cat['leeftijd'] . '|' . $cat['gewicht'];

                // Find best block with current strategy
                $besteBlokIndex = $this->vindBesteBlokMetStrategie(
                    $vorigeBlokIndex,
                    $cat['wedstrijden'],
                    $capaciteit,
                    $blokken,
                    $numBlokken,
                    $verdelingGewicht,
                    $aansluitingGewicht,
                    $randomFactor,
                    $strictAansluiting
                );

                // Record assignment
                $blok = $blokken[$besteBlokIndex];
                $toewijzingen[$key] = $blok->nummer;

                // Update capacity
                $capaciteit[$blok->id]['actueel'] += $cat['wedstrijden'];
                $capaciteit[$blok->id]['ruimte'] -= $cat['wedstrijden'];

                $vorigeBlokIndex = $besteBlokIndex;
            }
        }

        // Calculate scores for this variant
        $scores = $this->berekenScores($toewijzingen, $capaciteit, $blokken, $perLeeftijd);

        return [
            'toewijzingen' => $toewijzingen,
            'capaciteit' => $capaciteit,
            'scores' => $scores,
            'totaal_score' => $scores['verdeling_score'] + $scores['aansluiting_score'],
            'strategie' => [
                'verdeling' => round($verdelingGewicht * 100),
                'aansluiting' => round($aansluitingGewicht * 100),
            ],
        ];
    }

    /**
     * Find best block using weighted strategy
     * Balances even distribution with continuity
     * When strictAansluiting=true: force same block as previous (no breaks allowed)
     */
    private function vindBesteBlokMetStrategie(
        int $vorigeBlokIndex,
        int $wedstrijden,
        array $blokCapaciteit,
        $blokken,
        int $numBlokken,
        float $verdelingGewicht,
        float $aansluitingGewicht,
        float $randomFactor,
        bool $strictAansluiting = false
    ): int {
        // STRICT MODE: always stay in same block (100% aansluiting = no breaks)
        if ($strictAansluiting) {
            return $vorigeBlokIndex;
        }

        $opties = [];

        foreach ($blokken as $index => $blok) {
            $cap = $blokCapaciteit[$blok->id];
            $nieuweActueel = $cap['actueel'] + $wedstrijden;
            $gewenst = max(1, $cap['gewenst']);

            // Calculate how far from ideal this block is (can be negative = under, positive = over)
            $afwijkingPct = ($nieuweActueel - $gewenst) / $gewenst;

            // Distance penalty for continuity (0 = same block, 1 = adjacent, etc.)
            $afstand = abs($index - $vorigeBlokIndex);

            // Combined score calculation with DRAMATIC effect
            // Verdeling: penalize being over gewenst
            $verdelingsScore = $afwijkingPct > 0
                ? $afwijkingPct * 200  // Over capacity = big penalty
                : abs($afwijkingPct) * 30;  // Under capacity = smaller penalty

            // Aansluiting: HEAVY penalty for distance (exponential)
            $aansluitingScore = $afstand * $afstand * 50;  // Exponential: 0, 50, 200, 450...

            // Total score (lower = better) - weights have STRONG effect
            $score = ($verdelingsScore * $verdelingGewicht) + ($aansluitingScore * $aansluitingGewicht);

            // Bonus for blocks that are underfilled (only when verdeling matters)
            if ($verdelingGewicht > 0.3) {
                $vulgraad = $cap['actueel'] / $gewenst;
                if ($vulgraad < 0.7) {
                    $score -= 20 * $verdelingGewicht;
                } elseif ($vulgraad < 0.9) {
                    $score -= 8 * $verdelingGewicht;
                }
            }

            // Add randomness only if configured
            if ($randomFactor > 0) {
                $score += mt_rand(0, 20) * $randomFactor;
            }

            $opties[] = [
                'index' => $index,
                'score' => $score,
                'nieuwe_actueel' => $nieuweActueel,
                'afwijking_pct' => $afwijkingPct,
            ];
        }

        // Sort by score and pick best
        usort($opties, fn($a, $b) => $a['score'] <=> $b['score']);

        return $opties[0]['index'];
    }

    /**
     * Calculate quality scores for a variant
     */
    private function berekenScores(
        array $toewijzingen,
        array $capaciteit,
        $blokken,
        array $perLeeftijd
    ): array {
        // Verdeling score: sum of absolute deviations from gewenst
        $verdelingScore = 0;
        $maxAfwijking = 0;
        $blokStats = [];

        foreach ($blokken as $blok) {
            $cap = $capaciteit[$blok->id];
            $afwijking = abs($cap['actueel'] - $cap['gewenst']);
            $verdelingScore += $afwijking;
            $maxAfwijking = max($maxAfwijking, $afwijking);
            $blokStats[$blok->nummer] = [
                'actueel' => $cap['actueel'],
                'gewenst' => $cap['gewenst'],
                'afwijking' => $afwijking,
            ];
        }

        // Aansluiting score: count of "breaks" (adjacent weights in different blocks)
        $aansluitingScore = 0;
        $breaks = 0;

        foreach ($perLeeftijd as $leeftijd => $gewichten) {
            $vorigBlok = null;
            foreach ($gewichten as $cat) {
                $key = $cat['leeftijd'] . '|' . $cat['gewicht'];
                $blokNr = $toewijzingen[$key] ?? null;

                if ($vorigBlok !== null && $blokNr !== null && $blokNr !== $vorigBlok) {
                    // Adjacent weights in different blocks = break
                    $breaks++;
                    $aansluitingScore += abs($blokNr - $vorigBlok) * 5;
                }
                $vorigBlok = $blokNr;
            }
        }

        return [
            'verdeling_score' => $verdelingScore,
            'max_afwijking' => $maxAfwijking,
            'aansluiting_score' => $aansluitingScore,
            'breaks' => $breaks,
            'blok_stats' => $blokStats,
        ];
    }

    /**
     * Apply a chosen variant to the database
     * Only updates categories that are NOT yet assigned (respects manual placements)
     */
    public function pasVariantToe(Toernooi $toernooi, array $toewijzingen): void
    {
        DB::transaction(function () use ($toernooi, $toewijzingen) {
            foreach ($toewijzingen as $key => $blokNummer) {
                [$leeftijd, $gewicht] = explode('|', $key);

                $blok = $toernooi->blokken()->where('nummer', $blokNummer)->first();

                if ($blok) {
                    // Only update poules that are NOT yet assigned
                    Poule::where('toernooi_id', $toernooi->id)
                        ->where('leeftijdsklasse', $leeftijd)
                        ->where('gewichtsklasse', $gewicht)
                        ->whereNull('blok_id')
                        ->update(['blok_id' => $blok->id]);
                }
            }

            $toernooi->update(['blokken_verdeeld_op' => now()]);
        });
    }

    /**
     * Generate block distribution for unassigned categories (legacy single variant)
     */
    public function genereerVerdeling(Toernooi $toernooi): array
    {
        // Generate variants and apply the best one
        $result = $this->genereerVarianten($toernooi);

        if (!empty($result['varianten'])) {
            $beste = $result['varianten'][0];
            $this->pasVariantToe($toernooi, $beste['toewijzingen']);
        }

        return $this->getVerdelingsStatistieken($toernooi);
    }

    /**
     * Place categories for a single leeftijdsklasse
     */
    private function plaatsCategorieenVoorLeeftijd(
        Toernooi $toernooi,
        string $leeftijd,
        array $gewichten,
        $blokken,
        array &$blokCapaciteit,
        $alleCategorieen
    ): void {
        $numBlokken = $blokken->count();

        // Find anchor: block where this leeftijd already has weights placed
        $ankerBlokIndex = $this->vindAnkerBlok($leeftijd, $alleCategorieen, $blokken);

        // If no anchor found, start at block with most available space
        if ($ankerBlokIndex === null) {
            $ankerBlokIndex = $this->vindBlokMetMeesteRuimte($blokCapaciteit, $blokken);
        }

        $vorigeBlokIndex = $ankerBlokIndex;

        foreach ($gewichten as $cat) {
            // Try preference order: same, +1, +2, -1, +3
            $besteBlokIndex = $this->vindBesteBlokVoorCategorie(
                $vorigeBlokIndex,
                $cat['wedstrijden'],
                $blokCapaciteit,
                $blokken,
                $numBlokken
            );

            // Assign all poules of this category to this block
            $blok = $blokken[$besteBlokIndex];
            Poule::where('toernooi_id', $toernooi->id)
                ->where('leeftijdsklasse', $cat['leeftijd'])
                ->where('gewichtsklasse', $cat['gewicht'])
                ->update(['blok_id' => $blok->id]);

            // Update capacity
            $blokCapaciteit[$blok->id]['actueel'] += $cat['wedstrijden'];
            $blokCapaciteit[$blok->id]['ruimte'] -= $cat['wedstrijden'];

            $vorigeBlokIndex = $besteBlokIndex;
        }
    }

    /**
     * Find anchor block: where this leeftijd already has categories placed
     */
    private function vindAnkerBlok(string $leeftijd, $alleCategorieen, $blokken): ?int
    {
        // Find highest weight of this leeftijd that's already placed
        $geplaatst = $alleCategorieen
            ->filter(fn($cat) => $cat['leeftijd'] === $leeftijd && $cat['blok_id'] !== null)
            ->sortByDesc('gewicht_num');

        if ($geplaatst->isEmpty()) {
            return null;
        }

        $laatsteBlokId = $geplaatst->first()['blok_id'];

        // Find index of this block
        foreach ($blokken as $index => $blok) {
            if ($blok->id === $laatsteBlokId) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Find block with most available space
     */
    private function vindBlokMetMeesteRuimte(array $blokCapaciteit, $blokken): int
    {
        $maxRuimte = -PHP_INT_MAX;
        $besteIndex = 0;

        foreach ($blokken as $index => $blok) {
            $ruimte = $blokCapaciteit[$blok->id]['ruimte'] ?? 0;
            if ($ruimte > $maxRuimte) {
                $maxRuimte = $ruimte;
                $besteIndex = $index;
            }
        }

        return $besteIndex;
    }

    /**
     * Find best block for a category using preference order
     * Preference: same block (0), +1, +2, -1, +3
     *
     * IMPORTANT: Never exceed 110% of gewenst capacity
     * Balance is more important than keeping categories together
     */
    private function vindBesteBlokVoorCategorie(
        int $vorigeBlokIndex,
        int $wedstrijden,
        array $blokCapaciteit,
        $blokken,
        int $numBlokken
    ): int {
        $besteIndex = null;
        $besteScore = PHP_INT_MAX;

        // Maximum allowed = 110% of gewenst (never exceed this)
        $maxOverflow = 0.10;

        // First pass: try preference order, only if block has enough space
        foreach ($this->blokVoorkeur as $offset) {
            $testIndex = $vorigeBlokIndex + $offset;

            if ($testIndex < 0 || $testIndex >= $numBlokken) {
                continue;
            }

            $blok = $blokken[$testIndex];
            $cap = $blokCapaciteit[$blok->id];
            $ruimte = $cap['ruimte'];
            $gewenst = $cap['gewenst'];
            $actueel = $cap['actueel'];

            // Would this exceed 110% of gewenst?
            $nieuweActueel = $actueel + $wedstrijden;
            $maxToegestaan = $gewenst * (1 + $maxOverflow);

            if ($nieuweActueel > $maxToegestaan) {
                // Skip this block - would overflow too much
                continue;
            }

            // Score: proximity bonus + fill percentage penalty
            // Lower score = better
            $fillPct = $nieuweActueel / max(1, $gewenst);
            $score = abs($offset) * 5 + ($fillPct * 10);

            if ($score < $besteScore) {
                $besteScore = $score;
                $besteIndex = $testIndex;
            }
        }

        // If no block found in preference order, find ANY block with space
        if ($besteIndex === null) {
            $meestRuimte = -PHP_INT_MAX;

            foreach ($blokken as $index => $blok) {
                $cap = $blokCapaciteit[$blok->id];
                $ruimte = $cap['ruimte'];
                $gewenst = $cap['gewenst'];
                $actueel = $cap['actueel'];

                // Would this exceed 110%?
                $nieuweActueel = $actueel + $wedstrijden;
                $maxToegestaan = $gewenst * (1 + $maxOverflow);

                if ($nieuweActueel <= $maxToegestaan && $ruimte > $meestRuimte) {
                    $meestRuimte = $ruimte;
                    $besteIndex = $index;
                }
            }
        }

        // Last resort: if ALL blocks would exceed 110%, pick the one with most space
        if ($besteIndex === null) {
            $meestRuimte = -PHP_INT_MAX;

            foreach ($blokken as $index => $blok) {
                $ruimte = $blokCapaciteit[$blok->id]['ruimte'];
                if ($ruimte > $meestRuimte) {
                    $meestRuimte = $ruimte;
                    $besteIndex = $index;
                }
            }

            // Log warning
            Log::warning('Blokverdeling: alle blokken overvol, categorie geplaatst in blok met meeste ruimte', [
                'blok_index' => $besteIndex,
                'wedstrijden' => $wedstrijden,
            ]);
        }

        return $besteIndex ?? 0;
    }

    /**
     * Calculate capacity per block
     * gewenst: from database or calculated (totaal / aantal_blokken)
     * actueel: sum of wedstrijden already assigned
     * ruimte: gewenst - actueel
     */
    private function berekenBlokCapaciteit(Toernooi $toernooi, $blokken): array
    {
        $totaalWedstrijden = $toernooi->poules()->sum('aantal_wedstrijden');
        $defaultGewenst = $blokken->count() > 0 ? (int) ceil($totaalWedstrijden / $blokken->count()) : 0;

        $capaciteit = [];

        foreach ($blokken as $blok) {
            $actueel = $toernooi->poules()
                ->where('blok_id', $blok->id)
                ->sum('aantal_wedstrijden');

            $gewenst = $blok->gewenst_wedstrijden ?? $defaultGewenst;

            $capaciteit[$blok->id] = [
                'gewenst' => $gewenst,
                'actueel' => $actueel,
                'ruimte' => $gewenst - $actueel,
            ];
        }

        return $capaciteit;
    }

    /**
     * Get all categories with their block assignment
     */
    private function getCategoriesMetToewijzing(Toernooi $toernooi)
    {
        return $toernooi->poules()
            ->reorder() // Remove default orderBy('nummer') which conflicts with GROUP BY in MySQL
            ->select('leeftijdsklasse', 'gewichtsklasse', 'blok_id', 'blok_vast')
            ->selectRaw('SUM(aantal_wedstrijden) as wedstrijden')
            ->groupBy('leeftijdsklasse', 'gewichtsklasse', 'blok_id', 'blok_vast')
            ->orderBy('leeftijdsklasse')
            ->orderBy('gewichtsklasse')
            ->get()
            ->groupBy(fn($p) => $p->leeftijdsklasse . '|' . $p->gewichtsklasse)
            ->map(fn($g) => [
                'leeftijd' => $g->first()->leeftijdsklasse,
                'gewicht' => $g->first()->gewichtsklasse,
                'gewicht_num' => $this->parseGewicht($g->first()->gewichtsklasse),
                'wedstrijden' => $g->sum('wedstrijden'),
                'blok_id' => $g->first()->blok_id,
                'blok_vast' => (bool) $g->first()->blok_vast,
            ]);
    }

    /**
     * Group categories by leeftijd, sort weights ascending
     */
    private function groepeerPerLeeftijd($categories): array
    {
        $perLeeftijd = [];

        foreach ($categories as $cat) {
            $lk = $cat['leeftijd'];
            if (!isset($perLeeftijd[$lk])) {
                $perLeeftijd[$lk] = [];
            }
            $perLeeftijd[$lk][] = $cat;
        }

        // Sort each group by weight
        foreach ($perLeeftijd as $lk => &$cats) {
            usort($cats, fn($a, $b) => $a['gewicht_num'] <=> $b['gewicht_num']);
        }

        return $perLeeftijd;
    }

    /**
     * Parse weight class to numeric value for sorting
     * -50 = up to 50kg, +50 = over 50kg
     */
    private function parseGewicht(string $gewichtsklasse): float
    {
        if (preg_match('/([+-]?)(\d+)/', $gewichtsklasse, $match)) {
            $sign = $match[1];
            $num = (int) $match[2];
            return $sign === '+' ? $num + 1000 : $num;
        }
        return 999;
    }

    /**
     * Distribute poules over mats within each block (simple balanced distribution)
     */
    public function verdeelOverMatten(Toernooi $toernooi): void
    {
        $matten = $toernooi->matten->sortBy('nummer');
        $matIds = $matten->pluck('id')->toArray();

        foreach ($toernooi->blokken as $blok) {
            $wedstrijdenPerMat = array_fill_keys($matIds, 0);

            // Get poules ordered by wedstrijden (largest first for better balance)
            $poules = $blok->poules()->orderByDesc('aantal_wedstrijden')->get();

            foreach ($poules as $poule) {
                // Find mat with least wedstrijden
                $besteMat = $this->vindMinsteWedstrijdenMat($matIds, $wedstrijdenPerMat);
                $poule->update(['mat_id' => $besteMat]);
                $wedstrijdenPerMat[$besteMat] += $poule->aantal_wedstrijden;
            }
        }
    }

    /**
     * Find mat with least wedstrijden
     */
    private function vindMinsteWedstrijdenMat(array $matIds, array $wedstrijdenPerMat): int
    {
        $minWedstrijden = PHP_INT_MAX;
        $besteMat = $matIds[0];

        foreach ($matIds as $matId) {
            if (($wedstrijdenPerMat[$matId] ?? 0) < $minWedstrijden) {
                $minWedstrijden = $wedstrijdenPerMat[$matId] ?? 0;
                $besteMat = $matId;
            }
        }

        return $besteMat;
    }

    /**
     * Get distribution statistics
     */
    public function getVerdelingsStatistieken(Toernooi $toernooi): array
    {
        $stats = [];

        foreach ($toernooi->blokken as $blok) {
            $totaalWedstrijden = Poule::where('blok_id', $blok->id)->sum('aantal_wedstrijden');

            $blokStats = [
                'blok' => $blok->nummer,
                'gewenst' => $blok->gewenst_wedstrijden,
                'totaal_wedstrijden' => $totaalWedstrijden,
                'matten' => [],
            ];

            foreach ($toernooi->matten as $mat) {
                $wedstrijden = Poule::where('blok_id', $blok->id)
                    ->where('mat_id', $mat->id)
                    ->sum('aantal_wedstrijden');

                $poules = Poule::where('blok_id', $blok->id)
                    ->where('mat_id', $mat->id)
                    ->count();

                $blokStats['matten'][$mat->nummer] = [
                    'poules' => $poules,
                    'wedstrijden' => $wedstrijden,
                ];
            }

            $stats[$blok->nummer] = $blokStats;
        }

        return $stats;
    }

    /**
     * Move pool to different block
     */
    public function verplaatsPoule(Poule $poule, Blok $nieuweBlok): void
    {
        $poule->update(['blok_id' => $nieuweBlok->id]);
    }

    /**
     * Get hall overview (zaaloverzicht)
     */
    public function getZaalOverzicht(Toernooi $toernooi): array
    {
        $overzicht = [];

        foreach ($toernooi->blokken()->with('poules.mat')->get() as $blok) {
            $blokData = [
                'nummer' => $blok->nummer,
                'naam' => $blok->naam,
                'weging_gesloten' => $blok->weging_gesloten,
                'matten' => [],
            ];

            foreach ($toernooi->matten as $mat) {
                $poules = $blok->poules->where('mat_id', $mat->id);

                $blokData['matten'][$mat->nummer] = [
                    'mat_naam' => $mat->label,
                    'poules' => $poules->map(fn($p) => [
                        'id' => $p->id,
                        'nummer' => $p->nummer,
                        'titel' => $p->titel,
                        'leeftijdsklasse' => $p->leeftijdsklasse,
                        'gewichtsklasse' => $p->gewichtsklasse,
                        'judokas' => $p->aantal_judokas,
                        'wedstrijden' => $p->aantal_wedstrijden,
                    ])->values()->toArray(),
                ];
            }

            $overzicht[] = $blokData;
        }

        return $overzicht;
    }
}
