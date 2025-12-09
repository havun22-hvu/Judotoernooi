<?php

namespace App\Services;

use App\Models\Blok;
use App\Models\Mat;
use App\Models\Poule;
use App\Models\Toernooi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BlokMatVerdelingService
{
    private ?ConstraintSolverService $solver = null;

    public function __construct(?ConstraintSolverService $solver = null)
    {
        $this->solver = $solver ?? app(ConstraintSolverService::class);
    }

    /**
     * Distribute pools over blocks and mats using constraint solver
     * Falls back to simple algorithm if solver fails
     */
    public function genereerBlokMatVerdeling(Toernooi $toernooi, bool $forceFallback = false): array
    {
        return DB::transaction(function () use ($toernooi, $forceFallback) {
            $blokken = $toernooi->blokken;
            $matten = $toernooi->matten;
            $poules = $toernooi->poules()->orderBy('leeftijdsklasse')->orderBy('nummer')->get();

            if ($blokken->isEmpty() || $matten->isEmpty() || $poules->isEmpty()) {
                throw new \RuntimeException('Blokken, matten of poules ontbreken');
            }

            $method = 'fallback';

            // Try constraint solver first (unless forced to fallback)
            if (!$forceFallback && $this->solver && $this->solver->isAvailable()) {
                $solverResult = $this->solver->solveBlokMatDistribution($toernooi);

                if ($solverResult['success']) {
                    // Apply solver assignments
                    $this->applySolverAssignments($solverResult['assignments'], $toernooi);
                    $method = 'constraint_solver';

                    Log::info('Blok/mat distribution solved with constraint solver', [
                        'toernooi_id' => $toernooi->id,
                        'statistics' => $solverResult['statistics'] ?? [],
                    ]);
                } else {
                    Log::warning('Constraint solver failed, falling back', [
                        'error' => $solverResult['error'] ?? 'Unknown',
                    ]);
                }
            }

            // Fallback to simple algorithm
            if ($method === 'fallback') {
                $this->genereerBlokMatVerdelingFallback($toernooi);
            }

            $toernooi->update(['blokken_verdeeld_op' => now()]);

            $stats = $this->getVerdelingsStatistieken($toernooi);
            $stats['method'] = $method;

            return $stats;
        });
    }

    /**
     * Apply assignments from constraint solver
     */
    private function applySolverAssignments(array $assignments, Toernooi $toernooi): void
    {
        foreach ($assignments as $assignment) {
            $update = ['blok_id' => $assignment['blok_id']];
            Poule::where('id', $assignment['poule_id'])->update($update);
        }

        // Na blok toewijzing, verdeel poules over matten
        $this->verdeelPoulesOverMatten($toernooi);
    }

    /**
     * Verdeel poules over matten binnen elk blok, rekening houdend met voorkeuren
     */
    private function verdeelPoulesOverMatten(Toernooi $toernooi): void
    {
        $matten = $toernooi->matten->sortBy('nummer');
        $matIds = $matten->pluck('id')->toArray();
        $matIdByNummer = $matten->pluck('id', 'nummer')->toArray();
        $voorkeuren = $toernooi->mat_voorkeuren ?? [];

        // Bouw voorkeur lookup: leeftijdsklasse => [mat_ids]
        $voorkeurMatten = [];
        foreach ($voorkeuren as $v) {
            $lk = $v['leeftijdsklasse'] ?? '';
            $matNrs = $v['matten'] ?? [];
            $voorkeurMatten[$lk] = array_filter(array_map(
                fn($nr) => $matIdByNummer[$nr] ?? null,
                $matNrs
            ));
        }

        foreach ($toernooi->blokken as $blok) {
            $wedstrijdenPerMat = array_fill_keys($matIds, 0);

            // Eerst poules met voorkeur
            $poulesMetVoorkeur = $blok->poules()
                ->whereIn('leeftijdsklasse', array_keys($voorkeurMatten))
                ->orderByDesc('aantal_wedstrijden')
                ->get();

            foreach ($poulesMetVoorkeur as $poule) {
                $toegestaneMatten = $voorkeurMatten[$poule->leeftijdsklasse] ?? $matIds;
                if (empty($toegestaneMatten)) $toegestaneMatten = $matIds;

                $besteMat = $this->vindMinsteWedstrijdenMatUitLijst($toegestaneMatten, $wedstrijdenPerMat);
                $poule->update(['mat_id' => $besteMat]);
                $wedstrijdenPerMat[$besteMat] += $poule->aantal_wedstrijden;
            }

            // Dan poules zonder voorkeur
            $poulesZonderVoorkeur = $blok->poules()
                ->whereNotIn('leeftijdsklasse', array_keys($voorkeurMatten))
                ->orderByDesc('aantal_wedstrijden')
                ->get();

            foreach ($poulesZonderVoorkeur as $poule) {
                $besteMat = $this->vindMinsteWedstrijdenMatUitLijst($matIds, $wedstrijdenPerMat);
                $poule->update(['mat_id' => $besteMat]);
                $wedstrijdenPerMat[$besteMat] += $poule->aantal_wedstrijden;
            }
        }
    }

    /**
     * Vind mat met minste wedstrijden uit een lijst van mat IDs
     */
    private function vindMinsteWedstrijdenMatUitLijst(array $matIds, array $wedstrijdenPerMat): int
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

    /**
     * Fallback: Simple greedy algorithm (original method)
     */
    private function genereerBlokMatVerdelingFallback(Toernooi $toernooi): void
    {
        $blokken = $toernooi->blokken;
        $matten = $toernooi->matten;
        $poules = $toernooi->poules()->orderBy('leeftijdsklasse')->orderBy('nummer')->get();

        // Track matches per block/mat
        $wedstrijdenPerBlok = array_fill_keys($blokken->pluck('id')->toArray(), 0);
        $wedstrijdenPerMat = [];
        foreach ($blokken as $blok) {
            $wedstrijdenPerMat[$blok->id] = array_fill_keys($matten->pluck('id')->toArray(), 0);
        }

        $blokkenArray = $blokken->values()->all();
        $mattenArray = $matten->values()->all();

        // Group pools by age category
        $poulesPerLeeftijd = $poules->groupBy('leeftijdsklasse');

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
