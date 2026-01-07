<?php

namespace App\Services;

use App\Enums\Leeftijdsklasse;
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
        $this->prioriteiten = $toernooi->verdeling_prioriteiten ?? ['groepsgrootte', 'bandkleur', 'clubspreiding'];
        $this->gewichtsklassenConfig = $toernooi->getAlleGewichtsklassen();
    }

    public function __construct(DynamischeIndelingService $dynamischeIndelingService)
    {
        // Default values, will be overridden by initializeFromToernooi
        $this->voorkeur = [5, 4, 6, 3];
        $this->minJudokas = 3;
        $this->maxJudokas = 6;
        $this->clubspreiding = true;
        $this->prioriteiten = ['groepsgrootte', 'bandkleur', 'clubspreiding'];
        $this->dynamischeIndelingService = $dynamischeIndelingService;
    }

    /**
     * Recalculate age class and weight class for all judokas
     * Important after year change when judokas move to different age categories
     * Uses the tournament year (from datum) for age calculation
     */
    public function herberkenKlassen(Toernooi $toernooi): int
    {
        $bijgewerkt = 0;
        $tolerantie = $toernooi->gewicht_tolerantie ?? 0.5;

        // Use tournament year, fallback to current year
        $toernooiJaar = $toernooi->datum?->year ?? (int) date('Y');

        foreach ($toernooi->judokas as $judoka) {
            $leeftijd = $toernooiJaar - $judoka->geboortejaar;
            $nieuweLeeftijdsklasse = Leeftijdsklasse::fromLeeftijdEnGeslacht($leeftijd, $judoka->geslacht);

            // Determine new weight class based on NEW age class and weight
            $nieuweGewichtsklasse = $judoka->gewichtsklasse;
            if ($judoka->gewicht) {
                $nieuweGewichtsklasse = $this->bepaalGewichtsklasseVoorLeeftijd(
                    $judoka->gewicht,
                    $nieuweLeeftijdsklasse,
                    $tolerantie
                );
            }

            // Update if changed
            if ($judoka->leeftijdsklasse !== $nieuweLeeftijdsklasse->label() ||
                $judoka->gewichtsklasse !== $nieuweGewichtsklasse) {
                $judoka->update([
                    'leeftijdsklasse' => $nieuweLeeftijdsklasse->label(),
                    'gewichtsklasse' => $nieuweGewichtsklasse,
                ]);
                $bijgewerkt++;
            }
        }

        return $bijgewerkt;
    }

    /**
     * Determine weight class for a given weight and age class
     */
    private function bepaalGewichtsklasseVoorLeeftijd(float $gewicht, Leeftijdsklasse $leeftijdsklasse, float $tolerantie = 0.5): string
    {
        $klassen = $leeftijdsklasse->gewichtsklassen();

        foreach ($klassen as $klasse) {
            if ($klasse > 0) {
                // Plus category (minimum weight) - always last
                return "+{$klasse}";
            }
            // Minus category (maximum weight) with tolerance
            if ($gewicht <= abs($klasse) + $tolerantie) {
                return "{$klasse}";
            }
        }

        // Fallback to highest (+ category)
        $hoogste = end($klassen);
        return $hoogste > 0 ? "+{$hoogste}" : "{$hoogste}";
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

        // Recalculate judoka codes after class changes
        $this->herberekenJudokaCodes($toernooi);

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

                // Check if this category uses dynamic grouping (max_kg_verschil > 0 and no fixed weight classes)
                $usesDynamic = !$gebruikGewichtsklassen && $this->usesDynamicGrouping($leeftijdsklasse);

                if ($usesDynamic) {
                    // DYNAMIC GROUPING: Use DynamischeIndelingService to create weight groups
                    $maxKg = $this->getMaxKgVerschil($leeftijdsklasse);
                    $maxLeeftijd = $this->getMaxLeeftijdVerschil($leeftijdsklasse);

                    $indeling = $this->dynamischeIndelingService->berekenIndeling(
                        $judokas,
                        $maxLeeftijd,
                        $maxKg
                    );

                    // Check if this age class uses elimination system
                    $isDynamicEliminatie = $systeem === 'eliminatie';

                    // Create pools from dynamische indeling result
                    foreach ($indeling['poules'] as $pouleData) {
                        $pouleJudokas = $pouleData['judokas'];
                        if (empty($pouleJudokas)) continue;

                        // Build dynamic title with weight range from pool
                        $gewichtRange = $pouleData['gewicht_groep'] ?? '';
                        $pouleType = $isDynamicEliminatie ? 'eliminatie' : 'voorronde';
                        $titel = $this->maakPouleTitel($leeftijdsklasse, $gewichtRange, $geslacht, $pouleNummer, $pouleJudokas, $isDynamicEliminatie, $volgorde, $gewichtsklassenConfig);

                        $poule = Poule::create([
                            'toernooi_id' => $toernooi->id,
                            'nummer' => $pouleNummer,
                            'titel' => $titel,
                            'type' => $pouleType,
                            'leeftijdsklasse' => $leeftijdsklasse,
                            'gewichtsklasse' => $gewichtRange,
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

                    foreach ($pouleVerdelingen as $pouleJudokas) {
                        $titel = $this->maakPouleTitel($leeftijdsklasse, $gewichtsklasse, $geslacht, $pouleNummer, $pouleJudokas, $gebruikGewichtsklassen, $volgorde, $gewichtsklassenConfig);

                        $poule = Poule::create([
                            'toernooi_id' => $toernooi->id,
                            'nummer' => $pouleNummer,
                            'titel' => $titel,
                            'type' => 'voorronde',
                            'leeftijdsklasse' => $leeftijdsklasse,
                            'gewichtsklasse' => $gewichtsklasse,
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

            return $statistieken;
        });
    }

    /**
     * Get the key used in wedstrijd_systeem for a leeftijdsklasse label
     */
    private function getLeeftijdsklasseKey(string $label): string
    {
        $mapping = [
            "Mini's" => 'minis',
            'A-pupillen' => 'a_pupillen',
            'B-pupillen' => 'b_pupillen',
            'Dames -15' => 'dames_15',
            'Heren -15' => 'heren_15',
            'Dames -18' => 'dames_18',
            'Heren -18' => 'heren_18',
            'Dames' => 'dames',
            'Heren' => 'heren',
        ];

        return $mapping[$label] ?? strtolower(str_replace([' ', '-', "'"], '_', $label));
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
     * Sorted by judoka_code for correct ordering
     *
     * If gebruik_gewichtsklassen is OFF: group only by leeftijd (+ geslacht from config)
     * If gebruik_gewichtsklassen is ON: group by leeftijd + gewichtsklasse (+ geslacht from config)
     */
    private function groepeerJudokas(Toernooi $toernooi): Collection
    {
        // Default to true if null (for backwards compatibility)
        $gebruikGewichtsklassen = $toernooi->gebruik_gewichtsklassen === null ? true : $toernooi->gebruik_gewichtsklassen;

        // Sort by judoka_code (already sorted correctly based on settings)
        $judokas = $toernooi->judokas()
            ->orderBy('judoka_code')
            ->get();

        $groepen = $judokas->groupBy(function (Judoka $judoka) use ($gebruikGewichtsklassen, $toernooi) {
            $leeftijdsklasse = $judoka->leeftijdsklasse ?: 'Onbekend';
            $geslacht = strtoupper($judoka->geslacht);

            // Get config for this age class to determine gender handling
            $configKey = $this->findConfigKeyForJudoka($judoka, $toernooi);
            $config = $this->gewichtsklassenConfig[$configKey] ?? null;

            // Determine if we should separate by gender based on config
            // geslacht='gemengd' means mixed, 'M' or 'V' means single gender category
            $configGeslacht = $config['geslacht'] ?? 'gemengd';
            $includeGeslacht = $configGeslacht !== 'gemengd';

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

        // Sort groups by leeftijd order, then gewicht (if applicable)
        return $groepen->sortBy(function ($judokas, $key) {
            $delen = explode('|', $key);
            $leeftijd = $delen[0] ?? '';
            $gewicht = $delen[1] ?? '';

            // Leeftijd order: Mini's=1, A-pup=2, B-pup=3, etc.
            $leeftijdOrder = $this->getLeeftijdOrder($leeftijd);

            // Gewicht: numeriek sorteren (0 if no weight class)
            $gewichtNum = intval(preg_replace('/[^0-9]/', '', $gewicht));
            $gewichtPlus = str_starts_with($gewicht, '+') ? 1000 : 0;

            return sprintf('%02d%04d', $leeftijdOrder, $gewichtNum + $gewichtPlus);
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
     */
    private function getMaxLeeftijdVerschil(string $leeftijdsklasse): int
    {
        // Use tournament-level setting (same for all categories for safety)
        return (int) ($this->toernooi?->max_leeftijd_verschil ?? 2);
    }

    /**
     * Get sort order for leeftijdsklasse
     */
    private function getLeeftijdOrder(string $leeftijd): int
    {
        $order = [
            "Mini's" => 1,
            'A-pupillen' => 2,
            'B-pupillen' => 3,
            'C-pupillen' => 4,
            'Dames -15' => 5,
            'Heren -15' => 6,
            'Dames -18' => 7,
            'Heren -18' => 8,
            'Dames -21' => 9,
            'Heren -21' => 10,
            'Dames' => 11,
            'Heren' => 12,
        ];

        return $order[$leeftijd] ?? 99;
    }

    /**
     * Create optimal pool division based on preference order
     * Uses the configured preference list (e.g., [5, 4, 6, 3]) to score divisions
     */
    private function maakOptimalePoules(Collection $judokas): array
    {
        $aantal = $judokas->count();
        $judokasArray = $judokas->values()->all();

        // Less than minimum: single pool (can't split)
        if ($aantal <= $this->minJudokas) {
            return [$judokasArray];
        }

        // If within max, check if single pool is best
        if ($aantal <= $this->maxJudokas) {
            return [$judokasArray];
        }

        // Find best division based on preference scores
        $bestePouleGroottes = [];
        $besteScore = PHP_INT_MAX;

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

        // Distribute by slicing (preserves band order from judoka_code sorting)
        $verdeling = [];
        $index = 0;
        foreach ($bestePouleGroottes as $grootte) {
            $verdeling[] = array_slice($judokasArray, $index, $grootte);
            $index += $grootte;
        }

        // Apply club spreading as refinement
        // Check priority: if clubspreiding > bandkleur, allow cross-band swaps
        if ($this->clubspreiding && count($verdeling) > 1) {
            $bandkleurIdx = array_search('bandkleur', $this->prioriteiten);
            $clubspreidingIdx = array_search('clubspreiding', $this->prioriteiten);

            // If clubspreiding has higher priority (lower index), allow any swap
            // If bandkleur has higher priority, only swap same band
            $onlySwapSameBand = ($bandkleurIdx !== false && $clubspreidingIdx !== false)
                ? $bandkleurIdx < $clubspreidingIdx
                : true;

            $verdeling = $this->pasClubspreidingToe($verdeling, $onlySwapSameBand);
        }

        return $verdeling;
    }

    /**
     * Apply club spreading as refinement
     * @param bool $onlySwapSameBand If true, only swap judokas with same band
     */
    private function pasClubspreidingToe(array $poules, bool $onlySwapSameBand = true): array
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

                            // Different club, that club is not already in target pool
                            if ($bandMatch &&
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
                // Score based on position: first preference = 0, second = 10, etc.
                $score += $positie * 10;
            }
        }

        return $score;
    }

    /**
     * Create standardized pool title
     * Dynamic title based on actual judoka values:
     * - "Jeugd 9-10j 30-33kg" (dynamic ranges)
     * - "Mini's M 7-8j 24-27kg" (with gender)
     */
    private function maakPouleTitel(string $leeftijdsklasse, string $gewichtsklasse, ?string $geslacht, int $pouleNr, array $pouleJudokas = [], bool $gebruikGewichtsklassen = true, string $volgorde = 'gewicht_band', ?array $gewichtsklassenConfig = null): string
    {
        // Get label from tournament config if available
        $lk = $leeftijdsklasse ?: 'Onbekend';
        if ($gewichtsklassenConfig) {
            // Find matching config key based on leeftijdsklasse
            $configKey = $this->leeftijdsklasseToConfigKey($leeftijdsklasse);
            if ($configKey && isset($gewichtsklassenConfig[$configKey]['label'])) {
                $lk = $gewichtsklassenConfig[$configKey]['label'];
            }
        }

        // Gender label (short form)
        $geslachtLabel = match ($geslacht) {
            'M' => 'M',
            'V' => 'V',
            default => null,
        };

        // Calculate age range from judokas
        $leeftijdRange = '';
        if (!empty($pouleJudokas)) {
            $leeftijden = array_filter(array_map(fn($j) => $j->leeftijd, $pouleJudokas));
            if (!empty($leeftijden)) {
                $minLeeftijd = min($leeftijden);
                $maxLeeftijd = max($leeftijden);
                $leeftijdRange = $minLeeftijd == $maxLeeftijd
                    ? "{$minLeeftijd}j"
                    : "{$minLeeftijd}-{$maxLeeftijd}j";
            }
        }

        // Calculate weight range from judokas
        $gewichtRange = '';
        if ($gebruikGewichtsklassen && !empty($gewichtsklasse)) {
            // With weight classes: show weight class
            $gewichtRange = $gewichtsklasse;
            if (!str_contains($gewichtRange, 'kg')) {
                $gewichtRange .= 'kg';
            }
        } elseif (!empty($pouleJudokas)) {
            // Without weight classes: calculate range from judokas
            $gewichten = array_filter(array_map(fn($j) => $j->gewicht, $pouleJudokas));
            if (!empty($gewichten)) {
                $minGewicht = min($gewichten);
                $maxGewicht = max($gewichten);
                $gewichtRange = $minGewicht == $maxGewicht
                    ? "{$minGewicht}kg"
                    : "{$minGewicht}-{$maxGewicht}kg";
            }
        }

        // Build title: "Jeugd M 9-10j 30-33kg"
        $parts = [$lk];
        if ($geslachtLabel) {
            $parts[] = $geslachtLabel;
        }
        if ($leeftijdRange) {
            $parts[] = $leeftijdRange;
        }
        if ($gewichtRange) {
            $parts[] = $gewichtRange;
        }

        return implode(' ', $parts);
    }

    /**
     * Convert leeftijdsklasse label to config key
     */
    private function leeftijdsklasseToConfigKey(string $leeftijdsklasse): ?string
    {
        // Map old JBN labels to config keys
        $mapping = [
            "Mini's" => 'minis',
            'A-pupillen' => 'a_pupillen',
            'B-pupillen' => 'b_pupillen',
            'Dames -15' => 'dames_15',
            'Heren -15' => 'heren_15',
            'Dames -18' => 'dames_18',
            'Heren -18' => 'heren_18',
            'Dames' => 'dames',
            'Heren' => 'heren',
            // Also support direct config keys
            'minis' => 'minis',
            'a_pupillen' => 'a_pupillen',
            'b_pupillen' => 'b_pupillen',
            'dames_15' => 'dames_15',
            'heren_15' => 'heren_15',
            'dames_18' => 'dames_18',
            'heren_18' => 'heren_18',
            'dames' => 'dames',
            'heren' => 'heren',
        ];

        return $mapping[$leeftijdsklasse] ?? null;
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
     * Recalculate all judoka codes for tournament
     * Order depends on toernooi settings:
     * - gebruik_gewichtsklassen ON: Leeftijd → Gewichtsklasse → Band (laag→hoog)
     * - gebruik_gewichtsklassen OFF + gewicht_band: Leeftijd → Werkelijk gewicht → Band
     * - gebruik_gewichtsklassen OFF + band_gewicht: Leeftijd → Band → Werkelijk gewicht
     */
    public function herberekenJudokaCodes(Toernooi $toernooi): int
    {
        // Default to true if null (for backwards compatibility)
        $gebruikGewichtsklassen = $toernooi->gebruik_gewichtsklassen === null ? true : $toernooi->gebruik_gewichtsklassen;
        $volgorde = $toernooi->judoka_code_volgorde ?? 'gewicht_band';

        // Band order: low to high (wit first) - always used now
        $bandOrderLowToHigh = "CASE band
            WHEN 'wit' THEN 0
            WHEN 'geel' THEN 1
            WHEN 'oranje' THEN 2
            WHEN 'groen' THEN 3
            WHEN 'blauw' THEN 4
            WHEN 'bruin' THEN 5
            WHEN 'zwart' THEN 6
            ELSE 7 END";

        $query = $toernooi->judokas()
            ->orderBy('leeftijdsklasse');

        if ($gebruikGewichtsklassen) {
            // Gewichtsklassen AAN: Leeftijd → Gewichtsklasse → Band (laag→hoog) → Geslacht
            $query->orderBy('gewichtsklasse')
                  ->orderByRaw($bandOrderLowToHigh)
                  ->orderByRaw("CASE geslacht WHEN 'M' THEN 1 WHEN 'V' THEN 2 ELSE 3 END");
        } elseif ($volgorde === 'band_gewicht') {
            // Gewichtsklassen UIT + band_gewicht: Leeftijd → Band → Werkelijk gewicht → Geslacht
            $query->orderByRaw($bandOrderLowToHigh)
                  ->orderBy('gewicht')
                  ->orderByRaw("CASE geslacht WHEN 'M' THEN 1 WHEN 'V' THEN 2 ELSE 3 END");
        } else {
            // Gewichtsklassen UIT + gewicht_band: Leeftijd → Werkelijk gewicht → Band → Geslacht
            $query->orderBy('gewicht')
                  ->orderByRaw($bandOrderLowToHigh)
                  ->orderByRaw("CASE geslacht WHEN 'M' THEN 1 WHEN 'V' THEN 2 ELSE 3 END");
        }

        $judokas = $query->orderBy('naam')->get();

        $vorigeCategorie = null;
        $volgnummer = 0;
        $bijgewerkt = 0;

        foreach ($judokas as $judoka) {
            // Create category key for volgnummer reset
            $categorie = "{$judoka->leeftijdsklasse}|{$judoka->gewichtsklasse}|{$judoka->geslacht}";

            if ($categorie !== $vorigeCategorie) {
                $volgnummer = 1;
                $vorigeCategorie = $categorie;
            } else {
                $volgnummer++;
            }

            $nieuweCode = $judoka->berekenJudokaCode($volgnummer);
            if ($judoka->judoka_code !== $nieuweCode) {
                $judoka->update(['judoka_code' => $nieuweCode]);
                $bijgewerkt++;
            }
        }

        return $bijgewerkt;
    }
}
