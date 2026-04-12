<?php

namespace App\Services\BlokMatVerdeling;

use App\Models\Poule;
use App\Models\Toernooi;

/**
 * ZaalOverzichtBuilder
 *
 * Builds the hall overview (zaaloverzicht) data structure for a tournament.
 * Collects all poules per block per mat, including eliminatie A/B-groep entries
 * and kruisfinale entries, with the correct judoka- and match counts.
 *
 * Extracted from BlokMatVerdelingService so that overview building can be
 * unit-tested in isolation and reused from other services.
 */
class ZaalOverzichtBuilder
{
    /**
     * Get hall overview (zaaloverzicht) for a tournament.
     *
     * Structure:
     *   [
     *     [
     *       'nummer' => int,
     *       'naam' => string,
     *       'weging_gesloten' => bool,
     *       'matten' => [
     *         matNummer => [
     *           'mat_naam' => string,
     *           'poules' => [ [...], ... ],
     *         ],
     *       ],
     *     ],
     *     ...
     *   ]
     */
    public function build(Toernooi $toernooi): array
    {
        $overzicht = [];
        $tolerantie = $toernooi->gewicht_tolerantie ?? 0;

        foreach ($toernooi->blokken()->with('poules.mat', 'poules.judokas', 'poules.wedstrijden')->get() as $blok) {
            $blokData = [
                'nummer' => $blok->nummer,
                'naam' => $blok->naam,
                'weging_gesloten' => $blok->weging_gesloten,
                'matten' => [],
            ];

            foreach ($toernooi->matten as $mat) {
                $poules = $blok->poules->where('mat_id', $mat->id);
                $bPoules = $blok->poules
                    ->where('type', 'eliminatie')
                    ->where('b_mat_id', $mat->id);

                $pouleEntries = collect();

                foreach ($poules as $p) {
                    if ($p->type === 'eliminatie') {
                        $pouleEntries->push($this->buildEliminatieAEntry($p));
                        continue;
                    }

                    if ($p->type === 'kruisfinale') {
                        $pouleEntries->push($this->buildKruisfinaleEntry($p));
                        continue;
                    }

                    $pouleEntries->push($this->buildVoorrondeEntry($p, $tolerantie));
                }

                foreach ($bPoules as $p) {
                    $pouleEntries->push($this->buildEliminatieBEntry($p));
                }

                $blokData['matten'][$mat->nummer] = [
                    'mat_naam' => $mat->label,
                    'poules' => $pouleEntries
                        ->filter(fn($p) => ($p['judokas'] ?? 0) > 1 || ($p['type'] ?? null) === 'kruisfinale')
                        ->values()
                        ->toArray(),
                ];
            }

            $overzicht[] = $blokData;
        }

        return $overzicht;
    }

    /**
     * Build base entry fields shared by all poule types.
     */
    private function baseEntry(Poule $p): array
    {
        return [
            'id' => $p->id,
            'nummer' => $p->nummer,
            'titel' => $p->getDisplayTitel(),
            'leeftijdsklasse' => $p->leeftijdsklasse,
            'gewichtsklasse' => $p->gewichtsklasse,
            'type' => $p->type,
        ];
    }

    private function buildEliminatieAEntry(Poule $p): array
    {
        $aWedstrijden = $p->wedstrijden->where('groep', 'A')->count();
        // Fallback: use A-bracket formula (N-1) if bracket not generated yet
        if ($aWedstrijden === 0 && $p->aantal_judokas > 0) {
            $aWedstrijden = $p->berekenAWedstrijden();
        }

        return $this->baseEntry($p) + [
            'groep' => 'A',
            'judokas' => $p->aantal_judokas,
            'wedstrijden' => $aWedstrijden,
        ];
    }

    private function buildEliminatieBEntry(Poule $p): array
    {
        $bWedstrijden = $p->wedstrijden->where('groep', 'B')->count();
        $bJudokas = max(0, $p->aantal_judokas - 2);
        // Fallback: use B-bracket formula if bracket not generated yet
        if ($bWedstrijden === 0 && $bJudokas > 0) {
            $bWedstrijden = $p->berekenBWedstrijden();
        }

        return $this->baseEntry($p) + [
            'groep' => 'B',
            'judokas' => $bJudokas,
            'wedstrijden' => $bWedstrijden,
        ];
    }

    private function buildKruisfinaleEntry(Poule $p): array
    {
        return $this->baseEntry($p) + [
            'judokas' => $p->aantal_judokas,
            'wedstrijden' => $p->aantal_wedstrijden,
        ];
    }

    /**
     * Voorronde: count only judokas within tolerance.
     */
    private function buildVoorrondeEntry(Poule $p, float $tolerantie): array
    {
        $actieveJudokas = $p->judokas->filter(
            fn($j) => !$j->moetUitPouleVerwijderd($tolerantie)
        )->count();

        return $this->baseEntry($p) + [
            'judokas' => $actieveJudokas,
            'wedstrijden' => $p->berekenAantalWedstrijden($actieveJudokas),
        ];
    }
}
