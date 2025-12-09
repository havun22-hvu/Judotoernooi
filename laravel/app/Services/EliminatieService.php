<?php

namespace App\Services;

use App\Models\Judoka;
use App\Models\Poule;
use App\Models\Wedstrijd;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EliminatieService
{
    /**
     * Ronde namen mapping
     */
    private const RONDE_NAMEN = [
        1 => 'finale',
        2 => 'halve_finale',
        4 => 'kwartfinale',
        8 => 'achtste_finale',
        16 => 'zestiende_finale',
        32 => 'tweeendertigste_finale',
    ];

    /**
     * Generate elimination bracket for a poule
     *
     * @param Poule $poule The poule to generate bracket for
     * @param Collection|null $judokas Optional: specific judokas, otherwise uses poule judokas
     * @return array Statistics about generated bracket
     */
    public function genereerBracket(Poule $poule, ?Collection $judokas = null): array
    {
        $judokas = $judokas ?? $poule->judokas;
        $aantal = $judokas->count();

        if ($aantal < 2) {
            return ['error' => 'Minimaal 2 judoka\'s nodig voor eliminatie'];
        }

        return DB::transaction(function () use ($poule, $judokas, $aantal) {
            // Delete existing matches for this poule
            $poule->wedstrijden()->delete();

            // Calculate bracket size (smallest power of 2 >= aantal)
            $bracketGrootte = $this->berekenBracketGrootte($aantal);
            $aantalByes = $bracketGrootte - $aantal;

            // Seed judokas (by band, then random)
            $geseededJudokas = $this->seedJudokas($judokas);

            // Generate main bracket (Groep A)
            $hoofdboomWedstrijden = $this->genereerHoofdboom($poule, $geseededJudokas, $bracketGrootte, $aantalByes);

            // Generate repechage bracket (Groep B)
            $herkansingsWedstrijden = $this->genereerHerkansing($poule, $hoofdboomWedstrijden);

            // Generate bronze medal matches
            $bronsWedstrijden = $this->genereerBronsWedstrijden($poule, $hoofdboomWedstrijden, $herkansingsWedstrijden);

            // Update poule statistics
            $totaalWedstrijden = count($hoofdboomWedstrijden) + count($herkansingsWedstrijden) + count($bronsWedstrijden);
            $poule->update([
                'aantal_wedstrijden' => $totaalWedstrijden,
            ]);

            return [
                'bracket_grootte' => $bracketGrootte,
                'aantal_byes' => $aantalByes,
                'hoofdboom_wedstrijden' => count($hoofdboomWedstrijden),
                'herkansing_wedstrijden' => count($herkansingsWedstrijden),
                'brons_wedstrijden' => count($bronsWedstrijden),
                'totaal_wedstrijden' => $totaalWedstrijden,
            ];
        });
    }

    /**
     * Calculate smallest power of 2 >= n
     */
    private function berekenBracketGrootte(int $n): int
    {
        $power = 1;
        while ($power < $n) {
            $power *= 2;
        }
        return $power;
    }

    /**
     * Get round name for number of matches in round
     */
    private function getRondeNaam(int $aantalWedstrijden): string
    {
        return self::RONDE_NAMEN[$aantalWedstrijden] ?? "ronde_{$aantalWedstrijden}";
    }

    /**
     * Seed judokas by band (highest first), then random
     */
    private function seedJudokas(Collection $judokas): array
    {
        $bandVolgorde = [
            'zwart' => 0,
            'bruin' => 1,
            'blauw' => 2,
            'groen' => 3,
            'oranje' => 4,
            'geel' => 5,
            'wit' => 6,
        ];

        return $judokas->sortBy(function ($judoka) use ($bandVolgorde) {
            $bandScore = $bandVolgorde[$judoka->band] ?? 7;
            return [$bandScore, random_int(0, 1000)];
        })->values()->all();
    }

    /**
     * Generate main bracket (Groep A)
     */
    private function genereerHoofdboom(Poule $poule, array $judokas, int $bracketGrootte, int $aantalByes): array
    {
        $wedstrijden = [];
        $volgorde = 1;

        // Place judokas in bracket positions with byes for top seeds
        $bracketPosities = $this->verdeelInBracket($judokas, $bracketGrootte, $aantalByes);

        // Generate matches round by round, starting from first round
        $huidigeRondeWedstrijden = [];
        $aantalWedstrijdenInRonde = $bracketGrootte / 2;

        // First round
        $rondeNaam = $this->getRondeNaam($aantalWedstrijdenInRonde);

        for ($i = 0; $i < $aantalWedstrijdenInRonde; $i++) {
            $positieWit = $i * 2;
            $positieBlauw = $i * 2 + 1;

            $judokaWit = $bracketPosities[$positieWit] ?? null;
            $judokaBlauw = $bracketPosities[$positieBlauw] ?? null;

            // Skip if both are bye (shouldn't happen with proper distribution)
            if ($judokaWit === null && $judokaBlauw === null) {
                continue;
            }

            $wedstrijd = Wedstrijd::create([
                'poule_id' => $poule->id,
                'judoka_wit_id' => $judokaWit?->id,
                'judoka_blauw_id' => $judokaBlauw?->id,
                'volgorde' => $volgorde++,
                'ronde' => $rondeNaam,
                'groep' => 'A',
                'bracket_positie' => $i + 1,
            ]);

            $huidigeRondeWedstrijden[] = $wedstrijd;
            $wedstrijden[] = $wedstrijd;

            // If one is a bye, mark winner automatically
            if ($judokaWit === null || $judokaBlauw === null) {
                $winnaarId = $judokaWit?->id ?? $judokaBlauw?->id;
                $wedstrijd->update([
                    'winnaar_id' => $winnaarId,
                    'is_gespeeld' => true,
                    'uitslag_type' => 'bye',
                ]);
            }
        }

        // Generate subsequent rounds
        while (count($huidigeRondeWedstrijden) > 1) {
            $volgendeRondeWedstrijden = [];
            $aantalWedstrijdenInRonde = count($huidigeRondeWedstrijden) / 2;
            $rondeNaam = $this->getRondeNaam((int)$aantalWedstrijdenInRonde);

            for ($i = 0; $i < $aantalWedstrijdenInRonde; $i++) {
                $wedstrijd1 = $huidigeRondeWedstrijden[$i * 2];
                $wedstrijd2 = $huidigeRondeWedstrijden[$i * 2 + 1];

                $volgendeWedstrijd = Wedstrijd::create([
                    'poule_id' => $poule->id,
                    'judoka_wit_id' => null, // Filled when previous matches complete
                    'judoka_blauw_id' => null,
                    'volgorde' => $volgorde++,
                    'ronde' => $rondeNaam,
                    'groep' => 'A',
                    'bracket_positie' => $i + 1,
                ]);

                // Link previous matches to this one
                $wedstrijd1->update([
                    'volgende_wedstrijd_id' => $volgendeWedstrijd->id,
                    'winnaar_naar_slot' => 'wit',
                ]);
                $wedstrijd2->update([
                    'volgende_wedstrijd_id' => $volgendeWedstrijd->id,
                    'winnaar_naar_slot' => 'blauw',
                ]);

                // Auto-advance bye winners
                $this->processAutoAdvance($wedstrijd1, $volgendeWedstrijd, 'wit');
                $this->processAutoAdvance($wedstrijd2, $volgendeWedstrijd, 'blauw');

                $volgendeRondeWedstrijden[] = $volgendeWedstrijd;
                $wedstrijden[] = $volgendeWedstrijd;
            }

            $huidigeRondeWedstrijden = $volgendeRondeWedstrijden;
        }

        return $wedstrijden;
    }

    /**
     * Process automatic advancement for bye winners
     */
    private function processAutoAdvance(Wedstrijd $wedstrijd, Wedstrijd $volgendeWedstrijd, string $slot): void
    {
        if ($wedstrijd->is_gespeeld && $wedstrijd->winnaar_id) {
            $volgendeWedstrijd->update([
                "judoka_{$slot}_id" => $wedstrijd->winnaar_id,
            ]);
        }
    }

    /**
     * Distribute judokas in bracket with byes for top seeds
     */
    private function verdeelInBracket(array $judokas, int $bracketGrootte, int $aantalByes): array
    {
        $posities = array_fill(0, $bracketGrootte, null);

        // Standard seeding positions for power of 2 brackets
        // Seed 1 vs lowest, Seed 2 vs second lowest, etc.
        $seedPosities = $this->getSeedPosities($bracketGrootte);

        $judokaIndex = 0;
        $byeCount = 0;

        foreach ($seedPosities as $positie) {
            if ($byeCount < $aantalByes && $judokaIndex < count($judokas)) {
                // Top seeds get byes - place judoka, opponent position stays null
                $posities[$positie] = $judokas[$judokaIndex++] ?? null;
                $byeCount++;
            } elseif ($judokaIndex < count($judokas)) {
                $posities[$positie] = $judokas[$judokaIndex++] ?? null;
            }
        }

        // Fill remaining positions
        for ($i = 0; $i < $bracketGrootte && $judokaIndex < count($judokas); $i++) {
            if ($posities[$i] === null) {
                $posities[$i] = $judokas[$judokaIndex++];
            }
        }

        return $posities;
    }

    /**
     * Get standard seeding positions for bracket
     */
    private function getSeedPosities(int $bracketGrootte): array
    {
        // For a bracket of size n, seed positions follow a pattern
        // Seed 1 at position 0, Seed 2 at position n-1, etc.
        if ($bracketGrootte === 2) {
            return [0, 1];
        }
        if ($bracketGrootte === 4) {
            return [0, 3, 2, 1];
        }
        if ($bracketGrootte === 8) {
            return [0, 7, 4, 3, 2, 5, 6, 1];
        }
        if ($bracketGrootte === 16) {
            return [0, 15, 8, 7, 4, 11, 12, 3, 2, 13, 10, 5, 6, 9, 14, 1];
        }

        // Fallback: sequential
        return range(0, $bracketGrootte - 1);
    }

    /**
     * Generate repechage bracket (Groep B)
     */
    private function genereerHerkansing(Poule $poule, array $hoofdboomWedstrijden): array
    {
        $herkansingsWedstrijden = [];
        $volgorde = count($hoofdboomWedstrijden) + 1;

        // Group hoofdboom matches by round
        $perRonde = collect($hoofdboomWedstrijden)->groupBy('ronde');

        // Skip finale - no repechage for finale loser (they get silver)
        $rondesVoorHerkansing = $perRonde->except(['finale']);

        if ($rondesVoorHerkansing->isEmpty()) {
            return [];
        }

        $herkansingRonde = 1;
        $vorigeHerkansingWedstrijden = [];

        foreach ($rondesVoorHerkansing as $rondeNaam => $wedstrijden) {
            $verliezersUitRonde = $wedstrijden->count();

            // Matches in this repechage round
            $nieuweHerkansingWedstrijden = [];

            if (empty($vorigeHerkansingWedstrijden)) {
                // First repechage round: losers face each other
                $aantalWedstrijden = intdiv($verliezersUitRonde, 2);

                for ($i = 0; $i < $aantalWedstrijden; $i++) {
                    $wedstrijd = Wedstrijd::create([
                        'poule_id' => $poule->id,
                        'judoka_wit_id' => null, // Filled when loser is determined
                        'judoka_blauw_id' => null,
                        'volgorde' => $volgorde++,
                        'ronde' => "herkansing_r{$herkansingRonde}",
                        'groep' => 'B',
                        'bracket_positie' => $i + 1,
                    ]);

                    // Link main bracket losers to this repechage match
                    $bronWedstrijd1 = $wedstrijden[$i * 2] ?? null;
                    $bronWedstrijd2 = $wedstrijden[$i * 2 + 1] ?? null;

                    if ($bronWedstrijd1) {
                        $bronWedstrijd1->update([
                            'herkansing_wedstrijd_id' => $wedstrijd->id,
                            'verliezer_naar_slot' => 'wit',
                        ]);
                    }
                    if ($bronWedstrijd2) {
                        $bronWedstrijd2->update([
                            'herkansing_wedstrijd_id' => $wedstrijd->id,
                            'verliezer_naar_slot' => 'blauw',
                        ]);
                    }

                    $nieuweHerkansingWedstrijden[] = $wedstrijd;
                    $herkansingsWedstrijden[] = $wedstrijd;
                }
            } else {
                // Subsequent repechage rounds: previous winners + new losers
                $aantalVorigeWinnaars = count($vorigeHerkansingWedstrijden);
                $aantalNieuwVerliezers = $verliezersUitRonde;

                // Create matches: vorige winnaar vs nieuwe verliezer
                $aantalWedstrijden = min($aantalVorigeWinnaars, $aantalNieuwVerliezers);

                for ($i = 0; $i < $aantalWedstrijden; $i++) {
                    $wedstrijd = Wedstrijd::create([
                        'poule_id' => $poule->id,
                        'judoka_wit_id' => null,
                        'judoka_blauw_id' => null,
                        'volgorde' => $volgorde++,
                        'ronde' => "herkansing_r{$herkansingRonde}",
                        'groep' => 'B',
                        'bracket_positie' => $i + 1,
                    ]);

                    // Link previous repechage winner
                    $vorigeWedstrijd = $vorigeHerkansingWedstrijden[$i] ?? null;
                    if ($vorigeWedstrijd) {
                        $vorigeWedstrijd->update([
                            'volgende_wedstrijd_id' => $wedstrijd->id,
                            'winnaar_naar_slot' => 'wit',
                        ]);
                    }

                    // Link main bracket loser
                    $bronWedstrijd = $wedstrijden->values()[$i] ?? null;
                    if ($bronWedstrijd) {
                        $bronWedstrijd->update([
                            'herkansing_wedstrijd_id' => $wedstrijd->id,
                            'verliezer_naar_slot' => 'blauw',
                        ]);
                    }

                    $nieuweHerkansingWedstrijden[] = $wedstrijd;
                    $herkansingsWedstrijden[] = $wedstrijd;
                }
            }

            $vorigeHerkansingWedstrijden = $nieuweHerkansingWedstrijden;
            $herkansingRonde++;
        }

        return $herkansingsWedstrijden;
    }

    /**
     * Generate bronze medal matches
     * Losers of semi-finals vs winners of repechage
     */
    private function genereerBronsWedstrijden(Poule $poule, array $hoofdboomWedstrijden, array $herkansingsWedstrijden): array
    {
        $bronsWedstrijden = [];
        $volgorde = count($hoofdboomWedstrijden) + count($herkansingsWedstrijden) + 1;

        // Find halve finale matches
        $halveFinales = collect($hoofdboomWedstrijden)->where('ronde', 'halve_finale');

        if ($halveFinales->count() < 2) {
            return []; // Not enough for bronze matches
        }

        // Find last repechage round winners (if any)
        $laatsteHerkansing = collect($herkansingsWedstrijden)
            ->sortByDesc('ronde')
            ->groupBy('ronde')
            ->first();

        // Create 2 bronze matches
        $halveFinaleArray = $halveFinales->values()->all();

        for ($i = 0; $i < 2 && $i < count($halveFinaleArray); $i++) {
            $bronsWedstrijd = Wedstrijd::create([
                'poule_id' => $poule->id,
                'judoka_wit_id' => null, // Semi-final loser
                'judoka_blauw_id' => null, // Repechage winner
                'volgorde' => $volgorde++,
                'ronde' => 'brons',
                'groep' => 'A', // Bronze is part of main results
                'bracket_positie' => $i + 1,
            ]);

            // Link semi-final loser to bronze match
            $halveFinaleArray[$i]->update([
                'herkansing_wedstrijd_id' => $bronsWedstrijd->id,
                'verliezer_naar_slot' => 'wit',
            ]);

            // Link repechage winner if available
            if ($laatsteHerkansing && isset($laatsteHerkansing[$i])) {
                $laatsteHerkansing[$i]->update([
                    'volgende_wedstrijd_id' => $bronsWedstrijd->id,
                    'winnaar_naar_slot' => 'blauw',
                ]);
            }

            $bronsWedstrijden[] = $bronsWedstrijd;
        }

        return $bronsWedstrijden;
    }

    /**
     * Process match result and advance winner/loser to next matches
     */
    public function verwerkUitslag(Wedstrijd $wedstrijd, int $winnaarId): void
    {
        $verliezerId = $wedstrijd->winnaar_id === $wedstrijd->judoka_wit_id
            ? $wedstrijd->judoka_blauw_id
            : $wedstrijd->judoka_wit_id;

        // Advance winner to next match
        if ($wedstrijd->volgende_wedstrijd_id && $wedstrijd->winnaar_naar_slot) {
            $volgendeWedstrijd = Wedstrijd::find($wedstrijd->volgende_wedstrijd_id);
            if ($volgendeWedstrijd) {
                $slot = $wedstrijd->winnaar_naar_slot;
                $volgendeWedstrijd->update([
                    "judoka_{$slot}_id" => $winnaarId,
                ]);
            }
        }

        // Send loser to repechage (if not from repechage or finale)
        if ($wedstrijd->herkansing_wedstrijd_id && $wedstrijd->verliezer_naar_slot && $verliezerId) {
            $herkansingWedstrijd = Wedstrijd::find($wedstrijd->herkansing_wedstrijd_id);
            if ($herkansingWedstrijd) {
                $slot = $wedstrijd->verliezer_naar_slot;
                $herkansingWedstrijd->update([
                    "judoka_{$slot}_id" => $verliezerId,
                ]);
            }
        }
    }

    /**
     * Get bracket structure for display
     */
    public function getBracketStructuur(Poule $poule): array
    {
        $wedstrijden = $poule->wedstrijden()
            ->with(['judokaWit', 'judokaBlauw', 'winnaar'])
            ->orderBy('volgorde')
            ->get();

        return [
            'hoofdboom' => $wedstrijden->where('groep', 'A')->whereNotIn('ronde', ['brons'])->groupBy('ronde'),
            'herkansing' => $wedstrijden->where('groep', 'B')->groupBy('ronde'),
            'brons' => $wedstrijden->where('ronde', 'brons'),
        ];
    }
}
