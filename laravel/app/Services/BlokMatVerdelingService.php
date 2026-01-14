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
     * Service for variable categories (max_leeftijd_verschil > 0 or max_kg_verschil > 0)
     */
    private ?VariabeleBlokVerdelingService $variabeleService = null;

    /**
     * Preference order for placing categories relative to previous weight
     * +1 (next block), -1 (previous block), +2 (two blocks ahead) - max 3 options!
     */
    private array $aansluitingOpties = [0, 1, -1, 2];

    /**
     * Get "grote" leeftijdsklassen (primary structure)
     * Based on gender: M, gemengd, or no gender = groot
     */
    private function getGroteLeeftijden(Toernooi $toernooi): array
    {
        $config = $toernooi->getAlleGewichtsklassen();
        $grote = [];
        foreach ($config as $key => $data) {
            $geslacht = $data['geslacht'] ?? 'gemengd';
            // M, gemengd, or no gender = grote (primary structure)
            if ($geslacht !== 'V') {
                $grote[] = $data['label'] ?? $key;
            }
        }
        return $grote;
    }

    /**
     * Get "kleine" leeftijdsklassen (used as filler)
     * Based on gender: V = klein
     */
    private function getKleineLeeftijden(Toernooi $toernooi): array
    {
        $config = $toernooi->getAlleGewichtsklassen();
        $kleine = [];
        foreach ($config as $key => $data) {
            $geslacht = $data['geslacht'] ?? 'gemengd';
            // V = kleine (filler)
            if ($geslacht === 'V') {
                $kleine[] = $data['label'] ?? $key;
            }
        }
        return $kleine;
    }

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
        // Check for variable categories and delegate
        if ($this->heeftVariabeleCategorieen($toernooi)) {
            return $this->getVariabeleService()->genereerVarianten($toernooi, $userVerdelingGewicht);
        }

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

        // Keep generating until time limit
        $alleVarianten = [];
        $ongeligeVarianten = [];  // Backup als geen geldige gevonden
        $gezien = [];
        $poging = 0;
        $startTime = microtime(true);
        $maxTijd = 3.0;  // 3 seconden max

        while (true) {
            $elapsed = microtime(true) - $startTime;

            // ALTIJD stoppen na max tijd of max pogingen
            if ($elapsed >= $maxTijd || $poging >= 50000) {
                break;
            }

            $variant = $this->simuleerVerdeling(
                $perLeeftijd,
                $blokken,
                $baseCapaciteit,
                $categories,
                $poging,
                $userVerdelingGewicht,
                $userAansluitingGewicht
            );

            $variant['id'] = $poging + 1;
            $variant['poging'] = $poging;

            // Check for duplicates
            $toewijzingenSorted = $variant['toewijzingen'];
            ksort($toewijzingenSorted);
            $hash = md5(json_encode($toewijzingenSorted));

            if (!isset($gezien[$hash])) {
                $gezien[$hash] = true;

                if ($variant['scores']['is_valid']) {
                    $alleVarianten[] = $variant;
                } else {
                    // Bewaar ongeldige als backup
                    $ongeligeVarianten[] = $variant;
                }
            }

            $poging++;
        }

        $elapsed = round(microtime(true) - $startTime, 2);

        // Als geen geldige, gebruik beste ongeldige
        if (empty($alleVarianten) && !empty($ongeligeVarianten)) {
            $alleVarianten = $ongeligeVarianten;
            Log::warning("Geen geldige varianten, gebruik beste ongeldige", [
                'ongeldige_count' => count($ongeligeVarianten),
            ]);
        }

        // Sort all variants by totaal_score (lower = better)
        usort($alleVarianten, fn($a, $b) => $a['totaal_score'] <=> $b['totaal_score']);

        // Take top 5 UNIQUE variants (different toewijzingen)
        $beste = [];
        $gezienHashes = [];
        foreach ($alleVarianten as $variant) {
            $toewijzingen = $variant['toewijzingen'];
            ksort($toewijzingen);
            $hash = md5(json_encode($toewijzingen));

            if (!isset($gezienHashes[$hash])) {
                $gezienHashes[$hash] = true;
                $beste[] = $variant;

                Log::debug("Variant toegevoegd aan top 5", [
                    'index' => count($beste),
                    'totaal_score' => $variant['totaal_score'],
                    'verdeling' => $variant['scores']['verdeling_score'],
                    'aansluiting' => $variant['scores']['aansluiting_score'],
                ]);

                if (count($beste) >= 5) {
                    break;
                }
            }
        }

        Log::info("Blokverdeling klaar", [
            'pogingen' => $poging,
            'tijd_sec' => $elapsed,
            'per_seconde' => $elapsed > 0 ? round($poging / $elapsed) : $poging,
            'geldige_varianten' => count($alleVarianten),
            'beste_score' => isset($beste[0]) ? $beste[0]['totaal_score'] : 'N/A',
        ]);

        // Return top 5 unique variants with stats
        $stats = [
            'pogingen' => $poging,
            'tijd_sec' => $elapsed,
            'per_seconde' => $elapsed > 0 ? round($poging / $elapsed) : $poging,
            'unieke_varianten' => count($gezien),
            'geldige_varianten' => count($alleVarianten),
            'getoond' => count($beste),
        ];

        if (empty($beste)) {
            return [
                'varianten' => [],
                'stats' => $stats,
                'error' => 'Geen geldige verdeling mogelijk binnen 25% limiet. Pas het aantal blokken of categorieën aan.',
            ];
        }

        return ['varianten' => $beste, 'stats' => $stats];
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
        mt_srand($seed * 12345 + 67890);

        // User weights (slider) + kleine variatie per berekening
        $gewichtVariatie = (($seed % 20) - 10) / 100.0;  // -10% tot +10%
        $verdelingGewicht = max(0.1, min(0.9, ($userVerdelingGewicht / 100.0) + $gewichtVariatie));
        $aansluitingGewicht = 1.0 - $verdelingGewicht;

        // VEEL variatie parameters voor unieke resultaten
        $aansluitingVariant = $seed % 6;  // 6 verschillende aansluiting strategieën
        $randomFactor = ($seed % 100) / 100.0;  // 0.00 - 0.99 random factor
        $sorteerStrategie = $seed % 10;  // 10 sorteer strategieën
        $leeftijdShuffle = $seed % 8;  // 8 shuffle opties voor leeftijden

        // Maak kopie van grote leeftijden en shuffle (behalve Mini's blijft eerst!)
        $groteLeeftijdenVolgorde = $this->getGroteLeeftijden($toernooi);
        if ($leeftijdShuffle >= 4) {
            // Shuffle alleen posities 1-5 (Mini's blijft op 0)
            $rest = array_slice($groteLeeftijdenVolgorde, 1);
            shuffle($rest);
            $groteLeeftijdenVolgorde = array_merge([$groteLeeftijdenVolgorde[0]], $rest);
        }

        // STAP 1: Plaats grote leeftijdsklassen in volgorde
        // Check eerst of er vastgezette categorieën zijn per leeftijd - sluit daar bij aan!
        $vastgezetteBloknummersPerLeeftijd = [];
        foreach ($alleCategorieen as $cat) {
            if ($cat['blok_vast'] && $cat['blok_id']) {
                // Zoek bloknummer bij blok_id
                $blokNummer = null;
                foreach ($blokkenArray as $blok) {
                    if ($blok->id == $cat['blok_id']) {
                        $blokNummer = $blok->nummer;
                        break;
                    }
                }
                if ($blokNummer) {
                    $leeftijd = $cat['leeftijd'];
                    if (!isset($vastgezetteBloknummersPerLeeftijd[$leeftijd])) {
                        $vastgezetteBloknummersPerLeeftijd[$leeftijd] = [];
                    }
                    $vastgezetteBloknummersPerLeeftijd[$leeftijd][] = $blokNummer;
                }
            }
        }

        // Mini's start ALTIJD in blok 1 (index 0) TENZIJ er vastgezette zijn
        $huidigeBlokIndex = 0;

        foreach ($groteLeeftijdenVolgorde as $leeftijd) {
            if (!isset($perLeeftijd[$leeftijd])) continue;

            $gewichten = $perLeeftijd[$leeftijd];

            // CHECK: Zijn er vastgezette categorieën voor deze leeftijd?
            // Zo ja, start bij het LAAGSTE blok van de vastgezette (om aan te sluiten)
            if (isset($vastgezetteBloknummersPerLeeftijd[$leeftijd])) {
                $vastBlokken = $vastgezetteBloknummersPerLeeftijd[$leeftijd];
                $minVastBlok = min($vastBlokken);
                // Zoek de index van dit bloknummer
                foreach ($blokkenArray as $idx => $blok) {
                    if ($blok->nummer == $minVastBlok) {
                        $huidigeBlokIndex = max(0, $idx - 1);  // Start 1 blok eerder voor aansluiting
                        break;
                    }
                }
                Log::info("Leeftijd {$leeftijd} heeft vastgezette in blokken " . implode(',', $vastBlokken) . " - start bij index {$huidigeBlokIndex}");
            }

            // Variatie in sortering (maar altijd licht→zwaar als basis)
            usort($gewichten, fn($a, $b) => $a['gewicht_num'] <=> $b['gewicht_num']);

            // Meer shuffle strategieën
            if ($sorteerStrategie >= 3 && count($gewichten) > 2) {
                // Meerdere swaps mogelijk
                $aantalSwaps = ($sorteerStrategie >= 7) ? 2 : 1;
                for ($i = 0; $i < $aantalSwaps; $i++) {
                    $swapPos = mt_rand(0, count($gewichten) - 2);
                    $temp = $gewichten[$swapPos];
                    $gewichten[$swapPos] = $gewichten[$swapPos + 1];
                    $gewichten[$swapPos + 1] = $temp;
                }
            }

            $vorigeBlokIndex = $huidigeBlokIndex;

            foreach ($gewichten as $cat) {
                $key = $cat['leeftijd'] . '|' . $cat['gewicht'];

                // Vind beste blok met aansluiting + random factor
                $besteBlokIndex = $this->vindBesteBlokMetAansluiting(
                    $vorigeBlokIndex,
                    $cat['wedstrijden'],
                    $capaciteit,
                    $blokkenArray,
                    $numBlokken,
                    $aansluitingVariant,
                    $verdelingGewicht,
                    $randomFactor
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
        // Shuffle ook de volgorde van kleine leeftijden
        $kleineLeeftijdenVolgorde = $this->getKleineLeeftijden($toernooi);
        if ($leeftijdShuffle >= 2 && $leeftijdShuffle < 6) {
            shuffle($kleineLeeftijdenVolgorde);
        }

        foreach ($kleineLeeftijdenVolgorde as $leeftijd) {
            if (!isset($perLeeftijd[$leeftijd])) continue;

            $gewichten = $perLeeftijd[$leeftijd];
            usort($gewichten, fn($a, $b) => $a['gewicht_num'] <=> $b['gewicht_num']);

            // Ook hier swaps voor variatie
            if ($sorteerStrategie >= 5 && count($gewichten) > 2) {
                $swapPos = mt_rand(0, count($gewichten) - 2);
                $temp = $gewichten[$swapPos];
                $gewichten[$swapPos] = $gewichten[$swapPos + 1];
                $gewichten[$swapPos + 1] = $temp;
            }

            // Variatie: start in blok met meeste ruimte OF random blok met ruimte
            if ($randomFactor > 0.5) {
                $startBlok = $this->vindBlokMetMeesteRuimte($capaciteit, $blokkenArray);
            } else {
                // Random blok uit top 3 met meeste ruimte
                $startBlok = $this->vindRandomBlokMetRuimte($capaciteit, $blokkenArray);
            }
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
                    $verdelingGewicht,
                    $randomFactor
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
            $startBlok = $this->vindRandomBlokMetRuimte($capaciteit, $blokkenArray);
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
                    $verdelingGewicht,
                    $randomFactor
                );

                $blok = $blokkenArray[$besteBlokIndex];
                $toewijzingen[$key] = $blok->nummer;

                $capaciteit[$blok->id]['actueel'] += $cat['wedstrijden'];
                $capaciteit[$blok->id]['ruimte'] -= $cat['wedstrijden'];

                $vorigeBlokIndex = $besteBlokIndex;
            }
        }

        // Calculate scores for this variant (met slider gewichten)
        $scores = $this->berekenScores(
            $toewijzingen,
            $capaciteit,
            $blokkenArray,
            $perLeeftijd,
            $verdelingGewicht,
            $aansluitingGewicht
        );

        return [
            'toewijzingen' => $toewijzingen,
            'capaciteit' => $capaciteit,
            'scores' => $scores,
            'totaal_score' => $scores['totaal_score'],  // Gewogen totaal
        ];
    }

    /**
     * Vind beste blok met strikte aansluiting regels: +1, -1, +2 (max!)
     * Random factor voegt variatie toe aan de keuze
     */
    private function vindBesteBlokMetAansluiting(
        int $vorigeBlokIndex,
        int $wedstrijden,
        array $capaciteit,
        array $blokken,
        int $numBlokken,
        int $aansluitingVariant,
        float $verdelingGewicht,
        float $randomFactor = 0.0
    ): int {
        // 6 verschillende aansluiting strategieën voor variatie
        $opties = match($aansluitingVariant) {
            0 => [0, 1, -1, 2],   // Standaard: vooruit
            1 => [0, -1, 1, 2],   // Achteruit eerst
            2 => [0, 1, 2, -1],   // Vooruit, dan ver, dan terug
            3 => [1, 0, 2, -1],   // +1 eerst (spread out)
            4 => [0, 2, 1, -1],   // +2 als tweede optie
            5 => [1, -1, 0, 2],   // Wissel eerst
            default => [0, 1, -1, 2],
        };

        $kandidaten = [];  // Alle geldige opties verzamelen

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
            $score = ($vulgraad * 50 * $verdelingGewicht) + ($aansluitingPenalty * (1 - $verdelingGewicht));

            // Voeg random noise toe voor variatie
            $score += mt_rand(0, 100) * $randomFactor * 0.5;

            $kandidaten[] = ['idx' => $idx, 'score' => $score];
        }

        if (empty($kandidaten)) {
            // Geen geldige opties, zoek blok met meeste ruimte
            return $this->vindBlokMetMeesteRuimte($capaciteit, $blokken);
        }

        // Sorteer op score (laagste eerst)
        usort($kandidaten, fn($a, $b) => $a['score'] <=> $b['score']);

        // Met random factor: soms niet de beste maar 2e of 3e kiezen
        if ($randomFactor > 0.7 && count($kandidaten) > 1) {
            return $kandidaten[1]['idx'];  // Kies 2e beste
        }
        if ($randomFactor > 0.9 && count($kandidaten) > 2) {
            return $kandidaten[2]['idx'];  // Kies 3e beste
        }

        return $kandidaten[0]['idx'];
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
     * Find a random block from top 3 with most space (for variation)
     */
    private function vindRandomBlokMetRuimte(array $capaciteit, $blokken): int
    {
        $blokkenMetRuimte = [];

        foreach ($blokken as $index => $blok) {
            $ruimte = $capaciteit[$blok->id]['ruimte'] ?? 0;
            $blokkenMetRuimte[] = ['idx' => $index, 'ruimte' => $ruimte];
        }

        // Sorteer op ruimte (meeste eerst)
        usort($blokkenMetRuimte, fn($a, $b) => $b['ruimte'] <=> $a['ruimte']);

        // Kies random uit top 3
        $topN = min(3, count($blokkenMetRuimte));
        $keuze = mt_rand(0, $topN - 1);

        return $blokkenMetRuimte[$keuze]['idx'];
    }


    /**
     * Calculate quality scores for a variant
     *
     * Verdeling: som van absolute % afwijkingen per blok (lager = beter)
     * Aansluiting: punten per overgang tussen gewichtscategorieën
     *   - Zelfde blok (0) = 0 punten
     *   - Volgend blok (+1) = 10 punten
     *   - Vorig blok (-1) = 20 punten
     *   - 2 blokken later (+2) = 30 punten
     *   - Anders = 50+ punten (slecht)
     *
     * Totaal = (verdelingGewicht * verdeling) + (aansluitingGewicht * aansluiting)
     * Lager = beter
     */
    private function berekenScores(
        array $toewijzingen,
        array $capaciteit,
        $blokken,
        array $perLeeftijd,
        float $verdelingGewicht = 0.5,
        float $aansluitingGewicht = 0.5
    ): array {
        // Verdeling score: SOM van absolute % afwijkingen per blok
        $verdelingScore = 0;
        $maxAfwijkingPct = 0;
        $blokStats = [];
        $isValid = true;

        foreach ($blokken as $blok) {
            $cap = $capaciteit[$blok->id];
            $gewenst = max(1, $cap['gewenst']);
            $afwijkingPct = abs(($cap['actueel'] - $gewenst) / $gewenst * 100);

            // Verdeling score = som van alle % afwijkingen
            $verdelingScore += $afwijkingPct;
            $maxAfwijkingPct = max($maxAfwijkingPct, $afwijkingPct);

            // HARD LIMIT: if any block exceeds 25%, variant is INVALID
            if ($afwijkingPct > 25) {
                $isValid = false;
            }

            $blokStats[$blok->nummer] = [
                'actueel' => $cap['actueel'],
                'gewenst' => $gewenst,
                'afwijking_pct' => round($afwijkingPct, 1),
            ];
        }

        // Aansluiting score: punten per overgang tussen gewichtscategorieën
        // Zelfde blok = 0, +1 = 10, -1 = 20, +2 = 30, anders = 50+
        $aansluitingScore = 0;
        $overgangen = 0;
        $aflopendeLeeftijden = 0;  // Leeftijden die meer achteruit dan vooruit gaan

        foreach ($perLeeftijd as $leeftijd => $gewichten) {
            $vorigBlok = null;
            $eersteBlok = null;
            $laatsteBlok = null;
            $richting = 0;  // Som van alle overgangen binnen deze leeftijd

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
                    $richting += $verschil;  // Track netto richting

                    $punten = match(true) {
                        $verschil === 0 => 0,    // Zelfde blok = perfect
                        $verschil === 1 => 10,   // Volgend blok = goed
                        $verschil === -1 => 20,  // Vorig blok = matig (1x is ok)
                        $verschil === 2 => 30,   // 2 blokken later = acceptabel
                        $verschil < -1 => 50 + abs($verschil) * 10,  // Ver terug = slecht
                        default => 50 + $verschil * 10,  // Ver vooruit = slecht
                    };
                    $aansluitingScore += $punten;
                }
                $vorigBlok = $blokNr;
            }

            // Check: gaat deze leeftijd AFLOPEND? (laatste blok < eerste blok)
            // Dit betekent dat de lichtste gewichten in een later blok zitten dan de zwaarste
            if ($eersteBlok !== null && $laatsteBlok !== null && $laatsteBlok < $eersteBlok) {
                $aflopendeLeeftijden++;
                // Grote penalty voor aflopende leeftijdsklasse!
                $aansluitingScore += 200;
            }
        }

        // Totaal score: gewogen som (lager = beter)
        // Normaliseer aansluiting naar vergelijkbare schaal als verdeling
        $totaalScore = ($verdelingGewicht * $verdelingScore) + ($aansluitingGewicht * $aansluitingScore);

        return [
            'verdeling_score' => round($verdelingScore, 1),
            'aansluiting_score' => $aansluitingScore,
            'totaal_score' => round($totaalScore, 1),
            'max_afwijking_pct' => round($maxAfwijkingPct, 1),
            'overgangen' => $overgangen,
            'aflopend' => $aflopendeLeeftijden,  // Leeftijden die verkeerd om lopen
            'blok_stats' => $blokStats,
            'is_valid' => $isValid,
            'gewichten' => [
                'verdeling' => round($verdelingGewicht * 100),
                'aansluiting' => round($aansluitingGewicht * 100),
            ],
        ];
    }

    /**
     * Apply toewijzingen to the database
     * Updates all non-pinned categories to their assigned blocks
     *
     * Format: "leeftijdsklasse|gewichtsklasse" => blok_nummer
     */
    public function pasVariantToe(Toernooi $toernooi, array $toewijzingen): void
    {
        DB::transaction(function () use ($toernooi, $toewijzingen) {
            foreach ($toewijzingen as $key => $blokNummer) {
                [$leeftijd, $gewicht] = explode('|', $key);

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

            // Fix kruisfinales without blok_id: copy from their voorrondepoules
            $kruisfinalesZonderBlok = Poule::where('toernooi_id', $toernooi->id)
                ->where('type', 'kruisfinale')
                ->whereNull('blok_id')
                ->get();

            foreach ($kruisfinalesZonderBlok as $kruisfinale) {
                $voorrondeBlokId = Poule::where('toernooi_id', $toernooi->id)
                    ->where('leeftijdsklasse', $kruisfinale->leeftijdsklasse)
                    ->where('gewichtsklasse', $kruisfinale->gewichtsklasse)
                    ->where('type', 'voorronde')
                    ->whereNotNull('blok_id')
                    ->value('blok_id');

                if ($voorrondeBlokId) {
                    $kruisfinale->update(['blok_id' => $voorrondeBlokId]);
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

        // Fix kruisfinales without mat_id: copy from voorrondepoule of same category
        $kruisfinalesZonderMat = Poule::where('toernooi_id', $toernooi->id)
            ->where('type', 'kruisfinale')
            ->whereNull('mat_id')
            ->get();

        foreach ($kruisfinalesZonderMat as $kruisfinale) {
            $voorrondeMatId = Poule::where('toernooi_id', $toernooi->id)
                ->where('leeftijdsklasse', $kruisfinale->leeftijdsklasse)
                ->where('gewichtsklasse', $kruisfinale->gewichtsklasse)
                ->where('type', 'voorronde')
                ->whereNotNull('mat_id')
                ->value('mat_id');

            if ($voorrondeMatId) {
                $kruisfinale->update(['mat_id' => $voorrondeMatId]);
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
     * Check if toernooi has variable categories
     */
    private function heeftVariabeleCategorieen(Toernooi $toernooi): bool
    {
        return $this->getVariabeleService()->heeftVariabeleCategorieen($toernooi);
    }

    /**
     * Get or create VariabeleBlokVerdelingService instance
     */
    private function getVariabeleService(): VariabeleBlokVerdelingService
    {
        if ($this->variabeleService === null) {
            $this->variabeleService = app(VariabeleBlokVerdelingService::class);
        }
        return $this->variabeleService;
    }

    /**
     * Get hall overview (zaaloverzicht)
     * BELANGRIJK: Telt alleen ACTIEVE judoka's (niet afwezig, gewicht binnen klasse)
     * en herberekent wedstrijden op basis daarvan!
     */
    public function getZaalOverzicht(Toernooi $toernooi): array
    {
        $overzicht = [];
        $tolerantie = $toernooi->gewicht_tolerantie ?? 0;

        // Eager load judokas for active count calculation
        foreach ($toernooi->blokken()->with('poules.mat', 'poules.judokas')->get() as $blok) {
            $blokData = [
                'nummer' => $blok->nummer,
                'naam' => $blok->naam,
                'weging_gesloten' => $blok->weging_gesloten,
                'matten' => [],
            ];

            foreach ($toernooi->matten as $mat) {
                $poules = $blok->poules
                    ->where('mat_id', $mat->id);

                $blokData['matten'][$mat->nummer] = [
                    'mat_naam' => $mat->label,
                    'poules' => $poules->map(function($p) use ($tolerantie) {
                        // Kruisfinales: gebruik geplande aantallen (nog geen judokas gekoppeld)
                        if ($p->type === 'kruisfinale') {
                            return [
                                'id' => $p->id,
                                'nummer' => $p->nummer,
                                'titel' => $p->titel,
                                'leeftijdsklasse' => $p->leeftijdsklasse,
                                'gewichtsklasse' => $p->gewichtsklasse,
                                'type' => 'kruisfinale',
                                'judokas' => $p->aantal_judokas,
                                'wedstrijden' => $p->aantal_wedstrijden,
                            ];
                        }

                        // Tel alleen ACTIEVE judoka's (niet afwezig, gewicht binnen klasse)
                        $actieveJudokas = $p->judokas->filter(
                            fn($j) => !$j->moetUitPouleVerwijderd($tolerantie)
                        )->count();

                        return [
                            'id' => $p->id,
                            'nummer' => $p->nummer,
                            'titel' => $p->titel,
                            'leeftijdsklasse' => $p->leeftijdsklasse,
                            'gewichtsklasse' => $p->gewichtsklasse,
                            'type' => $p->type,
                            'judokas' => $actieveJudokas,
                            // BELANGRIJK: Herbereken wedstrijden op basis van actieve judokas!
                            'wedstrijden' => $p->berekenAantalWedstrijden($actieveJudokas),
                        ];
                    })
                    // Toon poules met 2+ judokas OF kruisfinales (met geplande aantallen)
                    ->filter(fn($p) => $p['judokas'] > 1 || ($p['type'] ?? null) === 'kruisfinale')
                    ->values()
                    ->toArray(),
                ];
            }

            $overzicht[] = $blokData;
        }

        return $overzicht;
    }
}
