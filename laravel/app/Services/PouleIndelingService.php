<?php

namespace App\Services;

use App\Models\Judoka;
use App\Models\Poule;
use App\Models\Toernooi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PouleIndelingService
{
    private int $minJudokas;
    private int $maxJudokas;
    private array $voorkeur;
    private bool $clubspreiding;
    private array $prioriteiten;
    private ?Toernooi $toernooi = null;
    private array $gewichtsklassenConfig = [];
    private DynamischeIndelingService $dynamischeIndelingService;

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
        $this->clubspreiding = $toernooi->clubspreiding ?? true;
        $this->prioriteiten = $toernooi->verdeling_prioriteiten ?? ['leeftijd', 'gewicht', 'band'];
        $this->gewichtsklassenConfig = $toernooi->getAlleGewichtsklassen();
    }

    public function __construct(DynamischeIndelingService $dynamischeIndelingService)
    {
        // Default values, will be overridden by initializeFromToernooi
        $this->voorkeur = [5, 4, 6, 3];
        $this->minJudokas = 3;
        $this->maxJudokas = 6;
        $this->clubspreiding = true;
        $this->prioriteiten = ['leeftijd', 'gewicht', 'band'];
        $this->dynamischeIndelingService = $dynamischeIndelingService;
    }

    /**
     * Recalculate category and sort fields for all judokas
     * Uses preset config for classification (not hardcoded enum)
     * Important after year change when judokas move to different age categories
     */
    public function herberkenKlassen(Toernooi $toernooi): int
    {
        $bijgewerkt = 0;

        // Ensure config is loaded
        $this->gewichtsklassenConfig = $toernooi->getAlleGewichtsklassen();

        foreach ($toernooi->judokas as $judoka) {
            // Store old values for comparison
            $oudeLeeftijdsklasse = $judoka->leeftijdsklasse;
            $oudeGewichtsklasse = $judoka->gewichtsklasse;
            $oudeSortCategorie = $judoka->sort_categorie;

            // Classify using preset config
            $classificatie = $this->classificeerJudoka($judoka, $toernooi);

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
                    'categorie_key' => $classificatie['configKey'],
                    'sort_categorie' => $nieuweSortCategorie,
                    'sort_gewicht' => $sortGewicht,
                    'sort_band' => $this->getBandNiveau($judoka->band ?? ''),
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

                // Check if this is an elimination weight class
                $klasseKey = $this->getLeeftijdsklasseKey($leeftijdsklasse);
                $systeem = $wedstrijdSysteem[$klasseKey] ?? 'poules';
                $isEliminatie = $systeem === 'eliminatie' &&
                    isset($eliminatieGewichtsklassen[$klasseKey]) &&
                    in_array($gewichtsklasse, $eliminatieGewichtsklassen[$klasseKey]);

                // For elimination: create one group with all judokas (no pool splitting)
                if ($isEliminatie) {
                    $aantalDeelnemers = $judokas->count();

                    // Warn if less than 8 participants (ideal for elimination)
                    if ($aantalDeelnemers < 8) {
                        $statistieken['waarschuwingen'][] = [
                            'type' => 'warning',
                            'categorie' => "{$leeftijdsklasse} {$gewichtsklasse}",
                            'bericht' => "Weinig deelnemers voor eliminatie ({$aantalDeelnemers}). Ideaal is 8+.",
                            'aantal' => $aantalDeelnemers,
                        ];
                    }

                    // Build dynamic title with actual ranges and config label
                    $gewichtsklassenConfig = $toernooi->getAlleGewichtsklassen();
                    $configKey = $this->leeftijdsklasseToConfigKey($leeftijdsklasse);
                    $lkLabel = ($configKey && isset($gewichtsklassenConfig[$configKey]['label']))
                        ? $gewichtsklassenConfig[$configKey]['label']
                        : $leeftijdsklasse;

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
                        'categorie_key' => $configKey,
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

                // Get sorting mode and config for title generation
                $gebruikGewichtsklassen = $toernooi->gebruik_gewichtsklassen === null ? true : $toernooi->gebruik_gewichtsklassen;
                $volgorde = $toernooi->judoka_code_volgorde ?? 'gewicht_band';
                $gewichtsklassenConfig = $toernooi->getAlleGewichtsklassen();

                // Check if this category uses dynamic grouping (max_kg_verschil > 0)
                // Per-category setting overrides global gebruik_gewichtsklassen
                $usesDynamic = $this->usesDynamicGrouping($leeftijdsklasse);

                if ($usesDynamic) {
                    // DYNAMIC GROUPING: Use DynamischeIndelingService to create weight groups
                    $maxKg = $this->getMaxKgVerschil($leeftijdsklasse);
                    $maxLeeftijd = $this->getMaxLeeftijdVerschil($leeftijdsklasse);

                    // Haal poule grootte voorkeur uit toernooi instellingen
                    $pouleGrootteVoorkeur = $toernooi->poule_grootte_voorkeur ?? [5, 4, 6, 3];

                    $indeling = $this->dynamischeIndelingService->berekenIndeling(
                        $judokas,
                        $maxLeeftijd,
                        $maxKg,
                        [
                            'poule_grootte_voorkeur' => $pouleGrootteVoorkeur,
                            'verdeling_prioriteiten' => $this->prioriteiten,
                            'clubspreiding' => $this->clubspreiding,
                        ]
                    );

                    // Check if this age class uses elimination system
                    $isDynamicEliminatie = $systeem === 'eliminatie';

                    // Get config key for grouping
                    $dynamicConfigKey = $this->leeftijdsklasseToConfigKey($leeftijdsklasse);

                    // Create pools from dynamische indeling result
                    foreach ($indeling['poules'] as $pouleData) {
                        $pouleJudokas = $pouleData['judokas'];
                        if (empty($pouleJudokas)) continue;

                        // Build dynamic title with weight range from pool
                        $gewichtRange = $pouleData['gewicht_groep'] ?? '';
                        $pouleType = $isDynamicEliminatie ? 'eliminatie' : 'voorronde';
                        $titel = $this->maakPouleTitel($leeftijdsklasse, $gewichtRange, $geslacht, $pouleNummer, $pouleJudokas, $isDynamicEliminatie, $volgorde, $gewichtsklassenConfig, $dynamicConfigKey);

                        $poule = Poule::create([
                            'toernooi_id' => $toernooi->id,
                            'nummer' => $pouleNummer,
                            'titel' => $titel,
                            'type' => $pouleType,
                            'leeftijdsklasse' => $leeftijdsklasse,
                            'gewichtsklasse' => $gewichtRange,
                            'categorie_key' => $dynamicConfigKey,
                            'aantal_judokas' => count($pouleJudokas),
                        ]);

                        // Attach judokas to pool
                        $positie = 1;
                        foreach ($pouleJudokas as $judoka) {
                            $poule->judokas()->attach($judoka->id, ['positie' => $positie++]);
                        }

                        // Calculate matches
                        $poule->updateStatistieken();

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
                        $categorieKey = "{$leeftijdsklasse}|{$gewichtRange}" . ($geslacht ? "|{$geslacht}" : '');
                        if (!isset($voorrondesPerCategorie[$categorieKey])) {
                            $voorrondesPerCategorie[$categorieKey] = [
                                'leeftijdsklasse' => $leeftijdsklasse,
                                'gewichtsklasse' => $gewichtRange,
                                'geslacht' => $geslacht,
                                'aantal_poules' => 0,
                            ];
                        }
                        $voorrondesPerCategorie[$categorieKey]['aantal_poules']++;

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
                    $pouleVerdelingen = $this->maakOptimalePoules($judokas);
                    $standardConfigKey = $this->leeftijdsklasseToConfigKey($leeftijdsklasse);

                    foreach ($pouleVerdelingen as $pouleJudokas) {
                        $titel = $this->maakPouleTitel($leeftijdsklasse, $gewichtsklasse, $geslacht, $pouleNummer, $pouleJudokas, $gebruikGewichtsklassen, $volgorde, $gewichtsklassenConfig, $standardConfigKey);

                        $poule = Poule::create([
                            'toernooi_id' => $toernooi->id,
                            'nummer' => $pouleNummer,
                            'titel' => $titel,
                            'type' => 'voorronde',
                            'leeftijdsklasse' => $leeftijdsklasse,
                            'gewichtsklasse' => $gewichtsklasse,
                            'categorie_key' => $standardConfigKey,
                            'aantal_judokas' => count($pouleJudokas),
                        ]);

                        // Attach judokas to pool
                        $positie = 1;
                        foreach ($pouleJudokas as $judoka) {
                            $poule->judokas()->attach($judoka->id, ['positie' => $positie++]);
                        }

                        // Calculate matches
                        $poule->updateStatistieken();

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
                        $categorieKey = $sleutel;
                        if (!isset($voorrondesPerCategorie[$categorieKey])) {
                            $voorrondesPerCategorie[$categorieKey] = [
                                'leeftijdsklasse' => $leeftijdsklasse,
                                'gewichtsklasse' => $gewichtsklasse,
                                'geslacht' => $geslacht,
                                'aantal_poules' => 0,
                            ];
                        }
                        $voorrondesPerCategorie[$categorieKey]['aantal_poules']++;

                        $pouleNummer++;
                    }
                }
            }

            // Create kruisfinale pools where applicable (per gewichtsklasse)
            foreach ($voorrondesPerCategorie as $categorieKey => $data) {
                $klasseKey = $this->getLeeftijdsklasseKey($data['leeftijdsklasse']);
                $systeem = $wedstrijdSysteem[$klasseKey] ?? 'poules';

                // Only create kruisfinale if system is poules_kruisfinale AND there are 2+ voorrondepoules
                if ($systeem === 'poules_kruisfinale' && $data['aantal_poules'] >= 2) {
                    // Calculate how many places qualify based on number of poules
                    // Goal: kruisfinale of 4-6 judokas
                    $aantalPoules = $data['aantal_poules'];
                    $kruisfinalesAantal = $this->berekenKruisfinalesPlaatsen($aantalPoules);
                    $aantalJudokasKruisfinale = $aantalPoules * $kruisfinalesAantal;

                    $geslachtLabel = match ($data['geslacht']) {
                        'M' => 'M',
                        'V' => 'V',
                        default => null,
                    };

                    // Get label from config
                    $gewichtsklassenConfig = $toernooi->getAlleGewichtsklassen();
                    $configKey = $this->leeftijdsklasseToConfigKey($data['leeftijdsklasse']);
                    $lkLabel = ($configKey && isset($gewichtsklassenConfig[$configKey]['label']))
                        ? $gewichtsklassenConfig[$configKey]['label']
                        : $data['leeftijdsklasse'];

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
                        'aantal_wedstrijden' => $this->berekenAantalWedstrijden($aantalJudokasKruisfinale),
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
            $nietIngedeeld = $this->vindNietIngedeeldeJudokas($toernooi);
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
     * Get the key used in wedstrijd_systeem for a leeftijdsklasse label
     * Uses preset config instead of hardcoded mapping
     */
    private function getLeeftijdsklasseKey(string $label): string
    {
        // Search in preset config by label
        foreach ($this->gewichtsklassenConfig as $key => $data) {
            if (($data['label'] ?? '') === $label) {
                return $key;
            }
        }

        // Fallback: normalize label to key format
        return strtolower(str_replace([' ', '-', "'"], '_', $label));
    }

    /**
     * Calculate how many places qualify for kruisfinale based on number of poules
     * Goal: kruisfinale of 4-6 judokas (ideal pool size)
     *
     * 2 poules → top 2 or 3 (4-6 judokas)
     * 3 poules → top 2 (6 judokas)
     * 4 poules → top 1 (4 judokas) or top 2 if we want more
     * 5+ poules → top 1 (5+ judokas)
     */
    private function berekenKruisfinalesPlaatsen(int $aantalPoules): int
    {
        if ($aantalPoules <= 2) {
            return 3; // 2 poules × 3 = 6 judokas
        }
        if ($aantalPoules === 3) {
            return 2; // 3 poules × 2 = 6 judokas
        }
        if ($aantalPoules <= 5) {
            return 1; // 4-5 poules × 1 = 4-5 judokas
        }
        // 6+ poules: still top 1, results in 6+ judokas kruisfinale
        return 1;
    }

    /**
     * Calculate number of matches for a given number of judokas
     */
    private function berekenAantalWedstrijden(int $aantal): int
    {
        if ($aantal <= 1) return 0;
        if ($aantal === 3) return 6; // Double round
        return intval(($aantal * ($aantal - 1)) / 2);
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

            // Get config for gender handling
            $config = $this->gewichtsklassenConfig[$categorieKey] ?? null;
            $configGeslacht = strtolower($config['geslacht'] ?? 'gemengd');
            $includeGeslacht = $configGeslacht !== 'gemengd';

            $geslacht = strtoupper($judoka->geslacht ?? '');

            if ($gebruikGewichtsklassen) {
                // Met gewichtsklassen: groepeer per leeftijd + gewichtsklasse
                $gewichtsklasse = $judoka->gewichtsklasse ?: 'Onbekend';
                if ($includeGeslacht) {
                    return "{$leeftijdsklasse}|{$gewichtsklasse}|{$geslacht}";
                }
                return "{$leeftijdsklasse}|{$gewichtsklasse}";
            } else {
                // Zonder gewichtsklassen: groepeer alleen per leeftijd
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
     * Find the config key for a judoka based on their age and gender
     */
    private function findConfigKeyForJudoka(Judoka $judoka, Toernooi $toernooi): ?string
    {
        $leeftijd = $judoka->leeftijd ?? ($toernooi->datum?->year ?? date('Y')) - $judoka->geboortejaar;
        $geslacht = strtoupper($judoka->geslacht);

        // Find matching config based on max_leeftijd
        foreach ($this->gewichtsklassenConfig as $key => $config) {
            $maxLeeftijd = $config['max_leeftijd'] ?? 99;
            $configGeslacht = strtoupper($config['geslacht'] ?? 'gemengd');

            // Check age match
            if ($leeftijd <= $maxLeeftijd) {
                // Check gender match (gemengd matches all, or specific gender must match)
                if ($configGeslacht === 'GEMENGD' || $configGeslacht === $geslacht) {
                    return $key;
                }
            }
        }

        return null;
    }

    /**
     * Get band niveau for sorting (1=wit/beginner, 7=zwart/expert)
     * Lower number = less experienced, should be sorted first
     * Supports formats: "wit", "wit (6 kyu)", "6 kyu", etc.
     */
    private function getBandNiveau(string $band): int
    {
        $mapping = [
            'wit' => 1,
            'geel' => 2,
            'oranje' => 3,
            'groen' => 4,
            'blauw' => 5,
            'bruin' => 6,
            'zwart' => 7,
        ];

        $bandLower = strtolower(trim($band));

        // Direct match
        if (isset($mapping[$bandLower])) {
            return $mapping[$bandLower];
        }

        // Extract first word (e.g., "wit (6 kyu)" -> "wit")
        $firstWord = explode(' ', $bandLower)[0];
        if (isset($mapping[$firstWord])) {
            return $mapping[$firstWord];
        }

        // Check if band contains color name
        foreach ($mapping as $color => $niveau) {
            if (str_contains($bandLower, $color)) {
                return $niveau;
            }
        }

        return 0;
    }

    /**
     * Classify a judoka into a category based on preset config
     * Returns array with: configKey, label, sortCategorie, gewichtsklasse
     */
    private function classificeerJudoka(Judoka $judoka, Toernooi $toernooi): array
    {
        $leeftijd = $judoka->leeftijd ?? ($toernooi->datum?->year ?? date('Y')) - $judoka->geboortejaar;
        $geslacht = strtoupper($judoka->geslacht ?? '');
        $bandNiveau = $this->getBandNiveau($judoka->band ?? '');
        $tolerantie = $toernooi->gewicht_tolerantie ?? 0.5;

        $sortCategorie = 0;
        foreach ($this->gewichtsklassenConfig as $key => $config) {
            $maxLeeftijd = $config['max_leeftijd'] ?? 99;
            $configGeslacht = strtoupper($config['geslacht'] ?? 'gemengd');
            $label = strtolower($config['label'] ?? '');

            // Normalize legacy values: meisjes -> V, jongens -> M
            if ($configGeslacht === 'MEISJES') {
                $configGeslacht = 'V';
            } elseif ($configGeslacht === 'JONGENS') {
                $configGeslacht = 'M';
            }

            // Auto-detect gender from label ONLY if geslacht is not explicitly set
            // Important: if geslacht is explicitly 'gemengd', don't override based on key suffix
            // The key suffix detection is only for cases where organisator forgot to set geslacht
            $originalGeslacht = strtolower($config['geslacht'] ?? '');
            $isExplicitGemengd = $originalGeslacht === 'gemengd';

            if ($configGeslacht === 'GEMENGD' && !$isExplicitGemengd) {
                // Only auto-detect if geslacht was empty/missing, not if explicitly set to 'gemengd'
                if (str_contains($label, 'dames') || str_contains($label, 'meisjes') || str_ends_with($key, '_d') || str_contains($key, '_d_')) {
                    $configGeslacht = 'V';
                } elseif (str_contains($label, 'heren') || str_contains($label, 'jongens') || str_ends_with($key, '_h') || str_contains($key, '_h_')) {
                    $configGeslacht = 'M';
                }
            }

            // Check leeftijd
            if ($leeftijd > $maxLeeftijd) {
                $sortCategorie++;
                continue;
            }

            // Check geslacht (gemengd matches all)
            if ($configGeslacht !== 'GEMENGD' && $configGeslacht !== $geslacht) {
                $sortCategorie++;
                continue;
            }

            // Check band_filter if set
            $bandFilter = $config['band_filter'] ?? null;
            if ($bandFilter && !$this->voldoetAanBandFilter($bandNiveau, $bandFilter)) {
                $sortCategorie++;
                continue;
            }

            // Match found! Determine gewichtsklasse
            $gewichtsklasse = $this->bepaalGewichtsklasseUitConfig(
                $judoka->gewicht ?? 0,
                $config,
                $tolerantie
            );

            return [
                'configKey' => $key,
                'label' => $config['label'] ?? $key,
                'sortCategorie' => $sortCategorie,
                'gewichtsklasse' => $gewichtsklasse,
            ];
        }

        // No match found
        return [
            'configKey' => null,
            'label' => 'Onbekend',
            'sortCategorie' => 99,
            'gewichtsklasse' => null,
        ];
    }

    /**
     * Check if band niveau matches the band filter
     * Filter format: "tm_oranje" (t/m oranje) or "vanaf_groen" (vanaf groen)
     */
    private function voldoetAanBandFilter(int $bandNiveau, string $filter): bool
    {
        // Parse filter: "tm_oranje", "vanaf_groen", etc.
        if (str_starts_with($filter, 'tm_') || str_starts_with($filter, 't/m ')) {
            $band = str_replace(['tm_', 't/m '], '', $filter);
            $maxNiveau = $this->getBandNiveau($band);
            return $bandNiveau <= $maxNiveau;
        }

        if (str_starts_with($filter, 'vanaf_') || str_starts_with($filter, 'vanaf ')) {
            $band = str_replace(['vanaf_', 'vanaf '], '', $filter);
            $minNiveau = $this->getBandNiveau($band);
            return $bandNiveau >= $minNiveau;
        }

        // Unknown filter format, allow all
        return true;
    }

    /**
     * Determine gewichtsklasse from config
     * Returns null for dynamic categories (max_kg_verschil > 0)
     */
    private function bepaalGewichtsklasseUitConfig(float $gewicht, array $config, float $tolerantie = 0.5): ?string
    {
        // Dynamic category - no fixed weight classes
        $maxKg = $config['max_kg_verschil'] ?? 0;
        if ($maxKg > 0) {
            return null;
        }

        // Fixed weight classes
        $gewichten = $config['gewichten'] ?? [];
        if (empty($gewichten)) {
            return null;
        }

        foreach ($gewichten as $klasse) {
            // Parse klasse: "-30" = max 30kg, "+30" = min 30kg
            $klasseStr = (string) $klasse;

            if (str_starts_with($klasseStr, '+')) {
                // Plus category (minimum weight) - always last, catch-all
                return $klasseStr;
            }

            // Minus category (maximum weight)
            $maxGewicht = abs((float) $klasseStr);
            if ($gewicht <= $maxGewicht + $tolerantie) {
                return $klasseStr;
            }
        }

        // Fallback to highest (+ category if exists)
        $laatste = end($gewichten);
        return (string) $laatste;
    }

    /**
     * Update sort fields for a judoka based on classification
     */
    public function updateSorteerVelden(Judoka $judoka, Toernooi $toernooi): void
    {
        // Ensure config is loaded
        if (empty($this->gewichtsklassenConfig)) {
            $this->gewichtsklassenConfig = $toernooi->getAlleGewichtsklassen();
        }

        $classificatie = $this->classificeerJudoka($judoka, $toernooi);

        // Calculate sort_gewicht (weight in grams for precision)
        $gewicht = $judoka->gewicht_gewogen ?? $judoka->gewicht ?? 0;
        $sortGewicht = (int) round($gewicht * 1000);

        $judoka->update([
            'leeftijdsklasse' => $classificatie['label'],
            'categorie_key' => $classificatie['configKey'],
            'sort_categorie' => $classificatie['sortCategorie'],
            'sort_gewicht' => $sortGewicht,
            'sort_band' => $this->getBandNiveau($judoka->band ?? ''),
            'gewichtsklasse' => $classificatie['gewichtsklasse'] ?? $judoka->gewichtsklasse,
        ]);
    }

    /**
     * Get config for a leeftijdsklasse label
     */
    private function getConfigForLeeftijdsklasse(string $leeftijdsklasse): ?array
    {
        $configKey = $this->leeftijdsklasseToConfigKey($leeftijdsklasse);
        return $this->gewichtsklassenConfig[$configKey] ?? null;
    }

    /**
     * Check if a category should use dynamic grouping (max_kg_verschil > 0)
     */
    private function usesDynamicGrouping(string $leeftijdsklasse): bool
    {
        $config = $this->getConfigForLeeftijdsklasse($leeftijdsklasse);
        if (!$config) {
            return false;
        }
        $maxKg = $config['max_kg_verschil'] ?? 0;
        return $maxKg > 0;
    }

    /**
     * Get max kg verschil for a leeftijdsklasse
     */
    private function getMaxKgVerschil(string $leeftijdsklasse): float
    {
        // First check category-specific config
        $config = $this->getConfigForLeeftijdsklasse($leeftijdsklasse);
        if ($config && isset($config['max_kg_verschil']) && $config['max_kg_verschil'] > 0) {
            return (float) $config['max_kg_verschil'];
        }
        // Fallback to tournament-level setting
        return (float) ($this->toernooi?->max_kg_verschil ?? 3.0);
    }

    /**
     * Get max leeftijd verschil for a leeftijdsklasse
     *
     * 0 = gebruik categorie limiet (judokas in deze categorie mogen allemaal samen)
     * 1+ = max dit aantal jaar verschil binnen een poule
     */
    private function getMaxLeeftijdVerschil(string $leeftijdsklasse): int
    {
        // First check category-specific config
        $config = $this->getConfigForLeeftijdsklasse($leeftijdsklasse);
        if ($config && isset($config['max_leeftijd_verschil'])) {
            $value = (int) $config['max_leeftijd_verschil'];
            if ($value === 0) {
                // 0 = gebruik categorie limiet, bereken uit max_leeftijd
                // Als categorie max_leeftijd=12, dan mogen 9-11 jarigen samen (3 jaar range)
                $maxLeeftijd = $config['max_leeftijd'] ?? 99;
                // Schat de range: typisch 2-3 jaar per categorie
                return $maxLeeftijd < 99 ? 3 : 2;
            }
            return $value;
        }
        // Fallback to tournament-level setting
        return (int) ($this->toernooi?->max_leeftijd_verschil ?? 2);
    }

    /**
     * @deprecated Use sort_categorie field instead
     * Get sort order for leeftijdsklasse from preset config
     */
    private function getLeeftijdOrder(string $leeftijd): int
    {
        // Get order from preset config (key position = order)
        $configKey = $this->leeftijdsklasseToConfigKey($leeftijd);
        $keys = array_keys($this->gewichtsklassenConfig);
        $index = array_search($configKey, $keys);

        return $index !== false ? $index : 99;
    }

    /**
     * Create optimal pool division based on preference order
     * Uses the configured preference list (e.g., [5, 4, 6, 3]) to score divisions
     */
    private function maakOptimalePoules(Collection $judokas): array
    {
        $aantal = $judokas->count();
        $judokasArray = $judokas->values()->all();

        // Less than minimum: single pool (can't split into valid pools)
        if ($aantal <= $this->minJudokas) {
            return [$judokasArray];
        }

        // Find best division based on preference scores
        // Even for small groups (4-6), check if splitting is preferred
        $bestePouleGroottes = [];
        $besteScore = PHP_INT_MAX;

        // Also consider 1 pool as option if within bounds
        if ($aantal >= $this->minJudokas && $aantal <= $this->maxJudokas) {
            $bestePouleGroottes = [$aantal];
            $besteScore = $this->berekenVerdelingScore([$aantal]);
        }

        $maxPoules = (int) floor($aantal / $this->minJudokas);

        for ($aantalPoules = 2; $aantalPoules <= $maxPoules; $aantalPoules++) {
            $basisGrootte = (int) floor($aantal / $aantalPoules);
            $rest = $aantal % $aantalPoules;

            // Calculate pool sizes for this division
            $pouleGroottes = array_fill(0, $aantalPoules, $basisGrootte);
            for ($i = 0; $i < $rest; $i++) {
                $pouleGroottes[$i]++;
            }

            // Skip if any pool is outside min/max bounds
            $valid = true;
            foreach ($pouleGroottes as $grootte) {
                if ($grootte < $this->minJudokas || $grootte > $this->maxJudokas) {
                    $valid = false;
                    break;
                }
            }
            if (!$valid) continue;

            // Calculate score based on preference order
            $score = $this->berekenVerdelingScore($pouleGroottes);

            if ($score < $besteScore) {
                $besteScore = $score;
                $bestePouleGroottes = $pouleGroottes;
            }
        }

        if (empty($bestePouleGroottes)) {
            return [$judokasArray];
        }

        // Distribute by slicing (preserves order from sort fields)
        $verdeling = [];
        $index = 0;
        foreach ($bestePouleGroottes as $grootte) {
            $verdeling[] = array_slice($judokasArray, $index, $grootte);
            $index += $grootte;
        }

        // Apply club spreading as refinement
        // Only swap judokas with same band and within max_kg_verschil
        if ($this->clubspreiding && count($verdeling) > 1) {
            $maxGewichtVerschilBijSwap = $this->toernooi?->max_kg_verschil ?? 3.0;
            $verdeling = $this->pasClubspreidingToe($verdeling, true, $maxGewichtVerschilBijSwap);
        }

        return $verdeling;
    }

    /**
     * Apply club spreading as refinement
     * @param bool $onlySwapSameBand If true, only swap judokas with same band
     * @param float|null $maxGewichtVerschil If set, only swap judokas within this weight difference
     */
    private function pasClubspreidingToe(array $poules, bool $onlySwapSameBand = true, ?float $maxGewichtVerschil = null): array
    {
        $aantalPoules = count($poules);

        // For each pool, check for club duplicates
        for ($p = 0; $p < $aantalPoules; $p++) {
            $clubCount = [];
            foreach ($poules[$p] as $idx => $judoka) {
                $clubId = $judoka->club_id ?? 0;
                if (!isset($clubCount[$clubId])) {
                    $clubCount[$clubId] = [];
                }
                $clubCount[$clubId][] = $idx;
            }

            // For clubs with multiple judokas, try to swap one to another pool
            foreach ($clubCount as $clubId => $indices) {
                if (count($indices) <= 1) continue;

                // Try to swap the second (and further) judoka(s) to other pools
                for ($i = 1; $i < count($indices); $i++) {
                    $judokaIdx = $indices[$i];
                    $judoka = $poules[$p][$judokaIdx];
                    $judokaBand = $judoka->band;

                    // Find a swap candidate in another pool
                    for ($q = 0; $q < $aantalPoules; $q++) {
                        if ($q === $p) continue;

                        foreach ($poules[$q] as $kandidaatIdx => $kandidaat) {
                            // Check band compatibility
                            $bandMatch = !$onlySwapSameBand || $kandidaat->band === $judokaBand;

                            // Check weight compatibility (only swap similar weights if gewicht has higher priority)
                            $gewichtMatch = ($maxGewichtVerschil === null) ||
                                (abs(($kandidaat->gewicht ?? 0) - ($judoka->gewicht ?? 0)) <= $maxGewichtVerschil);

                            // Different club, that club is not already in target pool
                            if ($bandMatch && $gewichtMatch &&
                                $kandidaat->club_id !== $clubId &&
                                !$this->clubInPoule($poules[$p], $kandidaat->club_id, $judokaIdx)) {

                                // Check if the kandidaat's club is not duplicated in their pool
                                if (!$this->clubInPoule($poules[$q], $judoka->club_id, $kandidaatIdx)) {
                                    // Swap
                                    $poules[$p][$judokaIdx] = $kandidaat;
                                    $poules[$q][$kandidaatIdx] = $judoka;
                                    break 2; // Move to next duplicate
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
     * Check if a club is already in a pool (excluding a specific index)
     */
    private function clubInPoule(array $poule, ?int $clubId, int $excludeIdx): bool
    {
        foreach ($poule as $idx => $judoka) {
            if ($idx !== $excludeIdx && ($judoka->club_id ?? 0) === $clubId) {
                return true;
            }
        }
        return false;
    }

    /**
     * Calculate score for a division based on preference order
     * Lower score = better division
     * Uses exponential scoring so 2x preferred size beats 1x less preferred size
     */
    private function berekenVerdelingScore(array $pouleGroottes): int
    {
        $score = 0;

        foreach ($pouleGroottes as $grootte) {
            // Find position in preference list (0 = best)
            $positie = array_search($grootte, $this->voorkeur);

            if ($positie === false) {
                // Size not in preference list - heavy penalty
                $score += 1000;
            } else {
                // Exponential scoring: 2^position (1, 2, 4, 8, ...)
                // This ensures 2 pools of position 2 (score 8) beats 1 pool of position 3 (score 8)
                // Add 1 to differentiate: 1, 3, 7, 15 (2^(n+1) - 1)
                $score += pow(2, $positie + 1) - 1;
            }
        }

        return $score;
    }

    /**
     * Create standardized pool title
     *
     * Title composition based on category config:
     * - Label: optional via toon_label_in_titel checkbox
     * - Age range: only if max_leeftijd_verschil > 0 (variable)
     * - Weight: fixed class OR variable range based on max_kg_verschil
     *
     * Examples:
     * - "Mini's U7 -26kg" (label on, fixed category)
     * - "Mini's U7 28-32kg" (label on, variable weight)
     * - "9-10j 28-32kg" (label off, both variable)
     */
    private function maakPouleTitel(string $leeftijdsklasse, string $gewichtsklasse, ?string $geslacht, int $pouleNr, array $pouleJudokas = [], bool $gebruikGewichtsklassen = true, string $volgorde = 'gewicht_band', ?array $gewichtsklassenConfig = null, ?string $categorieKey = null): string
    {
        $parts = [];

        // Get category config for this leeftijdsklasse
        // Same lookup logic as updateDynamischeTitel in PouleController
        $categorieConfig = null;
        if ($gewichtsklassenConfig) {
            // Try by label first (most reliable)
            foreach ($gewichtsklassenConfig as $key => $data) {
                if (($data['label'] ?? '') === $leeftijdsklasse) {
                    $categorieConfig = $data;
                    break;
                }
            }
            // Fallback: direct config key lookup
            if (!$categorieConfig && $categorieKey && isset($gewichtsklassenConfig[$categorieKey])) {
                $categorieConfig = $gewichtsklassenConfig[$categorieKey];
            }
        }

        // 1. Label (optional via checkbox, default true)
        $toonLabel = $categorieConfig['toon_label_in_titel'] ?? true;
        $label = $categorieConfig['label'] ?? $leeftijdsklasse;
        if ($toonLabel && !empty($label)) {
            $parts[] = $label;
        }

        // 2. Gender (if not mixed)
        if ($geslacht && $geslacht !== 'gemengd') {
            $parts[] = $geslacht; // 'M' or 'V'
        }

        // 3. Age range (only if variable: max_leeftijd_verschil > 0)
        $maxLftVerschil = (int) ($categorieConfig['max_leeftijd_verschil'] ?? 0);

        if ($maxLftVerschil > 0 && !empty($pouleJudokas)) {
            $leeftijden = array_filter(array_map(fn($j) => $j->leeftijd, $pouleJudokas));
            if (!empty($leeftijden)) {
                $min = min($leeftijden);
                $max = max($leeftijden);
                $parts[] = $min == $max ? "{$min}j" : "{$min}-{$max}j";
            }
        }

        // 4. Weight (fixed class OR variable range)
        $maxKgVerschil = (float) ($categorieConfig['max_kg_verschil'] ?? 0);
        if ($maxKgVerschil > 0 && !empty($pouleJudokas)) {
            // Variable: calculate range from judokas
            $gewichten = array_filter(array_map(fn($j) => $j->gewicht, $pouleJudokas));
            if (!empty($gewichten)) {
                $min = min($gewichten);
                $max = max($gewichten);
                $parts[] = $min == $max ? "{$min}kg" : "{$min}-{$max}kg";
            }
        } elseif (!empty($gewichtsklasse)) {
            // Fixed: use weight class from preset
            $gk = $gewichtsklasse;
            if (!str_contains($gk, 'kg')) {
                $gk .= 'kg';
            }
            $parts[] = $gk;
        }

        return implode(' ', $parts) ?: 'Onbekend';
    }

    /**
     * Convert leeftijdsklasse label to config key
     * Uses preset config instead of hardcoded mapping
     */
    private function leeftijdsklasseToConfigKey(string $leeftijdsklasse): ?string
    {
        // Search in preset config by label
        foreach ($this->gewichtsklassenConfig as $key => $data) {
            if (($data['label'] ?? '') === $leeftijdsklasse) {
                return $key;
            }
        }

        // Try as direct config key (already a key, not a label)
        if (isset($this->gewichtsklassenConfig[$leeftijdsklasse])) {
            return $leeftijdsklasse;
        }

        // Fallback: normalize label to key format
        $normalized = strtolower(preg_replace('/[\s\-\']+/', '_', $leeftijdsklasse));
        if (isset($this->gewichtsklassenConfig[$normalized])) {
            return $normalized;
        }

        return $normalized;
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

    /**
     * Calculate total matches for tournament
     */
    public function berekenTotaalWedstrijden(Toernooi $toernooi): int
    {
        return $toernooi->poules()->sum('aantal_wedstrijden');
    }

    /**
     * Vind judoka's die niet in een poule zijn ingedeeld
     * Dit wijst op een onvolledige categorie configuratie
     */
    private function vindNietIngedeeldeJudokas(Toernooi $toernooi): array
    {
        // Haal alle judoka IDs op die in een poule zitten
        $ingedeeldeIds = DB::table('poule_judoka')
            ->whereIn('poule_id', $toernooi->poules()->pluck('id'))
            ->pluck('judoka_id')
            ->toArray();

        // Vind judoka's die niet ingedeeld zijn
        $nietIngedeeld = $toernooi->judokas()
            ->whereNotIn('id', $ingedeeldeIds)
            ->get(['id', 'naam', 'leeftijdsklasse', 'gewichtsklasse', 'band', 'gewicht', 'geboortejaar'])
            ->map(function ($judoka) use ($toernooi) {
                $reden = $this->bepaalRedenNietIngedeeld($judoka, $toernooi);
                return [
                    'id' => $judoka->id,
                    'naam' => $judoka->naam,
                    'leeftijdsklasse' => $judoka->leeftijdsklasse,
                    'gewichtsklasse' => $judoka->gewichtsklasse,
                    'band' => $judoka->band,
                    'gewicht' => $judoka->gewicht,
                    'reden' => $reden,
                ];
            })
            ->toArray();

        return $nietIngedeeld;
    }

    /**
     * Bepaal waarom een judoka niet is ingedeeld
     */
    private function bepaalRedenNietIngedeeld($judoka, Toernooi $toernooi): string
    {
        $config = $toernooi->getAlleGewichtsklassen();
        $toernooiJaar = $toernooi->datum?->year ?? (int) date('Y');
        $leeftijd = $toernooiJaar - $judoka->geboortejaar;

        $leeftijdMatch = false;
        $bandMatch = false;
        $gewichtMatch = false;

        foreach ($config as $key => $cat) {
            $maxLeeftijd = $cat['max_leeftijd'] ?? 99;
            if ($leeftijd <= $maxLeeftijd) {
                $leeftijdMatch = true;

                // Check band filter
                $bandFilter = $cat['band_filter'] ?? '';
                if (empty($bandFilter) || $this->bandPastInFilter($judoka->band, $bandFilter)) {
                    $bandMatch = true;
                }

                // Check gewicht
                $gewichten = $cat['gewichten'] ?? [];
                if (!empty($gewichten) && $judoka->gewicht) {
                    foreach ($gewichten as $g) {
                        $klasse = (float) str_replace(['-', '+'], '', $g);
                        if (str_starts_with($g, '+')) {
                            if ($judoka->gewicht >= $klasse) {
                                $gewichtMatch = true;
                                break;
                            }
                        } else {
                            if ($judoka->gewicht <= $klasse) {
                                $gewichtMatch = true;
                                break;
                            }
                        }
                    }
                } else {
                    $gewichtMatch = true;
                }
            }
        }

        if (!$leeftijdMatch) {
            return "Geen categorie voor leeftijd {$leeftijd} jaar";
        }
        if (!$bandMatch) {
            return "Geen categorie voor band '{$judoka->band}' bij deze leeftijd";
        }
        if (!$gewichtMatch) {
            return "Geen gewichtsklasse voor {$judoka->gewicht}kg";
        }

        return "Te groot gewichtsverschil met andere judoka's in de groep";
    }

    /**
     * Check of een band past in een filter
     */
    private function bandPastInFilter(?string $band, string $filter): bool
    {
        if (empty($filter) || empty($band)) return true;

        $bandVolgorde = ['wit' => 0, 'geel' => 1, 'oranje' => 2, 'groen' => 3, 'blauw' => 4, 'bruin' => 5, 'zwart' => 6];
        $bandIdx = $bandVolgorde[strtolower($band)] ?? 0;

        if (str_starts_with($filter, 'tm_')) {
            $filterBand = str_replace('tm_', '', $filter);
            $filterIdx = $bandVolgorde[$filterBand] ?? 99;
            return $bandIdx <= $filterIdx;
        }

        if (str_starts_with($filter, 'vanaf_')) {
            $filterBand = str_replace('vanaf_', '', $filter);
            $filterIdx = $bandVolgorde[$filterBand] ?? 0;
            return $bandIdx >= $filterIdx;
        }

        return true;
    }
}
