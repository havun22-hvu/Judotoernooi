<?php

namespace App\Services\BlokMatVerdeling;

use App\Models\Poule;
use App\Models\Toernooi;

/**
 * MatAssigner
 *
 * Distributes poules over mats within each block.
 *
 * Strategy: sequential distribution to keep similar categories together.
 * 1. Sort poules by age (young -> old), then weight (light -> heavy)
 * 2. Distribute sequentially over mats until each mat reaches its target
 * Result: Mini's on mat 1-2, Jeugd on mat 3-4, Dames/Heren on mat 5-6.
 *
 * Extracted from BlokMatVerdelingService so that mat assignment logic can be
 * unit-tested in isolation and reused from other services.
 */
class MatAssigner
{
    /**
     * Distribute poules over mats within each block of the tournament.
     */
    public function verdeelOverMatten(Toernooi $toernooi): void
    {
        $matten = $toernooi->matten->sortBy('nummer');
        $matIds = $matten->pluck('id')->toArray();
        $aantalMatten = count($matIds);

        if ($aantalMatten === 0) {
            return;
        }

        foreach ($toernooi->blokken as $blok) {
            // Sort by category order: Mini's -> Jeugd -> Dames -> Heren
            // Within each category: sort by age (from categorie_key) then weight
            // Combined sort key: categorie * 100000 + leeftijd * 1000 + gewicht
            $poules = $blok->poules()->with('judokas')->get()->sortBy(function ($poule) {
                $categorie = $this->getCategorieVolgorde($poule->leeftijdsklasse);
                $leeftijd = $this->extractLeeftijdUitCategorieKey($poule->categorie_key);
                $gewicht = $this->extractGewichtVoorSortering($poule->gewichtsklasse);
                return $categorie * 100000 + $leeftijd * 1000 + $gewicht;
            })->values();

            if ($poules->isEmpty()) {
                continue;
            }

            // Dynamic target per mat based on what must remain for other mats
            // Mini's get lower target (matches take full 2 min), round down
            $totaalWedstrijden = $poules->sum('aantal_wedstrijden');
            $gemiddelde = $totaalWedstrijden / $aantalMatten;

            $wedstrijdenPerMat = array_fill_keys($matIds, 0);
            $huidigeMatIndex = 0;
            $resterend = $totaalWedstrijden;

            foreach ($poules as $poule) {
                $huidigeMat = $matIds[$huidigeMatIndex];
                $resterendeMatten = $aantalMatten - $huidigeMatIndex - 1;
                $isMinisPoule = $this->getCategorieVolgorde($poule->leeftijdsklasse) == 1;

                // Calculate target for this mat
                $moetOverblijven = $resterendeMatten * $gemiddelde;
                $doelVoorDezeMat = $resterend - $moetOverblijven;

                // Mini's: lower target (matches take full 2 min)
                if ($isMinisPoule) {
                    $doelVoorDezeMat = floor($doelVoorDezeMat * 0.85);
                }

                // Assign poule to current mat (eliminatie: b_mat_id defaults to same mat)
                $updateData = ['mat_id' => $huidigeMat];
                if ($poule->type === 'eliminatie') {
                    $updateData['b_mat_id'] = $huidigeMat;
                }
                $poule->update($updateData);
                $wedstrijdenPerMat[$huidigeMat] += $poule->aantal_wedstrijden;

                // Move to next mat when target reached
                if ($wedstrijdenPerMat[$huidigeMat] >= $doelVoorDezeMat && $huidigeMatIndex < $aantalMatten - 1) {
                    $resterend -= $wedstrijdenPerMat[$huidigeMat];
                    $huidigeMatIndex++;
                }
            }
        }

        $this->fixKruisfinaleMatten($toernooi);
    }

    /**
     * Get category order for sorting: Mini's=1, Jeugd=2, Dames=3, Heren=4.
     */
    public function getCategorieVolgorde(?string $leeftijdsklasse): int
    {
        if (empty($leeftijdsklasse)) {
            return 99;
        }

        $lower = strtolower($leeftijdsklasse);

        if (str_contains($lower, 'mini')) return 1;
        if (str_contains($lower, 'jeugd')) return 2;
        if (str_contains($lower, 'dame')) return 3;
        if (str_contains($lower, 'heren') || str_contains($lower, 'jongen')) return 4;

        return 99;
    }

