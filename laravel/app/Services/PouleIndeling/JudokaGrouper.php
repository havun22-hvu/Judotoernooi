<?php

namespace App\Services\PouleIndeling;

use App\Models\Judoka;
use App\Models\Toernooi;
use Illuminate\Support\Collection;

/**
 * Groups tournament judokas by age class, weight class and gender
 * according to the tournament's category configuration.
 *
 * Extracted from PouleIndelingService so the grouping/sorting rules
 * can be tested and reused independently of the main generation flow.
 */
class JudokaGrouper
{
    /**
     * Group judokas by age class, (optionally) weight class, and gender based on config.
     * Sorted by sort fields (sort_categorie, sort_gewicht, sort_band).
     *
     * If gebruik_gewichtsklassen is OFF: group only by leeftijd (+ geslacht from config)
     * If gebruik_gewichtsklassen is ON: group by leeftijd + gewichtsklasse (+ geslacht from config)
     *
     * @param  Toernooi            $toernooi              Tournament to pull judokas from.
     * @param  array<string,mixed> $gewichtsklassenConfig Pre-loaded category config map.
     * @param  string[]            $prioriteiten          Ordered priorities: any of 'leeftijd','gewicht','band'.
     * @return Collection<string, Collection<int, Judoka>>
     */
    public function group(Toernooi $toernooi, array $gewichtsklassenConfig, array $prioriteiten): Collection
    {
        // Default to true if null (for backwards compatibility)
        $gebruikGewichtsklassen = $toernooi->gebruik_gewichtsklassen === null ? true : $toernooi->gebruik_gewichtsklassen;

        // Determine priority positions (lower index = higher priority)
        $leeftijdIdx = array_search('leeftijd', $prioriteiten);
        $gewichtIdx = array_search('gewicht', $prioriteiten);
        $bandIdx = array_search('band', $prioriteiten);

        $bandFirst = ($bandIdx !== false && $gewichtIdx !== false) ? ($bandIdx < $gewichtIdx) : false;

        $sortFields = [];
        if ($leeftijdIdx !== false) $sortFields[$leeftijdIdx] = ['geboortejaar', 'DESC'];
        if ($gewichtIdx !== false) $sortFields[$gewichtIdx] = ['sort_gewicht', 'ASC'];
        if ($bandIdx !== false) $sortFields[$bandIdx] = ['sort_band', 'ASC'];
        ksort($sortFields);

        $query = $toernooi->judokas()->orderBy('sort_categorie');
        foreach ($sortFields as [$field, $direction]) {
            $query->orderBy($field, $direction);
        }

        $judokas = $query->get();

        $groepen = $judokas->groupBy(
            fn(Judoka $judoka) => $this->groupKey($judoka, $gewichtsklassenConfig)
        );

        // Re-sort judokas within each group (groupBy doesn't preserve order!)
        $groepen = $groepen->map(function ($judokasInGroep) use ($bandFirst) {
            return $judokasInGroep->sortBy($bandFirst
                ? [['sort_band', 'asc'], ['sort_gewicht', 'asc']]
                : [['sort_gewicht', 'asc'], ['sort_band', 'asc']]
            );
        });

        // Sort groups by sort_categorie of first judoka, then gewicht
        return $groepen->sortBy(function ($judokasInGroep, $key) {
            $eerste = $judokasInGroep->first();
            $sortCategorie = $eerste->sort_categorie ?? 99;

            $delen = explode('|', $key);
            $gewicht = $delen[1] ?? '';
            $gewichtNum = intval(preg_replace('/[^0-9]/', '', $gewicht));
            $gewichtPlus = str_starts_with($gewicht, '+') ? 1000 : 0;

            return sprintf('%02d%04d', $sortCategorie, $gewichtNum + $gewichtPlus);
        });
    }

    /**
     * Build the grouping key for a single judoka.
     *
     * @param  array<string,mixed>  $gewichtsklassenConfig
     */
    private function groupKey(Judoka $judoka, array $gewichtsklassenConfig): string
    {
        $leeftijdsklasse = $judoka->leeftijdsklasse ?: 'Onbekend';
        $categorieKey = $judoka->categorie_key ?: '';

        $config = $gewichtsklassenConfig[$categorieKey] ?? null;
        $configGeslacht = strtolower($config['geslacht'] ?? 'gemengd');
        $includeGeslacht = $configGeslacht !== 'gemengd';

        // Dynamic grouping (max_kg_verschil > 0): skip weight class - DynamischeIndelingService handles it
        $usesDynamic = ($config['max_kg_verschil'] ?? 0) > 0;
        $hasFixedWeightClasses = !empty($config['gewichten'] ?? []);

        $geslacht = strtoupper($judoka->geslacht ?? '');

        if ($usesDynamic) {
            return $includeGeslacht
                ? "{$leeftijdsklasse}||{$geslacht}"
                : "{$leeftijdsklasse}|";
        }

        if ($hasFixedWeightClasses) {
            $gewichtsklasse = $judoka->gewichtsklasse ?: 'Onbekend';
            return $includeGeslacht
                ? "{$leeftijdsklasse}|{$gewichtsklasse}|{$geslacht}"
                : "{$leeftijdsklasse}|{$gewichtsklasse}";
        }

        // No weight classes and not dynamic: group only by age
        return $includeGeslacht
            ? "{$leeftijdsklasse}||{$geslacht}"
            : "{$leeftijdsklasse}|";
    }
}
