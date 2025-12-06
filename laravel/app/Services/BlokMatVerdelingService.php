<?php

namespace App\Services;

use App\Models\Blok;
use App\Models\Mat;
use App\Models\Poule;
use App\Models\Toernooi;
use Illuminate\Support\Facades\DB;

class BlokMatVerdelingService
{
    /**
     * Distribute pools over blocks and mats
     */
    public function genereerBlokMatVerdeling(Toernooi $toernooi): array
    {
        return DB::transaction(function () use ($toernooi) {
            $blokken = $toernooi->blokken;
            $matten = $toernooi->matten;
            $poules = $toernooi->poules()->orderBy('leeftijdsklasse')->orderBy('nummer')->get();

            if ($blokken->isEmpty() || $matten->isEmpty() || $poules->isEmpty()) {
                throw new \RuntimeException('Blokken, matten of poules ontbreken');
            }

            // Calculate target matches per block/mat
            $totaalWedstrijden = $poules->sum('aantal_wedstrijden');
            $aantalSlots = $blokken->count() * $matten->count();
            $doelWedstrijdenPerSlot = (int) ceil($totaalWedstrijden / $aantalSlots);

            // Track matches per block/mat
            $wedstrijdenPerBlok = array_fill_keys($blokken->pluck('id')->toArray(), 0);
            $wedstrijdenPerMat = [];
            foreach ($blokken as $blok) {
                $wedstrijdenPerMat[$blok->id] = array_fill_keys($matten->pluck('id')->toArray(), 0);
            }

            // Group pools by age category
            $poulesPerLeeftijd = $poules->groupBy('leeftijdsklasse');

            $blokIndex = 0;
            $matIndex = 0;
            $blokkenArray = $blokken->values()->all();
            $mattenArray = $matten->values()->all();

            foreach ($poulesPerLeeftijd as $leeftijdsklasse => $leeftijdPoules) {
                foreach ($leeftijdPoules as $poule) {
                    // Find best block/mat combination
                    $besteBlok = $this->vindMinsteWedstrijdenBlok($blokkenArray, $wedstrijdenPerBlok);
                    $besteMat = $this->vindMinsteWedstrijdenMat(
                        $mattenArray,
                        $wedstrijdenPerMat[$besteBlok->id]
                    );

                    // Assign pool to block/mat
                    $poule->update([
                        'blok_id' => $besteBlok->id,
                        'mat_id' => $besteMat->id,
                    ]);

                    // Update counters
                    $wedstrijdenPerBlok[$besteBlok->id] += $poule->aantal_wedstrijden;
                    $wedstrijdenPerMat[$besteBlok->id][$besteMat->id] += $poule->aantal_wedstrijden;
                }
            }

            $toernooi->update(['blokken_verdeeld_op' => now()]);

            return $this->getVerdelingsStatistieken($toernooi);
        });
    }

    /**
     * Find block with least matches
     */
    private function vindMinsteWedstrijdenBlok(array $blokken, array $wedstrijdenPerBlok): Blok
    {
        $minWedstrijden = PHP_INT_MAX;
        $besteBlok = $blokken[0];

        foreach ($blokken as $blok) {
            if ($wedstrijdenPerBlok[$blok->id] < $minWedstrijden) {
                $minWedstrijden = $wedstrijdenPerBlok[$blok->id];
                $besteBlok = $blok;
            }
        }

        return $besteBlok;
    }

    /**
     * Find mat with least matches in a block
     */
    private function vindMinsteWedstrijdenMat(array $matten, array $wedstrijdenPerMat): Mat
    {
        $minWedstrijden = PHP_INT_MAX;
        $besteMat = $matten[0];

        foreach ($matten as $mat) {
            if ($wedstrijdenPerMat[$mat->id] < $minWedstrijden) {
                $minWedstrijden = $wedstrijdenPerMat[$mat->id];
                $besteMat = $mat;
            }
        }

        return $besteMat;
    }

    /**
     * Get distribution statistics
     */
    public function getVerdelingsStatistieken(Toernooi $toernooi): array
    {
        $stats = [];

        foreach ($toernooi->blokken as $blok) {
            $blokStats = [
                'blok' => $blok->nummer,
                'totaal_wedstrijden' => 0,
                'matten' => [],
            ];

            foreach ($toernooi->matten as $mat) {
                $wedstrijden = Poule::where('blok_id', $blok->id)
                    ->where('mat_id', $mat->id)
                    ->sum('aantal_wedstrijden');

                $poules = Poule::where('blok_id', $blok->id)
                    ->where('mat_id', $mat->id)
                    ->count();

                $blokStats['matten'][$mat->nummer] = [
                    'poules' => $poules,
                    'wedstrijden' => $wedstrijden,
                ];
                $blokStats['totaal_wedstrijden'] += $wedstrijden;
            }

            $stats[$blok->nummer] = $blokStats;
        }

        return $stats;
    }

    /**
     * Move pool to different block/mat
     */
    public function verplaatsPoule(Poule $poule, Blok $nieuweBlok, Mat $nieuweMat): void
    {
        $poule->update([
            'blok_id' => $nieuweBlok->id,
            'mat_id' => $nieuweMat->id,
        ]);
    }

    /**
     * Get hall overview (zaaloverzicht)
     */
    public function getZaalOverzicht(Toernooi $toernooi): array
    {
        $overzicht = [];

        foreach ($toernooi->blokken()->with('poules.mat')->get() as $blok) {
            $blokData = [
                'nummer' => $blok->nummer,
                'naam' => $blok->naam,
                'weging_gesloten' => $blok->weging_gesloten,
                'matten' => [],
            ];

            foreach ($toernooi->matten as $mat) {
                $poules = $blok->poules->where('mat_id', $mat->id);

                $blokData['matten'][$mat->nummer] = [
                    'mat_naam' => $mat->label,
                    'poules' => $poules->map(fn($p) => [
                        'id' => $p->id,
                        'titel' => $p->titel,
                        'judokas' => $p->aantal_judokas,
                        'wedstrijden' => $p->aantal_wedstrijden,
                    ])->values()->toArray(),
                ];
            }

            $overzicht[] = $blokData;
        }

        return $overzicht;
    }
}
