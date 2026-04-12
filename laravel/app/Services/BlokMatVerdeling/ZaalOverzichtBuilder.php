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
                // Poules op deze mat (via mat_id)
                $poules = $blok->poules->where('mat_id', $mat->id);
                // Eliminatie B-groep entries: poules where b_mat_id points to this mat
                // (either same mat or different mat via split)
                $bPoules = $blok->poules
                    ->where('type', 'eliminatie')
                    ->where('b_mat_id', $mat->id);

                $pouleEntries = collect();

                foreach ($poules as $p) {
                    // Eliminatie: always show as A-groep entry (B is added separately below)
                    if ($p->type === 'eliminatie') {
                        $pouleEntries->push($this->buildEliminatieAEntry($p));
                        continue;
                    }

                    // Kruisfinale: use stored values
                    if ($p->type === 'kruisfinale') {
                        $pouleEntries->push($this->buildKruisfinaleEntry($p));
                        continue;
                    }

                    $pouleEntries->push($this->buildVoorrondeEntry($p, $tolerantie));
                }

                // B-groep entries for eliminatie poules where b_mat_id = this mat
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
     * Build eliminatie A-groep entry.
     */
    private function buildEliminatieAEntry(Poule $p): array
    {
        $aWedstrijden = $p->wedstrijden->where('groep', 'A')->count();
        // Fallback: use A-bracket formula (N-1) if bracket not generated yet
        if ($aWedstrijden === 0 && $p->aantal_judokas > 0) {
            $aWedstrijden = $p->berekenAWedstrijden();
        }

        return [
            'id' => $p->id,
            'nummer' => $p->nummer,
            'titel' => $p->getDisplayTitel(),
            'leeftijdsklasse' => $p->leeftijdsklasse,
            'gewichtsklasse' => $p->gewichtsklasse,
            'type' => $p->type,
            'groep' => 'A',
            'judokas' => $p->aantal_judokas,
            'wedstrijden' => $aWedstrijden,
        ];
    }

    /**
     * Build eliminatie B-groep entry.
     */
    private function buildEliminatieBEntry(Poule $p): array
    {
        $bWedstrijden = $p->wedstrijden->where('groep', 'B')->count();
        $bJudokas = max(0, $p->aantal_judokas - 2);
        // Fallback: use B-bracket formula if bracket not generated yet
        if ($bWedstrijden === 0 && $bJudokas > 0) {
            $bWedstrijden = $p->berekenBWedstrijden();
        }

        return [
            'id' => $p->id,
            'nummer' => $p->nummer,
            'titel' => $p->getDisplayTitel(),
            'leeftijdsklasse' => $p->leeftijdsklasse,
            'gewichtsklasse' => $p->gewichtsklasse,
            'type' => $p->type,
            'groep' => 'B',
            'judokas' => $bJudokas,
            'wedstrijden' => $bWedstrijden,
        ];
    }

    /**
     * Build kruisfinale entry.
     */
    private function buildKruisfinaleEntry(Poule $p): array
    {
        return [
            'id' => $p->id,
            'nummer' => $p->nummer,
            'titel' => $p->getDisplayTitel(),
            'leeftijdsklasse' => $p->leeftijdsklasse,
            'gewichtsklasse' => $p->gewichtsklasse,
            'type' => $p->type,
            'judokas' => $p->aantal_judokas,
            'wedstrijden' => $p->aantal_wedstrijden,
        ];
    }

    /**
     * Build voorronde entry, counting only active judokas (within tolerance).
     */
    private function buildVoorrondeEntry(Poule $p, float $tolerantie): array
    {
        $actieveJudokas = $p->judokas->filter(
            fn($j) => !$j->moetUitPouleVerwijderd($tolerantie)
        )->count();

        return [
            'id' => $p->id,
            'nummer' => $p->nummer,
            'titel' => $p->getDisplayTitel(),
            'leeftijdsklasse' => $p->leeftijdsklasse,
            'gewichtsklasse' => $p->gewichtsklasse,
            'type' => $p->type,
            'judokas' => $actieveJudokas,
            'wedstrijden' => $p->berekenAantalWedstrijden($actieveJudokas),
        ];
    }
}
