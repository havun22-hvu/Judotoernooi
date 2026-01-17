<?php

namespace App\Services;

use App\Helpers\BandHelper;
use App\Models\Judoka;
use App\Models\Poule;

/**
 * Dedicated class for category classification based on hard criteria.
 *
 * Hard criteria for category identification:
 * - max_leeftijd: age limit for category
 * - geslacht: M / V / gemengd
 * - band_filter: tm_oranje, vanaf_groen, etc.
 * - gewichtsklassen: fixed weight classes (when max_kg_verschil = 0)
 *
 * NOT for category identification (pool level):
 * - max_kg_verschil: distribution within category
 * - max_leeftijd_verschil: distribution within category
 */
class CategorieClassifier
{
    private array $config;
    private float $tolerantie;

    public function __construct(array $gewichtsklassenConfig, float $tolerantie = 0.5)
    {
        $this->config = $gewichtsklassenConfig;
        $this->tolerantie = $tolerantie;
    }

    /**
     * Classify a judoka into a category based on hard criteria.
     *
     * @return array{key: ?string, label: string, sortCategorie: int, gewichtsklasse: ?string, isDynamisch: bool}
     */
    public function classificeer(Judoka $judoka, int $toernooiJaar = null): array
    {
        $leeftijd = $judoka->leeftijd ?? ($toernooiJaar ?? (int) date('Y')) - $judoka->geboortejaar;
        $geslacht = strtoupper($judoka->geslacht ?? '');
        $bandNiveau = BandHelper::getSortNiveau($judoka->band ?? '');
        $gewicht = $judoka->gewicht ?? 0;

        // STEP 1: Find first (lowest) max_leeftijd where judoka fits
        // Categories are sorted by max_leeftijd (young → old)
        $eersteMatchLeeftijd = null;
        $sortCategorie = 0;

        foreach ($this->config as $config) {
            $maxLeeftijd = $config['max_leeftijd'] ?? 99;
            if ($leeftijd <= $maxLeeftijd) {
                $eersteMatchLeeftijd = $maxLeeftijd;
                break;
            }
            $sortCategorie++;
        }

        // No age match → not categorized
        if ($eersteMatchLeeftijd === null) {
            return $this->nietGecategoriseerd();
        }

        // STEP 2: Check ONLY categories with this max_leeftijd
        // A 6-year-old in U7 should NEVER fall through to U11!
        $categorieSortIndex = 0;
        foreach ($this->config as $key => $config) {
            $maxLeeftijd = $config['max_leeftijd'] ?? 99;

            // Skip categories with different max_leeftijd
            if ($maxLeeftijd !== $eersteMatchLeeftijd) {
                $categorieSortIndex++;
                continue;
            }

            // Check geslacht
            if (!$this->geslachtMatcht($geslacht, $config, $key)) {
                $categorieSortIndex++;
                continue;
            }

            // Check band_filter if set
            $bandFilter = $config['band_filter'] ?? null;
            if ($bandFilter && !$this->voldoetAanBandFilter($bandNiveau, $bandFilter)) {
                $categorieSortIndex++;
                continue;
            }

            // Match found! Determine gewichtsklasse
            $isDynamisch = ($config['max_kg_verschil'] ?? 0) > 0;
            $gewichtsklasse = $this->bepaalGewichtsklasse($gewicht, $config);

            return [
                'key' => $key,
                'label' => $config['label'] ?? $key,
                'sortCategorie' => $categorieSortIndex,
                'gewichtsklasse' => $gewichtsklasse,
                'isDynamisch' => $isDynamisch,
            ];
        }

        // No match within age category → NOT CATEGORIZED
        // This happens when geslacht or band_filter doesn't match
        return $this->nietGecategoriseerd();
    }

    /**
     * Get config for a poule based on its stored categorie_key.
     * Uses direct key lookup - never searches on label.
     */
    public function getConfigVoorPoule(Poule $poule): ?array
    {
        // Direct lookup on categorie_key
        if ($poule->categorie_key && isset($this->config[$poule->categorie_key])) {
            return $this->config[$poule->categorie_key];
        }

        // No config found
        return null;
    }