    /**
     * Extract age from categorie_key (u7, u9, u11, u13, etc.).
     * This is the most reliable source for age sorting.
     */
    public function extractLeeftijdUitCategorieKey(?string $categorieKey): int
    {
        if (empty($categorieKey)) {
            return 999;
        }

        // Extract number from patterns like "u7", "u9_geel_plus", "u11", "u13_d"
        if (preg_match('/u(\d+)/', strtolower($categorieKey), $matches)) {
            return (int) $matches[1];
        }

        // Fallback for non-standard keys
        return 999;
    }

    /**
     * Determine heavy weight threshold (top 30% of weights).
     */
    public function bepaalZwaarGewichtGrens($poules): float
    {
        $gewichten = $poules->map(fn($p) => $this->extractGewichtVoorSortering($p->gewichtsklasse))
            ->filter(fn($g) => $g > 0)
            ->sort()
            ->values();

        if ($gewichten->isEmpty()) {
            return 9999;
        }

        // Top 30% threshold
        $index = (int) floor($gewichten->count() * 0.7);
        return $gewichten->get($index, $gewichten->last());
    }

    /**
     * Check if poule is for ladies based on judokas or leeftijdsklasse.
     */
    public function isPouleVoorDames(Poule $poule): bool
    {
        // Check leeftijdsklasse for "dames" or "meisjes"
        $lower = strtolower($poule->leeftijdsklasse ?? '');
        if (str_contains($lower, 'dame') || str_contains($lower, 'meisje') || str_contains($lower, 'vrouw')) {
            return true;
        }

        // Check judokas gender
        $judokas = $poule->judokas;
        if ($judokas->isEmpty()) {
            return false;
        }

        $vrouwen = $judokas->filter(fn($j) => in_array(strtolower($j->geslacht ?? ''), ['v', 'vrouw', 'female', 'f']));
        return $vrouwen->count() > ($judokas->count() / 2);
    }

    /**
     * Extract numeric weight value for sorting.
     * Handles formats: "-24", "-24kg", "24-27kg", "24-27", etc.
     */
    public function extractGewichtVoorSortering(?string $gewichtsklasse): float
    {
        if (empty($gewichtsklasse)) {
            return 0;
        }

        // Remove "kg" suffix
        $cleaned = str_replace('kg', '', $gewichtsklasse);

        // Handle range format "24-27" - take the first (min) value
        if (str_contains($cleaned, '-') && !str_starts_with($cleaned, '-')) {
            $parts = explode('-', $cleaned);
            return (float) trim($parts[0]);
        }

        // Handle "-24" format (single weight class)
        if (str_starts_with($cleaned, '-')) {
            return (float) substr($cleaned, 1);
        }

        // Handle "+90" format
        if (str_starts_with($cleaned, '+')) {
            return (float) substr($cleaned, 1) + 1000; // Put + classes at the end
        }

        // Fallback: try to extract any number
        if (preg_match('/(\d+(?:\.\d+)?)/', $cleaned, $matches)) {
            return (float) $matches[1];
        }

        return 0;
    }

    /**
     * Fix non-voorronde poules (kruisfinale, eliminatie) without mat_id.
     */
    public function fixKruisfinaleMatten(Toernooi $toernooi): void
    {
        $poules = Poule::where('toernooi_id', $toernooi->id)
            ->where('type', '!=', 'voorronde')
            ->whereNull('mat_id')
            ->get();

        foreach ($poules as $poule) {
            $voorrondeMatId = Poule::where('toernooi_id', $toernooi->id)
                ->where('leeftijdsklasse', $poule->leeftijdsklasse)
                ->where('gewichtsklasse', $poule->gewichtsklasse)
                ->where('type', 'voorronde')
                ->whereNotNull('mat_id')
                ->value('mat_id');

            if ($voorrondeMatId) {
                $poule->update(['mat_id' => $voorrondeMatId]);
            }
        }
    }

    /**
     * Find mat with least matches.
     */
    public function vindMinsteWedstrijdenMat(array $matIds, array $wedstrijdenPerMat): int
    {
        $minWedstrijden = PHP_INT_MAX;
        $besteMat = $matIds[0];

        foreach ($matIds as $matId) {
            if (($wedstrijdenPerMat[$matId] ?? 0) < $minWedstrijden) {
                $minWedstrijden = $wedstrijdenPerMat[$matId] ?? 0;
                $besteMat = $matId;
            }
        }

        return $besteMat;
    }
}
