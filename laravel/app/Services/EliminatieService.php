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
     */
    public function genereerBracket(Poule $poule, ?Collection $judokas = null): array
    {
        $judokas = $judokas ?? $poule->judokas;
        $aantal = $judokas->count();

        if ($aantal < 2) {
            return ['error' => 'Minimaal 2 judoka\'s nodig voor eliminatie'];
        }

        return DB::transaction(function () use ($poule, $judokas, $aantal) {
            // Delete existing matches
            $poule->wedstrijden()->delete();

            // Shuffle judokas (eerlijke loting)
            $geseededJudokas = $judokas->shuffle()->values()->all();

            // Calculate A-bracket structure
            $doelA = $this->berekenDoelGrootte($aantal);
            $voorrondeA = $aantal - $doelA;

            // Generate A-bracket
            $aWedstrijden = $this->genereerABracket($poule, $geseededJudokas, $doelA, $voorrondeA);

            // Calculate B-poule size: voorronde + 1/8 + 1/4 verliezers
            // Bij doel=16: voorronde + 8 + 4 = voorronde + 12
            // Bij doel=8: voorronde + 4 + 2 = voorronde + 6
            $verliezersNaarB = $voorrondeA + ($doelA / 2) + ($doelA / 4);

            // Generate B-poule
            $bWedstrijden = $this->genereerBPoule($poule, $aWedstrijden, $verliezersNaarB);

            // Generate bronze matches
            $bronsWedstrijden = $this->genereerBronsWedstrijden($poule, $aWedstrijden, $bWedstrijden);

            $totaal = count($aWedstrijden) + count($bWedstrijden) + count($bronsWedstrijden);
            $poule->update(['aantal_wedstrijden' => $totaal]);

            return [
                'aantal_judokas' => $aantal,
                'doel_a' => $doelA,
                'voorronde_a' => $voorrondeA,
                'a_wedstrijden' => count($aWedstrijden),
                'verliezers_naar_b' => $verliezersNaarB,
                'b_wedstrijden' => count($bWedstrijden),
                'brons_wedstrijden' => count($bronsWedstrijden),
                'totaal' => $totaal,
            ];
        });
    }

    /**
     * Grootste macht van 2 <= n
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
     * Ronde naam op basis van aantal wedstrijden
     */
    private function getRondeNaam(int $aantalWedstrijden): string
    {
        return self::RONDE_NAMEN[$aantalWedstrijden] ?? "ronde_{$aantalWedstrijden}";
    }

    /**
     * Genereer A-bracket (hoofdboom)
     */
    private function genereerABracket(Poule $poule, array $judokas, int $doel, int $voorrondeAantal): array
    {
        $wedstrijden = [];
        $volgorde = 1;

        // Split judokas
        $voorrondeJudokas = array_slice($judokas, 0, $voorrondeAantal * 2);
        $byeJudokas = array_slice($judokas, $voorrondeAantal * 2);

        // === VOORRONDE ===
        $voorrondeWeds = [];
        if ($voorrondeAantal > 0) {
            for ($i = 0; $i < $voorrondeAantal; $i++) {
                $wed = Wedstrijd::create([
                    'poule_id' => $poule->id,
                    'judoka_wit_id' => $voorrondeJudokas[$i * 2]->id,
                    'judoka_blauw_id' => $voorrondeJudokas[$i * 2 + 1]->id,
                    'volgorde' => $volgorde++,
                    'ronde' => 'voorronde',
                    'groep' => 'A',
                    'bracket_positie' => $i + 1,
                ]);
                $voorrondeWeds[] = $wed;
                $wedstrijden[] = $wed;
            }
        }

        // === EERSTE RONDE (1/8 of 1/4 etc) ===
        $rondeWedstrijden = $doel / 2;
        $rondeNaam = $this->getRondeNaam($rondeWedstrijden);
        $huidigeRonde = [];

        // We hebben $doel slots, gevuld door:
        // - $byeJudokas (direct geplaatst)
        // - $voorrondeAantal winnaars (komen later)
        // Totaal: count($byeJudokas) + $voorrondeAantal = $doel

        $byeIdx = 0;
        $voorrondeIdx = 0;

        for ($i = 0; $i < $rondeWedstrijden; $i++) {
            // Elke wedstrijd heeft 2 slots (wit en blauw)
            $witId = null;
            $blauwId = null;
            $witVanVoorronde = null;
            $blauwVanVoorronde = null;

            // Slot 1 (wit)
            if ($byeIdx < count($byeJudokas)) {
                $witId = $byeJudokas[$byeIdx]->id;
                $byeIdx++;
            } elseif ($voorrondeIdx < count($voorrondeWeds)) {
                $witVanVoorronde = $voorrondeWeds[$voorrondeIdx];
                $voorrondeIdx++;
            }

            // Slot 2 (blauw)
            if ($byeIdx < count($byeJudokas)) {
                $blauwId = $byeJudokas[$byeIdx]->id;
                $byeIdx++;
            } elseif ($voorrondeIdx < count($voorrondeWeds)) {
                $blauwVanVoorronde = $voorrondeWeds[$voorrondeIdx];
                $voorrondeIdx++;
            }

            $wed = Wedstrijd::create([
                'poule_id' => $poule->id,
                'judoka_wit_id' => $witId,
                'judoka_blauw_id' => $blauwId,
                'volgorde' => $volgorde++,
                'ronde' => $rondeNaam,
                'groep' => 'A',
                'bracket_positie' => $i + 1,
            ]);

            // Link voorronde winnaars
            if ($witVanVoorronde) {
                $witVanVoorronde->update([
                    'volgende_wedstrijd_id' => $wed->id,
                    'winnaar_naar_slot' => 'wit',
                ]);
            }
            if ($blauwVanVoorronde) {
                $blauwVanVoorronde->update([
                    'volgende_wedstrijd_id' => $wed->id,
                    'winnaar_naar_slot' => 'blauw',
                ]);
            }

            $huidigeRonde[] = $wed;
            $wedstrijden[] = $wed;
        }

        // === VOLGENDE RONDES tot FINALE ===
        while (count($huidigeRonde) > 1) {
            $volgendeRonde = [];
            $rondeWedstrijden = count($huidigeRonde) / 2;
            $rondeNaam = $this->getRondeNaam($rondeWedstrijden);

            for ($i = 0; $i < $rondeWedstrijden; $i++) {
                $wed1 = $huidigeRonde[$i * 2];
                $wed2 = $huidigeRonde[$i * 2 + 1];

                $wed = Wedstrijd::create([
                    'poule_id' => $poule->id,
                    'judoka_wit_id' => null,
                    'judoka_blauw_id' => null,
                    'volgorde' => $volgorde++,
                    'ronde' => $rondeNaam,
                    'groep' => 'A',
                    'bracket_positie' => $i + 1,
                ]);

                $wed1->update([
                    'volgende_wedstrijd_id' => $wed->id,
                    'winnaar_naar_slot' => 'wit',
                ]);
                $wed2->update([
                    'volgende_wedstrijd_id' => $wed->id,
                    'winnaar_naar_slot' => 'blauw',
                ]);

                $volgendeRonde[] = $wed;
                $wedstrijden[] = $wed;
            }

            $huidigeRonde = $volgendeRonde;
        }

        return $wedstrijden;
    }

    /**
     * Genereer B-poule (herkansing)
     * Ontvangt: voorronde verliezers + 1/8 verliezers + 1/4 verliezers
     */
    private function genereerBPoule(Poule $poule, array $aWedstrijden, int $aantalVerliezers): array
    {
        if ($aantalVerliezers < 2) {
            return [];
        }

        $wedstrijden = [];
        $volgorde = count($aWedstrijden) + 1;

        // B-poule doel: naar 2 winnaars (voor bronswedstrijden)
        // Bracket van $aantalVerliezers naar 2
        $doelB = $this->berekenDoelGrootte($aantalVerliezers);
        $voorrondeB = $aantalVerliezers - $doelB;

        // Vind A-wedstrijden die verliezers leveren
        $aCol = collect($aWedstrijden);
        $voorrondeA = $aCol->where('ronde', 'voorronde')->values();
        $eersteRondeA = $aCol->whereNotIn('ronde', ['voorronde', 'halve_finale', 'finale'])
            ->sortByDesc(fn($w) => $aCol->where('ronde', $w->ronde)->count())
            ->groupBy('ronde')
            ->first() ?? collect();
        $kwartfinaleA = $aCol->where('ronde', 'kwartfinale')->values();

        // Alle bron-wedstrijden die verliezers naar B sturen
        $bronWedstrijden = collect()
            ->concat($voorrondeA)
            ->concat($eersteRondeA)
            ->concat($kwartfinaleA)
            ->values();

        // === B-POULE VOORRONDE (indien nodig) ===
        $bVoorrondeWeds = [];
        if ($voorrondeB > 0) {
            // Eerste $voorrondeB * 2 verliezers gaan naar B-voorronde
            for ($i = 0; $i < $voorrondeB; $i++) {
                $wed = Wedstrijd::create([
                    'poule_id' => $poule->id,
                    'judoka_wit_id' => null,
                    'judoka_blauw_id' => null,
                    'volgorde' => $volgorde++,
                    'ronde' => 'b_voorronde',
                    'groep' => 'B',
                    'bracket_positie' => $i + 1,
                ]);

                // Link verliezers van A naar B-voorronde
                $bronIdx1 = $i * 2;
                $bronIdx2 = $i * 2 + 1;

                if (isset($bronWedstrijden[$bronIdx1])) {
                    $bronWedstrijden[$bronIdx1]->update([
                        'herkansing_wedstrijd_id' => $wed->id,
                        'verliezer_naar_slot' => 'wit',
                    ]);
                }
                if (isset($bronWedstrijden[$bronIdx2])) {
                    $bronWedstrijden[$bronIdx2]->update([
                        'herkansing_wedstrijd_id' => $wed->id,
                        'verliezer_naar_slot' => 'blauw',
                    ]);
                }

                $bVoorrondeWeds[] = $wed;
                $wedstrijden[] = $wed;
            }
        }

        // === B-POULE EERSTE RONDE ===
        $rondeWedstrijden = $doelB / 2;
        $huidigeRonde = [];

        // Slots: B-voorronde winnaars + resterende A-verliezers
        $bVoorrondeIdx = 0;
        $bronStartIdx = $voorrondeB * 2; // Skip de verliezers die al naar B-voorronde gingen

        for ($i = 0; $i < $rondeWedstrijden; $i++) {
            $wed = Wedstrijd::create([
                'poule_id' => $poule->id,
                'judoka_wit_id' => null,
                'judoka_blauw_id' => null,
                'volgorde' => $volgorde++,
                'ronde' => 'b_ronde_1',
                'groep' => 'B',
                'bracket_positie' => $i + 1,
            ]);

            // Wit slot
            if ($bVoorrondeIdx < count($bVoorrondeWeds)) {
                $bVoorrondeWeds[$bVoorrondeIdx]->update([
                    'volgende_wedstrijd_id' => $wed->id,
                    'winnaar_naar_slot' => 'wit',
                ]);
                $bVoorrondeIdx++;
            } elseif (isset($bronWedstrijden[$bronStartIdx])) {
                $bronWedstrijden[$bronStartIdx]->update([
                    'herkansing_wedstrijd_id' => $wed->id,
                    'verliezer_naar_slot' => 'wit',
                ]);
                $bronStartIdx++;
            }

            // Blauw slot
            if ($bVoorrondeIdx < count($bVoorrondeWeds)) {
                $bVoorrondeWeds[$bVoorrondeIdx]->update([
                    'volgende_wedstrijd_id' => $wed->id,
                    'winnaar_naar_slot' => 'blauw',
                ]);
                $bVoorrondeIdx++;
            } elseif (isset($bronWedstrijden[$bronStartIdx])) {
                $bronWedstrijden[$bronStartIdx]->update([
                    'herkansing_wedstrijd_id' => $wed->id,
                    'verliezer_naar_slot' => 'blauw',
                ]);
                $bronStartIdx++;
            }

            $huidigeRonde[] = $wed;
            $wedstrijden[] = $wed;
        }

        // === B-POULE VOLGENDE RONDES tot 2 overblijven ===
        $rondeNr = 2;
        while (count($huidigeRonde) > 2) {
            $volgendeRonde = [];
            $rondeWedstrijden = count($huidigeRonde) / 2;

            for ($i = 0; $i < $rondeWedstrijden; $i++) {
                $wed1 = $huidigeRonde[$i * 2];
                $wed2 = $huidigeRonde[$i * 2 + 1];

                $wed = Wedstrijd::create([
                    'poule_id' => $poule->id,
                    'judoka_wit_id' => null,
                    'judoka_blauw_id' => null,
                    'volgorde' => $volgorde++,
                    'ronde' => "b_ronde_{$rondeNr}",
                    'groep' => 'B',
                    'bracket_positie' => $i + 1,
                ]);

                $wed1->update([
                    'volgende_wedstrijd_id' => $wed->id,
                    'winnaar_naar_slot' => 'wit',
                ]);
                $wed2->update([
                    'volgende_wedstrijd_id' => $wed->id,
                    'winnaar_naar_slot' => 'blauw',
                ]);

                $volgendeRonde[] = $wed;
                $wedstrijden[] = $wed;
            }

            $huidigeRonde = $volgendeRonde;
            $rondeNr++;
        }

        // De laatste 2 wedstrijden zijn de B-halve finales
        // Hun winnaars gaan naar bronswedstrijden

        return $wedstrijden;
    }

    /**
     * Genereer bronswedstrijden
     * 2 wedstrijden: A halve finale verliezer vs B-poule winnaar
     */
    private function genereerBronsWedstrijden(Poule $poule, array $aWedstrijden, array $bWedstrijden): array
    {
        $bronsWeds = [];
        $volgorde = count($aWedstrijden) + count($bWedstrijden) + 1;

        // Vind A halve finales
        $halveFinales = collect($aWedstrijden)->where('ronde', 'halve_finale')->values();

        if ($halveFinales->count() < 2) {
            return [];
        }

        // Vind laatste B-ronde (de 2 halve finales)
        $laatsteBRonde = collect($bWedstrijden)
            ->sortByDesc('ronde')
            ->groupBy('ronde')
            ->first();

        if (!$laatsteBRonde || $laatsteBRonde->count() < 2) {
            return [];
        }

        // 2 bronswedstrijden
        for ($i = 0; $i < 2; $i++) {
            $brons = Wedstrijd::create([
                'poule_id' => $poule->id,
                'judoka_wit_id' => null,
                'judoka_blauw_id' => null,
                'volgorde' => $volgorde++,
                'ronde' => 'brons',
                'groep' => 'A',
                'bracket_positie' => $i + 1,
            ]);

            // A halve finale verliezer → wit slot
            $halveFinales[$i]->update([
                'herkansing_wedstrijd_id' => $brons->id,
                'verliezer_naar_slot' => 'wit',
            ]);

            // B-poule winnaar → blauw slot
            $laatsteBRonde[$i]->update([
                'volgende_wedstrijd_id' => $brons->id,
                'winnaar_naar_slot' => 'blauw',
            ]);

            $bronsWeds[] = $brons;
        }

        return $bronsWeds;
    }

    /**
     * Process match result and advance winner/loser
     */
    public function verwerkUitslag(Wedstrijd $wedstrijd, int $winnaarId): void
    {
        $verliezerId = $wedstrijd->judoka_wit_id == $winnaarId
            ? $wedstrijd->judoka_blauw_id
            : $wedstrijd->judoka_wit_id;

        // Winner to next match
        if ($wedstrijd->volgende_wedstrijd_id && $wedstrijd->winnaar_naar_slot) {
            $volgende = Wedstrijd::find($wedstrijd->volgende_wedstrijd_id);
            if ($volgende) {
                $slot = $wedstrijd->winnaar_naar_slot;
                $volgende->update(["judoka_{$slot}_id" => $winnaarId]);
            }
        }

        // Loser to repechage
        if ($wedstrijd->herkansing_wedstrijd_id && $wedstrijd->verliezer_naar_slot && $verliezerId) {
            $herkansing = Wedstrijd::find($wedstrijd->herkansing_wedstrijd_id);
            if ($herkansing) {
                $slot = $wedstrijd->verliezer_naar_slot;
                $herkansing->update(["judoka_{$slot}_id" => $verliezerId]);
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
