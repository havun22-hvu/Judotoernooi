<?php

namespace App\Services;

use App\Models\Blok;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Services\BlokVerdeling\BlokCapaciteitHelper;
use App\Services\BlokVerdeling\BlokPlaatsingsHelper;
use App\Services\BlokVerdeling\BlokScoreCalculator;
use App\Services\BlokVerdeling\BlokVerdelingConstants;
use App\Services\BlokVerdeling\CategorieHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BlokMatVerdelingService
{
    private ?VariabeleBlokVerdelingService $variabeleService = null;
    private BlokScoreCalculator $scoreCalculator;
    private BlokCapaciteitHelper $capaciteitHelper;
    private BlokPlaatsingsHelper $plaatsingsHelper;
    private CategorieHelper $categorieHelper;

    public function __construct(
        BlokScoreCalculator $scoreCalculator,
        BlokCapaciteitHelper $capaciteitHelper,
        BlokPlaatsingsHelper $plaatsingsHelper,
        CategorieHelper $categorieHelper
    ) {
        $this->scoreCalculator = $scoreCalculator;
        $this->capaciteitHelper = $capaciteitHelper;
        $this->plaatsingsHelper = $plaatsingsHelper;
        $this->categorieHelper = $categorieHelper;
    }

    /**
     * Generate distribution variants until we have 5 acceptable ones
     */
    public function genereerVarianten(Toernooi $toernooi, int $userVerdelingGewicht = 50, int $userAansluitingGewicht = 50): array
    {
        if ($this->isGemengdToernooi($toernooi)) {
            return $this->genereerGemengdeVerdeling($toernooi, $userVerdelingGewicht, $userAansluitingGewicht);
        }

        if ($this->heeftVariabeleCategorieen($toernooi)) {
            return $this->getVariabeleService()->genereerVarianten($toernooi, $userVerdelingGewicht);
        }

        return $this->genereerVasteVerdeling($toernooi, $userVerdelingGewicht, $userAansluitingGewicht);
    }

    /**
     * Generate distribution for ONLY fixed categories
     */
    private function genereerVasteVerdeling(Toernooi $toernooi, int $userVerdelingGewicht, int $userAansluitingGewicht): array
    {
        $blokken = $toernooi->blokken->sortBy('nummer')->values();

        if ($blokken->isEmpty()) {
            throw new \RuntimeException('Geen blokken gevonden');
        }

        $categories = $this->categorieHelper->getCategoriesMetToewijzing($toernooi);
        $nietVerdeeld = $categories->filter(fn($cat) => $cat['blok_id'] === null && !$cat['blok_vast']);

        if ($nietVerdeeld->isEmpty()) {
            return ['varianten' => [], 'message' => 'Alle categorieën zijn al verdeeld'];
        }

        $baseCapaciteit = $this->capaciteitHelper->berekenCapaciteit($toernooi, $blokken);
        $perLeeftijd = $this->categorieHelper->groepeerPerLeeftijd($nietVerdeeld);

        Log::info('Blokverdeling start', [
            'blokken' => $blokken->count(),
            'al_geplaatst' => $categories->count() - $nietVerdeeld->count(),
            'te_verdelen' => $nietVerdeeld->count(),
        ]);

        return $this->runVariantGeneratie(
            $toernooi, $perLeeftijd, $blokken, $baseCapaciteit, $categories,
            $userVerdelingGewicht, $userAansluitingGewicht
        );
    }

    /**
     * Run variant generation loop
     */
    private function runVariantGeneratie(
        Toernooi $toernooi,
        array $perLeeftijd,
        $blokken,
        array $baseCapaciteit,
        $alleCategorieen,
        int $userVerdelingGewicht,
        int $userAansluitingGewicht
    ): array {
        $alleVarianten = [];
        $ongeligeVarianten = [];
        $gezien = [];
        $poging = 0;
        $startTime = microtime(true);

        while (true) {
            $elapsed = microtime(true) - $startTime;
            if ($elapsed >= BlokVerdelingConstants::MAX_TIJD_SECONDEN || $poging >= BlokVerdelingConstants::MAX_POGINGEN) {
                break;
            }

            $variant = $this->simuleerVerdeling(
                $toernooi, $perLeeftijd, $blokken, $baseCapaciteit, $alleCategorieen,
                $poging, $userVerdelingGewicht, $userAansluitingGewicht
            );

            $variant['id'] = $poging + 1;
            $variant['poging'] = $poging;

            $hash = $this->hashToewijzingen($variant['toewijzingen']);

            if (!isset($gezien[$hash])) {
                $gezien[$hash] = true;
                if ($variant['scores']['is_valid']) {
                    $alleVarianten[] = $variant;
                } else {
                    $ongeligeVarianten[] = $variant;
                }
            }

            $poging++;
        }

        return $this->selecteerBesteVarianten($alleVarianten, $ongeligeVarianten, $gezien, $poging, $startTime);
    }

    /**
     * Select best 5 unique variants
     */
    private function selecteerBesteVarianten(array $alleVarianten, array $ongeligeVarianten, array $gezien, int $poging, float $startTime): array
    {
        $elapsed = round(microtime(true) - $startTime, 2);

        if (empty($alleVarianten) && !empty($ongeligeVarianten)) {
            $alleVarianten = $ongeligeVarianten;
            Log::warning("Geen geldige varianten, gebruik beste ongeldige");
        }

        usort($alleVarianten, fn($a, $b) => $a['totaal_score'] <=> $b['totaal_score']);

        $beste = [];
        $gezienHashes = [];
        foreach ($alleVarianten as $variant) {
            $hash = $this->hashToewijzingen($variant['toewijzingen']);
            if (!isset($gezienHashes[$hash])) {
                $gezienHashes[$hash] = true;
                $beste[] = $variant;
                if (count($beste) >= BlokVerdelingConstants::AANTAL_VARIANTEN) {
                    break;
                }
            }
        }

        Log::info("Blokverdeling klaar", [
            'pogingen' => $poging,
            'tijd_sec' => $elapsed,
            'geldige_varianten' => count($alleVarianten),
        ]);

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
                'error' => 'Geen geldige verdeling mogelijk binnen 25% limiet.',
            ];
        }

        return ['varianten' => $beste, 'stats' => $stats];
    }

    /**
     * Simulate a distribution without saving to database
     */
    private function simuleerVerdeling(
        Toernooi $toernooi,
        array $perLeeftijd,
        $blokken,
        array $baseCapaciteit,
        $alleCategorieen,
        int $seed,
        int $userVerdelingGewicht,
        int $userAansluitingGewicht
    ): array {
        $capaciteit = $baseCapaciteit;
        $toewijzingen = [];
        $numBlokken = $blokken->count();
        $blokkenArray = $blokken->values()->all();

        mt_srand($seed * 12345 + 67890);

        $params = $this->berekenVariatieParameters($seed, $userVerdelingGewicht);

        $groteLeeftijden = $this->categorieHelper->getGroteLeeftijden($toernooi);
        $kleineLeeftijden = $this->categorieHelper->getKleineLeeftijden($toernooi);

        if ($params['leeftijdShuffle'] >= 4 && count($groteLeeftijden) > 1) {
            $rest = array_slice($groteLeeftijden, 1);
            shuffle($rest);
            $groteLeeftijden = array_merge([$groteLeeftijden[0]], $rest);
        }

        $vastgezet = $this->categorieHelper->getVastgezetteBloknummersPerLeeftijd($alleCategorieen, $blokkenArray);

        // STAP 1: Place grote leeftijdsklassen
        $huidigeBlokIndex = 0;
        $toewijzingen = $this->plaatsLeeftijden(
            $groteLeeftijden, $perLeeftijd, $vastgezet, $blokkenArray, $numBlokken,
            $capaciteit, $toewijzingen, $huidigeBlokIndex, $params
        );

        // STAP 2: Place kleine leeftijdsklassen as filler
        if ($params['leeftijdShuffle'] >= 2 && $params['leeftijdShuffle'] < 6) {
            shuffle($kleineLeeftijden);
        }

        foreach ($kleineLeeftijden as $leeftijd) {
            if (!isset($perLeeftijd[$leeftijd])) continue;

            $gewichten = $this->sorteerGewichten($perLeeftijd[$leeftijd], $params['sorteerStrategie']);
            $startBlok = $params['randomFactor'] > 0.5
                ? $this->capaciteitHelper->vindBlokMetMeesteRuimte($capaciteit, $blokkenArray)
                : $this->capaciteitHelper->vindRandomBlokMetRuimte($capaciteit, $blokkenArray);

            $toewijzingen = $this->plaatsGewichtenVanafBlok(
                $gewichten, $startBlok, $blokkenArray, $numBlokken, $capaciteit, $toewijzingen, $params
            );
        }

        // STAP 3: Place remaining age classes
        foreach ($perLeeftijd as $leeftijd => $gewichten) {
            if (in_array($leeftijd, $groteLeeftijden) || in_array($leeftijd, $kleineLeeftijden)) {
                continue;
            }

            $gewichten = $this->sorteerGewichten($gewichten, $params['sorteerStrategie']);
            $startBlok = $this->capaciteitHelper->vindRandomBlokMetRuimte($capaciteit, $blokkenArray);

            $toewijzingen = $this->plaatsGewichtenVanafBlok(
                $gewichten, $startBlok, $blokkenArray, $numBlokken, $capaciteit, $toewijzingen, $params
            );
        }

        $scores = $this->scoreCalculator->berekenScores(
            $toewijzingen, $capaciteit, $blokkenArray, $perLeeftijd,
            $params['verdelingGewicht'], 1.0 - $params['verdelingGewicht']
        );

        return [
            'toewijzingen' => $toewijzingen,
            'capaciteit' => $capaciteit,
            'scores' => $scores,
            'totaal_score' => $scores['totaal_score'],
        ];
    }

    /**
     * Calculate variation parameters for a seed
     */
    private function berekenVariatieParameters(int $seed, int $userVerdelingGewicht): array
    {
        $gewichtVariatie = (($seed % 20) - 10) / 100.0;

        return [
            'verdelingGewicht' => max(0.1, min(0.9, ($userVerdelingGewicht / 100.0) + $gewichtVariatie)),
            'aansluitingVariant' => $seed % BlokVerdelingConstants::AANTAL_AANSLUITING_STRATEGIEEN,
            'randomFactor' => ($seed % 100) / 100.0,
            'sorteerStrategie' => $seed % BlokVerdelingConstants::AANTAL_SORTEER_STRATEGIEEN,
            'leeftijdShuffle' => $seed % BlokVerdelingConstants::AANTAL_SHUFFLE_OPTIES,
        ];
    }

    /**
     * Place age classes in blocks
     */
    private function plaatsLeeftijden(
        array $leeftijden,
        array $perLeeftijd,
        array $vastgezet,
        array $blokken,
        int $numBlokken,
        array &$capaciteit,
        array $toewijzingen,
        int &$huidigeBlokIndex,
        array $params
    ): array {
        foreach ($leeftijden as $leeftijd) {
            if (!isset($perLeeftijd[$leeftijd])) continue;

            if (isset($vastgezet[$leeftijd])) {
                $minVastBlok = min($vastgezet[$leeftijd]);
                foreach ($blokken as $idx => $blok) {
                    if ($blok->nummer == $minVastBlok) {
                        $huidigeBlokIndex = max(0, $idx - 1);
                        break;
                    }
                }
            }

            $gewichten = $this->sorteerGewichten($perLeeftijd[$leeftijd], $params['sorteerStrategie']);

            $toewijzingen = $this->plaatsGewichtenVanafBlok(
                $gewichten, $huidigeBlokIndex, $blokken, $numBlokken, $capaciteit, $toewijzingen, $params
            );

            $huidigeBlokIndex = $this->vindLaatsteBlokIndex($toewijzingen, $gewichten, $blokken);
        }

        return $toewijzingen;
    }

    /**
     * Place weight classes starting from a block
     */
    private function plaatsGewichtenVanafBlok(
        array $gewichten,
        int $startBlok,
        array $blokken,
        int $numBlokken,
        array &$capaciteit,
        array $toewijzingen,
        array $params
    ): array {
        $vorigeBlokIndex = $startBlok;

        foreach ($gewichten as $cat) {
            $key = $cat['leeftijd'] . '|' . $cat['gewicht'];

            $besteBlokIndex = $this->plaatsingsHelper->vindBesteBlokMetAansluiting(
                $vorigeBlokIndex, $cat['wedstrijden'], $capaciteit, $blokken, $numBlokken,
                $params['aansluitingVariant'], $params['verdelingGewicht'], $params['randomFactor']
            );

            $blok = $blokken[$besteBlokIndex];
            $toewijzingen[$key] = $blok->nummer;
            $this->capaciteitHelper->updateCapaciteit($capaciteit, $blok->id, $cat['wedstrijden']);

            $vorigeBlokIndex = $besteBlokIndex;
        }

        return $toewijzingen;
    }

    /**
     * Sort weight classes with optional swaps for variation
     */
    private function sorteerGewichten(array $gewichten, int $sorteerStrategie): array
    {
        usort($gewichten, fn($a, $b) => $a['gewicht_num'] <=> $b['gewicht_num']);

        if ($sorteerStrategie >= 3 && count($gewichten) > 2) {
            $aantalSwaps = ($sorteerStrategie >= 7) ? 2 : 1;
            for ($i = 0; $i < $aantalSwaps; $i++) {
                $swapPos = mt_rand(0, count($gewichten) - 2);
                [$gewichten[$swapPos], $gewichten[$swapPos + 1]] = [$gewichten[$swapPos + 1], $gewichten[$swapPos]];
            }
        }

        return $gewichten;
    }

    /**
     * Find last block index used for given weight classes
     */
    private function vindLaatsteBlokIndex(array $toewijzingen, array $gewichten, array $blokken): int
    {
        $laatsteKey = end($gewichten)['leeftijd'] . '|' . end($gewichten)['gewicht'];
        $laatsteBlokNr = $toewijzingen[$laatsteKey] ?? null;

        if ($laatsteBlokNr) {
            foreach ($blokken as $idx => $blok) {
                if ($blok->nummer == $laatsteBlokNr) {
                    return $idx;
                }
            }
        }

        return 0;
    }

    /**
     * Generate hash for toewijzingen
     */
    private function hashToewijzingen(array $toewijzingen): string
    {
        ksort($toewijzingen);
        return md5(json_encode($toewijzingen));
    }

    /**
     * Generate distribution for mixed tournaments (both fixed AND variable)
     */
    private function genereerGemengdeVerdeling(Toernooi $toernooi, int $userVerdelingGewicht, int $userAansluitingGewicht): array
    {
        $blokken = $toernooi->blokken->sortBy('nummer')->values();

        if ($blokken->isEmpty()) {
            throw new \RuntimeException('Geen blokken gevonden');
        }

        $blokkenArray = $blokken->values()->all();
        [$vasteCategorieen, $variabelePoules] = $this->categorieHelper->splitsCategorieenOpType($toernooi, $this->getVariabeleService());

        $totaalWedstrijden = $vasteCategorieen->sum('wedstrijden') + $variabelePoules->sum('aantal_wedstrijden');
        $doelPerBlok = (int) ceil($totaalWedstrijden / count($blokkenArray));

        Log::info('Gemengde blokverdeling start', [
            'blokken' => count($blokkenArray),
            'vaste_categorieen' => $vasteCategorieen->count(),
            'variabele_poules' => $variabelePoules->count(),
        ]);

        $startTime = microtime(true);
        $alleVarianten = [];
        $gezien = [];

        for ($poging = 0; $poging < 1000; $poging++) {
            if ((microtime(true) - $startTime) >= BlokVerdelingConstants::MAX_TIJD_SECONDEN) break;

            $variant = $this->simuleerGemengdeVerdeling(
                $toernooi, $vasteCategorieen, $variabelePoules, $blokkenArray,
                $doelPerBlok, $poging, $userVerdelingGewicht, $userAansluitingGewicht
            );

            $hash = $this->hashToewijzingen($variant['toewijzingen']);
            if (!isset($gezien[$hash])) {
                $gezien[$hash] = true;
                $alleVarianten[] = $variant;
            }
        }

        usort($alleVarianten, fn($a, $b) => $a['totaal_score'] <=> $b['totaal_score']);
        $beste = array_slice($alleVarianten, 0, BlokVerdelingConstants::AANTAL_VARIANTEN);

        $elapsed = round(microtime(true) - $startTime, 2);

        return [
            'varianten' => $beste,
            'stats' => [
                'pogingen' => $poging,
                'tijd_sec' => $elapsed,
                'vaste_categorieen' => $vasteCategorieen->count(),
                'variabele_poules' => $variabelePoules->count(),
            ],
        ];
    }

    /**
     * Simulate a mixed distribution
     */
    private function simuleerGemengdeVerdeling(
        Toernooi $toernooi,
        $vasteCategorieen,
        $variabelePoules,
        array $blokken,
        int $doelPerBlok,
        int $seed,
        int $userVerdelingGewicht,
        int $userAansluitingGewicht
    ): array {
        $numBlokken = count($blokken);
        $toewijzingen = [];

        $capaciteit = $this->capaciteitHelper->initializeSimulatieCapaciteit($toernooi, $blokken, $doelPerBlok);
        mt_srand($seed * 12345 + 67890);

        $params = $this->berekenVariatieParameters($seed, $userVerdelingGewicht);
        $perLeeftijd = $this->categorieHelper->groepeerPerLeeftijd($vasteCategorieen);

        $groteLeeftijden = $this->categorieHelper->getGroteLeeftijden($toernooi);
        $kleineLeeftijden = $this->categorieHelper->getKleineLeeftijden($toernooi);

        if ($seed % 8 >= 4 && count($groteLeeftijden) > 1) {
            $rest = array_slice($groteLeeftijden, 1);
            shuffle($rest);
            $groteLeeftijden = array_merge([$groteLeeftijden[0]], $rest);
        }

        // PHASE 1: Place fixed categories
        $huidigeBlokIndex = 0;

        foreach ($groteLeeftijden as $leeftijd) {
            if (!isset($perLeeftijd[$leeftijd])) continue;
            $gewichten = $this->sorteerGewichten($perLeeftijd[$leeftijd], $params['sorteerStrategie']);
            $toewijzingen = $this->plaatsGewichtenVanafBlok(
                $gewichten, $huidigeBlokIndex, $blokken, $numBlokken, $capaciteit, $toewijzingen, $params
            );
            $huidigeBlokIndex = $this->vindLaatsteBlokIndex($toewijzingen, $gewichten, $blokken);
        }

        foreach ($kleineLeeftijden as $leeftijd) {
            if (!isset($perLeeftijd[$leeftijd])) continue;
            $gewichten = $this->sorteerGewichten($perLeeftijd[$leeftijd], $params['sorteerStrategie']);
            $startBlok = $this->capaciteitHelper->vindBlokMetMeesteRuimte($capaciteit, $blokken);
            $toewijzingen = $this->plaatsGewichtenVanafBlok(
                $gewichten, $startBlok, $blokken, $numBlokken, $capaciteit, $toewijzingen, $params
            );
        }

        // PHASE 2: Fill with variable pools
        $gesorteerdeVariabele = $variabelePoules->sortBy([
            ['sort_leeftijd', 'asc'],
            ['sort_gewicht', 'asc'],
        ])->values();

        foreach ($gesorteerdeVariabele as $poule) {
            $besteBlokIndex = $this->plaatsingsHelper->vindBesteBlokVoorVariabelePoule(
                $poule['aantal_wedstrijden'], $capaciteit, $blokken, $numBlokken
            );

            $blok = $blokken[$besteBlokIndex];
            $toewijzingen[$poule['key']] = $blok->nummer;
            $this->capaciteitHelper->updateCapaciteit($capaciteit, $blok->id, $poule['aantal_wedstrijden']);
        }

        $scores = $this->scoreCalculator->berekenScores(
            $toewijzingen, $capaciteit, $blokken, $perLeeftijd,
            $params['verdelingGewicht'], 1.0 - $params['verdelingGewicht']
        );

        return [
            'toewijzingen' => $toewijzingen,
            'capaciteit' => $capaciteit,
            'scores' => $scores,
            'totaal_score' => $scores['totaal_score'],
        ];
    }

    /**
     * Apply toewijzingen to the database
     */
    public function pasVariantToe(Toernooi $toernooi, array $toewijzingen): void
    {
        DB::transaction(function () use ($toernooi, $toewijzingen) {
            foreach ($toewijzingen as $key => $blokNummer) {
                [$leeftijd, $gewicht] = explode('|', $key);

                $blok = $toernooi->blokken()->where('nummer', $blokNummer)->first();

                if ($blok) {
                    Poule::where('toernooi_id', $toernooi->id)
                        ->where('leeftijdsklasse', $leeftijd)
                        ->where('gewichtsklasse', $gewicht)
                        ->where('blok_vast', false)
                        ->update(['blok_id' => $blok->id]);
                }
            }

            $this->fixKruisfinaleBlokken($toernooi);
            $toernooi->update(['blokken_verdeeld_op' => now()]);
        });
    }

    /**
     * Fix kruisfinales without blok_id
     */
    private function fixKruisfinaleBlokken(Toernooi $toernooi): void
    {
        $kruisfinales = Poule::where('toernooi_id', $toernooi->id)
            ->where('type', 'kruisfinale')
            ->whereNull('blok_id')
            ->get();

        foreach ($kruisfinales as $kruisfinale) {
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
    }

    /**
     * Generate block distribution (legacy single variant)
     */
    public function genereerVerdeling(Toernooi $toernooi): array
    {
        $result = $this->genereerVarianten($toernooi);

        if (!empty($result['varianten'])) {
            $this->pasVariantToe($toernooi, $result['varianten'][0]['toewijzingen']);
        }

        return $this->getVerdelingsStatistieken($toernooi);
    }

    /**
     * Distribute poules over mats within each block
     *
     * Strategy: Sequential distribution to keep similar categories together
     * 1. Sort poules by age (young→old), then weight (light→heavy)
     * 2. Distribute sequentially over mats until each mat reaches its target
     * Result: Mini's on mat 1-2, Jeugd on mat 3-4, Dames/Heren on mat 5-6
     */
    public function verdeelOverMatten(Toernooi $toernooi): void
    {
        $matten = $toernooi->matten->sortBy('nummer');
        $matIds = $matten->pluck('id')->toArray();
        $aantalMatten = count($matIds);

        if ($aantalMatten === 0) {
            return;
        }

        foreach ($toernooi->blokken as $blok) {
            // Get all poules sorted by leeftijd (jong→oud), then gewicht (licht→zwaar)
            // Use categorie_key (u7, u9, u11) for age, gewichtsklasse for weight
            // Combined sort key: leeftijd * 1000 + gewicht (e.g., 7*1000+20 = 7020)
            $poules = $blok->poules()->with('judokas')->get()->sortBy(function ($poule) {
                $leeftijd = $this->extractLeeftijdUitCategorieKey($poule->categorie_key);
                $gewicht = $this->extractGewichtVoorSortering($poule->gewichtsklasse);
                return $leeftijd * 1000 + $gewicht;
            })->values();

            if ($poules->isEmpty()) {
                continue;
            }

            // Dynamic target per mat based on what must remain for other mats
            // Example: 196 total, 6 mats, avg=33
            // Mat 1: must leave 5×33=165 → target = 196-165 = 31
            // Mat 2 (if mat1 got 30): remaining=166, must leave 4×33=132 → target = 166-132 = 34
            $totaalWedstrijden = $poules->sum('aantal_wedstrijden');
            $gemiddelde = $totaalWedstrijden / $aantalMatten;

            $wedstrijdenPerMat = array_fill_keys($matIds, 0);
            $huidigeMatIndex = 0;
            $resterend = $totaalWedstrijden;

            foreach ($poules as $poule) {
                $huidigeMat = $matIds[$huidigeMatIndex];
                $resterendeMatten = $aantalMatten - $huidigeMatIndex - 1; // mats after current

                // Calculate target for this mat: remaining - what other mats need
                $moetOverblijven = $resterendeMatten * $gemiddelde;
                $doelVoorDezeMat = $resterend - $moetOverblijven;

                // Assign poule to current mat
                $poule->update(['mat_id' => $huidigeMat]);
                $wedstrijdenPerMat[$huidigeMat] += $poule->aantal_wedstrijden;

                // Move to next mat when current mat reached its dynamic target
                if ($wedstrijdenPerMat[$huidigeMat] >= $doelVoorDezeMat && $huidigeMatIndex < $aantalMatten - 1) {
                    $resterend -= $wedstrijdenPerMat[$huidigeMat];
                    $huidigeMatIndex++;
                }
            }
        }

        $this->fixKruisfinaleMatten($toernooi);
    }

    /**
     * Extract age from categorie_key (u7, u9, u11, u13, etc.)
     * This is the most reliable source for age sorting
     */
    private function extractLeeftijdUitCategorieKey(?string $categorieKey): int
    {
        if (empty($categorieKey)) {
            return 999;
        }

        // Extract number from patterns like "u7", "u9_geel_plus", "u11", "u13_d"
        if (preg_match('/u(\d+)/', strtolower($categorieKey), $matches)) {
            return (int) $matches[1];
        }

        // Fallback for non-standard keys
        return 999;
    }

    /**
     * Determine heavy weight threshold (top 30% of weights)
     */
    private function bepaalZwaarGewichtGrens($poules): float
    {
        $gewichten = $poules->map(fn($p) => $this->extractGewichtVoorSortering($p->gewichtsklasse))
            ->filter(fn($g) => $g > 0)
            ->sort()
            ->values();

        if ($gewichten->isEmpty()) {
            return 9999;
        }

        // Top 30% threshold
        $index = (int) floor($gewichten->count() * 0.7);
        return $gewichten->get($index, $gewichten->last());
    }

    /**
     * Check if poule is for ladies based on judokas or leeftijdsklasse
     */
    private function isPouleVoorDames(Poule $poule): bool
    {
        // Check leeftijdsklasse for "dames" or "meisjes"
        $lower = strtolower($poule->leeftijdsklasse ?? '');
        if (str_contains($lower, 'dame') || str_contains($lower, 'meisje') || str_contains($lower, 'vrouw')) {
            return true;
        }

        // Check judokas gender
        $judokas = $poule->judokas;
        if ($judokas->isEmpty()) {
            return false;
        }

        $vrouwen = $judokas->filter(fn($j) => in_array(strtolower($j->geslacht ?? ''), ['v', 'vrouw', 'female', 'f']));
        return $vrouwen->count() > ($judokas->count() / 2);
    }

    /**
     * Extract numeric weight value for sorting
     * Handles formats: "-24", "-24kg", "24-27kg", "24-27", etc.
     */
    private function extractGewichtVoorSortering(?string $gewichtsklasse): float
    {
        if (empty($gewichtsklasse)) {
            return 0;
        }

        // Remove "kg" suffix
        $cleaned = str_replace('kg', '', $gewichtsklasse);

        // Handle range format "24-27" - take the first (min) value
        if (str_contains($cleaned, '-') && !str_starts_with($cleaned, '-')) {
            $parts = explode('-', $cleaned);
            return (float) trim($parts[0]);
        }

        // Handle "-24" format (single weight class)
        if (str_starts_with($cleaned, '-')) {
            return (float) substr($cleaned, 1);
        }

        // Handle "+90" format
        if (str_starts_with($cleaned, '+')) {
            return (float) substr($cleaned, 1) + 1000; // Put + classes at the end
        }

        // Fallback: try to extract any number
        if (preg_match('/(\d+(?:\.\d+)?)/', $cleaned, $matches)) {
            return (float) $matches[1];
        }

        return 0;
    }

    /**
     * Fix kruisfinales without mat_id
     */
    private function fixKruisfinaleMatten(Toernooi $toernooi): void
    {
        $kruisfinales = Poule::where('toernooi_id', $toernooi->id)
            ->where('type', 'kruisfinale')
            ->whereNull('mat_id')
            ->get();

        foreach ($kruisfinales as $kruisfinale) {
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
     * Find mat with least matches
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
     * Check if toernooi has fixed categories
     */
    private function heeftVasteCategorieen(Toernooi $toernooi): bool
    {
        $config = $toernooi->getAlleGewichtsklassen();
        if (empty($config)) return false;

        foreach ($config as $categorie) {
            if ((float) ($categorie['max_kg_verschil'] ?? 0) == 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if toernooi has BOTH fixed AND variable categories
     */
    public function isGemengdToernooi(Toernooi $toernooi): bool
    {
        return $this->heeftVasteCategorieen($toernooi) && $this->heeftVariabeleCategorieen($toernooi);
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
     */
    public function getZaalOverzicht(Toernooi $toernooi): array
    {
        $overzicht = [];
        $tolerantie = $toernooi->gewicht_tolerantie ?? 0;

        foreach ($toernooi->blokken()->with('poules.mat', 'poules.judokas')->get() as $blok) {
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
                    'poules' => $poules->map(function($p) use ($tolerantie) {
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
                            'wedstrijden' => $p->berekenAantalWedstrijden($actieveJudokas),
                        ];
                    })
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
