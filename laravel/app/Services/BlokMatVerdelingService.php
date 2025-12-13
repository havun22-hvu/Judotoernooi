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

                // Only add VALID variants (within 25% limit)
                if ($variant['scores']['is_valid']) {
                    $alleVarianten[] = $variant;

                    // Log first few attempts for debugging
                    if (count($alleVarianten) <= 5) {
                        Log::info("Geldige variant #{$poging}", [
                            'max_afwijking_pct' => $variant['scores']['max_afwijking_pct'] . '%',
                            'breaks' => $variant['scores']['breaks'],
                        ]);
                    }
                } else {
                    Log::debug("Variant #{$poging} verworpen - overschrijdt 25% limiet", [
                        'max_afwijking_pct' => $variant['scores']['max_afwijking_pct'] . '%',
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
            'geldige_varianten' => count($alleVarianten),
            'beste_afwijking_pct' => isset($beste[0]) ? $beste[0]['scores']['max_afwijking_pct'] . '%' : 'N/A',
        ]);

        // Return top 5 unique variants
        if (empty($beste)) {
            return [
                'varianten' => [],
                'error' => 'Geen geldige verdeling mogelijk binnen 25% limiet. Pas het aantal blokken of categorieën aan.',
            ];
        }

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
     * SMARTER APPROACH: First distribute leeftijden to balance blocks, then place weights
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
        $blokkenArray = $blokken->values()->all();

        // Use seed for reproducible randomness
        mt_srand($seed * 12345);

        // User weights
        $verdelingGewicht = $userVerdelingGewicht / 100.0;
        $aansluitingGewicht = $userAansluitingGewicht / 100.0;

        // Calculate total wedstrijden per leeftijd
        $leeftijdTotalen = [];
        foreach ($perLeeftijd as $leeftijd => $gewichten) {
            $leeftijdTotalen[$leeftijd] = array_sum(array_column($gewichten, 'wedstrijden'));
        }

        // STEP 1: Assign each leeftijd to starting block(s) using bin-packing
        // Goal: distribute total wedstrijden evenly across blocks
        $leeftijdBlokToewijzing = $this->verdeelLeeftijdenOverBlokken(
            $leeftijdTotalen,
            $capaciteit,
            $blokkenArray,
            $seed,
            $verdelingGewicht
        );

        // STEP 2: Place individual weights within assigned blocks
        // Respect aansluiting: consecutive weights in same/next block
        foreach ($perLeeftijd as $leeftijd => $gewichten) {
            $startBlokIndex = $leeftijdBlokToewijzing[$leeftijd] ?? 0;
            $vorigeBlokIndex = $startBlokIndex;

            // Sort weights by gewicht (light to heavy)
            usort($gewichten, fn($a, $b) => $a['gewicht_num'] <=> $b['gewicht_num']);

            foreach ($gewichten as $cat) {
                $key = $cat['leeftijd'] . '|' . $cat['gewicht'];

                // Find best block: prefer staying in same/next block (aansluiting)
                // but respect capacity limits
                $besteBlokIndex = $this->vindBesteBlokVoorGewicht(
                    $vorigeBlokIndex,
                    $cat['wedstrijden'],
                    $capaciteit,
                    $blokkenArray,
                    $numBlokken,
                    $aansluitingGewicht
                );

                // Record assignment
                $blok = $blokkenArray[$besteBlokIndex];
                $toewijzingen[$key] = $blok->nummer;

                // Update capacity
                $capaciteit[$blok->id]['actueel'] += $cat['wedstrijden'];
                $capaciteit[$blok->id]['ruimte'] -= $cat['wedstrijden'];

                $vorigeBlokIndex = $besteBlokIndex;
            }
        }

        // Calculate scores for this variant
        $scores = $this->berekenScores($toewijzingen, $capaciteit, $blokkenArray, $perLeeftijd);

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
     * Distribute leeftijden to starting blocks using bin-packing approach
     * Returns array of leeftijd => starting block index
     */
    private function verdeelLeeftijdenOverBlokken(
        array $leeftijdTotalen,
        array $capaciteit,
        array $blokken,
        int $seed,
        float $verdelingGewicht
    ): array {
        $numBlokken = count($blokken);
        $toewijzing = [];

        // Sort leeftijden by total wedstrijden (largest first for better bin-packing)
        arsort($leeftijdTotalen);

        // Track wedstrijden per block
        $blokWedstrijden = [];
        foreach ($blokken as $idx => $blok) {
            $blokWedstrijden[$idx] = $capaciteit[$blok->id]['actueel'];
        }

        // Calculate target per block
        $totaal = array_sum($leeftijdTotalen) + array_sum($blokWedstrijden);
        $targetPerBlok = $totaal / $numBlokken;

        // Vary starting point based on seed
        $startOffset = $seed % $numBlokken;

        foreach ($leeftijdTotalen as $leeftijd => $totaalWed) {
            // Find block with most remaining capacity (below target)
            $besteBlok = 0;
            $minWedstrijden = PHP_INT_MAX;

            for ($i = 0; $i < $numBlokken; $i++) {
                $idx = ($i + $startOffset) % $numBlokken;
                $huidig = $blokWedstrijden[$idx];

                // Prefer blocks that are furthest below target
                if ($huidig < $minWedstrijden) {
                    $minWedstrijden = $huidig;
                    $besteBlok = $idx;
                }
            }

            $toewijzing[$leeftijd] = $besteBlok;
            $blokWedstrijden[$besteBlok] += $totaalWed;

            // Rotate start for variety
            $startOffset = ($besteBlok + 1) % $numBlokken;
        }

        return $toewijzing;
    }

    /**
     * Find best block for a single weight category
     * HARD LIMIT: block can NEVER exceed 25% deviation
     * Aansluiting is secondary - breaks if necessary to respect limit
     */
    private function vindBesteBlokVoorGewicht(
        int $vorigeBlokIndex,
        int $wedstrijden,
        array $capaciteit,
        array $blokken,
        int $numBlokken,
        float $aansluitingGewicht
    ): int {
        $besteBlok = null;
        $besteScore = PHP_INT_MAX;

        // Check blocks in preference order: same, +1, +2, +3, +4, +5, -1, -2, etc.
        $checkOrder = [0, 1, 2, 3, 4, 5, -1, -2, -3, -4, -5];

        foreach ($checkOrder as $offset) {
            $idx = $vorigeBlokIndex + $offset;
            if ($idx < 0 || $idx >= $numBlokken) continue;

            // HARD LIMITS gebaseerd op aansluiting gewicht:
            // 100%: alleen 0 of +1 toegestaan
            // ≥50%: vermijd -1 (terug) en +3+ (te ver vooruit)
            if ($aansluitingGewicht >= 0.99) {
                // 100% aansluiting: alleen zelfde of volgend blok
                if ($offset !== 0 && $offset !== 1) {
                    continue;
                }
            } elseif ($aansluitingGewicht >= 0.50) {
                // ≥50% aansluiting: geen -1 (terug) of +3+ (te ver)
                if ($offset < 0 || $offset >= 3) {
                    continue;
                }
            }
            // <50%: alles toegestaan via scoring

            $blok = $blokken[$idx];
            $cap = $capaciteit[$blok->id];
            $gewenst = max(1, $cap['gewenst']);
            $nieuweActueel = $cap['actueel'] + $wedstrijden;

            // HARD LIMIT: NEVER exceed 25% over gewenst
            $maxAllowed = $gewenst * 1.25;
            if ($nieuweActueel > $maxAllowed) {
                continue;  // Skip this block entirely - cannot use it
            }

            // Aansluiting score (only matters if within limit)
            // 0 of +1 = perfect, +2 = acceptabel, -1 of +3+ = slecht
            if ($offset === 0 || $offset === 1) {
                $aansluitingScore = 0;  // Perfect (zelfde of volgend blok)
            } elseif ($offset === 2) {
                $aansluitingScore = 20;  // Acceptable
            } elseif ($offset < 0) {
                $aansluitingScore = 200 + abs($offset) * 100;  // Backwards = very bad
            } else {
                $aansluitingScore = 50 + $offset * 30;  // Forward gaps
            }

            // Prefer blocks with more room (further from 25% limit)
            $vulgraad = $nieuweActueel / $gewenst;
            $capacityScore = $vulgraad * 50;

            // Combined score
            $score = ($capacityScore * (1 - $aansluitingGewicht)) + ($aansluitingScore * $aansluitingGewicht);

            if ($score < $besteScore) {
                $besteScore = $score;
                $besteBlok = $idx;
            }
        }

        // If no block found within 25% limit, log warning and use block with most room
        // This variant will be rejected during scoring if it exceeds limits
        if ($besteBlok === null) {
            Log::warning('Blokverdeling: geen blok beschikbaar binnen 25% limiet', [
                'vorige_blok' => $vorigeBlokIndex,
                'wedstrijden' => $wedstrijden,
            ]);

            // Return block with most room - variant will be marked as invalid
            $maxRuimte = -PHP_INT_MAX;
            foreach ($blokken as $idx => $blok) {
                $cap = $capaciteit[$blok->id];
                $ruimte = $cap['gewenst'] - $cap['actueel'];
                if ($ruimte > $maxRuimte) {
                    $maxRuimte = $ruimte;
                    $besteBlok = $idx;
                }
            }
        }

        return $besteBlok ?? $vorigeBlokIndex;
    }

    /**
     * Calculate quality scores for a variant
     * Marks variant as invalid if any block exceeds 25% deviation
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
        $maxAfwijkingPct = 0;
        $blokStats = [];
        $isValid = true;  // Variant is valid unless a block exceeds 25% limit

        foreach ($blokken as $blok) {
            $cap = $capaciteit[$blok->id];
            $gewenst = max(1, $cap['gewenst']);
            $afwijking = abs($cap['actueel'] - $gewenst);
            $afwijkingPct = ($cap['actueel'] - $gewenst) / $gewenst * 100;

            $verdelingScore += $afwijking;
            $maxAfwijking = max($maxAfwijking, $afwijking);
            $maxAfwijkingPct = max($maxAfwijkingPct, abs($afwijkingPct));

            // HARD LIMIT: if any block exceeds 25%, variant is INVALID
            if ($afwijkingPct > 25) {
                $isValid = false;
            }

            $blokStats[$blok->nummer] = [
                'actueel' => $cap['actueel'],
                'gewenst' => $gewenst,
                'afwijking' => $afwijking,
                'afwijking_pct' => round($afwijkingPct, 1),
            ];
        }

        // Aansluiting score: count "breaks" based on block transitions
        // Same block (0) or +1 = perfect (no break)
        // +2 = minor break, -1 or +3+ = major break
        $aansluitingScore = 0;
        $breaks = 0;

        foreach ($perLeeftijd as $leeftijd => $gewichten) {
            $vorigBlok = null;
            foreach ($gewichten as $cat) {
                $key = $cat['leeftijd'] . '|' . $cat['gewicht'];
                $blokNr = $toewijzingen[$key] ?? null;

                if ($vorigBlok !== null && $blokNr !== null) {
                    $verschil = $blokNr - $vorigBlok;

                    if ($verschil === 0 || $verschil === 1) {
                        // Same block or +1 = PERFECT, no break
                    } elseif ($verschil === 2) {
                        // +2 blocks = minor break
                        $breaks++;
                        $aansluitingScore += 10;
                    } elseif ($verschil < 0) {
                        // Going BACKWARDS = major break!
                        $breaks += 2;  // Count as 2 breaks (very bad)
                        $aansluitingScore += 50 + abs($verschil) * 20;
                    } else {
                        // +3 or more = major break
                        $breaks += 2;
                        $aansluitingScore += 30 + ($verschil - 2) * 15;
                    }
                }
                $vorigBlok = $blokNr;
            }
        }

        return [
            'verdeling_score' => $verdelingScore,
            'max_afwijking' => $maxAfwijking,
            'max_afwijking_pct' => round($maxAfwijkingPct, 1),
            'aansluiting_score' => $aansluitingScore,
            'breaks' => $breaks,
            'blok_stats' => $blokStats,
            'is_valid' => $isValid,
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
                $poules = $blok->poules
                    ->where('mat_id', $mat->id)
                    ->filter(fn($p) => $p->aantal_judokas > 0); // Filter empty poules

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
