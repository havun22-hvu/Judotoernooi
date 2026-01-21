<?php

namespace App\Services;

use App\Enums\Band;
use App\Enums\Geslacht;
use App\Models\Club;
use App\Models\Coach;
use App\Models\CoachKaart;
use App\Models\Judoka;
use App\Models\Toernooi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportService
{
    private PouleIndelingService $pouleIndelingService;
    private array $gewichtsklassenConfig = [];

    public function __construct(PouleIndelingService $pouleIndelingService)
    {
        $this->pouleIndelingService = $pouleIndelingService;
    }

    /**
     * Analyse CSV data and detect column mapping
     * Returns detected mapping + warnings + preview data
     */
    public function analyseerCsvData(array $header, array $data): array
    {
        $verwachteVelden = [
            'naam' => ['naam', 'name', 'volledige naam', 'judoka', 'deelnemer'],
            'club' => ['club', 'vereniging', 'sportclub', 'judoclub', 'judoschool', 'school', 'dojo'],
            'geboortejaar' => ['geboortejaar', 'jaar', 'geb.jaar', 'birth year', 'geb'],
            'geslacht' => ['geslacht', 'gender', 'sex', 'm/v', 'jongen/meisje'],
            'gewicht' => ['gewicht', 'weight', 'kg', 'gewicht kg'],
            'band' => ['band', 'gordel', 'belt', 'kyu', 'graad'],
            'gewichtsklasse' => ['gewichtsklasse', 'klasse', 'categorie', 'weight class'],
            'telefoon' => ['telefoon', 'tel', 'phone', 'mobiel', 'gsm', 'telefoonnummer', 'mobile'],
        ];

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
                    if (!is_numeric($val) || (int)$val < 1950 || (int)$val > date('Y')) {
                        return "Verwacht jaren (1950-" . date('Y') . "), gevonden: " . $val;
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
     */
    public function importeerDeelnemers(Toernooi $toernooi, array $data, array $kolomMapping = []): array
    {
        // Load preset config for classification
        $this->gewichtsklassenConfig = $toernooi->getAlleGewichtsklassen();

        return DB::transaction(function () use ($toernooi, $data, $kolomMapping) {
            $resultaat = [
                'geimporteerd' => 0,
                'overgeslagen' => 0,
                'fouten' => [],
                'codes_bijgewerkt' => 0,
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
                'telefoon' => 'telefoon',
            ], $kolomMapping);

            foreach ($data as $index => $rij) {
                $rijNummer = $index + 2; // +2 for header and 0-index

                try {
                    $judoka = $this->verwerkRij($toernooi, $rij, $mapping);
                    if ($judoka) {
                        $resultaat['geimporteerd']++;
                    } else {
                        $resultaat['overgeslagen']++;
                    }
                } catch (\Exception $e) {
                    $naam = $this->getWaarde($rij, $mapping['naam']) ?? '(geen naam)';
                    $leesbareFout = $this->maakFoutLeesbaar($e->getMessage(), $naam);
                    $resultaat['fouten'][] = "Rij {$rijNummer} ({$naam}): {$leesbareFout}";
                    \Log::error("Import fout rij {$rijNummer}: {$e->getMessage()}", ['exception' => $e->getTraceAsString()]);
                }
            }

            // Create coaches and coach cards for clubs without one
            $resultaat['coaches_aangemaakt'] = $this->maakCoachesVoorClubs($toernooi);

            return $resultaat;
        });
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
        $geslacht = $this->getWaarde($rij, $mapping['geslacht']);
        $geboortejaar = $this->getWaarde($rij, $mapping['geboortejaar']);
        $telefoon = $this->getWaarde($rij, $mapping['telefoon']);

        // Skip rows without name (name is required)
        if (empty($naam)) {
            return null;
        }

        // Track if judoka has incomplete data
        // Weight is not required if weight class is provided
        $isOnvolledig = empty($geboortejaar) || empty($geslacht) || (empty($gewicht) && empty($gewichtsklasseRaw));

        // Parse and validate data
        $naam = $this->normaliseerNaam($naam);
        $geboortejaar = !empty($geboortejaar) ? $this->parseGeboortejaar($geboortejaar) : null;
        $geslacht = $this->parseGeslacht($geslacht);
        $band = $this->parseBand($band);
        $gewicht = $this->parseGewicht($gewicht);

        // Get or create club
        $club = null;
        if (!empty($clubNaam)) {
            $club = Club::findOrCreateByName($clubNaam);
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
            // gewichtsklasse is null for variable categories (max_kg_verschil > 0)
            $gewichtsklasse = $classificatie['gewichtsklasse'];
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

        if ($bestaande) {
            // Update existing judoka instead of creating new one
            $bestaande->update([
                'club_id' => $club?->id,
                'geslacht' => $geslacht,
                'band' => $band,
                'gewicht' => $gewicht,
                'leeftijdsklasse' => $leeftijdsklasse,
                'categorie_key' => $categorieKey,
                'sort_categorie' => $sortCategorie,
                'gewichtsklasse' => $gewichtsklasse,
                'is_onvolledig' => $isOnvolledig,
                'telefoon' => $this->parseTelefoon($telefoon),
            ]);
            return null; // Return null to count as skipped
        }

        // Create judoka (judoka_code will be calculated after full import)
        $judoka = Judoka::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club?->id,
            'naam' => $naam,
            'geboortejaar' => $geboortejaar,
            'geslacht' => $geslacht,
            'band' => $band,
            'gewicht' => $gewicht,
            'leeftijdsklasse' => $leeftijdsklasse,
            'categorie_key' => $categorieKey,
            'sort_categorie' => $sortCategorie,
            'gewichtsklasse' => $gewichtsklasse,
            'is_onvolledig' => $isOnvolledig,
            'telefoon' => $this->parseTelefoon($telefoon),
        ]);

        return $judoka;
    }

    /**
     * Get value from row by column name (case-insensitive)
     */
    private function getWaarde(array $rij, string $kolom): mixed
    {
        // If column is numeric index
        if (is_numeric($kolom)) {
            return $rij[(int)$kolom] ?? null;
        }

        // Find by column name (case-insensitive)
        foreach ($rij as $key => $value) {
            if (strtolower($key) === strtolower($kolom)) {
                return $value;
            }
        }

        return $rij[$kolom] ?? null;
    }

    /**
     * Normalize name (proper case, trim)
     */
    private function normaliseerNaam(string $naam): string
    {
        $naam = trim($naam);

        // Handle common name prefixes
        $prefixen = ['van', 'de', 'den', 'der', 'het', 'ten', 'ter', 'vd'];
        $woorden = explode(' ', $naam);
        $result = [];

        foreach ($woorden as $woord) {
            $lowerWoord = strtolower($woord);
            if (in_array($lowerWoord, $prefixen)) {
                $result[] = $lowerWoord;
            } else {
                $result[] = ucfirst($lowerWoord);
            }
        }

        return implode(' ', $result);
    }

    /**
     * Parse birth year from various formats
     */
    private function parseGeboortejaar(mixed $waarde): int
    {
        if (is_numeric($waarde)) {
            $jaar = (int)$waarde;
            // Handle 2-digit years
            if ($jaar < 100) {
                $jaar = ($jaar > 50) ? 1900 + $jaar : 2000 + $jaar;
            }
            return $jaar;
        }

        // Try to parse date string
        if (preg_match('/(\d{4})/', (string)$waarde, $matches)) {
            return (int)$matches[1];
        }

        throw new \InvalidArgumentException("Ongeldig geboortejaar: {$waarde}");
    }

    /**
     * Parse gender from various formats
     */
    private function parseGeslacht(mixed $waarde): string
    {
        $geslacht = Geslacht::fromString((string)$waarde);
        return $geslacht?->value ?? 'M';
    }

    /**
     * Parse belt color
     */
    private function parseBand(mixed $waarde): string
    {
        if (empty($waarde)) {
            return 'Wit (6e kyu)';
        }

        $band = Band::fromString((string)$waarde);
        // Sla op met kyu notatie voor consistentie
        return $band ? $band->labelMetKyu() : trim((string)$waarde);
    }

    /**
     * Parse weight class from CSV (handles Excel formatting like '-38 kg)
     */
    private function parseGewichtsklasse(mixed $waarde): ?string
    {
        if (empty($waarde)) {
            return null;
        }

        $klasse = trim((string)$waarde);

        // Remove leading apostrophe (Excel text format)
        $klasse = ltrim($klasse, "'");

        // Remove 'kg' suffix and extra spaces
        $klasse = preg_replace('/\s*kg\s*$/i', '', $klasse);
        $klasse = trim($klasse);

        if (empty($klasse)) {
            return null;
        }

        return $klasse;
    }

    /**
     * Parse weight (handle comma/point decimal separator)
     */
    private function parseGewicht(mixed $waarde): ?float
    {
        if (empty($waarde)) {
            return null;
        }

        // Replace comma with point
        $waarde = str_replace(',', '.', (string)$waarde);

        // Extract numeric value
        if (preg_match('/([0-9.]+)/', $waarde, $matches)) {
            return (float)$matches[1];
        }

        return null;
    }

    /**
     * Parse phone number (clean up formatting)
     */
    private function parseTelefoon(mixed $waarde): ?string
    {
        if (empty($waarde)) {
            return null;
        }

        // Remove all non-numeric characters except +
        $telefoon = preg_replace('/[^0-9+]/', '', (string)$waarde);

        // Convert 06 to +316
        if (str_starts_with($telefoon, '06')) {
            $telefoon = '+31' . substr($telefoon, 1);
        }
        // Convert 0031 to +31
        elseif (str_starts_with($telefoon, '0031')) {
            $telefoon = '+31' . substr($telefoon, 4);
        }

        return $telefoon ?: null;
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
            $gewichtsklasse = null;
            if ($maxKgVerschil == 0) {
                $gewichtsklasse = $this->bepaalGewichtsklasseUitConfig($gewicht ?? 0, $config, $tolerantie);
            }

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
     * Get band niveau (0 = wit, 6 = zwart)
     */
    private function getBandNiveau(string $band): int
    {
        $band = strtolower(trim($band));
        return match($band) {
            'wit', 'white' => 0,
            'geel', 'yellow' => 1,
            'oranje', 'orange' => 2,
            'groen', 'green' => 3,
            'blauw', 'blue' => 4,
            'bruin', 'brown' => 5,
            'zwart', 'black' => 6,
            default => 0,
        };
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
        if (preg_match('/[+-]?(\d+)/', $klasse, $matches)) {
            return (float) $matches[1];
        }
        return null;
    }

    /**
     * Create coaches and coach cards for clubs that don't have one yet
     */
    private function maakCoachesVoorClubs(Toernooi $toernooi): int
    {
        $aangemaakt = 0;

        // Get all clubs with judokas in this tournament
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
            return 'Ongeldig geboortejaar - verwacht 4 cijfers (bijv. 2015)';
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
}
