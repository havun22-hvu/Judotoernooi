<?php

namespace App\Services;

use App\Models\Blok;
use App\Models\Mat;
use App\Models\Poule;
use App\Models\Wedstrijd;
use Illuminate\Support\Facades\DB;

class WedstrijdSchemaService
{
    /**
     * Generate matches for a single pool
     * Only includes active judokas (not absent)
     * Note: For dynamic categories, weight class check is skipped (poule determines range)
     */
    public function genereerWedstrijdenVoorPoule(Poule $poule): array
    {
        // Delete existing matches
        $poule->wedstrijden()->delete();

        // Get tolerance from toernooi settings
        $tolerantie = $poule->toernooi?->gewicht_tolerantie ?? 0.5;

        // Check if this is a dynamic category (variable weight range)
        $isDynamisch = $poule->isDynamisch();

        // Refresh judokas from database to get current aanwezigheid status
        $poule->load('judokas');

        // Filter: only active judokas (not absent)
        // For dynamic categories: skip weight class check (poule determines who's in it)
        // For fixed categories: also check weight class
        $actieveJudokas = $poule->judokas->filter(function ($judoka) use ($tolerantie, $isDynamisch) {
            // Skip if absent
            if ($judoka->aanwezigheid === 'afwezig') {
                return false;
            }
            // For fixed categories: skip if weighed and outside weight class
            if (!$isDynamisch && $judoka->gewicht_gewogen !== null && !$judoka->isGewichtBinnenKlasse(null, $tolerantie)) {
                return false;
            }
            return true;
        });

        // Generate schema with only active judokas
        $schema = $this->genereerSchemaVoorJudokas($poule, $actieveJudokas->pluck('id')->toArray());
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
     * Generate match schema for given judoka IDs
     * Uses optimal match order to minimize consecutive matches for same judoka
     */
    private function genereerSchemaVoorJudokas(Poule $poule, array $judokaIds): array
    {
        $aantal = count($judokaIds);

        if ($aantal < 2) {
            return [];
        }

        // Get optimal order indices (1-based)
        $volgorde = $this->getOptimaleWedstrijdvolgorde($poule, $aantal);

        // Convert indices to actual judoka IDs
        $wedstrijden = [];
        foreach ($volgorde as $paar) {
            $wedstrijden[] = [
                $judokaIds[$paar[0] - 1],  // Convert 1-based to 0-based
                $judokaIds[$paar[1] - 1],
            ];
        }

        return $wedstrijden;
    }

    /**
     * Get optimal match order for given number of judokas
     */
    private function getOptimaleWedstrijdvolgorde(Poule $poule, int $aantal): array
    {
        // Barrage: altijd single round-robin (1x tegen elkaar)
        if ($poule->type === 'barrage') {
            return match ($aantal) {
                2 => [[1, 2]],
                3 => [[1, 2], [1, 3], [2, 3]],
                default => $this->genereerRoundRobinSchema($aantal),
            };
        }

        // Check if tournament has custom schemas (fresh load to avoid stale cache)
        $toernooi = $poule->toernooi()->first();
        $customSchemas = $toernooi?->wedstrijd_schemas ?? [];

        // JSON decode can return string keys, so check both int and string
        if (!empty($customSchemas[$aantal]) || !empty($customSchemas[(string) $aantal])) {
            return $customSchemas[$aantal] ?? $customSchemas[(string) $aantal];
        }

        // Check if we need double matches (2 or 3 judokas)
        $dubbelBij2 = $toernooi?->dubbel_bij_2_judokas ?? true;
        $bestOfThreeBij2 = $toernooi?->best_of_three_bij_2 ?? false;
        $dubbelBij3 = $toernooi?->dubbel_bij_3_judokas ?? true;

        // Default optimized schemas
        // For 2 judokas: Best of Three (3 matches) > Dubbel (2 matches) > Single (1 match)
        return match ($aantal) {
            2 => $bestOfThreeBij2 ? [[1, 2], [2, 1], [1, 2]] : ($dubbelBij2 ? [[1, 2], [2, 1]] : [[1, 2]]),
            3 => $dubbelBij3 ? [[1, 2], [1, 3], [2, 3], [2, 1], [3, 2], [3, 1]] : [[1, 2], [1, 3], [2, 3]],
            4 => [[1, 2], [3, 4], [2, 3], [1, 4], [2, 4], [1, 3]],
            5 => [[1, 2], [3, 4], [1, 5], [2, 3], [4, 5], [1, 3], [2, 4], [3, 5], [1, 4], [2, 5]],
            6 => [[1, 2], [3, 4], [5, 6], [1, 3], [2, 5], [4, 6], [3, 5], [2, 4], [1, 6], [2, 3], [4, 5], [3, 6], [1, 4], [2, 6], [1, 5]],
            7 => [[1, 2], [3, 4], [5, 6], [1, 7], [2, 3], [4, 5], [6, 7], [1, 3], [2, 4], [5, 7], [3, 6], [1, 4], [2, 5], [3, 7], [4, 6], [1, 5], [2, 6], [4, 7], [1, 6], [3, 5], [2, 7]],
            default => $this->genereerRoundRobinSchema($aantal),
        };
    }

    /**
     * Generate round-robin schema using circle method
     */
    private function genereerRoundRobinSchema(int $n): array
    {
        $wedstrijden = [];
        $judokas = range(1, $n);

        // Add dummy for odd numbers
        if ($n % 2 !== 0) {
            $judokas[] = null;
        }

        $totaal = count($judokas);

        for ($ronde = 0; $ronde < $totaal - 1; $ronde++) {
            for ($i = 0; $i < $totaal / 2; $i++) {
                $j = $totaal - 1 - $i;
                $judoka1 = $judokas[$i];
                $judoka2 = $judokas[$j];

                if ($judoka1 !== null && $judoka2 !== null) {
                    $wedstrijden[] = [$judoka1, $judoka2];
                }
            }

            // Rotate (first position stays fixed)
            $laatste = array_pop($judokas);
            array_splice($judokas, 1, 0, [$laatste]);
        }

        return $wedstrijden;
    }

    /**
     * Get match schedule for a mat in a block
     * Returns mat-level green/yellow/blue selection plus all poules
     */
    public function getSchemaVoorMat(Blok $blok, Mat $mat): array
    {
        // Refresh mat to get latest wedstrijd selectie
        $mat->refresh();

        // Alleen poules die doorgestuurd zijn EN wedstrijden hebben
        // Poules zonder wedstrijden mogen niet op mat interface verschijnen
        $poules = Poule::where('blok_id', $blok->id)
            ->where('mat_id', $mat->id)
            ->whereNotNull('doorgestuurd_op')
            ->whereHas('wedstrijden')  // Moet minimaal 1 wedstrijd hebben
            ->with(['judokas', 'wedstrijden.judokaWit', 'wedstrijden.judokaBlauw', 'wedstrijden.winnaar', 'mat'])
            ->get();

        $pouleSchemas = [];

        foreach ($poules as $poule) {
            $isEliminatie = $poule->type === 'eliminatie';

            // Tel alleen aanwezige judoka's (niet afwezig)
            $judokaCount = $poule->judokas->filter(fn($j) => $j->aanwezigheid !== 'afwezig')->count();

            $pouleSchema = [
                'poule_id' => $poule->id,
                'poule_nummer' => $poule->nummer,
                'type' => $poule->type ?? 'poule',
                'leeftijdsklasse' => $poule->leeftijdsklasse,
                'gewichtsklasse' => $poule->gewichtsklasse,
                'blok_nummer' => $blok->nummer,
                'mat_nummer' => $mat->nummer,
                'titel' => $poule->getDisplayTitel(),
                'judoka_count' => $judokaCount,
                'spreker_klaar' => $poule->spreker_klaar !== null,
                'spreker_klaar_tijd' => $poule->spreker_klaar ? $poule->spreker_klaar->format('H:i') : null,
                // DEPRECATED - kept for backwards compatibility during transition
                'huidige_wedstrijd_id' => $poule->huidige_wedstrijd_id,
                'actieve_wedstrijd_id' => $poule->actieve_wedstrijd_id,
                'judokas' => $poule->judokas
                    ->filter(fn($j) => $j->aanwezigheid !== 'afwezig')
                    ->map(fn($j) => [
                        'id' => $j->id,
                        'naam' => $j->naam,
                        'gewichtsklasse' => $j->gewichtsklasse,
                        'club' => $j->club?->naam,
                        'band' => $j->band,
                    ])->values()->toArray(),
                'wedstrijden' => $poule->wedstrijden->map(function ($w) use ($isEliminatie) {
                    $wedstrijd = [
                        'id' => $w->id,
                        'volgorde' => $w->volgorde,
                        'wit' => $w->judokaWit ? [
                            'id' => $w->judokaWit->id,
                            'naam' => $w->judokaWit->naam,
                        ] : null,
                        'blauw' => $w->judokaBlauw ? [
                            'id' => $w->judokaBlauw->id,
                            'naam' => $w->judokaBlauw->naam,
                        ] : null,
                        'is_gespeeld' => $w->is_gespeeld,
                        'winnaar_id' => $w->winnaar_id,
                        'score_wit' => $w->score_wit,
                        'score_blauw' => $w->score_blauw,
                    ];

                    // Add elimination-specific fields
                    if ($isEliminatie) {
                        $wedstrijd['groep'] = $w->groep;
                        $wedstrijd['ronde'] = $w->ronde;
                        $wedstrijd['bracket_positie'] = $w->bracket_positie;
                        $wedstrijd['volgende_wedstrijd_id'] = $w->volgende_wedstrijd_id;
                        $wedstrijd['winnaar_naar_slot'] = $w->winnaar_naar_slot;
                        $wedstrijd['uitslag_type'] = $w->uitslag_type;
                        $wedstrijd['locatie_wit'] = $w->locatie_wit;
                        $wedstrijd['locatie_blauw'] = $w->locatie_blauw;
                    }

                    return $wedstrijd;
                })->toArray(),
            ];

            $pouleSchemas[] = $pouleSchema;
        }

        // Clean up invalid selections (gespeelde wedstrijden)
        $mat->cleanupGespeeldeSelecties();

        // Return with mat-level selection (groen/geel/blauw)
        return [
            'mat' => [
                'id' => $mat->id,
                'nummer' => $mat->nummer,
                'actieve_wedstrijd_id' => $mat->actieve_wedstrijd_id,
                'volgende_wedstrijd_id' => $mat->volgende_wedstrijd_id,
                'gereedmaken_wedstrijd_id' => $mat->gereedmaken_wedstrijd_id,
            ],
            'poules' => $pouleSchemas,
        ];
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