    /**
     * Check if a category is dynamic (max_kg_verschil > 0).
     */
    public function isDynamisch(string $categorieKey): bool
    {
        $config = $this->config[$categorieKey] ?? null;
        if (!$config) {
            return false;
        }

        return ($config['max_kg_verschil'] ?? 0) > 0;
    }

    /**
     * Get max_kg_verschil for a category.
     * Returns 0 for fixed weight class categories.
     */
    public function getMaxKgVerschil(string $categorieKey): float
    {
        $config = $this->config[$categorieKey] ?? null;
        if (!$config) {
            return 0;
        }

        return (float) ($config['max_kg_verschil'] ?? 0);
    }

    /**
     * Check if geslacht matches category config.
     */
    private function geslachtMatcht(string $judokaGeslacht, array $config, string $key): bool
    {
        $configGeslacht = strtoupper($config['geslacht'] ?? 'gemengd');
        $label = strtolower($config['label'] ?? '');

        // Normalize legacy values
        if ($configGeslacht === 'MEISJES') {
            $configGeslacht = 'V';
        } elseif ($configGeslacht === 'JONGENS') {
            $configGeslacht = 'M';
        }

        // Auto-detect gender from label ONLY if geslacht is not explicitly set
        $originalGeslacht = strtolower($config['geslacht'] ?? '');
        $isExplicitGemengd = $originalGeslacht === 'gemengd';

        if ($configGeslacht === 'GEMENGD' && !$isExplicitGemengd) {
            if (str_contains($label, 'dames') || str_contains($label, 'meisjes') || str_ends_with($key, '_d') || str_contains($key, '_d_')) {
                $configGeslacht = 'V';
            } elseif (str_contains($label, 'heren') || str_contains($label, 'jongens') || str_ends_with($key, '_h') || str_contains($key, '_h_')) {
                $configGeslacht = 'M';
            }
        }

        // Gemengd matches all
        if ($configGeslacht === 'GEMENGD') {
            return true;
        }

        return $configGeslacht === $judokaGeslacht;
    }

    /**
     * Check if band niveau matches the band filter.
     * Filter format: "tm_oranje" (t/m oranje) or "vanaf_groen" (vanaf groen)
     */
    private function voldoetAanBandFilter(int $bandNiveau, string $filter): bool
    {
        if (str_starts_with($filter, 'tm_') || str_starts_with($filter, 't/m ')) {
            $band = str_replace(['tm_', 't/m '], '', $filter);
            $maxNiveau = BandHelper::getSortNiveau($band);
            return $bandNiveau <= $maxNiveau;
        }

        if (str_starts_with($filter, 'vanaf_') || str_starts_with($filter, 'vanaf ')) {
            $band = str_replace(['vanaf_', 'vanaf '], '', $filter);
            $minNiveau = BandHelper::getSortNiveau($band);
            return $bandNiveau >= $minNiveau;
        }

        // Unknown filter format, allow all
        return true;
    }

    /**
     * Determine gewichtsklasse from config.
     * Returns null for dynamic categories (max_kg_verschil > 0).
     */
    private function bepaalGewichtsklasse(float $gewicht, array $config): ?string
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
            $klasseStr = (string) $klasse;

            if (str_starts_with($klasseStr, '+')) {
                // Plus category (minimum weight) - always last, catch-all
                return $klasseStr;
            }

            // Minus category (maximum weight)
            $maxGewicht = abs((float) $klasseStr);
            if ($gewicht <= $maxGewicht + $this->tolerantie) {
                return $klasseStr;
            }
        }

        // Fallback to highest (+ category if exists)
        $laatste = end($gewichten);
        return (string) $laatste;
    }

    /**
     * Return structure for uncategorized judoka.
     */
    private function nietGecategoriseerd(): array
    {
        return [
            'key' => null,
            'label' => 'Onbekend',
            'sortCategorie' => 99,
            'gewichtsklasse' => null,
            'isDynamisch' => false,
        ];
    }
}
