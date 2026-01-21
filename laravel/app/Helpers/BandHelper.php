<?php

namespace App\Helpers;

/**
 * Helper class for belt (band) related operations.
 * Centralizes belt level parsing and filter logic to avoid duplication.
 */
class BandHelper
{
    /**
     * Belt order mapping: zwart (expert) = 0, wit (beginner) = 6
     * Matches the Band enum values (kyu system: 0=dan, 6=6e kyu).
     * For sorting: higher value = lower belt = comes first in ascending sort.
     */
    public const BAND_VOLGORDE = [
        'zwart' => 0,
        'bruin' => 1,
        'blauw' => 2,
        'groen' => 3,
        'oranje' => 4,
        'geel' => 5,
        'wit' => 6,
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

        return 6; // Unknown = treat as beginner (wit)
    }

    /**
     * Check if a belt matches a filter.
     * Filters: "tm_groen" (up to green/beginners), "vanaf_blauw" (from blue onwards/advanced)
     * Note: Lower value = higher belt (zwart=0, wit=6)
     */
    public static function pastInFilter(?string $band, ?string $filter): bool
    {
        if (empty($filter) || empty($band)) {
            return true;
        }

        $bandIdx = self::getNiveau($band);

        if (str_starts_with($filter, 'tm_')) {
            // "tm_groen" = beginners up to green (wit, geel, oranje, groen)
            // bandIdx must be >= filterIdx (higher value = lower belt)
            $filterBand = str_replace('tm_', '', $filter);
            $filterIdx = self::BAND_VOLGORDE[$filterBand] ?? 0;
            return $bandIdx >= $filterIdx;
        }

        if (str_starts_with($filter, 'vanaf_')) {
            // "vanaf_blauw" = advanced from blue onwards (blauw, bruin, zwart)
            // bandIdx must be <= filterIdx (lower value = higher belt)
            $filterBand = str_replace('vanaf_', '', $filter);
            $filterIdx = self::BAND_VOLGORDE[$filterBand] ?? 6;
            return $bandIdx <= $filterIdx;
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
