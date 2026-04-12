<?php

namespace App\Services;

use App\Helpers\BandHelper;
use App\Models\Judoka;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Services\PouleIndeling\PouleCalculator;
use App\Services\PouleIndeling\PouleTitleBuilder;
use App\Services\PouleIndeling\UnassignedJudokaFinder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PouleIndelingService
{
    private int $minJudokas;
    private int $maxJudokas;
    private array $voorkeur;
    private array $prioriteiten;
    private ?Toernooi $toernooi = null;
    private array $gewichtsklassenConfig = [];
    private DynamischeIndelingService $dynamischeIndelingService;
    private ?CategorieClassifier $classifier = null;
    private PouleTitleBuilder $titleBuilder;
    private UnassignedJudokaFinder $unassignedFinder;
    private PouleCalculator $calculator;

    /**
     * Initialize with tournament-specific settings
     */
    public function initializeFromToernooi(Toernooi $toernooi): void
    {
        $this->toernooi = $toernooi;
        $this->voorkeur = $toernooi->getPouleGrootteVoorkeurOfDefault();
        // Min/max are derived from preference list
        $this->minJudokas = $toernooi->min_judokas_poule;
        $this->maxJudokas = $toernooi->max_judokas_poule;
        $this->prioriteiten = $toernooi->verdeling_prioriteiten ?? ['band', 'gewicht', 'leeftijd'];
        $this->gewichtsklassenConfig = $toernooi->getAlleGewichtsklassen();
        $this->classifier = new CategorieClassifier(
            $this->gewichtsklassenConfig,
            $toernooi->gewicht_tolerantie ?? 0.5
        );
    }

    public function __construct(
        DynamischeIndelingService $dynamischeIndelingService,
        ?PouleTitleBuilder $titleBuilder = null,
        ?UnassignedJudokaFinder $unassignedFinder = null,
        ?PouleCalculator $calculator = null
    ) {
        // Default values, will be overridden by initializeFromToernooi
        $this->voorkeur = [5, 4, 6, 3];
        $this->minJudokas = 3;
        $this->maxJudokas = 6;
        $this->prioriteiten = ['leeftijd', 'gewicht', 'band'];
        $this->dynamischeIndelingService = $dynamischeIndelingService;
        $this->titleBuilder = $titleBuilder ?? new PouleTitleBuilder();
        $this->unassignedFinder = $unassignedFinder ?? new UnassignedJudokaFinder();
        $this->calculator = $calculator ?? new PouleCalculator();
    }

    /**
     * Recalculate category and sort fields for all judokas
     * Uses preset config for classification (not hardcoded enum)
     * Important after year change when judokas move to different age categories
     */
    public function herberkenKlassen(Toernooi $toernooi): int
    {
        $bijgewerkt = 0;

        // Ensure config and classifier are loaded
        $this->gewichtsklassenConfig = $toernooi->getAlleGewichtsklassen();
        $this->classifier = new CategorieClassifier(
            $this->gewichtsklassenConfig,
            $toernooi->gewicht_tolerantie ?? 0.5
        );

        foreach ($toernooi->judokas as $judoka) {
            // Store old values for comparison
            $oudeLeeftijdsklasse = $judoka->leeftijdsklasse;
            $oudeGewichtsklasse = $judoka->gewichtsklasse;
            $oudeSortCategorie = $judoka->sort_categorie;

            // Classify using CategorieClassifier
            $classificatie = $this->classifier->classificeer($judoka, $toernooi->datum?->year);

            // Calculate sort_gewicht (weight in grams for precision)
            $gewicht = $judoka->gewicht_gewogen ?? $judoka->gewicht ?? 0;
            $sortGewicht = (int) round($gewicht * 1000);

            // Check if anything changed
            $nieuweLeeftijdsklasse = $classificatie['label'];
            $nieuweGewichtsklasse = $classificatie['gewichtsklasse'] ?? $judoka->gewichtsklasse;
            $nieuweSortCategorie = $classificatie['sortCategorie'];

            if ($oudeLeeftijdsklasse !== $nieuweLeeftijdsklasse ||
                $oudeGewichtsklasse !== $nieuweGewichtsklasse ||
                $oudeSortCategorie !== $nieuweSortCategorie) {

                $judoka->update([
                    'leeftijdsklasse' => $nieuweLeeftijdsklasse,
                    'categorie_key' => $classificatie['key'],
                    'sort_categorie' => $nieuweSortCategorie,
                    'sort_gewicht' => $sortGewicht,
                    'sort_band' => BandHelper::getSortNiveau($judoka->band ?? ''),
                    'gewichtsklasse' => $nieuweGewichtsklasse,
                ]);
                $bijgewerkt++;
            }
        }

        return $bijgewerkt;
    }

    /**
     * Generate pool division for a tournament
     */
    public function genereerPouleIndeling(Toernooi $toernooi): array
    {
        // Initialize settings from tournament
        $this->initializeFromToernooi($toernooi);

        // Recalculate age/weight classes for all judokas (important after year change)
        $this->herberkenKlassen($toernooi);

        return DB::transaction(function () use ($toernooi) {
            // Delete existing pools and their matches
            $pouleIds = $toernooi->poules()->pluck('id');
            \App\Models\Wedstrijd::whereIn('poule_id', $pouleIds)->delete();
            $toernooi->poules()->delete();

            // Reset SQLite auto-increment sequences for clean IDs
            if (DB::getDriverName() === 'sqlite') {
                // Only reset if tables are empty for this tournament
                if ($toernooi->poules()->count() === 0) {
                    $minPouleId = Poule::min('id') ?? 0;
                    $minWedstrijdId = \App\Models\Wedstrijd::min('id') ?? 0;
                    if ($minPouleId > 0) {
                        DB::statement("UPDATE sqlite_sequence SET seq = ? WHERE name = 'poules'", [$minPouleId - 1]);
                    }
                    if ($minWedstrijdId > 0) {
                        DB::statement("UPDATE sqlite_sequence SET seq = ? WHERE name = 'wedstrijden'", [$minWedstrijdId - 1]);
                    }
                }
            }

            // Get all judokas grouped by category
            $groepen = $this->groepeerJudokas($toernooi);

            // Get wedstrijd_systeem settings
            $wedstrijdSysteem = $toernooi->wedstrijd_systeem ?? [];

            $pouleNummer = 1;
            $statistieken = [
                'totaal_poules' => 0,
                'totaal_wedstrijden' => 0,
                'totaal_kruisfinales' => 0,
                'per_leeftijdsklasse' => [],
                'waarschuwingen' => [],
            ];

            // Track voorrondepoules per categorie (leeftijdsklasse + gewichtsklasse) for kruisfinale creation
            $voorrondesPerCategorie = [];

            // Get eliminatie gewichtsklassen settings
            $eliminatieGewichtsklassen = $toernooi->eliminatie_gewichtsklassen ?? [];

            foreach ($groepen as $sleutel => $judokas) {
                if ($judokas->isEmpty()) continue;

                // Parse group key: "Leeftijdsklasse|Gewichtsklasse" or "Leeftijdsklasse|Gewichtsklasse|Geslacht"
                $delen = explode('|', $sleutel);
                $leeftijdsklasse = $delen[0];
                $gewichtsklasse = $delen[1] ?? 'Onbekend';
                $geslacht = $delen[2] ?? null;

                // Get categorie_key from first judoka (the reliable link to config)
                $categorieKey = $judokas->first()->categorie_key;
                $gewichtsklassenConfig = $toernooi->getAlleGewichtsklassen();
                $categorieConfig = $gewichtsklassenConfig[$categorieKey] ?? [];

                // Check if this is an elimination category
                $systeem = $wedstrijdSysteem[$categorieKey] ?? 'poules';
                $isVasteCategorie = (($categorieConfig['max_kg_verschil'] ?? 0) == 0)
                                 && (($categorieConfig['max_leeftijd_verschil'] ?? 0) == 0);
                $isEliminatie = $systeem === 'eliminatie' && $isVasteCategorie;

                // For elimination: create one group with all judokas (no pool splitting)
                if ($isEliminatie) {
                    $aantalDeelnemers = $judokas->count();

                    // Warn if less than 8 participants for elimination
                    if ($aantalDeelnemers < 7) {
                        $statistieken['waarschuwingen'][] = [
                            'type' => 'error',
                            'categorie' => "{$leeftijdsklasse} {$gewichtsklasse}",
                            'bericht' => "Te weinig deelnemers voor eliminatie: {$leeftijdsklasse} {$gewichtsklasse} ({$aantalDeelnemers}). Minimaal 7 nodig.",
                            'aantal' => $aantalDeelnemers,
                        ];
                    } elseif ($aantalDeelnemers === 7) {
                        $statistieken['waarschuwingen'][] = [
                            'type' => 'warning',
                            'categorie' => "{$leeftijdsklasse} {$gewichtsklasse}",
                            'bericht' => "Weinig deelnemers voor eliminatie: {$leeftijdsklasse} {$gewichtsklasse} (7). Ideaal is 8+.",
                            'aantal' => $aantalDeelnemers,
                        ];
                    }

                    // Build dynamic title with actual ranges and config label
                    $lkLabel = $categorieConfig['label'] ?? $leeftijdsklasse;

                    $leeftijden = $judokas->pluck('leeftijd')->filter()->toArray();
                    $gewichten = $judokas->pluck('gewicht')->filter()->toArray();
                    $leeftijdRange = !empty($leeftijden)
                        ? (min($leeftijden) == max($leeftijden) ? min($leeftijden) . 'j' : min($leeftijden) . '-' . max($leeftijden) . 'j')
                        : '';
                    $gewichtRange = !empty($gewichten)
                        ? (min($gewichten) == max($gewichten) ? min($gewichten) . 'kg' : min($gewichten) . '-' . max($gewichten) . 'kg')
                        : $gewichtsklasse;
                    $geslachtLabel = match ($geslacht) { 'M' => 'M', 'V' => 'V', default => null };
                    $titelParts = [$lkLabel];
                    if ($geslachtLabel) $titelParts[] = $geslachtLabel;
                    if ($leeftijdRange) $titelParts[] = $leeftijdRange;
                    if ($gewichtRange) $titelParts[] = $gewichtRange;
                    $titel = implode(' ', $titelParts) . ' - Eliminatie';

                    $poule = Poule::create([
                        'toernooi_id' => $toernooi->id,
                        'nummer' => $pouleNummer,
                        'titel' => $titel,
                        'type' => 'eliminatie',
                        'leeftijdsklasse' => $leeftijdsklasse,
                        'gewichtsklasse' => $gewichtsklasse,
                        'categorie_key' => $categorieKey,
                        'aantal_judokas' => $aantalDeelnemers,
                    ]);

                    // Attach all judokas to elimination group
                    $positie = 1;
                    foreach ($judokas as $judoka) {
                        $poule->judokas()->attach($judoka->id, ['positie' => $positie++]);
                    }

                    $poule->updateStatistieken();

                    $statistieken['totaal_poules']++;
                    if (!isset($statistieken['per_leeftijdsklasse'][$leeftijdsklasse])) {
                        $statistieken['per_leeftijdsklasse'][$leeftijdsklasse] = [
                            'poules' => 0,
                            'wedstrijden' => 0,
                            'kruisfinales' => 0,
                            'eliminatie' => 0,
                        ];
                    }
                    $statistieken['per_leeftijdsklasse'][$leeftijdsklasse]['eliminatie'] = ($statistieken['per_leeftijdsklasse'][$leeftijdsklasse]['eliminatie'] ?? 0) + 1;

                    $pouleNummer++;
                    continue; // Skip normal pool creation
                }

                // Get sorting mode for title generation
                $gebruikGewichtsklassen = $toernooi->gebruik_gewichtsklassen === null ? true : $toernooi->gebruik_gewichtsklassen;
                $volgorde = $toernooi->judoka_code_volgorde ?? 'gewicht_band';

                // Check if this category uses dynamic grouping (max_kg_verschil > 0)
                $maxKg = (float) ($categorieConfig['max_kg_verschil'] ?? 0);
                $maxLeeftijd = (int) ($categorieConfig['max_leeftijd_verschil'] ?? 0);
                $maxBand = (int) ($categorieConfig['max_band_verschil'] ?? 0);
                $bandGrens = (string) ($categorieConfig['band_grens'] ?? '');
                $bandVerschilBeginners = (int) ($categorieConfig['band_verschil_beginners'] ?? 1);
                $usesDynamic = $maxKg > 0;

                if ($usesDynamic) {
                    // DYNAMIC GROUPING: Use DynamischeIndelingService to create weight groups

                    // Haal poule grootte voorkeur uit toernooi instellingen
                    $pouleGrootteVoorkeur = $toernooi->poule_grootte_voorkeur ?? [5, 4, 6, 3];

                    $indeling = $this->dynamischeIndelingService->berekenIndeling(
                        $judokas,
                        $maxLeeftijd,
                        $maxKg,
                        $maxBand,
                        $bandGrens,
                        $bandVerschilBeginners,
                        [
                            'poule_grootte_voorkeur' => $pouleGrootteVoorkeur,
                            'verdeling_prioriteiten' => $this->prioriteiten,
                            'gewicht_tolerantie' => $toernooi->gewicht_tolerantie ?? 0.5,
                        ]
                    );

                    // Check for incomplete judokas (missing weight/age)
                    $onvolledigeJudokas = $indeling['onvolledige_judokas'] ?? [];
                    if (!empty($onvolledigeJudokas)) {
                        $namen = array_map(fn($j) => $j->naam, $onvolledigeJudokas);
                        $statistieken['waarschuwingen'][] = [
                            'type' => 'warning',
                            'categorie' => $leeftijdsklasse,
                            'bericht' => count($onvolledigeJudokas) . " judoka's met ontbrekende gegevens (gewicht/leeftijd) niet ingedeeld: " . implode(', ', array_slice($namen, 0, 5)) . (count($namen) > 5 ? '...' : ''),
                            'onvolledige_judokas' => $onvolledigeJudokas,
                        ];
                    }

                    // Check if this age class uses elimination system
                    $isDynamicEliminatie = $systeem === 'eliminatie';

                    // Create pools from dynamische indeling result
                    foreach ($indeling['poules'] as $pouleData) {
                        $pouleJudokas = $pouleData['judokas'];
                        if (empty($pouleJudokas)) continue;

                        // Build dynamic title with weight range from pool
                        $gewichtRange = $pouleData['gewicht_groep'] ?? '';
                        $pouleType = $isDynamicEliminatie ? 'eliminatie' : 'voorronde';
                        $titel = $this->titleBuilder->build($leeftijdsklasse, $gewichtRange, $geslacht, $pouleJudokas, $gewichtsklassenConfig, $categorieKey);

                        $poule = Poule::create([
                            'toernooi_id' => $toernooi->id,
                            'nummer' => $pouleNummer,
                            'titel' => $titel,
                            'type' => $pouleType,
                            'leeftijdsklasse' => $leeftijdsklasse,
                            'gewichtsklasse' => $gewichtRange,
                            'categorie_key' => $categorieKey,
                            'aantal_judokas' => count($pouleJudokas),
                        ]);

                        // Attach judokas to pool
                        $positie = 1;
                        foreach ($pouleJudokas as $judoka) {
                            $poule->judokas()->attach($judoka->id, ['positie' => $positie++]);
                        }

                        // Calculate matches
                        $poule->updateStatistieken();

                        // Check gewichtsverschil binnen poule tegen categorie max
                        if ($maxKg > 0) {
                            $gewichten = array_filter(array_map(fn($j) => $j->gewicht, $pouleJudokas));
                            if (count($gewichten) >= 2) {
                                $verschil = max($gewichten) - min($gewichten);
                                if ($verschil > $maxKg) {
                                    $statistieken['waarschuwingen'][] = [
                                        'type' => 'warning',
                                        'categorie' => "#{$pouleNummer} {$leeftijdsklasse} {$gewichtRange}",
                                        'bericht' => "Gewichtsverschil te groot: " . round($verschil, 1) . "kg (max {$maxKg}kg)",
                                        'poule_nummer' => $pouleNummer,
                                    ];
                                }
                            }
                        }

                        $statistieken['totaal_poules']++;
                        $statistieken['totaal_wedstrijden'] += $poule->aantal_wedstrijden;

                        if (!isset($statistieken['per_leeftijdsklasse'][$leeftijdsklasse])) {
                            $statistieken['per_leeftijdsklasse'][$leeftijdsklasse] = [
                                'poules' => 0,
                                'wedstrijden' => 0,
                                'kruisfinales' => 0,
                            ];
                        }
                        $statistieken['per_leeftijdsklasse'][$leeftijdsklasse]['poules']++;
                        $statistieken['per_leeftijdsklasse'][$leeftijdsklasse]['wedstrijden'] += $poule->aantal_wedstrijden;

                        // Track for kruisfinale (use leeftijdsklasse + gewichtRange)
                        $kruisfinaleKey = "{$leeftijdsklasse}|{$gewichtRange}" . ($geslacht ? "|{$geslacht}" : '');
                        if (!isset($voorrondesPerCategorie[$kruisfinaleKey])) {
                            $voorrondesPerCategorie[$kruisfinaleKey] = [
                                'leeftijdsklasse' => $leeftijdsklasse,
                                'gewichtsklasse' => $gewichtRange,
                                'geslacht' => $geslacht,
                                'config_key' => $categorieKey,
                                'aantal_poules' => 0,
                            ];
                        }
                        $voorrondesPerCategorie[$kruisfinaleKey]['aantal_poules']++;

                        $pouleNummer++;
                    }

                    // Log dynamic grouping stats
                    $statistieken['dynamische_indeling'][$leeftijdsklasse] = [
                        'max_kg_verschil' => $maxKg,
                        'max_leeftijd_verschil' => $maxLeeftijd,
                        'score' => $indeling['score'],
                        'stats' => $indeling['stats'],
                    ];

                } else {
                    // STANDARD GROUPING: Split into optimal pools (existing flow)
                    // Judokas are already sorted by groepeerJudokas() based on priorities
                    // For fixed weight classes, re-sort by weight → band → leeftijd for optimal distribution
                    $judokas = $judokas->sortBy([
                        ['sort_gewicht', 'asc'],
                        ['sort_band', 'asc'],
                        ['geboortejaar', 'desc'], // jongste eerst
                    ])->values();
                    $pouleVerdelingen = $this->calculator->optimalePoules($judokas, $this->minJudokas, $this->maxJudokas, $this->voorkeur);

                    foreach ($pouleVerdelingen as $pouleJudokas) {
                        $titel = $this->titleBuilder->build($leeftijdsklasse, $gewichtsklasse, $geslacht, $pouleJudokas, $gewichtsklassenConfig, $categorieKey);

                        $poule = Poule::create([
                            'toernooi_id' => $toernooi->id,
                            'nummer' => $pouleNummer,
                            'titel' => $titel,
                            'type' => 'voorronde',
                            'leeftijdsklasse' => $leeftijdsklasse,
                            'gewichtsklasse' => $gewichtsklasse,
                            'categorie_key' => $categorieKey,
                            'aantal_judokas' => count($pouleJudokas),
                        ]);

                        // Attach judokas to pool
                        $positie = 1;
                        foreach ($pouleJudokas as $judoka) {
                            $poule->judokas()->attach($judoka->id, ['positie' => $positie++]);
                        }

                        // Calculate matches
                        $poule->updateStatistieken();

                        // Check gewichtsverschil binnen poule tegen categorie max
                        if ($maxKg > 0) {
                            $gewichten = array_filter(array_map(fn($j) => $j->gewicht, $pouleJudokas));
                            if (count($gewichten) >= 2) {
                                $verschil = max($gewichten) - min($gewichten);
                                if ($verschil > $maxKg) {
                                    $statistieken['waarschuwingen'][] = [
                                        'type' => 'warning',
                                        'categorie' => "#{$pouleNummer} {$leeftijdsklasse} {$gewichtsklasse}",
                                        'bericht' => "Gewichtsverschil te groot: " . round($verschil, 1) . "kg (max {$maxKg}kg)",
                                        'poule_nummer' => $pouleNummer,
                                    ];
                                }
                            }
                        }

                        $statistieken['totaal_poules']++;
                        $statistieken['totaal_wedstrijden'] += $poule->aantal_wedstrijden;

                        if (!isset($statistieken['per_leeftijdsklasse'][$leeftijdsklasse])) {
                            $statistieken['per_leeftijdsklasse'][$leeftijdsklasse] = [
                                'poules' => 0,
                                'wedstrijden' => 0,
                                'kruisfinales' => 0,
                            ];
                        }
                        $statistieken['per_leeftijdsklasse'][$leeftijdsklasse]['poules']++;
                        $statistieken['per_leeftijdsklasse'][$leeftijdsklasse]['wedstrijden'] += $poule->aantal_wedstrijden;

                        // Track for kruisfinale per categorie (leeftijdsklasse + gewichtsklasse + geslacht)
                        $kruisfinaleKey = $sleutel;
                        if (!isset($voorrondesPerCategorie[$kruisfinaleKey])) {
                            $voorrondesPerCategorie[$kruisfinaleKey] = [
                                'leeftijdsklasse' => $leeftijdsklasse,
                                'gewichtsklasse' => $gewichtsklasse,
                                'geslacht' => $geslacht,
                                'config_key' => $categorieKey,
                                'aantal_poules' => 0,
                            ];
                        }
                        $voorrondesPerCategorie[$kruisfinaleKey]['aantal_poules']++;

                        $pouleNummer++;
                    }
                }
            }

            // Create kruisfinale pools where applicable (per gewichtsklasse)
            foreach ($voorrondesPerCategorie as $kruisfinaleKey => $data) {
                $configKey = $data['config_key'];
                $systeem = $wedstrijdSysteem[$configKey] ?? 'poules';

                // Kruisfinale bij categorieën met vaste gewichtsklassen (gewichten gedefinieerd)
                $gewichtsklassenConfig = $toernooi->getAlleGewichtsklassen();
                $categorieConfig = $gewichtsklassenConfig[$configKey] ?? [];
                $hasFixedWeightClasses = !empty($categorieConfig['gewichten'] ?? []);

                // Only create kruisfinale if system is poules_kruisfinale AND fixed weight classes AND 2+ voorrondepoules
                if ($systeem === 'poules_kruisfinale' && $hasFixedWeightClasses && $data['aantal_poules'] >= 2) {
                    // Calculate how many places qualify based on number of poules
                    // Goal: kruisfinale of 4-6 judokas
                    $aantalPoules = $data['aantal_poules'];
                    $kruisfinalesAantal = $this->calculator->kruisfinalePlaatsen($aantalPoules);
                    $aantalJudokasKruisfinale = $aantalPoules * $kruisfinalesAantal;

                    $geslachtLabel = match ($data['geslacht']) {
                        'M' => 'M',
                        'V' => 'V',
                        default => null,
                    };

                    // Get label from config using stored config_key
                    $gewichtsklassenConfig = $toernooi->getAlleGewichtsklassen();
                    $configKey = $data['config_key'];
                    $categorieConfig = $gewichtsklassenConfig[$configKey] ?? [];
                    $lkLabel = $categorieConfig['label'] ?? $data['leeftijdsklasse'];

                    // Include qualifying places in title
                    $plaatsenTekst = $kruisfinalesAantal === 1 ? 'top 1' : "top {$kruisfinalesAantal}";
                    $titelParts = ['Kruisfinale', $lkLabel];
                    if ($geslachtLabel) $titelParts[] = $geslachtLabel;
                    $titelParts[] = $data['gewichtsklasse'];
                    $titel = implode(' ', $titelParts) . " ({$plaatsenTekst})";

                    // Get blok_id from voorrondepoules of same category
                    $voorrondeBlokId = Poule::where('toernooi_id', $toernooi->id)
                        ->where('leeftijdsklasse', $data['leeftijdsklasse'])
                        ->where('gewichtsklasse', $data['gewichtsklasse'])
                        ->where('type', 'voorronde')
                        ->whereNotNull('blok_id')
                        ->value('blok_id');

                    $kruisfinalePoule = Poule::create([
                        'toernooi_id' => $toernooi->id,
                        'nummer' => $pouleNummer,
                        'titel' => $titel,
                        'type' => 'kruisfinale',
                        'kruisfinale_plaatsen' => $kruisfinalesAantal,
                        'leeftijdsklasse' => $data['leeftijdsklasse'],
                        'gewichtsklasse' => $data['gewichtsklasse'],
                        'categorie_key' => $configKey,
                        'blok_id' => $voorrondeBlokId,
                        'aantal_judokas' => $aantalJudokasKruisfinale,
                        'aantal_wedstrijden' => $this->calculator->aantalWedstrijden($aantalJudokasKruisfinale),
                    ]);

                    $statistieken['totaal_poules']++;
                    $statistieken['totaal_kruisfinales']++;
                    $statistieken['totaal_wedstrijden'] += $kruisfinalePoule->aantal_wedstrijden;
                    $statistieken['per_leeftijdsklasse'][$data['leeftijdsklasse']]['kruisfinales']++;
                    $statistieken['per_leeftijdsklasse'][$data['leeftijdsklasse']]['wedstrijden'] += $kruisfinalePoule->aantal_wedstrijden;

                    $pouleNummer++;
                }
            }

            $toernooi->update(['poules_gegenereerd_op' => now()]);
            // STAP 4: VALIDATIE - Check of alle judoka's zijn ingedeeld
            $nietIngedeeld = $this->unassignedFinder->find($toernooi);
            if (!empty($nietIngedeeld)) {
                $statistieken['niet_ingedeeld'] = $nietIngedeeld;
                $statistieken['waarschuwingen'][] = [
                    'type' => 'error',
                    'bericht' => count($nietIngedeeld) . ' judoka(s) niet ingedeeld - controleer categorie configuratie',
                ];
            }


            return $statistieken;
        });
    }

    /**
     * Group judokas by age class, (optionally) weight class, and gender based on config
     * Sorted by sort fields (sort_categorie, sort_gewicht, sort_band)
     *
     * If gebruik_gewichtsklassen is OFF: group only by leeftijd (+ geslacht from config)
     * If gebruik_gewichtsklassen is ON: group by leeftijd + gewichtsklasse (+ geslacht from config)
     */
    private function groepeerJudokas(Toernooi $toernooi): Collection
    {
        // Default to true if null (for backwards compatibility)
        $gebruikGewichtsklassen = $toernooi->gebruik_gewichtsklassen === null ? true : $toernooi->gebruik_gewichtsklassen;

        // Sort by new sort fields, respecting prioriteiten order
        // prioriteiten can be: leeftijd, gewicht, band (in any order)
        $leeftijdIdx = array_search('leeftijd', $this->prioriteiten);
        $gewichtIdx = array_search('gewicht', $this->prioriteiten);
        $bandIdx = array_search('band', $this->prioriteiten);

        // Determine if band has higher priority than gewicht (for re-sorting within groups)
        $bandFirst = ($bandIdx !== false && $gewichtIdx !== false) ? ($bandIdx < $gewichtIdx) : false;

        // Build sort order based on priorities (lower index = higher priority)
        $sortFields = [];
        if ($leeftijdIdx !== false) $sortFields[$leeftijdIdx] = ['geboortejaar', 'DESC']; // DESC = jongste eerst (hoger geboortejaar)
        if ($gewichtIdx !== false) $sortFields[$gewichtIdx] = ['sort_gewicht', 'ASC'];
        if ($bandIdx !== false) $sortFields[$bandIdx] = ['sort_band', 'ASC'];
        ksort($sortFields);

        $query = $toernooi->judokas()->orderBy('sort_categorie');
        foreach ($sortFields as [$field, $direction]) {
            $query->orderBy($field, $direction);
        }

        $judokas = $query->get();

        $groepen = $judokas->groupBy(function (Judoka $judoka) use ($gebruikGewichtsklassen) {
            $leeftijdsklasse = $judoka->leeftijdsklasse ?: 'Onbekend';
            $categorieKey = $judoka->categorie_key ?: '';

            // Get config for gender handling and dynamic grouping
            $config = $this->gewichtsklassenConfig[$categorieKey] ?? null;
            $configGeslacht = strtolower($config['geslacht'] ?? 'gemengd');
            $includeGeslacht = $configGeslacht !== 'gemengd';

            // Check if this category uses dynamic grouping (max_kg_verschil > 0)
            // If so, don't split by gewichtsklasse - let DynamischeIndelingService handle it
            $usesDynamic = ($config['max_kg_verschil'] ?? 0) > 0;

            // Check if category has fixed weight classes defined
            $hasFixedWeightClasses = !empty($config['gewichten'] ?? []);

            $geslacht = strtoupper($judoka->geslacht ?? '');

            if ($usesDynamic) {
                // Dynamic grouping: group only by age class
                // DynamischeIndelingService will create weight groups
                if ($includeGeslacht) {
                    return "{$leeftijdsklasse}||{$geslacht}";
                }
                return "{$leeftijdsklasse}|";
            } elseif ($hasFixedWeightClasses) {
                // Fixed weight classes defined: always group by weight class
                $gewichtsklasse = $judoka->gewichtsklasse ?: 'Onbekend';
                if ($includeGeslacht) {
                    return "{$leeftijdsklasse}|{$gewichtsklasse}|{$geslacht}";
                }
                return "{$leeftijdsklasse}|{$gewichtsklasse}";
            } else {
                // No weight classes defined and not dynamic: group only by age
                if ($includeGeslacht) {
                    return "{$leeftijdsklasse}||{$geslacht}";
                }
                return "{$leeftijdsklasse}|";
            }
        });

        // Re-sort judokas within each group (groupBy doesn't preserve order!)
        $groepen = $groepen->map(function ($judokasInGroep) use ($bandFirst) {
            if ($bandFirst) {
                return $judokasInGroep->sortBy([
                    ['sort_band', 'asc'],
                    ['sort_gewicht', 'asc'],
                ]);
            } else {
                return $judokasInGroep->sortBy([
                    ['sort_gewicht', 'asc'],
                    ['sort_band', 'asc'],
                ]);
            }
        });

        // Sort groups by sort_categorie of first judoka, then gewicht
        return $groepen->sortBy(function ($judokasInGroep, $key) {
            // Use sort fields from first judoka in group
            $eerste = $judokasInGroep->first();
            $sortCategorie = $eerste->sort_categorie ?? 99;

            // Parse gewicht from key for secondary sort
            $delen = explode('|', $key);
            $gewicht = $delen[1] ?? '';
            $gewichtNum = intval(preg_replace('/[^0-9]/', '', $gewicht));
            $gewichtPlus = str_starts_with($gewicht, '+') ? 1000 : 0;

            return sprintf('%02d%04d', $sortCategorie, $gewichtNum + $gewichtPlus);
        });
    }

    /**
     * Move judoka to different pool
     */
    public function verplaatsJudoka(Judoka $judoka, Poule $nieuwePoule): void
    {
        DB::transaction(function () use ($judoka, $nieuwePoule) {
            // Remove from current pool(s)
            $huidigePoules = $judoka->poules;
            foreach ($huidigePoules as $poule) {
                $poule->judokas()->detach($judoka->id);
                $poule->updateStatistieken();
            }

            // Add to new pool
            $positie = $nieuwePoule->judokas()->count() + 1;
            $nieuwePoule->judokas()->attach($judoka->id, ['positie' => $positie]);
            $nieuwePoule->updateStatistieken();

            // Update judoka's weight class if needed
            if ($judoka->gewichtsklasse !== $nieuwePoule->gewichtsklasse) {
                $judoka->update(['gewichtsklasse' => $nieuwePoule->gewichtsklasse]);
            }
        });
    }

}
