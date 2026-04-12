<?php

namespace App\Services\PouleIndeling;

/**
 * Builds pool titles based on category config and pool contents.
 *
 * Stateless formatter — no database or framework access.
 * Extracted from PouleIndelingService so title rules can be unit-tested
 * and reused from other pool-aware code.
 *
 * Rules:
 * - Vaste categorie met gewichtsklassen: "Label -24kg"
 * - Dynamische categorie (max_kg>0 of max_lft>0): "Label 6-7j 22-25kg"
 * - Alle variabelen=0: alleen "Label"
 */
class PouleTitleBuilder
{
    /**
     * Create pool title.
     *
     * @param  string       $leeftijdsklasse       Age class label
     * @param  string       $gewichtsklasse        Weight class label
     * @param  string|null  $geslacht              Gender marker (M/V/gemengd/null)
     * @param  array        $pouleJudokas          Judokas in this pool (used for dynamic ranges)
     * @param  array|null   $gewichtsklassenConfig Full category config map
     * @param  string|null  $categorieKey          Key into the category config map
     */
    public function build(
        string $leeftijdsklasse,
        string $gewichtsklasse,
        ?string $geslacht,
        array $pouleJudokas = [],
        ?array $gewichtsklassenConfig = null,
        ?string $categorieKey = null
    ): string {
        $categorieConfig = $gewichtsklassenConfig[$categorieKey] ?? null;

        $maxKgVerschil = (float) ($categorieConfig['max_kg_verschil'] ?? 0);
        $maxLftVerschil = (int) ($categorieConfig['max_leeftijd_verschil'] ?? 0);
        $isDynamisch = $maxKgVerschil > 0 || $maxLftVerschil > 0;

        $parts = [];

        $toonLabel = $categorieConfig['toon_label_in_titel'] ?? true;
        $label = $categorieConfig['label'] ?? $leeftijdsklasse;
        if ($toonLabel && !empty($label)) {
            $parts[] = $label;
        }

        if ($geslacht && $geslacht !== 'gemengd') {
            $parts[] = $geslacht;
        }

        $isVasteGewichtsklasse = !$isDynamisch
            && !empty($gewichtsklasse)
            && $gewichtsklasse !== 'Onbekend';

        if ($isVasteGewichtsklasse) {
            $gk = $gewichtsklasse;
            if (!str_contains($gk, 'kg')) {
                $gk .= 'kg';
            }
            $parts[] = $gk;
            return implode(' ', $parts) ?: 'Onbekend';
        }

        if ($isDynamisch && !empty($pouleJudokas)) {
            if ($maxLftVerschil > 0) {
                $leeftijden = array_filter(array_map(fn($j) => $j->leeftijd, $pouleJudokas));
                if (!empty($leeftijden)) {
                    $min = min($leeftijden);
                    $max = max($leeftijden);
                    $parts[] = $min == $max ? "{$min}j" : "{$min}-{$max}j";
                }
            }

            if ($maxKgVerschil > 0) {
                $gewichten = array_filter(array_map(fn($j) => $j->gewicht, $pouleJudokas));
                if (!empty($gewichten)) {
                    $min = min($gewichten);
                    $max = max($gewichten);
                    $parts[] = $min == $max ? "{$min}kg" : "{$min}-{$max}kg";
                }
            }
        }

        return implode(' ', $parts) ?: 'Onbekend';
    }
}
