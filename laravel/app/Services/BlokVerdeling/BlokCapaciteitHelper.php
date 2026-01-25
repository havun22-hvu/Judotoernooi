<?php

namespace App\Services\BlokVerdeling;

use App\Models\Toernooi;
use Illuminate\Support\Collection;

/**
 * Helper for block capacity calculations
 */
class BlokCapaciteitHelper
{
    /**
     * Calculate capacity per block
     *
     * @param Toernooi $toernooi
     * @param Collection $blokken
     * @return array blok_id => ['gewenst' => x, 'actueel' => y, 'ruimte' => z]
     */
    public function berekenCapaciteit(Toernooi $toernooi, Collection $blokken): array
    {
        $totaalWedstrijden = $toernooi->poules()->sum('aantal_wedstrijden');
        $defaultGewenst = $blokken->count() > 0
            ? (int) ceil($totaalWedstrijden / $blokken->count())
            : 0;

        $capaciteit = [];

        foreach ($blokken as $blok) {
            $actueel = $toernooi->poules()
                ->where('blok_id', $blok->id)
                ->sum('aantal_wedstrijden');

            $gewenst = $blok->gewenst_wedstrijden ?? $defaultGewenst;

            $capaciteit[$blok->id] = [
                'gewenst' => $gewenst,
                'actueel' => $actueel,
                'ruimte' => $gewenst - $actueel,
            ];
        }

        return $capaciteit;
    }

    /**
     * Initialize capacity for simulation (includes pinned items)
     *
     * @param Toernooi $toernooi
     * @param array $blokken
     * @param int $doelPerBlok
     * @return array
     */
    public function initializeSimulatieCapaciteit(Toernooi $toernooi, array $blokken, int $doelPerBlok): array
    {
        $capaciteit = [];

        foreach ($blokken as $blok) {
            $vastWedstrijden = $toernooi->poules()
                ->where('blok_id', $blok->id)
                ->where('blok_vast', true)
                ->sum('aantal_wedstrijden');

            $capaciteit[$blok->id] = [
                'gewenst' => $doelPerBlok,
                'actueel' => $vastWedstrijden,
                'ruimte' => $doelPerBlok - $vastWedstrijden,
            ];
        }

        return $capaciteit;
    }

    /**
     * Find block index with most available space
     */
    public function vindBlokMetMeesteRuimte(array $capaciteit, array $blokken): int
    {
        $maxRuimte = -PHP_INT_MAX;
        $besteIndex = 0;

        foreach ($blokken as $index => $blok) {
            $ruimte = $capaciteit[$blok->id]['ruimte'] ?? 0;
            if ($ruimte > $maxRuimte) {
                $maxRuimte = $ruimte;
                $besteIndex = $index;
            }
        }

        return $besteIndex;
    }

    /**
     * Find a random block from top 3 with most space (for variation)
     */
    public function vindRandomBlokMetRuimte(array $capaciteit, array $blokken): int
    {
        $blokkenMetRuimte = [];

        foreach ($blokken as $index => $blok) {
            $ruimte = $capaciteit[$blok->id]['ruimte'] ?? 0;
            $blokkenMetRuimte[] = ['idx' => $index, 'ruimte' => $ruimte];
        }

        usort($blokkenMetRuimte, fn($a, $b) => $b['ruimte'] <=> $a['ruimte']);

        $topN = min(3, count($blokkenMetRuimte));
        $keuze = mt_rand(0, $topN - 1);

        return $blokkenMetRuimte[$keuze]['idx'];
    }

    /**
     * Update capacity after placing a category
     */
    public function updateCapaciteit(array &$capaciteit, int $blokId, int $wedstrijden): void
    {
        $capaciteit[$blokId]['actueel'] += $wedstrijden;
        $capaciteit[$blokId]['ruimte'] -= $wedstrijden;
    }

    /**
     * Check if block can accept more matches without exceeding limit
     */
    public function kanPlaatsen(array $capaciteit, int $blokId, int $wedstrijden): bool
    {
        $cap = $capaciteit[$blokId];
        $gewenst = max(1, $cap['gewenst']);
        $nieuweActueel = $cap['actueel'] + $wedstrijden;

        return $nieuweActueel <= ($gewenst * BlokVerdelingConstants::MAX_VULGRAAD_FACTOR);
    }
}
