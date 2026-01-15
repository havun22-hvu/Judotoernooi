<?php

namespace App\Helpers;

/**
 * Helper class for belt (band) related operations.
 * Centralizes belt level parsing and filter logic to avoid duplication.
 */
class BandHelper
{
    /**
     * Belt order mapping: wit (beginner) = 0, zwart (expert) = 6
     * Matches the Band enum values.
     */
    public const BAND_VOLGORDE = [
        'wit' => 0,
        'geel' => 1,
        'oranje' => 2,
        'groen' => 3,
        'blauw' => 4,
        'bruin' => 5,
        'zwart' => 6,
    ];

    /**
     * Extract band niveau from strings like "groen (3 kyu)" or "wit".
     * Returns 0 for unknown belts (treated as beginner).
     */
    public static function getNiveau(string $band): int
    {
        $bandLower = strtolower(trim($band));

        // Direct match
        if (isset(self::BAND_VOLGORDE[$bandLower])) {
            return self::BAND_VOLGORDE[$bandLower];
        }

        // Extract first word: "groen (3 kyu)" → "groen"
        $eersteWoord = explode(' ', $bandLower)[0];
        if (isset(self::BAND_VOLGORDE[$eersteWoord])) {
            return self::BAND_VOLGORDE[$eersteWoord];
        }

        // Check if band contains color name
        foreach (self::BAND_VOLGORDE as $kleur => $niveau) {
            if (str_contains($bandLower, $kleur)) {
                return $niveau;
            }
        }

        return 0; // Unknown = treat as beginner (wit)
    }

    /**
     * Check if a belt matches a filter.
     * Filters: "tm_groen" (up to green), "vanaf_blauw" (from blue onwards)
     */
    public static function pastInFilter(?string $band, ?string $filter): bool
    {
        if (empty($filter) || empty($band)) {
            return true;
        }

        $bandIdx = self::getNiveau($band);

        if (str_starts_with($filter, 'tm_')) {
            $filterBand = str_replace('tm_', '', $filter);
            $filterIdx = self::BAND_VOLGORDE[$filterBand] ?? 99;
            return $bandIdx <= $filterIdx;
        }

        if (str_starts_with($filter, 'vanaf_')) {
            $filterBand = str_replace('vanaf_', '', $filter);
            $filterIdx = self::BAND_VOLGORDE[$filterBand] ?? 0;
            return $bandIdx >= $filterIdx;
        }

        return true;
    }

    /**
     * Get niveau for sorting (1-7 scale, for ascending sort where beginners come first)
     * Use this when you need 1-based indexing for sort operations.
     */
    public static function getSortNiveau(string $band): int
    {
        return self::getNiveau($band) + 1;
    }
}
