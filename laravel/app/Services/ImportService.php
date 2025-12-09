<?php

namespace App\Services;

use App\Enums\Band;
use App\Enums\Geslacht;
use App\Enums\Leeftijdsklasse;
use App\Models\Club;
use App\Models\Judoka;
use App\Models\Toernooi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportService
{
    private PouleIndelingService $pouleIndelingService;

    public function __construct(PouleIndelingService $pouleIndelingService)
    {
        $this->pouleIndelingService = $pouleIndelingService;
    }

    /**
     * Import participants from array data (CSV/Excel)
     */
    public function importeerDeelnemers(Toernooi $toernooi, array $data, array $kolomMapping = []): array
    {
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
                    $resultaat['fouten'][] = "Rij {$rijNummer}: {$e->getMessage()}";
                }
            }

            // Recalculate all judoka codes after import
            $resultaat['codes_bijgewerkt'] = $this->pouleIndelingService->herberekenJudokaCodes($toernooi);

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

        // Skip empty rows
        if (empty($naam) || empty($geboortejaar)) {
            return null;
        }

        // Parse and validate data
        $naam = $this->normaliseerNaam($naam);
        $geboortejaar = $this->parseGeboortejaar($geboortejaar);
        $geslacht = $this->parseGeslacht($geslacht);
        $band = $this->parseBand($band);
        $gewicht = $this->parseGewicht($gewicht);

        // Get or create club
        $club = null;
        if (!empty($clubNaam)) {
            $club = Club::findOrCreateByName($clubNaam);
        }

        // Calculate age class
        $leeftijd = date('Y') - $geboortejaar;
        $leeftijdsklasse = Leeftijdsklasse::fromLeeftijdEnGeslacht($leeftijd, $geslacht);

        // Always calculate weight class from actual weight if provided (takes precedence)
        // This ensures correct classification based on tolerance
        $tolerantie = $toernooi->gewicht_tolerantie ?? 0.5;
        if ($gewicht) {
            $gewichtsklasse = $this->bepaalGewichtsklasse($gewicht, $leeftijdsklasse, $tolerantie);
        } else {
            // No weight provided, use CSV weight class or unknown
            $gewichtsklasse = $this->parseGewichtsklasse($gewichtsklasseRaw) ?? 'onbekend';
        }

        // Check for duplicate (same name + birth year + tournament) - case insensitive
        $bestaande = Judoka::where('toernooi_id', $toernooi->id)
            ->whereRaw('LOWER(naam) = ?', [strtolower($naam)])
            ->where('geboortejaar', $geboortejaar)
            ->first();

        if ($bestaande) {
            // Update existing judoka instead of creating new one
            $bestaande->update([
                'club_id' => $club?->id,
                'geslacht' => $geslacht,
                'band' => $band,
                'gewicht' => $gewicht,
                'leeftijdsklasse' => $leeftijdsklasse->label(),
                'gewichtsklasse' => $gewichtsklasse,
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
            'leeftijdsklasse' => $leeftijdsklasse->label(),
            'gewichtsklasse' => $gewichtsklasse,
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
            return 'wit';
        }

        $band = Band::fromString((string)$waarde);
        return $band ? strtolower($band->label()) : strtolower(trim((string)$waarde));
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
     * Determine weight class based on weight, age category and tolerance
     *
     * Example with tolerance 0.5:
     * - Weight 32.5 kg → fits in -32 (32.5 <= 32 + 0.5)
     * - Weight 32.6 kg → goes to -36 (32.6 > 32 + 0.5)
     */
    private function bepaalGewichtsklasse(?float $gewicht, Leeftijdsklasse $leeftijdsklasse, float $tolerantie = 0.5): string
    {
        if (!$gewicht) {
            return 'onbekend';
        }

        $klassen = $leeftijdsklasse->gewichtsklassen();

        foreach ($klassen as $klasse) {
            if ($klasse > 0) {
                // Plus category (minimum weight) - this is always the last one
                // With tolerance, you can be slightly under the limit
                // e.g., +63 with tolerance 0.5 means 62.5+ kg qualifies
                return "+{$klasse}";
            } else {
                // Minus category (maximum weight)
                // With tolerance, the limit is extended
                // e.g., -32 with tolerance 0.5 means up to 32.5 kg qualifies
                $limiet = abs($klasse);
                if ($gewicht <= $limiet + $tolerantie) {
                    return "{$klasse}";
                }
            }
        }

        // If heavier than all minus categories, use the plus category
        $laatsteKlasse = end($klassen);
        if ($laatsteKlasse > 0) {
            return "+{$laatsteKlasse}";
        }

        // Fallback: create plus category from last minus class
        return "+" . abs($laatsteKlasse);
    }
}
