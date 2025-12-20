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

            // Calculate target size (largest power of 2 <= aantal)
            $doelGrootte = $this->berekenDoelGrootte($aantal);

            // How many need to be eliminated in preliminary round?
            $aantalVoorronde = $aantal - $doelGrootte;

            // Seed judokas (random)
            $geseededJudokas = $this->seedJudokas($judokas);

            // Generate main bracket (Groep A) with optional preliminary round
            $hoofdboomWedstrijden = $this->genereerHoofdboom($poule, $geseededJudokas, $doelGrootte, $aantalVoorronde);

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
                'doel_grootte' => $doelGrootte,
                'voorronde_wedstrijden' => $aantalVoorronde,
                'hoofdboom_wedstrijden' => count($hoofdboomWedstrijden),
                'herkansing_wedstrijden' => count($herkansingsWedstrijden),
                'brons_wedstrijden' => count($bronsWedstrijden),
                'totaal_wedstrijden' => $totaalWedstrijden,
            ];
        });
    }

    /**
     * Calculate largest power of 2 <= n
     * We want to reduce TO this number via preliminary round
     */
    private function berekenDoelGrootte(int $n): int
    {
        $power = 1;
        while ($power * 2 <= $n) {
            $power *= 2;
        }
        return $power;
    }

    /**
     * Calculate smallest power of 2 >= n (for herkansing)
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
     * Seed judokas - eerlijke loting (puur random)
     */
    private function seedJudokas(Collection $judokas): array
    {
        return $judokas->shuffle()->values()->all();
    }

    /**
     * Generate main bracket (Groep A)
     * Met optionele voorronde om naar doelGrootte te komen
     */
    private function genereerHoofdboom(Poule $poule, array $judokas, int $doelGrootte, int $aantalVoorronde): array
    {
        $wedstrijden = [];
        $volgorde = 1;

        // Judokas voor voorronde (2x aantalVoorronde spelen, rest krijgt bye)
        $voorrondeJudokas = array_slice($judokas, 0, $aantalVoorronde * 2);
        $byeJudokas = array_slice($judokas, $aantalVoorronde * 2);

        $hoofdboomJudokas = $byeJudokas; // Start met judokas die voorronde skippen

        // === VOORRONDE (indien nodig) ===
        if ($aantalVoorronde > 0) {
            for ($i = 0; $i < $aantalVoorronde; $i++) {
                $judokaWit = $voorrondeJudokas[$i * 2] ?? null;
                $judokaBlauw = $voorrondeJudokas[$i * 2 + 1] ?? null;

                $wedstrijd = Wedstrijd::create([
                    'poule_id' => $poule->id,
                    'judoka_wit_id' => $judokaWit?->id,
                    'judoka_blauw_id' => $judokaBlauw?->id,
                    'volgorde' => $volgorde++,
                    'ronde' => 'voorronde',
                    'groep' => 'A',
                    'bracket_positie' => $i + 1,
                ]);

                $wedstrijden[] = $wedstrijd;
            }
        }

        // === HOOFDBRACKET (vanaf doelGrootte) ===
        // Bouw bracket van doelGrootte naar finale
        $aantalWedstrijdenInRonde = $doelGrootte / 2;
        $rondeNaam = $this->getRondeNaam($aantalWedstrijdenInRonde);

        $huidigeRondeWedstrijden = [];

        // Eerste ronde van hoofdbracket
        // Slots worden gevuld door: byeJudokas + winnaars voorronde
        $byeIndex = 0;
        $voorrondeWedstrijdIndex = 0;
        $voorrondeWedstrijden = array_filter($wedstrijden, fn($w) => $w->ronde === 'voorronde');

        for ($i = 0; $i < $aantalWedstrijdenInRonde; $i++) {
            $wedstrijd = Wedstrijd::create([
                'poule_id' => $poule->id,
                'judoka_wit_id' => null,
                'judoka_blauw_id' => null,
                'volgorde' => $volgorde++,
                'ronde' => $rondeNaam,
                'groep' => 'A',
                'bracket_positie' => $i + 1,
            ]);

            // Vul wit slot
            if ($byeIndex < count($byeJudokas)) {
                // Bye judoka gaat direct naar dit slot
                $wedstrijd->update(['judoka_wit_id' => $byeJudokas[$byeIndex]->id]);
                $byeIndex++;
            } elseif ($voorrondeWedstrijdIndex < count($voorrondeWedstrijden)) {
                // Link voorronde winnaar naar dit slot
                $voorrondeWed = array_values($voorrondeWedstrijden)[$voorrondeWedstrijdIndex];
                $voorrondeWed->update([
                    'volgende_wedstrijd_id' => $wedstrijd->id,
                    'winnaar_naar_slot' => 'wit',
                ]);
                $voorrondeWedstrijdIndex++;
            }

            // Vul blauw slot
            if ($byeIndex < count($byeJudokas)) {
                $wedstrijd->update(['judoka_blauw_id' => $byeJudokas[$byeIndex]->id]);
                $byeIndex++;
            } elseif ($voorrondeWedstrijdIndex < count($voorrondeWedstrijden)) {
                $voorrondeWed = array_values($voorrondeWedstrijden)[$voorrondeWedstrijdIndex];
                $voorrondeWed->update([
                    'volgende_wedstrijd_id' => $wedstrijd->id,
                    'winnaar_naar_slot' => 'blauw',
                ]);
                $voorrondeWedstrijdIndex++;
            }

            $huidigeRondeWedstrijden[] = $wedstrijd;
            $wedstrijden[] = $wedstrijd;
        }

        // === VOLGENDE RONDES ===
        while (count($huidigeRondeWedstrijden) > 1) {
            $volgendeRondeWedstrijden = [];
            $aantalWedstrijdenInRonde = (int) floor(count($huidigeRondeWedstrijden) / 2);
            $rondeNaam = $this->getRondeNaam($aantalWedstrijdenInRonde);

            for ($i = 0; $i < $aantalWedstrijdenInRonde; $i++) {
                $wedstrijd1 = $huidigeRondeWedstrijden[$i * 2];
                $wedstrijd2 = $huidigeRondeWedstrijden[$i * 2 + 1];

                $volgendeWedstrijd = Wedstrijd::create([
                    'poule_id' => $poule->id,
                    'judoka_wit_id' => null,
                    'judoka_blauw_id' => null,
                    'volgorde' => $volgorde++,
                    'ronde' => $rondeNaam,
                    'groep' => 'A',
                    'bracket_positie' => $i + 1,
                ]);

                $wedstrijd1->update([
                    'volgende_wedstrijd_id' => $volgendeWedstrijd->id,
                    'winnaar_naar_slot' => 'wit',
                ]);
                $wedstrijd2->update([
                    'volgende_wedstrijd_id' => $volgendeWedstrijd->id,
                    'winnaar_naar_slot' => 'blauw',
                ]);

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
     * Verliezers uit voorronde (of eerste ronde als geen voorronde) vormen apart afvalsysteem
     */
    private function genereerHerkansing(Poule $poule, array $hoofdboomWedstrijden): array
    {
        $herkansingsWedstrijden = [];
        $volgorde = count($hoofdboomWedstrijden) + 1;

        // Check of er voorronde wedstrijden zijn
        $voorrondeWedstrijden = collect($hoofdboomWedstrijden)->where('ronde', 'voorronde');

        if ($voorrondeWedstrijden->count() > 0) {
            // Verliezers van voorronde gaan naar herkansing
            $bronWedstrijden = $voorrondeWedstrijden;
        } else {
            // Geen voorronde - gebruik eerste ronde van hoofdboom
            $perRonde = collect($hoofdboomWedstrijden)->groupBy('ronde');
            $eersteRondeNaam = $perRonde->sortByDesc(fn($weds) => $weds->count())->keys()->first();
            $bronWedstrijden = $perRonde->get($eersteRondeNaam);
        }

        // Filter alleen wedstrijden met 2 judoka's (geen byes)
        $echteWedstrijden = $bronWedstrijden->filter(fn($w) => $w->judoka_wit_id && $w->judoka_blauw_id);

        if ($echteWedstrijden->count() < 2) {
            return []; // Niet genoeg verliezers voor herkansing
        }

        // Aantal verliezers = aantal echte wedstrijden
        $aantalVerliezers = $echteWedstrijden->count();
        $echteWedstrijdenArray = $echteWedstrijden->values()->all();

        // Herkansing: direct koppelen, geen byes nodig
        $huidigeRondeWedstrijden = [];
        $aantalWedstrijdenInRonde = intdiv($aantalVerliezers, 2);
        $herkansingRonde = 1;

        // Eerste ronde: verliezers direct koppelen
        for ($i = 0; $i < $aantalWedstrijdenInRonde; $i++) {
            $wedstrijd = Wedstrijd::create([
                'poule_id' => $poule->id,
                'judoka_wit_id' => null,
                'judoka_blauw_id' => null,
                'volgorde' => $volgorde++,
                'ronde' => "herkansing_r{$herkansingRonde}",
                'groep' => 'B',
                'bracket_positie' => $i + 1,
            ]);

            // Link verliezers uit hoofdboom
            $bronWedstrijd1 = $echteWedstrijdenArray[$i * 2] ?? null;
            $bronWedstrijd2 = $echteWedstrijdenArray[$i * 2 + 1] ?? null;

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

            $huidigeRondeWedstrijden[] = $wedstrijd;
            $herkansingsWedstrijden[] = $wedstrijd;
        }

        // Volgende rondes: winnaars tegen elkaar (standaard halvering)
        // STOP bij 2 wedstrijden (halve finales) - geen finale in Groep B
        // Die winnaars gaan naar bronswedstrijden
        while (count($huidigeRondeWedstrijden) > 2) {
            $herkansingRonde++;
            $volgendeRondeWedstrijden = [];
            $aantalWedstrijdenInRonde = intdiv(count($huidigeRondeWedstrijden), 2);

            for ($i = 0; $i < $aantalWedstrijdenInRonde; $i++) {
                $wedstrijd = Wedstrijd::create([
                    'poule_id' => $poule->id,
                    'judoka_wit_id' => null,
                    'judoka_blauw_id' => null,
                    'volgorde' => $volgorde++,
                    'ronde' => "herkansing_r{$herkansingRonde}",
                    'groep' => 'B',
                    'bracket_positie' => $i + 1,
                ]);

                // Link vorige ronde winnaars
                $vorigeWedstrijd1 = $huidigeRondeWedstrijden[$i * 2] ?? null;
                $vorigeWedstrijd2 = $huidigeRondeWedstrijden[$i * 2 + 1] ?? null;

                if ($vorigeWedstrijd1) {
                    $vorigeWedstrijd1->update([
                        'volgende_wedstrijd_id' => $wedstrijd->id,
                        'winnaar_naar_slot' => 'wit',
                    ]);
                }
                if ($vorigeWedstrijd2) {
                    $vorigeWedstrijd2->update([
                        'volgende_wedstrijd_id' => $wedstrijd->id,
                        'winnaar_naar_slot' => 'blauw',
                    ]);
                }

                $volgendeRondeWedstrijden[] = $wedstrijd;
                $herkansingsWedstrijden[] = $wedstrijd;
            }

            $huidigeRondeWedstrijden = $volgendeRondeWedstrijden;
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
