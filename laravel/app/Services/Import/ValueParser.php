<?php

namespace App\Services\Import;

use App\Enums\Band;
use App\Enums\Geslacht;

/**
 * Static-only parsing utilities used by the import pipeline.
 *
 * Extracted from ImportService to keep parsing concerns isolated and
 * independently testable. Methods remain static so existing callers
 * (including ImportService itself via facade methods) continue to work.
 */
class ValueParser
{
    /**
     * Get value from row by column name (case-insensitive).
     * Supports comma-separated indices for multi-column fields
     * (e.g., "0,1,2" for combining voornaam, tussenvoegsel, achternaam).
     */
    public static function getWaarde(array $rij, string $kolom): mixed
    {
        // Check for comma-separated indices (multi-column)
        if (str_contains($kolom, ',')) {
            $indices = array_map('intval', explode(',', $kolom));
            $parts = [];
            foreach ($indices as $idx) {
                $val = $rij[$idx] ?? null;
                if ($val !== null && trim((string)$val) !== '') {
                    $parts[] = trim((string)$val);
                }
            }
            return $parts ? implode(' ', $parts) : null;
        }

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
    public static function normaliseerNaam(string $naam): string
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
     * Parse birth year from ANY format imaginable.
     *
     * Supported: 2015, 15, 43831, 43831.5, 43831,5,
     * 24-01-2015, 01/24/2015, 2015-01-24, 24.01.2015, 24\01\2015,
     * 24 01 2015, 24 - 01 - 2015, (2015), [24-01-2015],
     * 24-01-15, 15\01\24, 20150124, 24012015, 240115,
     * 24 januari 2015, 15 mrt 2010, 2015-01-24T12:00:00Z, etc.
     */
    public static function parseGeboortejaar(mixed $waarde): int
    {
        $huidigJaar = (int) date('Y');

        // --- Phase 1: Clean up ---
        $clean = trim((string) $waarde);
        // Strip parentheses, brackets, braces: (2015) → 2015, [24-01-2015] → 24-01-2015
        $clean = preg_replace('/^[\(\[\{]+|[\)\]\}]+$/', '', trim($clean));
        // European comma decimal for Excel serials: 43831,5 → 43831.5
        $clean = preg_replace('/^(\d+),(\d+)$/', '$1.$2', $clean);

        // --- Phase 2: Numeric values (int/float) ---
        if (is_numeric($clean)) {
            $jaar = (int) $clean;
            if ($jaar < 100) {
                return ($jaar > 50) ? 1900 + $jaar : 2000 + $jaar;
            }
            if ($jaar > 30000 && $jaar < 60000) {
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $clean);
                return (int) $date->format('Y');
            }
            if ($jaar >= 1950 && $jaar <= $huidigJaar) {
                return $jaar;
            }
        }

        // --- Phase 3: Normalize string separators ---
        $norm = str_replace('\\', '/', $clean);
        // Spaces around separators: "24 - 01 - 2015" → "24-01-2015"
        $norm = preg_replace('/\s*([-.\/])\s*/', '$1', $norm);
        // Space-only separators: "24 01 2015" → "24/01/2015"
        $norm = preg_replace('/^(\d{1,4})\s+(\d{1,2})\s+(\d{2,4})$/', '$1/$2/$3', $norm);

        // --- Phase 4: 4-digit year in any string ---
        if (preg_match('/\b(19\d{2}|20\d{2})\b/', $norm, $matches)) {
            return (int) $matches[1];
        }

        // --- Phase 5: Date with 2-digit year at end (dd-mm-yy, dd/mm/yy, dd.mm.yy) ---
        if (preg_match('/^\d{1,2}[-\/.]\d{1,2}[-\/.]\d{2}$/', $norm)) {
            preg_match('/(\d{2})$/', $norm, $m);
            $yy = (int) $m[1];
            return ($yy > 50) ? 1900 + $yy : 2000 + $yy;
        }

        // --- Phase 6: Date with 2-digit year at start (yy-mm-dd, yy/mm/dd) ---
        if (preg_match('/^(\d{2})[-\/.]\d{1,2}[-\/.]\d{1,2}$/', $norm, $m)) {
            $yy = (int) $m[1];
            $candidate = ($yy > 50) ? 1900 + $yy : 2000 + $yy;
            if ($candidate >= 1950 && $candidate <= $huidigJaar) {
                return $candidate;
            }
        }

        // --- Phase 7: Compact dates without separators ---
        // YYYYMMDD: 20150124
        if (preg_match('/^(19\d{2}|20\d{2})(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])$/', $norm, $m)) {
            return (int) $m[1];
        }
        // DDMMYYYY: 24012015
        if (preg_match('/^(0[1-9]|[12]\d|3[01])(0[1-9]|1[0-2])(19\d{2}|20\d{2})$/', $norm, $m)) {
            return (int) $m[3];
        }
        // DDMMYY: 240115 (6 digits, last 2 = year)
        if (preg_match('/^(\d{2})(\d{2})(\d{2})$/', $norm, $m)) {
            $dd = (int) $m[1];
            $mm = (int) $m[2];
            $yy = (int) $m[3];
            if ($dd >= 1 && $dd <= 31 && $mm >= 1 && $mm <= 12) {
                return ($yy > 50) ? 1900 + $yy : 2000 + $yy;
            }
            // Try YYMMDD: first 2 = year
            if ($mm >= 1 && $mm <= 12 && $yy >= 1 && $yy <= 31) {
                return ($dd > 50) ? 1900 + $dd : 2000 + $dd;
            }
        }

        // --- Phase 8: Dutch month names → English ---
        $nlMaanden = [
            'januari' => 'january', 'februari' => 'february', 'maart' => 'march',
            'april' => 'april', 'mei' => 'may', 'juni' => 'june',
            'juli' => 'july', 'augustus' => 'august', 'september' => 'september',
            'oktober' => 'october', 'november' => 'november', 'december' => 'december',
            'jan' => 'jan', 'feb' => 'feb', 'mrt' => 'mar', 'apr' => 'apr',
            'jun' => 'jun', 'jul' => 'jul', 'aug' => 'aug', 'sep' => 'sep',
            'okt' => 'oct', 'nov' => 'nov', 'dec' => 'dec',
        ];
        // Strip ordinals: "24ste", "1e", "2de", "3de"
        $vertaald = preg_replace('/(\d+)\s*(ste|de|e)\b/i', '$1', $norm);
        $vertaald = str_ireplace(array_keys($nlMaanden), array_values($nlMaanden), $vertaald);

        // --- Phase 9: strtotime (English dates, natural language, ISO 8601) ---
        $ts = strtotime($vertaald);
        if ($ts !== false) {
            $jaar = (int) date('Y', $ts);
            if ($jaar >= 1950 && $jaar <= $huidigJaar) {
                return $jaar;
            }
        }

        // --- Phase 10: DateTime::createFromFormat fallback ---
        $formats = ['d-m-y', 'd/m/y', 'd.m.y', 'y-m-d', 'y/m/d', 'y.m.d'];
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $norm);
            if ($date !== false) {
                $jaar = (int) $date->format('Y');
                if ($jaar >= 1950 && $jaar <= $huidigJaar) {
                    return $jaar;
                }
            }
        }

        throw new \InvalidArgumentException("Ongeldig geboortejaar: {$waarde}");
    }

    /**
     * Parse gender from various formats
     */
    public static function parseGeslacht(mixed $waarde): string
    {
        $geslacht = Geslacht::fromString((string)$waarde);
        return $geslacht?->value ?? 'M';
    }

    /**
     * Parse belt color - returns lowercase base value (geel, groen, etc.)
     */
    public static function parseBand(mixed $waarde): string
    {
        if (empty($waarde)) {
            return 'wit';
        }

        $band = Band::fromString((string)$waarde);
        // Sla op als lowercase kleur naam (wit, geel, oranje, groen, blauw, bruin, zwart)
        // Band enum value is integer, dus gebruik name property voor de string
        return $band ? strtolower($band->name) : strtolower(trim(explode(' ', (string)$waarde)[0]));
    }

    /**
     * Parse weight class from CSV (handles Excel formatting like '-38 kg)
     */
    public static function parseGewichtsklasse(mixed $waarde): ?string
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
    public static function parseGewicht(mixed $waarde): ?float
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
     * Derive weight from weight class
     * -34 => 34.0, +63 => 63.0
     */
    public static function gewichtVanKlasse(string $klasse): ?float
    {
        if (preg_match('/[+-]?(\d+)/', $klasse, $matches)) {
            return (float) $matches[1];
        }
        return null;
    }
}
