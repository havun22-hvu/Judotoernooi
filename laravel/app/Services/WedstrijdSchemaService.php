<?php

namespace App\Services;

use App\Models\Blok;
use App\Models\Mat;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use Illuminate\Support\Facades\DB;

class WedstrijdSchemaService
{
    /**
     * Generate match schedules for all pools in a block
     */
    public function genereerWedstrijdSchemas(Blok $blok): array
    {
        return DB::transaction(function () use ($blok) {
            $poules = $blok->poules()->with('judokas')->get();
            $gegenereerd = [];

            foreach ($poules as $poule) {
                $wedstrijden = $this->genereerWedstrijdenVoorPoule($poule);
                $gegenereerd[$poule->id] = count($wedstrijden);
            }

            return $gegenereerd;
        });
    }

    /**
     * Generate matches for a single pool
     */
    public function genereerWedstrijdenVoorPoule(Poule $poule): array
    {
        // Delete existing matches
        $poule->wedstrijden()->delete();

        $schema = $poule->genereerWedstrijdSchema();
        $wedstrijden = [];

        foreach ($schema as $index => $paar) {
            $wedstrijd = Wedstrijd::create([
                'poule_id' => $poule->id,
                'judoka_wit_id' => $paar[0],
                'judoka_blauw_id' => $paar[1],
                'volgorde' => $index + 1,
            ]);
            $wedstrijden[] = $wedstrijd;
        }

        return $wedstrijden;
    }

    /**
     * Get match schedule for a mat in a block
     */
    public function getSchemaVoorMat(Blok $blok, Mat $mat): array
    {
        $poules = Poule::where('blok_id', $blok->id)
            ->where('mat_id', $mat->id)
            ->with(['judokas', 'wedstrijden.judokaWit', 'wedstrijden.judokaBlauw'])
            ->get();

        $schema = [];

        foreach ($poules as $poule) {
            $pouleSchema = [
                'poule_id' => $poule->id,
                'titel' => $poule->titel,
                'spreker_klaar' => $poule->spreker_klaar !== null,
                'judokas' => $poule->judokas->map(fn($j) => [
                    'id' => $j->id,
                    'naam' => $j->naam,
                    'club' => $j->club?->naam,
                    'band' => $j->band,
                ])->toArray(),
                'wedstrijden' => $poule->wedstrijden->map(fn($w) => [
                    'id' => $w->id,
                    'volgorde' => $w->volgorde,
                    'wit' => [
                        'id' => $w->judokaWit->id,
                        'naam' => $w->judokaWit->naam,
                    ],
                    'blauw' => [
                        'id' => $w->judokaBlauw->id,
                        'naam' => $w->judokaBlauw->naam,
                    ],
                    'is_gespeeld' => $w->is_gespeeld,
                    'winnaar_id' => $w->winnaar_id,
                    'score_wit' => $w->score_wit,
                    'score_blauw' => $w->score_blauw,
                ])->toArray(),
            ];

            $schema[] = $pouleSchema;
        }

        return $schema;
    }

    /**
     * Register match result
     */
    public function registreerUitslag(
        Wedstrijd $wedstrijd,
        ?int $winnaarId,
        string $scoreWit = '',
        string $scoreBlauw = '',
        string $type = 'beslissing'
    ): void {
        DB::transaction(function () use ($wedstrijd, $winnaarId, $scoreWit, $scoreBlauw, $type) {
            $wedstrijd->update([
                'winnaar_id' => $winnaarId,
                'score_wit' => $scoreWit,
                'score_blauw' => $scoreBlauw,
                'uitslag_type' => $type,
                'is_gespeeld' => true,
                'gespeeld_op' => now(),
            ]);

            // Update pool standings
            $this->updatePouleStand($wedstrijd->poule);
        });
    }

    /**
     * Update pool standings after a match
     */
    private function updatePouleStand(Poule $poule): void
    {
        $judokas = $poule->judokas;
        $wedstrijden = $poule->wedstrijden()->where('is_gespeeld', true)->get();

        foreach ($judokas as $judoka) {
            $gewonnen = $wedstrijden->where('winnaar_id', $judoka->id)->count();
            $verloren = $wedstrijden->filter(fn($w) =>
                $w->winnaar_id !== null &&
                $w->winnaar_id !== $judoka->id &&
                ($w->judoka_wit_id === $judoka->id || $w->judoka_blauw_id === $judoka->id)
            )->count();
            $gelijk = $wedstrijden->filter(fn($w) =>
                $w->winnaar_id === null &&
                ($w->judoka_wit_id === $judoka->id || $w->judoka_blauw_id === $judoka->id)
            )->count();

            $punten = ($gewonnen * 10) + ($gelijk * 5);

            $poule->judokas()->updateExistingPivot($judoka->id, [
                'gewonnen' => $gewonnen,
                'verloren' => $verloren,
                'gelijk' => $gelijk,
                'punten' => $punten,
            ]);
        }

        // Calculate final positions
        $this->berekenEindpositie($poule);
    }

    /**
     * Calculate final positions in pool
     */
    private function berekenEindpositie(Poule $poule): void
    {
        $stand = $poule->judokas()
            ->orderByPivot('punten', 'desc')
            ->orderByPivot('gewonnen', 'desc')
            ->get();

        $positie = 1;
        foreach ($stand as $judoka) {
            $poule->judokas()->updateExistingPivot($judoka->id, [
                'eindpositie' => $positie++,
            ]);
        }
    }

    /**
     * Get pool standings
     */
    public function getPouleStand(Poule $poule): array
    {
        return $poule->judokas()
            ->orderByPivot('punten', 'desc')
            ->orderByPivot('gewonnen', 'desc')
            ->get()
            ->map(fn($judoka) => [
                'positie' => $judoka->pivot->eindpositie,
                'judoka_id' => $judoka->id,
                'naam' => $judoka->naam,
                'club' => $judoka->club?->naam,
                'gewonnen' => $judoka->pivot->gewonnen,
                'verloren' => $judoka->pivot->verloren,
                'gelijk' => $judoka->pivot->gelijk,
                'punten' => $judoka->pivot->punten,
            ])
            ->toArray();
    }
}
