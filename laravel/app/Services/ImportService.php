<?php

namespace App\Services;

use App\Enums\Band;
use App\Enums\Geslacht;
use App\Exceptions\ImportException;
use App\Models\Club;
use App\Models\Coach;
use App\Models\CoachKaart;
use App\Models\Judoka;
use App\Models\Toernooi;
use App\Services\Import\ValueParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImportService
{
    private PouleIndelingService $pouleIndelingService;
    private array $gewichtsklassenConfig = [];

    /** @var array<string, Club|null> Cache for clubs during import (prevents N+1 queries) */
    private array $clubCache = [];

    /** @var int|null Cached organisator ID for import session */
    private ?int $cachedOrganisatorId = null;

    public function __construct(PouleIndelingService $pouleIndelingService)
    {
        $this->pouleIndelingService = $pouleIndelingService;
    }

    /**
     * Analyse CSV data and detect column mapping
     * Returns detected mapping + warnings + preview data
     *
     * @param array $header CSV header row
     * @param array $data CSV data rows
     * @param bool $heeftVasteGewichtsklassen Whether to detect gewichtsklasse column (only for tournaments with fixed weight classes)
     */
    public function analyseerCsvData(array $header, array $data, bool $heeftVasteGewichtsklassen = true): array
    {
        $verwachteVelden = [
            'naam' => ['naam', 'name', 'volledige naam', 'judoka', 'deelnemer', 'voornaam', 'achternaam', 'first name', 'last name'],
            'club' => ['club', 'vereniging', 'sportclub', 'judoclub', 'judoschool', 'school', 'dojo'],
            'geboortejaar' => ['geboortejaar', 'geboortedatum', 'jaar', 'geb.jaar', 'birth year', 'geb', 'birthdate', 'dob'],
            'geslacht' => ['geslacht', 'gender', 'sex', 'm/v', 'jongen/meisje'],
            'gewicht' => ['gewicht', 'weight', 'kg', 'gewicht kg'],
            'band' => ['band', 'gordel', 'belt', 'kyu', 'graad'],
            'jbn_lidnummer' => ['jbn', 'jbn nummer', 'jbn lidnummer', 'lidnummer', 'bondsnummer', 'jbn nr', 'jbn_lidnummer'],
        ];

        // Gewichtsklasse veld alleen detecteren als toernooi vaste gewichtsklassen heeft
        if ($heeftVasteGewichtsklassen) {
            $verwachteVelden['gewichtsklasse'] = ['gewichtsklasse', 'klasse', 'categorie', 'weight class'];
        }

        $detectie = [];
        $headerLower = array_map('strtolower', $header);

        foreach ($verwachteVelden as $veld => $zoektermen) {
            $gevonden = null;
            $gevondenIndex = null;

            // First try exact match, then partial match
            foreach ([true, false] as $exactMatch) {
                foreach ($zoektermen as $zoekterm) {
                    foreach ($headerLower as $index => $kolomNaam) {
                        $match = $exactMatch
                            ? ($kolomNaam === $zoekterm)
                            : str_contains($kolomNaam, $zoekterm);
                        if ($match) {
                            $gevonden = $header[$index];
                            $gevondenIndex = $index;
                            break 3;
                        }
                    }
                }
            }

            $detectie[$veld] = [
                'csv_kolom' => $gevonden,
                'csv_index' => $gevondenIndex,
                'waarschuwing' => null,
            ];

            // Validate detected column with actual data
            if ($gevondenIndex !== null && count($data) > 0) {
                $waarschuwing = $this->valideerKolomData($veld, $data, $gevondenIndex);
                if ($waarschuwing) {
                    $detectie[$veld]['waarschuwing'] = $waarschuwing;
                }
            }
        }

        return [
            'header' => $header,
            'detectie' => $detectie,
            'preview_data' => array_slice($data, 0, 5),
            'totaal_rijen' => count($data),
        ];
    }

    /**
     * Validate if column data matches expected field type
     */
    private function valideerKolomData(string $veld, array $data, int $kolomIndex): ?string
    {
        $samples = array_slice($data, 0, 10);
        $values = array_column($samples, $kolomIndex);
        $values = array_filter($values, fn($v) => $v !== null && $v !== '');

        if (empty($values)) {
            return 'Kolom bevat geen data';
        }

        switch ($veld) {
            case 'geboortejaar':
                foreach ($values as $val) {
                    $val = trim((string) $val);
                    if ($val === '') continue;
                    try {
                        $jaar = ValueParser::parseGeboortejaar($val);
                        if ($jaar < 1950 || $jaar > (int) date('Y')) {
                            return "Ongeldig jaar: {$jaar} (uit: {$val})";
                        }
                    } catch (\InvalidArgumentException $e) {
                        return "Verwacht jaar of datum, gevonden: " . $val;
                    }
                }
                break;

            case 'geslacht':
                foreach ($values as $val) {
                    $val = strtoupper(trim($val));
                    if (!in_array($val, ['M', 'V', 'J', 'JONGEN', 'MEISJE', 'MAN', 'VROUW'])) {
                        return "Verwacht M/V, gevonden: " . $val;
                    }
                }
                break;

            case 'gewicht':
                foreach ($values as $val) {
                    $num = str_replace(',', '.', $val);
                    if (!is_numeric($num) || (float)$num < 10 || (float)$num > 200) {
                        return "Verwacht gewicht (10-200), gevonden: " . $val;
                    }
                }
                break;
        }

        return null;
    }

    /**
     * Import participants from array data (CSV/Excel)
     *
     * @throws ImportException On critical database errors
     */
    public function importeerDeelnemers(Toernooi $toernooi, array $data, array $kolomMapping = []): array
    {
        if (empty($data)) {
            return [
                'geimporteerd' => 0,
                'overgeslagen' => 0,
                'fouten' => ['Geen data om te importeren'],
                'codes_bijgewerkt' => 0,
            ];
        }

        // Load preset config for classification
        $this->gewichtsklassenConfig = $toernooi->getAlleGewichtsklassen();

        try {
            return DB::transaction(function () use ($toernooi, $data, $kolomMapping) {
                $resultaat = [
                    'geimporteerd' => 0,
                    'overgeslagen' => 0,
                    'fouten' => [],
                    'codes_bijgewerkt' => 0,
                    'zonder_club' => 0,
                ];

                // Default column mapping
                $mapping = array_merge([
                    'naam' => 'naam',
                    'band' => 'band',
                    'club' => 'club',
                    'gewicht' => 'gewicht',
                    'gewichtsklasse' => 'gewichtsklasse',
                    'geslacht' => 'geslacht',
                    'geboortejaar' => 'geboortejaar',
                    'jbn_lidnummer' => 'jbn_lidnummer',
                ], $kolomMapping);

                foreach ($data as $index => $rij) {
                    $rijNummer = $index + 2; // +2 for header and 0-index

                    // Skip completely empty rows
                    if ($this->isEmptyRow($rij)) {
                        continue;
                    }

                    try {
                        $judoka = $this->verwerkRij($toernooi, $rij, $mapping);
                        if ($judoka) {
                            $resultaat['geimporteerd']++;
                            if (!$judoka->club_id) {
                                $resultaat['zonder_club']++;
                            }
                        } else {
                            $resultaat['overgeslagen']++;
                        }
                    } catch (\Exception $e) {
                        $naam = $this->getWaarde($rij, $mapping['naam']) ?? '(geen naam)';
                        $leesbareFout = $this->maakFoutLeesbaar($e->getMessage(), $naam);
                        $resultaat['fouten'][] = "Rij {$rijNummer} ({$naam}): {$leesbareFout}";
                        Log::warning("Import fout rij {$rijNummer}", [
                            'naam' => $naam,
                            'error' => $e->getMessage(),
                            'toernooi_id' => $toernooi->id,
                        ]);
                    }
                }

                // Create coaches and coach cards for clubs without one
                $resultaat['coaches_aangemaakt'] = $this->maakCoachesVoorClubs($toernooi);

                // Log summary
                Log::info('Import completed', [
                    'toernooi_id' => $toernooi->id,
                    'imported' => $resultaat['geimporteerd'],
                    'skipped' => $resultaat['overgeslagen'],
                    'errors' => count($resultaat['fouten']),
                ]);

                return $resultaat;
            }, 3); // 3 retries on deadlock
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Import database error', [
                'toernooi_id' => $toernooi->id,
                'error' => $e->getMessage(),
            ]);
            throw ImportException::databaseError($e->getMessage());
        }
    }

    /**
     * Check if a row is completely empty.
     */
    private function isEmptyRow(array $rij): bool
    {
        foreach ($rij as $value) {
            if ($value !== null && trim((string)$value) !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * Process a single row of import data
     */
    private function verwerkRij(Toernooi $toernooi, array $rij, array $mapping): ?Judoka
    {
        // Get values from row using mapping
        $naam = $this->getWaarde($rij, $mapping['naam']);
        $band = $this->getWaarde($rij, $mapping['band']);
        $clubNaam = $this->getWaarde($rij, $mapping['club']);
        $gewicht = $this->getWaarde($rij, $mapping['gewicht']);
        $gewichtsklasseRaw = $this->getWaarde($rij, $mapping['gewichtsklasse']);
        $geslachtRaw = $this->getWaarde($rij, $mapping['geslacht']);
        $geboortejaarRaw = $this->getWaarde($rij, $mapping['geboortejaar']);
        $jbnLidnummer = trim($this->getWaarde($rij, $mapping['jbn_lidnummer']) ?? '');
        // Skip rows without name (name is required)
        if (empty($naam)) {
            return null;
        }

        // Track if judoka has incomplete data
        // Weight is not required if weight class is provided
        $isOnvolledig = empty($geboortejaarRaw) || empty($geslachtRaw) || (empty($gewicht) && empty($gewichtsklasseRaw));

        // Collect warnings during parsing
        $warnings = [];

        // Parse and validate data
        $naam = $this->normaliseerNaam($naam);
        $geboortejaar = !empty($geboortejaarRaw) ? $this->parseGeboortejaar($geboortejaarRaw) : null;
        $geslacht = $this->parseGeslacht($geslachtRaw);
        $band = $this->parseBand($band);
        $gewicht = $this->parseGewicht($gewicht);

        // Check for parsing issues and unusual values
        if (!empty($geslachtRaw) && !Geslacht::fromString((string)$geslachtRaw)) {
            $warnings[] = "Geslacht '{$geslachtRaw}' niet herkend, standaard M gebruikt";
        }

        if ($gewicht !== null) {
            if ($gewicht > 100) {
                $warnings[] = "Gewicht {$gewicht} kg lijkt hoog";
            } elseif ($gewicht < 15) {
                $warnings[] = "Gewicht {$gewicht} kg lijkt laag";
            }
        }

        if ($geboortejaar !== null) {
            $leeftijd = date('Y') - $geboortejaar;
            if ($leeftijd < 4) {
                $warnings[] = "Leeftijd {$leeftijd} jaar erg jong";
            } elseif ($leeftijd > 50) {
                $warnings[] = "Leeftijd {$leeftijd} jaar erg hoog";
            }
        }

        // Get or create club (scoped to organisator, with caching to prevent N+1)
        $club = null;
        if (!empty($clubNaam)) {
            $club = $this->getOrCreateClubCached($clubNaam, $toernooi);
        }

        // Calculate age class using preset config (not hardcoded enum)
        $leeftijdsklasse = null;
        $categorieKey = null;
        $sortCategorie = 99;
        $gewichtsklasse = 'onbekend';
        $tolerantie = $toernooi->gewicht_tolerantie ?? 0.5;

        if ($geboortejaar && !empty($this->gewichtsklassenConfig)) {
            $leeftijd = ($toernooi->datum?->year ?? date('Y')) - $geboortejaar;
            $bandNiveau = $this->getBandNiveau($band);

            // Classify using preset config
            $classificatie = $this->classificeerJudoka($leeftijd, $geslacht, $bandNiveau, $gewicht, $tolerantie);
            $leeftijdsklasse = $classificatie['label'];
            $categorieKey = $classificatie['configKey'];
            $sortCategorie = $classificatie['sortCategorie'];
            // gewichtsklasse is never null - use 'Onbekend' or 'Variabel' as fallback
            $gewichtsklasse = $classificatie['gewichtsklasse'] ?? 'Onbekend';
        } else {
            // No config or no birth year - use CSV weight class if provided
            if ($gewichtsklasseRaw) {
                $gewichtsklasse = $this->parseGewichtsklasse($gewichtsklasseRaw) ?? 'onbekend';

                // If no weight but weight class given, derive weight from class (use upper limit)
                if (!$gewicht && $gewichtsklasse !== 'onbekend') {
                    $gewicht = $this->gewichtVanKlasse($gewichtsklasse);
                }
            }
            // Set fallback leeftijdsklasse based on birth year
            if ($geboortejaar) {
                $leeftijd = (date('Y')) - $geboortejaar;
                if ($leeftijd <= 6) {
                    $leeftijdsklasse = "Mini's";
                } elseif ($leeftijd <= 10) {
                    $leeftijdsklasse = 'Jeugd';
                } elseif ($leeftijd <= 14) {
                    $leeftijdsklasse = 'Aspiranten';
                } else {
                    $leeftijdsklasse = 'Senioren';
                }
            } else {
                $leeftijdsklasse = 'Onbekend';
            }
        }

        // Check for duplicate (same name + birth year + tournament) - case insensitive
        $query = Judoka::where('toernooi_id', $toernooi->id)
            ->whereRaw('LOWER(naam) = ?', [strtolower($naam)]);

        if ($geboortejaar) {
            $query->where('geboortejaar', $geboortejaar);
        } else {
            $query->whereNull('geboortejaar');
        }

        $bestaande = $query->first();

        // Convert warnings array to string (or null if empty)
        $importWarnings = !empty($warnings) ? implode(' | ', $warnings) : null;

        // Bepaal import_status:
        // - 'niet_in_categorie' als judoka niet past in een leeftijdscategorie (te oud/jong)
        // - 'te_corrigeren' als er warnings zijn
        // - 'compleet' anders
        $importStatus = 'compleet';
        if ($leeftijdsklasse === 'Onbekend' || empty($leeftijdsklasse)) {
            $importStatus = 'niet_in_categorie';
            // Add warning for portal display
            $warnings[] = $this->bepaalCategorieProbleem($geboortejaar, $toernooi);
            $importWarnings = implode(' | ', array_filter($warnings));
        } elseif (!empty($warnings)) {
            $importStatus = 'te_corrigeren';
        }

        if ($bestaande && $bestaande->club_id === $club?->id) {
            // Same name + birth year + same club = same person, update
            $bestaande->update([
                'geslacht' => $geslacht,
                'band' => $band,
                'jbn_lidnummer' => $jbnLidnummer ?: $bestaande->jbn_lidnummer,
                'gewicht' => $gewicht,
                'leeftijdsklasse' => $leeftijdsklasse,
                'categorie_key' => $categorieKey,
                'sort_categorie' => $sortCategorie,
                'gewichtsklasse' => $gewichtsklasse,
                'is_onvolledig' => $isOnvolledig,
                'import_warnings' => $importWarnings,
                'import_status' => $importStatus,
            ]);
            return null; // Return null to count as skipped
        }

        if ($bestaande) {
            // Same name + birth year but different club = different person, warn
            $clubNaam = $bestaande->club?->naam ?? 'onbekend';
            $warnings[] = "Naamgenoot: er is al een {$naam} ({$geboortejaar}) van {$clubNaam}";
            $importWarnings = implode(' | ', array_filter($warnings));
            if ($importStatus === 'compleet') {
                $importStatus = 'te_corrigeren';
            }
        }

        // Create judoka (judoka_code will be calculated after full import)
        $judoka = Judoka::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club?->id,
            'naam' => $naam,
            'geboortejaar' => $geboortejaar,
            'geslacht' => $geslacht,
            'band' => $band,
            'jbn_lidnummer' => $jbnLidnummer ?: null,
            'gewicht' => $gewicht,
            'leeftijdsklasse' => $leeftijdsklasse,
            'categorie_key' => $categorieKey,
            'sort_categorie' => $sortCategorie,
            'gewichtsklasse' => $gewichtsklasse,
            'is_onvolledig' => $isOnvolledig,
            'import_warnings' => $importWarnings,
            'import_status' => $importStatus,
        ]);

        return $judoka;
    }

    /**
     * Get value from row by column name (case-insensitive)
     * Supports comma-separated indices for multi-column fields.
     */
    private function getWaarde(array $rij, string $kolom): mixed
    {
        return ValueParser::getWaarde($rij, $kolom);
    }

    /**
     * Normalize name (proper case, trim)
     *
     * @see ValueParser::normaliseerNaam()
     */
    public static function normaliseerNaam(string $naam): string
    {
        return ValueParser::normaliseerNaam($naam);
    }

    /**
     * Parse birth year from ANY format imaginable.
     *
     * @see ValueParser::parseGeboortejaar()
     */
    public static function parseGeboortejaar(mixed $waarde): int
    {
        return ValueParser::parseGeboortejaar($waarde);
    }

    /**
     * Parse gender from various formats
     *
     * @see ValueParser::parseGeslacht()
     */
    public static function parseGeslacht(mixed $waarde): string
    {
        return ValueParser::parseGeslacht($waarde);
    }

    /**
     * Parse belt color - returns lowercase base value
     *
     * @see ValueParser::parseBand()
     */
    public static function parseBand(mixed $waarde): string
    {
        return ValueParser::parseBand($waarde);
    }

    /**
     * Parse weight class from CSV (handles Excel formatting like '-38 kg)
     */
    private function parseGewichtsklasse(mixed $waarde): ?string
    {
        return ValueParser::parseGewichtsklasse($waarde);
    }

    /**
     * Parse weight (handle comma/point decimal separator)
     *
     * @see ValueParser::parseGewicht()
     */
    public static function parseGewicht(mixed $waarde): ?float
    {
        return ValueParser::parseGewicht($waarde);
    }

    /**
     * Classify a judoka into a category based on preset config
     * Returns array with: configKey, label, sortCategorie, gewichtsklasse
     */
    private function classificeerJudoka(int $leeftijd, string $geslacht, int $bandNiveau, ?float $gewicht, float $tolerantie): array
    {
        $geslacht = strtoupper($geslacht);
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

            // Auto-detect gender from label if geslacht=gemengd but label contains gender indicator
            if ($configGeslacht === 'GEMENGD') {
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

            // Match found!
            // Only determine gewichtsklasse if NOT variable (max_kg_verschil == 0)
            $maxKgVerschil = (float) ($config['max_kg_verschil'] ?? 0);
            $gewichtsklasse = 'Variabel'; // Default for variable categories
            if ($maxKgVerschil == 0) {
                $gewichtsklasse = $this->bepaalGewichtsklasseUitConfig($gewicht ?? 0, $config, $tolerantie) ?? 'Onbekend';
            }

            return [
                'configKey' => $key,
                'label' => !empty($config['label']) ? $config['label'] : $key,
                'sortCategorie' => $sortCategorie,
                'gewichtsklasse' => $gewichtsklasse,
            ];
        }

        // No match found - still import with 'Onbekend' values
        return [
            'configKey' => null,
            'label' => 'Onbekend',
            'sortCategorie' => 99,
            'gewichtsklasse' => 'Onbekend',
        ];
    }

    /**
     * Check if band niveau matches the band filter
     */
    private function voldoetAanBandFilter(int $bandNiveau, string $filter): bool
    {
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

        return true;
    }

    /**
     * Get band niveau voor classificatie (0 = wit/beginner, 6 = zwart/expert)
     *
     * VOLGORDE beginner → expert:
     *   wit(0) → geel(1) → oranje(2) → groen(3) → blauw(4) → bruin(5) → zwart(6)
     */
    private function getBandNiveau(string $band): int
    {
        $bandEnum = Band::fromString($band);
        return $bandEnum ? $bandEnum->niveau() : 0;
    }

    /**
     * Determine weight class from config
     */
    private function bepaalGewichtsklasseUitConfig(float $gewicht, array $config, float $tolerantie): ?string
    {
        if ($gewicht <= 0) {
            return null;
        }

        $klassen = $config['gewichten'] ?? [];
        if (empty($klassen)) {
            return null;
        }

        foreach ($klassen as $klasse) {
            $klasse = (int) $klasse;
            if ($klasse > 0) {
                // Plus category (minimum weight)
                return "+{$klasse}";
            } else {
                // Minus category (maximum weight)
                $limiet = abs($klasse);
                if ($gewicht <= $limiet + $tolerantie) {
                    return "{$klasse}";
                }
            }
        }

        // Heavier than all minus categories
        $laatsteKlasse = end($klassen);
        if ($laatsteKlasse > 0) {
            return "+{$laatsteKlasse}";
        }
        return "+" . abs($laatsteKlasse);
    }

    /**
     * Derive weight from weight class
     * -34 => 34.0, +63 => 63.0
     */
    private function gewichtVanKlasse(string $klasse): ?float
    {
        return ValueParser::gewichtVanKlasse($klasse);
    }

    /**
     * Create coaches and coach cards for clubs that don't have one yet
     */
    private function maakCoachesVoorClubs(Toernooi $toernooi): int
    {
        $aangemaakt = 0;

        // Only create wedstrijdcoaches for clubs that have judokas in this tournament
        $clubIds = Judoka::where('toernooi_id', $toernooi->id)
            ->whereNotNull('club_id')
            ->distinct()
            ->pluck('club_id');

        foreach ($clubIds as $clubId) {
            $club = Club::find($clubId);
            if (!$club) continue;

            // Check if club already has a coach for this tournament
            $bestaandeCoach = Coach::where('club_id', $clubId)
                ->where('toernooi_id', $toernooi->id)
                ->first();

            if (!$bestaandeCoach) {
                // Create coach
                $coach = Coach::create([
                    'club_id' => $clubId,
                    'toernooi_id' => $toernooi->id,
                    'naam' => 'Coach ' . $club->naam,
                ]);

                // Create 1 standard coach card
                CoachKaart::create([
                    'toernooi_id' => $toernooi->id,
                    'club_id' => $clubId,
                ]);

                $aangemaakt++;
            } else {
                // Ensure at least 1 coach card exists
                $bestaandeKaart = CoachKaart::where('club_id', $clubId)
                    ->where('toernooi_id', $toernooi->id)
                    ->first();

                if (!$bestaandeKaart) {
                    CoachKaart::create([
                        'toernooi_id' => $toernooi->id,
                        'club_id' => $clubId,
                    ]);
                }
            }
        }

        return $aangemaakt;
    }

    /**
     * Bepaal waarom judoka niet in categorie past (voor import warnings)
     */
    private function bepaalCategorieProbleem(?int $geboortejaar, Toernooi $toernooi): string
    {
        if (!$geboortejaar) {
            return 'Geboortejaar ontbreekt';
        }

        $toernooiJaar = $toernooi->datum?->year ?? (int) date('Y');
        $leeftijd = $toernooiJaar - $geboortejaar;

        // Zoek max leeftijd uit config
        $maxLeeftijd = 0;
        foreach ($this->gewichtsklassenConfig as $cat) {
            $catMax = $cat['max_leeftijd'] ?? 99;
            if ($catMax > $maxLeeftijd && $catMax < 99) {
                $maxLeeftijd = $catMax;
            }
        }

        if ($leeftijd > $maxLeeftijd) {
            return "Te oud ({$leeftijd} jaar, max {$maxLeeftijd})";
        }

        return "Past niet in categorie (leeftijd {$leeftijd})";
    }

    /**
     * Convert technical error messages to human-readable Dutch
     */
    private function maakFoutLeesbaar(string $error, string $naam): string
    {
        // Database constraint errors
        if (str_contains($error, 'cannot be null')) {
            if (str_contains($error, 'leeftijdsklasse')) {
                return 'Kan leeftijdsklasse niet bepalen - controleer geboortejaar';
            }
            if (str_contains($error, 'geslacht')) {
                return 'Geslacht ontbreekt of ongeldig (verwacht M/V)';
            }
            if (str_contains($error, 'gewicht')) {
                return 'Gewicht ontbreekt';
            }
            if (str_contains($error, 'naam')) {
                return 'Naam ontbreekt';
            }
            return 'Verplicht veld ontbreekt';
        }

        // Duplicate entry
        if (str_contains($error, 'Duplicate entry') || str_contains($error, 'UNIQUE constraint')) {
            return 'Dubbele invoer - judoka bestaat al';
        }

        // Invalid data format
        if (str_contains($error, 'Ongeldig geboortejaar')) {
            return 'Ongeldig geboortejaar - verwacht jaar (2015) of datum (1/1/2015)';
        }

        // Data too long
        if (str_contains($error, 'Data too long')) {
            return 'Tekst te lang voor database veld';
        }

        // Generic fallback - shorten technical message
        if (strlen($error) > 100) {
            // Extract the useful part before SQL details
            if (preg_match('/^(.+?)(?:\s*\(Connection:|SQLSTATE)/s', $error, $matches)) {
                return trim($matches[1]);
            }
            return substr($error, 0, 100) . '...';
        }

        return $error;
    }

    /**
     * Get or create club with caching to prevent N+1 queries during import.
     */
    private function getOrCreateClubCached(string $clubNaam, Toernooi $toernooi): ?Club
    {
        // Normalize club name for cache key
        $cacheKey = mb_strtolower(trim($clubNaam));

        // Return from cache if already looked up
        if (array_key_exists($cacheKey, $this->clubCache)) {
            return $this->clubCache[$cacheKey];
        }

        // Get organisator ID (cached for entire import session)
        if ($this->cachedOrganisatorId === null) {
            $this->cachedOrganisatorId = $toernooi->organisatoren()->first()?->id ?? 0;
        }

        // Find or create club
        $organisatorId = $this->cachedOrganisatorId ?: null;
        $club = Club::findOrCreateByName($clubNaam, $organisatorId);

        // Cache the result
        $this->clubCache[$cacheKey] = $club;

        return $club;
    }

    /**
     * Clear import session caches (call after import completes).
     */
    public function clearCache(): void
    {
        $this->clubCache = [];
        $this->cachedOrganisatorId = null;
        $this->gewichtsklassenConfig = [];
    }
}
