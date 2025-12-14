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
     * +1 (next block), -1 (previous block), +2 (two blocks ahead) - max 3 options!
     */
    private array $aansluitingOpties = [0, 1, -1, 2];

    /**
     * Grote leeftijdsklassen in volgorde (jongste eerst)
     * Deze bepalen de structuur van de verdeling
     */
    private array $groteLeeftijden = ["Mini's", 'A-pupillen', 'B-pupillen', 'Heren -15', 'Heren -18', 'Heren'];

    /**
     * Kleine leeftijdsklassen (worden als opvulling gebruikt)
     */
    private array $kleineLeeftijden = ['Dames -15', 'Dames -18', 'Dames'];

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
        $maxPogingen = 500;  // More attempts for better variety

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
     * NEW APPROACH:
     * 1. Grote leeftijden eerst (Mini's → A-pup → B-pup → H-15 → H-18 → Heren)
     * 2. Mini's start in blok 1, volgende sluit aan
     * 3. Per leeftijd: gewichtscategorieën aansluitend (+1, -1, +2 max)
     * 4. Kleine leeftijden als opvulling waar verdeling laag is
     * 5. Variatie door: startposities, aansluitingkeuzes variëren
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

        // User weights (slider)
        $verdelingGewicht = $userVerdelingGewicht / 100.0;
        $aansluitingGewicht = $userAansluitingGewicht / 100.0;

        // Variatie parameters gebaseerd op seed
        $startBlokOffset = $seed % 3;  // 0, 1, of 2 blokken offset voor Mini's
        $aansluitingVariant = $seed % 4;  // Welke aansluiting optie bij vol blok

        // STAP 1: Plaats grote leeftijdsklassen in volgorde
        $huidigeBlokIndex = $startBlokOffset;  // Mini's start hier

        foreach ($this->groteLeeftijden as $leeftijd) {
            if (!isset($perLeeftijd[$leeftijd])) continue;

            $gewichten = $perLeeftijd[$leeftijd];
            // Sorteer gewichten (licht naar zwaar)
            usort($gewichten, fn($a, $b) => $a['gewicht_num'] <=> $b['gewicht_num']);

            // Variatie: soms start met grootste gewichtscategorie in andere positie
            if ($seed % 7 === 0) {
                // Start met de grootste gewichtscategorie eerst
                usort($gewichten, fn($a, $b) => $b['wedstrijden'] <=> $a['wedstrijden']);
            }

            $vorigeBlokIndex = $huidigeBlokIndex;

            foreach ($gewichten as $cat) {
                $key = $cat['leeftijd'] . '|' . $cat['gewicht'];

                // Vind beste blok met aansluiting beperking (+1, -1, +2)
                $besteBlokIndex = $this->vindBesteBlokMetAansluiting(
                    $vorigeBlokIndex,
                    $cat['wedstrijden'],
                    $capaciteit,
                    $blokkenArray,
                    $numBlokken,
                    $aansluitingVariant,
                    $verdelingGewicht
                );

                // Record assignment
                $blok = $blokkenArray[$besteBlokIndex];
                $toewijzingen[$key] = $blok->nummer;

                // Update capacity
                $capaciteit[$blok->id]['actueel'] += $cat['wedstrijden'];
                $capaciteit[$blok->id]['ruimte'] -= $cat['wedstrijden'];

                $vorigeBlokIndex = $besteBlokIndex;
            }

            // Volgende leeftijd start waar deze eindigde (aansluiting)
            $huidigeBlokIndex = $vorigeBlokIndex;
        }

        // STAP 2: Plaats kleine leeftijdsklassen als opvulling
        foreach ($this->kleineLeeftijden as $leeftijd) {
            if (!isset($perLeeftijd[$leeftijd])) continue;

            $gewichten = $perLeeftijd[$leeftijd];
            usort($gewichten, fn($a, $b) => $a['gewicht_num'] <=> $b['gewicht_num']);

            // Start in blok met meeste ruimte
            $startBlok = $this->vindBlokMetMeesteRuimte($capaciteit, $blokkenArray);
            $vorigeBlokIndex = $startBlok;

            foreach ($gewichten as $cat) {
                $key = $cat['leeftijd'] . '|' . $cat['gewicht'];

                $besteBlokIndex = $this->vindBesteBlokMetAansluiting(
                    $vorigeBlokIndex,
                    $cat['wedstrijden'],
                    $capaciteit,
                    $blokkenArray,
                    $numBlokken,
                    $aansluitingVariant,
                    $verdelingGewicht
                );

                $blok = $blokkenArray[$besteBlokIndex];
                $toewijzingen[$key] = $blok->nummer;

                $capaciteit[$blok->id]['actueel'] += $cat['wedstrijden'];
                $capaciteit[$blok->id]['ruimte'] -= $cat['wedstrijden'];

                $vorigeBlokIndex = $besteBlokIndex;
            }
        }

        // STAP 3: Plaats eventuele overige leeftijden (niet in grote of kleine lijst)
        foreach ($perLeeftijd as $leeftijd => $gewichten) {
            if (in_array($leeftijd, $this->groteLeeftijden) || in_array($leeftijd, $this->kleineLeeftijden)) {
                continue;
            }

            usort($gewichten, fn($a, $b) => $a['gewicht_num'] <=> $b['gewicht_num']);
            $startBlok = $this->vindBlokMetMeesteRuimte($capaciteit, $blokkenArray);
            $vorigeBlokIndex = $startBlok;

            foreach ($gewichten as $cat) {
                $key = $cat['leeftijd'] . '|' . $cat['gewicht'];

                $besteBlokIndex = $this->vindBesteBlokMetAansluiting(
                    $vorigeBlokIndex,
                    $cat['wedstrijden'],
                    $capaciteit,
                    $blokkenArray,
                    $numBlokken,
                    $aansluitingVariant,
                    $verdelingGewicht
                );

                $blok = $blokkenArray[$besteBlokIndex];
                $toewijzingen[$key] = $blok->nummer;

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
                'start_offset' => $startBlokOffset,
            ],
        ];
    }

    /**
     * Vind beste blok met strikte aansluiting regels: +1, -1, +2 (max!)
     */
    private function vindBesteBlokMetAansluiting(
        int $vorigeBlokIndex,
        int $wedstrijden,
        array $capaciteit,
        array $blokken,
        int $numBlokken,
        int $aansluitingVariant,
        float $verdelingGewicht
    ): int {
        // Aansluiting opties in volgorde: zelfde, +1, -1, +2
        // Varieer de volgorde gebaseerd op variant
        $opties = match($aansluitingVariant) {
            0 => [0, 1, -1, 2],   // Standaard: vooruit
            1 => [0, -1, 1, 2],   // Achteruit eerst
            2 => [0, 1, 2, -1],   // Vooruit, dan ver, dan terug
            default => [0, 1, -1, 2],
        };

        $besteBlok = null;
        $besteScore = PHP_INT_MAX;

        foreach ($opties as $offset) {
            $idx = $vorigeBlokIndex + $offset;
            if ($idx < 0 || $idx >= $numBlokken) continue;

            $blok = $blokken[$idx];
            $cap = $capaciteit[$blok->id];
            $gewenst = max(1, $cap['gewenst']);
            $nieuweActueel = $cap['actueel'] + $wedstrijden;

            // HARD LIMIT: nooit meer dan 30% over gewenst
            $maxAllowed = $gewenst * 1.30;
            if ($nieuweActueel > $maxAllowed) {
                continue;
            }

            // Score: combinatie van vulgraad en aansluiting
            $vulgraad = $nieuweActueel / $gewenst;
            $aansluitingPenalty = abs($offset) * 20;

            // Als verdeling belangrijk is: prefereer blokken met meer ruimte
            // Als aansluiting belangrijk is: prefereer dichtbij vorige
            $score = ($vulgraad * 50 * $verdelingGewicht) + ($aansluitingPenalty * (1 - $verdelingGewicht));

            if ($score < $besteScore) {
                $besteScore = $score;
                $besteBlok = $idx;
            }
        }

        // Als geen blok past binnen aansluiting opties, zoek blok met meeste ruimte
        if ($besteBlok === null) {
            $besteBlok = $this->vindBlokMetMeesteRuimte($capaciteit, $blokken);
        }

        return $besteBlok ?? $vorigeBlokIndex;
    }

    /**
     * Find block with most available space
     */
    private function vindBlokMetMeesteRuimte(array $capaciteit, $blokken): int
    {
        $maxRuimte = -PHP_INT_MAX;
        $besteIndex = 0;

        foreach ($blokken as $index => $blok) {
            $ruimte = $capaciteit[$blok->id]['ruimte'] ?? 0;
            if ($ruimte > $maxRuimte) {
                $maxRuimte = $ruimte;
                $besteIndex = $index;
            }
        }

        return $besteIndex;
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
