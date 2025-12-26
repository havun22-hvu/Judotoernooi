<?php

namespace App\Services;

use App\Models\Poule;
use App\Models\Wedstrijd;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EliminatieService
{
    private const RONDE_NAMEN = [
        1 => 'finale',
        2 => 'halve_finale',
        4 => 'kwartfinale',
        8 => 'achtste_finale',
        16 => 'zestiende_finale',
    ];

    /**
     * Haal bracket structuur op voor weergave
     */
    public function getBracketStructuur(Poule $poule): array
    {
        $wedstrijden = $poule->wedstrijden()
            ->with(['judokaWit', 'judokaBlauw', 'winnaar'])
            ->orderBy('bracket_positie')
            ->get();

        // A-groep (hoofdboom) - sorteer rondes in juiste volgorde
        $hoofdboom = $wedstrijden->where('groep', 'A')
            ->groupBy('ronde')
            ->sortBy(function ($items, $ronde) {
                return match ($ronde) {
                    'voorronde' => 0,
                    'zestiende_finale' => 1,
                    'achtste_finale' => 2,
                    'kwartfinale' => 3,
                    'halve_finale' => 4,
                    'finale' => 5,
                    default => 99,
                };
            });

        // B-groep (herkansing) - exclusief brons
        $herkansing = $wedstrijden->where('groep', 'B')
            ->where('ronde', '!=', 'b_brons')
            ->groupBy('ronde')
            ->sortBy(function ($items, $ronde) {
                return match ($ronde) {
                    'b_voorronde' => 0,
                    'b_achtste_finale' => 1,
                    'b_achtste_finale_2' => 2,
                    'b_kwartfinale_1' => 3,
                    'b_kwartfinale_2' => 4,
                    'b_halve_finale_1' => 5,
                    'b_halve_finale' => 5, // legacy
                    default => 99,
                };
            });

        // Brons wedstrijden apart
        $brons = $wedstrijden->where('ronde', 'b_brons');

        return [
            'hoofdboom' => $hoofdboom,
            'herkansing' => $herkansing,
            'brons' => $brons,
        ];
    }

    public function genereerBracket(Poule $poule, ?Collection $judokas = null): array
    {
        $judokas = $judokas ?? $poule->judokas;
        $aantal = $judokas->count();

        if ($aantal < 2) {
            return ['error' => 'Minimaal 2 judoka\'s nodig'];
        }

        return DB::transaction(function () use ($poule, $judokas, $aantal) {
            $poule->wedstrijden()->delete();

            // Seed met clubgenoten-spreiding
            $geseededJudokas = $this->seedMetClubSpreiding($judokas);

            $doelA = $this->berekenDoelGrootte($aantal);
            $voorrondeA = $aantal - $doelA;

            // Genereer A-bracket
            $aWedstrijden = $this->genereerABracket($poule, $geseededJudokas, $doelA, $voorrondeA);

            // Genereer B-poule met lege plekken
            $bWedstrijden = $this->genereerBPoule($poule, count($aWedstrijden), $doelA, $voorrondeA);

            $totaal = count($aWedstrijden) + count($bWedstrijden);
            $poule->update(['aantal_wedstrijden' => $totaal]);

            return [
                'aantal_judokas' => $aantal,
                'doel_a' => $doelA,
                'voorronde_a' => $voorrondeA,
                'a_wedstrijden' => count($aWedstrijden),
                'b_wedstrijden' => count($bWedstrijden),
                'totaal' => $totaal,
            ];
        });
    }

    private function berekenDoelGrootte(int $n): int
    {
        $power = 1;
        while ($power * 2 <= $n) {
            $power *= 2;
        }
        return $power;
    }

    /**
     * Seed judoka's met clubgenoten-spreiding
     *
     * Algoritme:
     * 1. Groepeer per club
     * 2. Verdeel elke club over 2 bracket-helften (50/50)
     * 3. Shuffle binnen elke helft
     * 4. Combineer: helft1 + helft2 (interleaved)
     */
    private function seedMetClubSpreiding(Collection $judokas): array
    {
        $perClub = $judokas->groupBy('club_id');
        $helft1 = [];
        $helft2 = [];

        foreach ($perClub as $clubId => $clubJudokas) {
            $shuffled = $clubJudokas->shuffle()->values();
            $count = $shuffled->count();

            // Verdeel 50/50 over beide helften
            for ($i = 0; $i < $count; $i++) {
                if ($i % 2 === 0) {
                    $helft1[] = $shuffled[$i];
                } else {
                    $helft2[] = $shuffled[$i];
                }
            }
        }

        // Shuffle binnen elke helft voor extra randomness
        shuffle($helft1);
        shuffle($helft2);

        // Combineer: interleave om spreiding te behouden
        // Helft1 = posities 0,2,4,6... (bovenste deel bracket)
        // Helft2 = posities 1,3,5,7... (onderste deel bracket)
        $result = [];
        $max = max(count($helft1), count($helft2));
        for ($i = 0; $i < $max; $i++) {
            if (isset($helft1[$i])) $result[] = $helft1[$i];
            if (isset($helft2[$i])) $result[] = $helft2[$i];
        }

        \Log::info("Seeding: " . count($judokas) . " judoka's, " . $perClub->count() . " clubs, helft1=" . count($helft1) . ", helft2=" . count($helft2));

        return $result;
    }

    /**
     * Check of bracket in seeding-fase is (geen wedstrijden gespeeld)
     */
    public function isInSeedingFase(Poule $poule): bool
    {
        return !Wedstrijd::where('poule_id', $poule->id)
            ->where('is_gespeeld', true)
            ->exists();
    }

    /**
     * Swap twee judoka's in de eerste ronde (seeding)
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function swapJudokas(Poule $poule, int $judokaAId, int $judokaBId): array
    {
        // Check seeding-fase
        if (!$this->isInSeedingFase($poule)) {
            return ['success' => false, 'message' => 'Bracket is locked - er zijn al wedstrijden gespeeld'];
        }

        // Vind wedstrijden waar judoka's in zitten
        $wedstrijdA = $this->vindEersteRondeWedstrijd($poule, $judokaAId);
        $wedstrijdB = $this->vindEersteRondeWedstrijd($poule, $judokaBId);

        if (!$wedstrijdA) {
            return ['success' => false, 'message' => 'Judoka A niet gevonden in eerste ronde'];
        }
        if (!$wedstrijdB) {
            return ['success' => false, 'message' => 'Judoka B niet gevonden in eerste ronde'];
        }

        // Bepaal positie (wit/blauw) van beide
        $positieA = $wedstrijdA->judoka_wit_id == $judokaAId ? 'wit' : 'blauw';
        $positieB = $wedstrijdB->judoka_wit_id == $judokaBId ? 'wit' : 'blauw';

        // Swap uitvoeren
        DB::transaction(function () use ($wedstrijdA, $wedstrijdB, $judokaAId, $judokaBId, $positieA, $positieB) {
            // Zet A op plek van B
            $wedstrijdB->update(["judoka_{$positieB}_id" => $judokaAId]);
            // Zet B op plek van A
            $wedstrijdA->update(["judoka_{$positieA}_id" => $judokaBId]);
        });

        \Log::info("Seeding swap: judoka {$judokaAId} <-> {$judokaBId} in poule {$poule->id}");

        return ['success' => true, 'message' => 'Judoka\'s gewisseld'];
    }

    /**
     * Verplaats judoka naar lege plek in eerste ronde
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function moveJudokaNaarLegePlek(Poule $poule, int $judokaId, int $doelWedstrijdId, string $doelPositie): array
    {
        // Check seeding-fase
        if (!$this->isInSeedingFase($poule)) {
            return ['success' => false, 'message' => 'Bracket is locked - er zijn al wedstrijden gespeeld'];
        }

        // Valideer doelPositie
        if (!in_array($doelPositie, ['wit', 'blauw'])) {
            return ['success' => false, 'message' => 'Ongeldige positie (moet wit of blauw zijn)'];
        }

        // Vind huidige wedstrijd van judoka
        $huidigeWedstrijd = $this->vindEersteRondeWedstrijd($poule, $judokaId);
        if (!$huidigeWedstrijd) {
            return ['success' => false, 'message' => 'Judoka niet gevonden in eerste ronde'];
        }

        // Vind doel wedstrijd
        $doelWedstrijd = Wedstrijd::where('poule_id', $poule->id)
            ->where('id', $doelWedstrijdId)
            ->whereIn('ronde', ['voorronde', 'achtste_finale', 'kwartfinale', 'zestiende_finale'])
            ->where('groep', 'A')
            ->first();

        if (!$doelWedstrijd) {
            return ['success' => false, 'message' => 'Doel wedstrijd niet gevonden in eerste ronde'];
        }

        // Check of doelplek leeg is
        if ($doelWedstrijd->{"judoka_{$doelPositie}_id"} !== null) {
            return ['success' => false, 'message' => "Plek {$doelPositie} is niet leeg - gebruik swap"];
        }

        // Bepaal huidige positie
        $huidigePositie = $huidigeWedstrijd->judoka_wit_id == $judokaId ? 'wit' : 'blauw';

        // Verplaats
        DB::transaction(function () use ($huidigeWedstrijd, $doelWedstrijd, $judokaId, $huidigePositie, $doelPositie) {
            // Verwijder van huidige plek
            $huidigeWedstrijd->update(["judoka_{$huidigePositie}_id" => null]);
            // Plaats op nieuwe plek
            $doelWedstrijd->update(["judoka_{$doelPositie}_id" => $judokaId]);
        });

        \Log::info("Seeding move: judoka {$judokaId} naar wedstrijd {$doelWedstrijdId} positie {$doelPositie}");

        return ['success' => true, 'message' => 'Judoka verplaatst'];
    }

    /**
     * Vind eerste-ronde wedstrijd waar judoka in zit
     */
    private function vindEersteRondeWedstrijd(Poule $poule, int $judokaId): ?Wedstrijd
    {
        return Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'A')
            ->whereIn('ronde', ['voorronde', 'achtste_finale', 'kwartfinale', 'zestiende_finale'])
            ->where(function ($q) use ($judokaId) {
                $q->where('judoka_wit_id', $judokaId)
                  ->orWhere('judoka_blauw_id', $judokaId);
            })
            ->first();
    }

    private function getRondeNaam(int $aantalWedstrijden): string
    {
        return self::RONDE_NAMEN[$aantalWedstrijden] ?? "ronde_{$aantalWedstrijden}";
    }

    private function genereerABracket(Poule $poule, array $judokas, int $doel, int $voorrondeAantal): array
    {
        $wedstrijden = [];
        $volgorde = 1;

        $voorrondeJudokas = array_slice($judokas, 0, $voorrondeAantal * 2);
        $byeJudokas = array_slice($judokas, $voorrondeAantal * 2);

        // === VOORRONDE ===
        $voorrondeWeds = [];
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

        // === EERSTE RONDE ===
        // Elke bye-judoka moet gekoppeld worden aan een voorronde-winnaar
        // Resterende voorronde-winnaars spelen tegen elkaar
        $rondeWedstrijden = $doel / 2;
        $rondeNaam = $this->getRondeNaam($rondeWedstrijden);
        $huidigeRonde = [];

        $byeIdx = 0;
        $voorrondeIdx = 0;

        for ($i = 0; $i < $rondeWedstrijden; $i++) {
            $witId = null;
            $blauwId = null;
            $witVanVoorronde = null;
            $blauwVanVoorronde = null;

            // Prioriteit: koppel bye aan voorronde-winnaar
            if ($byeIdx < count($byeJudokas) && $voorrondeIdx < count($voorrondeWeds)) {
                // Bye vs Voorronde winnaar
                $witId = $byeJudokas[$byeIdx++]->id;
                $blauwVanVoorronde = $voorrondeWeds[$voorrondeIdx++];
            } elseif ($voorrondeIdx + 1 < count($voorrondeWeds)) {
                // Twee voorronde winnaars tegen elkaar
                $witVanVoorronde = $voorrondeWeds[$voorrondeIdx++];
                $blauwVanVoorronde = $voorrondeWeds[$voorrondeIdx++];
            } elseif ($byeIdx + 1 < count($byeJudokas)) {
                // Twee bye-judokas (alleen als meer byes dan voorrondes)
                $witId = $byeJudokas[$byeIdx++]->id;
                $blauwId = $byeJudokas[$byeIdx++]->id;
            } else {
                // Restanten opvullen
                if ($byeIdx < count($byeJudokas)) {
                    $witId = $byeJudokas[$byeIdx++]->id;
                } elseif ($voorrondeIdx < count($voorrondeWeds)) {
                    $witVanVoorronde = $voorrondeWeds[$voorrondeIdx++];
                }
                if ($byeIdx < count($byeJudokas)) {
                    $blauwId = $byeJudokas[$byeIdx++]->id;
                } elseif ($voorrondeIdx < count($voorrondeWeds)) {
                    $blauwVanVoorronde = $voorrondeWeds[$voorrondeIdx++];
                }
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

            // Update voorronde wedstrijden met koppeling EN juiste bracket_positie
            // De bracket_positie moet overeenkomen met de 1/8 wedstrijd voor visuele alignment
            if ($witVanVoorronde) {
                $witVanVoorronde->update([
                    'volgende_wedstrijd_id' => $wed->id,
                    'winnaar_naar_slot' => 'wit',
                    'bracket_positie' => ($i + 1) * 2 - 1, // Bovenste helft van 1/8 wedstrijd i
                ]);
            }
            if ($blauwVanVoorronde) {
                $blauwVanVoorronde->update([
                    'volgende_wedstrijd_id' => $wed->id,
                    'winnaar_naar_slot' => 'blauw',
                    'bracket_positie' => ($i + 1) * 2, // Onderste helft van 1/8 wedstrijd i
                ]);
            }

            $huidigeRonde[] = $wed;
            $wedstrijden[] = $wed;
        }

        // === VOLGENDE RONDES ===
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

                $wed1->update(['volgende_wedstrijd_id' => $wed->id, 'winnaar_naar_slot' => 'wit']);
                $wed2->update(['volgende_wedstrijd_id' => $wed->id, 'winnaar_naar_slot' => 'blauw']);

                $volgendeRonde[] = $wed;
                $wedstrijden[] = $wed;
            }

            $huidigeRonde = $volgendeRonde;
        }

        return $wedstrijden;
    }

    /**
     * Genereer B-poule structuur met DYNAMISCHE berekening
     *
     * Formules:
     * - D = doel = grootste macht van 2 ≤ N
     * - V = N - D (aantal A-voorrondes)
     * - Naar_B = V + D/2 (totaal naar B eerste ronde)
     * - B_voorrondes = V (overflow t.o.v. capaciteit D/2)
     *
     * Grenzen:
     * - Dubbele 1/2: vanaf 8 spelers
     * - Enkele 1/4: vanaf 12 spelers
     * - Dubbele 1/4: vanaf 16 spelers (D=16)
     * - Enkele 1/8: vanaf 20 spelers
     * - Volle 1/8: vanaf 24 spelers
     * - Dubbele 1/8: vanaf 32 spelers (D=32)
     *
     * @see docs/2-FEATURES/ELIMINATIE_SYSTEEM.md voor complete tabel
     */
    private function genereerBPoule(Poule $poule, int $aCount, int $doelA, int $voorrondeA): array
    {
        $wedstrijden = [];
        $volgorde = $aCount + 1;

        $n = $doelA + $voorrondeA;
        $naarB = $voorrondeA + ($doelA / 2);  // V + D/2
        $bVoorrondes = $voorrondeA;  // B voorrondes = V

        \Log::info("B-groep genereren: N={$n}, D={$doelA}, V={$voorrondeA}, NaarB={$naarB}, BVoorrondes={$bVoorrondes}");

        // Bepaal welke rondes nodig zijn op basis van D en NaarB
        // B 1/8 alleen bij D>=32 (A heeft dan 1/16 finale, verliezers → B 1/8)
        // B 1/4 dubbel bij D>=16 (A 1/8 verliezers → B 1e 1/4, A 1/4 verliezers → B 2e 1/4)
        // B 1/4 enkel bij D=8 met NaarB >= 8
        $heeftDubbele18 = $doelA >= 64;  // Dubbele B 1/8 bij D=64
        $heeftEnkele18 = $doelA >= 32;   // Enkele B 1/8 alleen bij D>=32!
        $heeftDubbele14 = $doelA >= 16;  // Dubbele B 1/4 bij D=16
        $heeftEnkele14 = $naarB >= 8;    // B 1/4 alleen als 8+ naar B gaan (12+ spelers bij D=8)

        $vorigeRondeWeds = [];

        // === STAP 1: B VOORRONDES (= V wedstrijden) ===
        if ($bVoorrondes > 0) {
            for ($i = 0; $i < $bVoorrondes; $i++) {
                $wed = Wedstrijd::create([
                    'poule_id' => $poule->id,
                    'volgorde' => $volgorde++,
                    'ronde' => 'b_voorronde',
                    'groep' => 'B',
                    'bracket_positie' => $i + 1,
                ]);
                $wedstrijden[] = $wed;
                $vorigeRondeWeds[] = $wed;
            }
            \Log::info("B voorronde: {$bVoorrondes} wedstrijden");
        }

        // === STAP 2: B 1/8 DEEL 1 (alleen bij D>=16) ===
        $b18Deel1Weds = [];
        if ($heeftEnkele18) {
            // Aantal wedstrijden = D/4 (8 bij D=32, 4 bij D=16... maar standaard 8)
            $aantalB18 = min(8, $doelA / 2);
            for ($i = 0; $i < $aantalB18; $i++) {
                $wed = Wedstrijd::create([
                    'poule_id' => $poule->id,
                    'volgorde' => $volgorde++,
                    'ronde' => 'b_achtste_finale',
                    'groep' => 'B',
                    'bracket_positie' => $i + 1,
                ]);
                $wedstrijden[] = $wed;
                $b18Deel1Weds[] = $wed;
            }

            // Koppel B voorronde → B 1/8
            $this->koppelRondes($vorigeRondeWeds, $b18Deel1Weds);
            $vorigeRondeWeds = $b18Deel1Weds;
            \Log::info("B 1/8 deel 1: {$aantalB18} wedstrijden");
        }

        // === STAP 3: B 1/8 DEEL 2 (alleen bij D>=32) ===
        $b18Deel2Weds = [];
        if ($heeftDubbele18) {
            for ($i = 0; $i < 8; $i++) {
                $wed = Wedstrijd::create([
                    'poule_id' => $poule->id,
                    'volgorde' => $volgorde++,
                    'ronde' => 'b_achtste_finale_2',
                    'groep' => 'B',
                    'bracket_positie' => $i + 1,
                ]);
                $wedstrijden[] = $wed;
                $b18Deel2Weds[] = $wed;
            }

            // B 1/8 deel 1 winnaars → B 1/8 deel 2 als BLAUW
            foreach ($b18Deel1Weds as $idx => $wed) {
                if (isset($b18Deel2Weds[$idx])) {
                    $wed->update([
                        'volgende_wedstrijd_id' => $b18Deel2Weds[$idx]->id,
                        'winnaar_naar_slot' => 'blauw',
                    ]);
                }
            }
            $vorigeRondeWeds = $b18Deel2Weds;
            \Log::info("B 1/8 deel 2: 8 wedstrijden");
        }

        // === STAP 4: B 1/4 DEEL 1 (bij D>=8 met dubbele 1/4, of eerste ronde bij D=8 zonder 1/8) ===
        $b14Deel1Weds = [];
        if ($heeftEnkele14) {
            for ($i = 0; $i < 4; $i++) {
                $wed = Wedstrijd::create([
                    'poule_id' => $poule->id,
                    'volgorde' => $volgorde++,
                    'ronde' => $heeftDubbele14 ? 'b_kwartfinale_1' : 'b_kwartfinale',
                    'groep' => 'B',
                    'bracket_positie' => $i + 1,
                ]);
                $wedstrijden[] = $wed;
                $b14Deel1Weds[] = $wed;
            }

            // Koppel vorige ronde → B 1/4 deel 1
            $this->koppelRondes($vorigeRondeWeds, $b14Deel1Weds);
            \Log::info("B 1/4 deel 1: 4 wedstrijden");
        }

        // === STAP 5: B 1/4 DEEL 2 (alleen bij D>=16) ===
        $b14Deel2Weds = [];
        if ($heeftDubbele14) {
            for ($i = 0; $i < 4; $i++) {
                $wed = Wedstrijd::create([
                    'poule_id' => $poule->id,
                    'volgorde' => $volgorde++,
                    'ronde' => 'b_kwartfinale_2',
                    'groep' => 'B',
                    'bracket_positie' => $i + 1,
                ]);
                $wedstrijden[] = $wed;
                $b14Deel2Weds[] = $wed;
            }

            // B 1/4 deel 1 winnaars → B 1/4 deel 2 als BLAUW
            foreach ($b14Deel1Weds as $idx => $wed) {
                if (isset($b14Deel2Weds[$idx])) {
                    $wed->update([
                        'volgende_wedstrijd_id' => $b14Deel2Weds[$idx]->id,
                        'winnaar_naar_slot' => 'blauw',
                    ]);
                }
            }
            $vorigeRondeWeds = $b14Deel2Weds;
            \Log::info("B 1/4 deel 2: 4 wedstrijden");
        } else {
            $vorigeRondeWeds = $b14Deel1Weds;
        }

        // === STAP 6: B 1/2 DEEL 1 ===
        $b12Deel1Weds = [];
        for ($i = 0; $i < 2; $i++) {
            $wed = Wedstrijd::create([
                'poule_id' => $poule->id,
                'volgorde' => $volgorde++,
                'ronde' => 'b_halve_finale_1',
                'groep' => 'B',
                'bracket_positie' => $i + 1,
            ]);
            $wedstrijden[] = $wed;
            $b12Deel1Weds[] = $wed;
        }

        // Koppel vorige ronde → B 1/2 deel 1
        $this->koppelRondes($vorigeRondeWeds, $b12Deel1Weds);
        \Log::info("B 1/2 deel 1: 2 wedstrijden");

        // === STAP 7: BRONS (B 1/2 DEEL 2) ===
        $bronsWeds = [];
        for ($i = 0; $i < 2; $i++) {
            $wed = Wedstrijd::create([
                'poule_id' => $poule->id,
                'volgorde' => $volgorde++,
                'ronde' => 'b_brons',
                'groep' => 'B',
                'bracket_positie' => $i + 1,
            ]);
            $wedstrijden[] = $wed;
            $bronsWeds[] = $wed;
        }

        // B 1/2 deel 1 winnaars → Brons als BLAUW
        foreach ($b12Deel1Weds as $idx => $wed) {
            if (isset($bronsWeds[$idx])) {
                $wed->update([
                    'volgende_wedstrijd_id' => $bronsWeds[$idx]->id,
                    'winnaar_naar_slot' => 'blauw',
                ]);
            }
        }
        \Log::info("Brons: 2 wedstrijden");

        \Log::info("B-groep totaal: " . count($wedstrijden) . " wedstrijden");

        return $wedstrijden;
    }

    /**
     * Koppel wedstrijden van vorige ronde naar volgende ronde
     * 2 wedstrijden uit vorige → 1 wedstrijd in volgende
     */
    private function koppelRondes(array $vorigeRonde, array $volgendeRonde): void
    {
        foreach ($vorigeRonde as $idx => $wed) {
            $volgendeIdx = (int) ($idx / 2);
            if (isset($volgendeRonde[$volgendeIdx])) {
                $wed->update([
                    'volgende_wedstrijd_id' => $volgendeRonde[$volgendeIdx]->id,
                    'winnaar_naar_slot' => ($idx % 2 === 0) ? 'wit' : 'blauw',
                ]);
            }
        }
    }

    /**
     * Geef B-ronde naam op basis van aantal wedstrijden
     */
    private function getBRondeNaamVoorAantal(int $aantal): string
    {
        return match (true) {
            $aantal >= 8 => 'b_achtste_finale',
            $aantal >= 4 => 'b_kwartfinale',
            $aantal >= 2 => 'b_halve_finale',
            default => 'b_ronde_1',
        };
    }

    /**
     * Geef B-ronde naam op basis van aantal wedstrijden
     * B eindigt bij 2 halve finales (2 bronzen), geen finale
     */
    private function getBRondeNaam(int $aantalWedstrijden): string
    {
        return match ($aantalWedstrijden) {
            2 => 'b_halve_finale',      // 2 matches = eindpunt B, 2 winnaars = 2x brons
            4 => 'b_kwartfinale',
            8 => 'b_achtste_finale',
            16 => 'b_zestiende_finale',
            default => "b_ronde_{$aantalWedstrijden}",
        };
    }

    /**
     * Verwerk uitslag en zet winnaar/verliezer door
     * Handelt ook correcties af: verwijdert oude plaatsingen eerst
     * Returns array met correctie-informatie voor admin
     */
    public function verwerkUitslag(Wedstrijd $wedstrijd, int $winnaarId, ?int $oudeWinnaarId = null): array
    {
        \Log::info("verwerkUitslag: wedstrijd={$wedstrijd->id}, winnaar={$winnaarId}, oudeWinnaar={$oudeWinnaarId}, groep={$wedstrijd->groep}, ronde={$wedstrijd->ronde}");

        $correcties = [];
        $verliezerId = $wedstrijd->judoka_wit_id == $winnaarId
            ? $wedstrijd->judoka_blauw_id
            : $wedstrijd->judoka_wit_id;

        \Log::info("verwerkUitslag: verliezer={$verliezerId} (wit={$wedstrijd->judoka_wit_id}, blauw={$wedstrijd->judoka_blauw_id})");

        // Bij correctie: verwijder oude winnaar uit volgende ronde en oude verliezer uit B
        if ($oudeWinnaarId && $oudeWinnaarId != $winnaarId) {
            \Log::info("verwerkUitslag: correctie detecteerd, oude winnaar verwijderen");
            $correcties = $this->verwijderOudePlaatsingen($wedstrijd, $oudeWinnaarId);
        }

        // Winnaar naar volgende A-wedstrijd (direct)
        if ($wedstrijd->volgende_wedstrijd_id && $wedstrijd->winnaar_naar_slot) {
            $volgende = Wedstrijd::find($wedstrijd->volgende_wedstrijd_id);
            if ($volgende) {
                $volgende->update(["judoka_{$wedstrijd->winnaar_naar_slot}_id" => $winnaarId]);
                \Log::info("verwerkUitslag: winnaar {$winnaarId} naar volgende wedstrijd {$volgende->id} slot {$wedstrijd->winnaar_naar_slot}");
            }
        }

        // Verliezer direct naar B (niet wachten op ronde compleet)
        if ($wedstrijd->groep === 'A' && $wedstrijd->ronde !== 'finale' && $verliezerId) {
            \Log::info("verwerkUitslag: plaatsVerliezerInB aanroepen voor {$verliezerId}");
            $this->plaatsVerliezerInB($wedstrijd, $verliezerId);
        } else {
            \Log::info("verwerkUitslag: SKIP plaatsVerliezerInB - groep={$wedstrijd->groep}, ronde={$wedstrijd->ronde}, verliezerId={$verliezerId}");
        }

        // B-wedstrijd winnaar naar volgende B-ronde (direct)
        if ($wedstrijd->groep === 'B' && $wedstrijd->ronde !== 'b_brons') {
            $this->schuifBWinnaarDoor($wedstrijd, $winnaarId);
        }

        return $correcties;
    }

    /**
     * Verwijder oude plaatsingen bij correctie van uitslag
     * Returns array met correctie-informatie
     */
    private function verwijderOudePlaatsingen(Wedstrijd $wedstrijd, int $oudeWinnaarId): array
    {
        $correcties = [];

        // Haal judoka namen op voor melding
        $oudeWinnaar = \App\Models\Judoka::find($oudeWinnaarId);
        $nieuweWinnaarId = $wedstrijd->judoka_wit_id == $oudeWinnaarId
            ? $wedstrijd->judoka_blauw_id
            : $wedstrijd->judoka_wit_id;
        $nieuweWinnaar = \App\Models\Judoka::find($nieuweWinnaarId);

        // Oude winnaar was eigenlijk verliezer - verwijder uit volgende A-ronde
        if ($wedstrijd->volgende_wedstrijd_id) {
            $volgende = Wedstrijd::find($wedstrijd->volgende_wedstrijd_id);
            if ($volgende) {
                $verwijderdUitA = false;
                if ($volgende->judoka_wit_id == $oudeWinnaarId) {
                    $volgende->update(['judoka_wit_id' => null]);
                    $verwijderdUitA = true;
                }
                if ($volgende->judoka_blauw_id == $oudeWinnaarId) {
                    $volgende->update(['judoka_blauw_id' => null]);
                    $verwijderdUitA = true;
                }
                if ($verwijderdUitA && $oudeWinnaar) {
                    $correcties[] = "{$oudeWinnaar->naam} verwijderd uit A {$volgende->ronde}";
                }
            }
        }

        // Nieuwe winnaar stond misschien in B - verwijderen
        if ($nieuweWinnaarId) {
            $verwijderdUitB = $this->verwijderUitB($wedstrijd->poule_id, $nieuweWinnaarId);
            if ($verwijderdUitB && $nieuweWinnaar) {
                $correcties[] = "{$nieuweWinnaar->naam} verwijderd uit B-groep (was foutief geplaatst)";
            }
        }

        // Oude winnaar was eigenlijk verliezer - moet naar B (als A-wedstrijd, niet finale)
        if ($wedstrijd->groep === 'A' && $wedstrijd->ronde !== 'finale' && $oudeWinnaarId) {
            $this->plaatsVerliezerInB($wedstrijd, $oudeWinnaarId);
            if ($oudeWinnaar) {
                $correcties[] = "{$oudeWinnaar->naam} geplaatst in B-groep (echte verliezer)";
            }
        }

        return $correcties;
    }

    /**
     * Verwijder judoka uit alle B-wedstrijden
     * Returns true als er iets verwijderd is
     */
    public function verwijderUitB(int $pouleId, int $judokaId): bool
    {
        $count1 = Wedstrijd::where('poule_id', $pouleId)
            ->where('groep', 'B')
            ->where('judoka_wit_id', $judokaId)
            ->update(['judoka_wit_id' => null]);

        $count2 = Wedstrijd::where('poule_id', $pouleId)
            ->where('groep', 'B')
            ->where('judoka_blauw_id', $judokaId)
            ->update(['judoka_blauw_id' => null]);

        return ($count1 + $count2) > 0;
    }

    /**
     * Check of twee judoka's al tegen elkaar hebben gespeeld in deze poule
     */
    public function heeftAlGespeeld(int $pouleId, int $judokaA, int $judokaB): bool
    {
        return Wedstrijd::where('poule_id', $pouleId)
            ->where('is_gespeeld', true)
            ->where(function ($q) use ($judokaA, $judokaB) {
                $q->where(function ($inner) use ($judokaA, $judokaB) {
                    $inner->where('judoka_wit_id', $judokaA)
                          ->where('judoka_blauw_id', $judokaB);
                })->orWhere(function ($inner) use ($judokaA, $judokaB) {
                    $inner->where('judoka_wit_id', $judokaB)
                          ->where('judoka_blauw_id', $judokaA);
                });
            })
            ->exists();
    }

    /**
     * Haal alle tegenstanders op waar een judoka al tegen heeft gespeeld
     */
    public function getGespeeldeTegenstanders(int $pouleId, int $judokaId): array
    {
        $tegenstanders = [];

        $wedstrijden = Wedstrijd::where('poule_id', $pouleId)
            ->where('is_gespeeld', true)
            ->where(function ($q) use ($judokaId) {
                $q->where('judoka_wit_id', $judokaId)
                  ->orWhere('judoka_blauw_id', $judokaId);
            })
            ->get();

        foreach ($wedstrijden as $wed) {
            if ($wed->judoka_wit_id == $judokaId && $wed->judoka_blauw_id) {
                $tegenstanders[] = $wed->judoka_blauw_id;
            } elseif ($wed->judoka_blauw_id == $judokaId && $wed->judoka_wit_id) {
                $tegenstanders[] = $wed->judoka_wit_id;
            }
        }

        return array_unique($tegenstanders);
    }

    /**
     * Plaats verliezer direct in B-groep
     *
     * Routing:
     * - A voorronde verliezers → B 1/8 (gespaard)
     * - ALLE A 1/8 verliezers → B voorronde (extra wedstrijd)
     * - A 1/4 verliezers → B 1/4 deel 2 (slim geplaatst om rematches te vermijden)
     * - A 1/2 verliezers → B brons
     */
    private function plaatsVerliezerInB(Wedstrijd $wedstrijd, int $verliezerId): void
    {
        // Check of dit een bye-verliezer is (had vrijstelling in A = speelde NIET in voorronde)
        // Alleen relevant voor A 1/8 verliezers
        $hadByeInA = false;
        if ($wedstrijd->ronde === 'achtste_finale') {
            // Check of verliezer in een A-voorronde wedstrijd zat
            $wasInVoorronde = Wedstrijd::where('poule_id', $wedstrijd->poule_id)
                ->where('groep', 'A')
                ->where('ronde', 'voorronde')
                ->where(function ($q) use ($verliezerId) {
                    $q->where('judoka_wit_id', $verliezerId)
                      ->orWhere('judoka_blauw_id', $verliezerId);
                })
                ->exists();
            $hadByeInA = !$wasInVoorronde;
        }

        \Log::info("plaatsVerliezerInB: verliezer={$verliezerId}, wedstrijd={$wedstrijd->id}, ronde={$wedstrijd->ronde}, hadBye={$hadByeInA}");

        // Check of verliezer al in B zit
        $alInB = Wedstrijd::where('poule_id', $wedstrijd->poule_id)
            ->where('groep', 'B')
            ->where(function ($q) use ($verliezerId) {
                $q->where('judoka_wit_id', $verliezerId)
                  ->orWhere('judoka_blauw_id', $verliezerId);
            })
            ->exists();

        if ($alInB) {
            \Log::info("plaatsVerliezerInB: verliezer {$verliezerId} zit al in B-groep, skip");
            return;
        }

        // Bepaal target B-ronde op basis van A-ronde (dynamisch)
        $targetRonde = $this->getBRondeVoorARonde($wedstrijd->ronde, $wedstrijd->poule_id);
        \Log::info("plaatsVerliezerInB: targetRonde={$targetRonde}");
        if (!$targetRonde) {
            \Log::info("plaatsVerliezerInB: geen target ronde voor {$wedstrijd->ronde}");
            return;
        }

        $legeWedstrijd = null;
        $plaatsKleur = null;

        // === NIEUWE LOGICA ===
        // Bye-verliezers (hadden al vrijstelling in A) gaan EERST naar B-voorronde
        // Zo krijgen ze geen 2e vrijstelling
        if ($hadByeInA) {
            // Probeer eerst B-voorronde (als die bestaat)
            $legeWedstrijd = $this->zoekLegePlekMetKleur($wedstrijd->poule_id, 'b_voorronde', 'wit');
            $plaatsKleur = 'wit';
            if (!$legeWedstrijd) {
                $legeWedstrijd = $this->zoekLegePlekMetKleur($wedstrijd->poule_id, 'b_voorronde', 'blauw');
                $plaatsKleur = 'blauw';
            }
            \Log::info("plaatsVerliezerInB: bye-verliezer, zoek plek in b_voorronde = " . ($legeWedstrijd ? $legeWedstrijd->id : 'GEEN'));

            // Als B-voorronde vol, zoek plek in target ronde NAAST bestaande tegenstander
            if (!$legeWedstrijd) {
                $legeWedstrijd = $this->zoekWedstrijdMetTegenstander($wedstrijd->poule_id, $targetRonde);
                $plaatsKleur = 'blauw';
                \Log::info("plaatsVerliezerInB: bye-verliezer fallback naar {$targetRonde} met tegenstander = " . ($legeWedstrijd ? $legeWedstrijd->id : 'GEEN'));
            }
        }

        // === DIRECTE PLAATSING VOOR A 1/2 VERLIEZERS → BRONS ===
        if (!$legeWedstrijd && $wedstrijd->ronde === 'halve_finale') {
            $legeWedstrijd = $this->zoekLegePlekMetKleur($wedstrijd->poule_id, 'b_brons', 'wit', false);
            $plaatsKleur = 'wit';  // A 1/2 verliezers komen als WIT in Brons
            \Log::info("plaatsVerliezerInB: halve finale verliezer → Brons WIT = " . ($legeWedstrijd ? $legeWedstrijd->id : 'GEEN'));
        }

        // === SLIMME PLAATSING VOOR A 1/4 VERLIEZERS → B 1/4 deel 2 ===
        if (!$legeWedstrijd && $wedstrijd->ronde === 'kwartfinale') {
            $legeWedstrijd = $this->zoekSlimmePlekVoorKwartfinaleVerliezer(
                $wedstrijd->poule_id,
                $verliezerId,
                $targetRonde
            );
            $plaatsKleur = 'wit';  // A 1/4 verliezers komen als WIT in B 1/4 deel 2
            \Log::info("plaatsVerliezerInB: kwartfinale verliezer → B 1/4 deel 2 WIT = " . ($legeWedstrijd ? $legeWedstrijd->id : 'GEEN'));
        }

        // Normale verliezer OF geen plek gevonden voor bye-verliezer/kwartfinale
        // BELANGRIJK: Vul B voorronde EERST volledig voordat B 1/8 wordt gevuld
        // SKIP voor kwartfinale en halve_finale verliezers (die hebben eigen routing)
        $skipBVoorronde = in_array($wedstrijd->ronde, ['kwartfinale', 'halve_finale']);
        if (!$legeWedstrijd && $targetRonde !== 'b_voorronde' && !$skipBVoorronde) {
            // Check of B voorronde nog BLAUW plekken heeft (WIT is vaak al gevuld)
            $bVoorrondeLeeg = $this->zoekLegePlekMetKleur($wedstrijd->poule_id, 'b_voorronde', 'blauw', false);
            if ($bVoorrondeLeeg) {
                $legeWedstrijd = $bVoorrondeLeeg;
                $plaatsKleur = 'blauw';
                \Log::info("plaatsVerliezerInB: B voorronde BLAUW nog vrij = " . $legeWedstrijd->id);
            }
            // Ook WIT checken als die nog leeg is
            if (!$legeWedstrijd) {
                $bVoorrondeWit = $this->zoekLegePlekMetKleur($wedstrijd->poule_id, 'b_voorronde', 'wit', false);
                if ($bVoorrondeWit) {
                    $legeWedstrijd = $bVoorrondeWit;
                    $plaatsKleur = 'wit';
                    \Log::info("plaatsVerliezerInB: B voorronde WIT nog vrij = " . $legeWedstrijd->id);
                }
            }
        }

        // Dan pas naar target ronde (B 1/8) als B voorronde vol is
        if (!$legeWedstrijd) {
            // Eerst WIT plekken vullen in target ronde (skip gereserveerde)
            $legeWedstrijd = $this->zoekLegePlekMetKleur($wedstrijd->poule_id, $targetRonde, 'wit');
            $plaatsKleur = 'wit';
            \Log::info("plaatsVerliezerInB: zoek WIT in {$targetRonde} = " . ($legeWedstrijd ? $legeWedstrijd->id : 'GEEN'));
        }

        // Dan BLAUW plekken (skip gereserveerde)
        if (!$legeWedstrijd) {
            $legeWedstrijd = $this->zoekLegePlekMetKleur($wedstrijd->poule_id, $targetRonde, 'blauw');
            $plaatsKleur = 'blauw';
            \Log::info("plaatsVerliezerInB: zoek BLAUW in {$targetRonde} = " . ($legeWedstrijd ? $legeWedstrijd->id : 'GEEN'));
        }

        if ($legeWedstrijd && $plaatsKleur) {
            $legeWedstrijd->update(["judoka_{$plaatsKleur}_id" => $verliezerId]);
            \Log::info("plaatsVerliezerInB: verliezer {$verliezerId} geplaatst als {$plaatsKleur} in wedstrijd {$legeWedstrijd->id} ({$legeWedstrijd->ronde})");
        } else {
            \Log::warning("Geen plek in B-groep voor verliezer {$verliezerId} uit {$wedstrijd->ronde}");
        }
    }

    /**
     * Zoek slimme plek voor A 1/4 verliezer in B 1/4 deel 2
     * Vermijdt rematches door te kijken naar potentiële tegenstanders uit B 1/4 deel 1
     */
    private function zoekSlimmePlekVoorKwartfinaleVerliezer(int $pouleId, int $verliezerId, string $targetRonde): ?Wedstrijd
    {
        // Haal alle eerdere tegenstanders op
        $gespeeldTegen = $this->getGespeeldeTegenstanders($pouleId, $verliezerId);
        \Log::info("zoekSlimmePlek: verliezer {$verliezerId} heeft gespeeld tegen: " . implode(',', $gespeeldTegen));

        // Haal alle lege WIT plekken in B 1/4 deel 2
        $legeWedstrijden = Wedstrijd::where('poule_id', $pouleId)
            ->where('groep', 'B')
            ->where('ronde', $targetRonde)
            ->whereNull('judoka_wit_id')
            ->orderBy('bracket_positie')
            ->get();

        if ($legeWedstrijden->isEmpty()) {
            return null;
        }

        // Voor elke lege plek, check potentiële tegenstanders
        $besteWedstrijd = null;
        $geenRematch = false;

        foreach ($legeWedstrijden as $wed) {
            // Vind de B 1/4 deel 1 wedstrijd die naar deze wedstrijd leidt
            $bronWedstrijd = Wedstrijd::where('poule_id', $pouleId)
                ->where('groep', 'B')
                ->where('ronde', 'b_kwartfinale_1')
                ->where('volgende_wedstrijd_id', $wed->id)
                ->first();

            if (!$bronWedstrijd) {
                // Geen bron wedstrijd gevonden, dit is een veilige plek
                if (!$besteWedstrijd) {
                    $besteWedstrijd = $wed;
                    $geenRematch = true;
                }
                continue;
            }

            // Check of verliezer al tegen een van de potentiële tegenstanders heeft gespeeld
            $potentieleTegenstanders = array_filter([
                $bronWedstrijd->judoka_wit_id,
                $bronWedstrijd->judoka_blauw_id
            ]);

            $heeftRematch = false;
            foreach ($potentieleTegenstanders as $tegenstander) {
                if (in_array($tegenstander, $gespeeldTegen)) {
                    $heeftRematch = true;
                    \Log::info("zoekSlimmePlek: wedstrijd {$wed->id} zou rematch geven met {$tegenstander}");
                    break;
                }
            }

            if (!$heeftRematch) {
                // Geen rematch, dit is een goede plek
                $besteWedstrijd = $wed;
                $geenRematch = true;
                break;
            } elseif (!$besteWedstrijd) {
                // Fallback: eerste beschikbare plek (zelfs met rematch)
                $besteWedstrijd = $wed;
            }
        }

        if ($besteWedstrijd && !$geenRematch) {
            \Log::warning("zoekSlimmePlek: alleen plek met potentiële rematch beschikbaar voor {$verliezerId}");
        }

        return $besteWedstrijd;
    }

    /**
     * Zoek wedstrijd waar al een tegenstander op WIT staat (voor bye-verliezers)
     */
    private function zoekWedstrijdMetTegenstander(int $pouleId, string $ronde): ?Wedstrijd
    {
        return Wedstrijd::where('poule_id', $pouleId)
            ->where('groep', 'B')
            ->where('ronde', $ronde)
            ->whereNotNull('judoka_wit_id')  // Er staat al iemand op WIT
            ->whereNull('judoka_blauw_id')   // BLAUW is nog vrij
            ->orderBy('bracket_positie')
            ->first();
    }

    /**
     * Zoek lege plek met specifieke kleur
     * Skip gereserveerde plekken (waar B voorronde winnaars naartoe gaan)
     */
    private function zoekLegePlekMetKleur(int $pouleId, string $ronde, string $kleur, bool $skipReserved = true): ?Wedstrijd
    {
        $query = Wedstrijd::where('poule_id', $pouleId)
            ->where('groep', 'B')
            ->where('ronde', $ronde)
            ->whereNull("judoka_{$kleur}_id")
            ->orderBy('bracket_positie');

        // Skip plekken die gereserveerd zijn voor B voorronde winnaars
        if ($skipReserved && $ronde !== 'b_voorronde') {
            $reservedIds = Wedstrijd::where('poule_id', $pouleId)
                ->where('groep', 'B')
                ->where('ronde', 'b_voorronde')
                ->whereNotNull('volgende_wedstrijd_id')
                ->where('winnaar_naar_slot', $kleur)
                ->pluck('volgende_wedstrijd_id');

            if ($reservedIds->isNotEmpty()) {
                $query->whereNotIn('id', $reservedIds);
            }
        }

        return $query->first();
    }

    /**
     * Geef vorige ronde (eerder in het toernooi) als fallback
     * Verliezers mogen alleen naar een eerdere ronde, nooit naar een latere
     */
    private function getVorigeRonde(string $ronde): ?string
    {
        return match ($ronde) {
            'b_achtste_finale' => 'b_voorronde',
            'b_achtste_finale_2' => 'b_achtste_finale',
            'b_kwartfinale_1' => 'b_achtste_finale_2',
            'b_kwartfinale_2' => 'b_kwartfinale_1',
            'b_halve_finale_1' => 'b_kwartfinale_2',
            'b_halve_finale' => 'b_halve_finale_1',
            'b_brons' => 'b_halve_finale_1',
            default => null,
        };
    }

    /**
     * Verwerk byes in B-groep: judoka's zonder tegenstander schuiven automatisch door
     * Returns array met verwerkte byes
     */
    public function verwerkBByes(int $pouleId): array
    {
        $verwerkt = [];
        $rondeVolgorde = [
            'b_voorronde',
            'b_achtste_finale',
            'b_achtste_finale_2',
            'b_kwartfinale_1',
            'b_kwartfinale_2',
            'b_halve_finale_1',
            'b_halve_finale',
        ];

        foreach ($rondeVolgorde as $ronde) {
            $wedstrijden = Wedstrijd::where('poule_id', $pouleId)
                ->where('groep', 'B')
                ->where('ronde', $ronde)
                ->where('is_gespeeld', false)
                ->get();

            foreach ($wedstrijden as $wed) {
                $heeftWit = $wed->judoka_wit_id !== null;
                $heeftBlauw = $wed->judoka_blauw_id !== null;

                // Bye: precies 1 judoka, geen tegenstander
                if (($heeftWit xor $heeftBlauw) && $wed->volgende_wedstrijd_id) {
                    $byeJudokaId = $heeftWit ? $wed->judoka_wit_id : $wed->judoka_blauw_id;
                    $judoka = \App\Models\Judoka::find($byeJudokaId);

                    // Markeer als gespeeld (bye)
                    $wed->update([
                        'winnaar_id' => $byeJudokaId,
                        'is_gespeeld' => true,
                        'uitslag_type' => 'bye',
                    ]);

                    // Plaats in volgende ronde
                    $volgende = Wedstrijd::find($wed->volgende_wedstrijd_id);
                    if ($volgende && $wed->winnaar_naar_slot) {
                        $volgende->update(["judoka_{$wed->winnaar_naar_slot}_id" => $byeJudokaId]);
                        $verwerkt[] = ($judoka ? $judoka->naam : "Judoka $byeJudokaId") . " → bye naar " . $this->getRondeDisplayNaam($volgende->ronde);
                    }
                }
            }
        }

        return $verwerkt;
    }

    /**
     * Geef leesbare ronde naam
     */
    private function getRondeDisplayNaam(string $ronde): string
    {
        return match ($ronde) {
            'b_voorronde' => 'B voorronde',
            'b_achtste_finale' => 'B 1/8',
            'b_achtste_finale_2' => 'B 1/8 (2)',
            'b_kwartfinale_1' => 'B 1/4 (1)',
            'b_kwartfinale_2' => 'B 1/4 (2)',
            'b_halve_finale_1' => 'B 1/2 (1)',
            'b_halve_finale' => 'B 1/2',
            'b_brons' => 'Brons',
            default => $ronde,
        };
    }

    /**
     * Bepaal B-ronde voor A-ronde verliezers (DYNAMISCH)
     *
     * Routing per bracket grootte (D):
     *
     * D=32:
     * - A 1/16 → B 1/8 deel 1
     * - A 1/8 → B 1/8 deel 2
     * - A 1/4 → B 1/4 deel 2
     * - A 1/2 → Brons
     *
     * D=16:
     * - A voorronde → B 1/8
     * - A 1/8 (bye) → B voorronde
     * - A 1/8 (geen bye) → B 1/8
     * - A 1/4 → B 1/4 deel 2
     * - A 1/2 → Brons
     *
     * D=8:
     * - A voorronde → B 1/4 deel 1
     * - A 1/4 → B 1/4 deel 2
     * - A 1/2 → Brons
     *
     * D=4:
     * - A voorronde → B 1/2 deel 1
     * - A 1/2 → Brons
     *
     * @param string $aRonde De A-ronde naam
     * @param int $pouleId Om beschikbare B-rondes te vinden
     */
    private function getBRondeVoorARonde(string $aRonde, ?int $pouleId = null): ?string
    {
        // Halve finale verliezers → brons (altijd)
        if ($aRonde === 'halve_finale') {
            return 'b_brons';
        }

        // Kwartfinale verliezers → B 1/4 deel 2
        if ($aRonde === 'kwartfinale') {
            return 'b_kwartfinale_2';
        }

        // A 1/8 verliezers: hangt af van D
        if ($aRonde === 'achtste_finale' && $pouleId) {
            // Check of er een B 1/8 deel 2 bestaat (D=32)
            $heeftDeel2 = Wedstrijd::where('poule_id', $pouleId)
                ->where('groep', 'B')
                ->where('ronde', 'b_achtste_finale_2')
                ->exists();

            if ($heeftDeel2) {
                return 'b_achtste_finale_2';
            }
            // Anders naar B 1/8 (bye-verliezers worden apart afgehandeld in plaatsVerliezerInB)
            return 'b_achtste_finale';
        }

        // A 1/16 verliezers (D=32) → B 1/8 deel 1
        if ($aRonde === 'zestiende_finale') {
            return 'b_achtste_finale';
        }

        // A voorronde verliezers: hangt af van beschikbare B-rondes
        if ($aRonde === 'voorronde' && $pouleId) {
            // Check welke B-rondes bestaan
            $heeftB18 = Wedstrijd::where('poule_id', $pouleId)
                ->where('groep', 'B')
                ->where('ronde', 'b_achtste_finale')
                ->exists();

            if ($heeftB18) {
                return 'b_achtste_finale';  // D=16+
            }

            $heeftB14 = Wedstrijd::where('poule_id', $pouleId)
                ->where('groep', 'B')
                ->where('ronde', 'b_kwartfinale_1')
                ->exists();

            if ($heeftB14) {
                return 'b_kwartfinale_1';  // D=8
            }

            return 'b_halve_finale_1';  // D=4
        }

        // Fallback: probeer B 1/8, dan B 1/4, dan B 1/2
        if ($pouleId) {
            $heeftB18 = Wedstrijd::where('poule_id', $pouleId)
                ->where('groep', 'B')
                ->where('ronde', 'b_achtste_finale')
                ->exists();
            if ($heeftB18) return 'b_achtste_finale';

            $heeftB14 = Wedstrijd::where('poule_id', $pouleId)
                ->where('groep', 'B')
                ->where('ronde', 'b_kwartfinale_1')
                ->exists();
            if ($heeftB14) return 'b_kwartfinale_1';

            return 'b_halve_finale_1';
        }

        return 'b_achtste_finale';
    }

    private function zoekLegePlek(int $pouleId, string $ronde): ?Wedstrijd
    {
        return Wedstrijd::where('poule_id', $pouleId)
            ->where('groep', 'B')
            ->where('ronde', $ronde)
            ->where(function ($q) {
                $q->whereNull('judoka_wit_id')
                  ->orWhereNull('judoka_blauw_id');
            })
            ->inRandomOrder()
            ->first();
    }

    /**
     * Schuif B-winnaar door naar volgende ronde via de gelinkte volgende_wedstrijd_id
     */
    private function schuifBWinnaarDoor(Wedstrijd $wedstrijd, int $winnaarId): void
    {
        // Gebruik de gelinkte volgende wedstrijd (gezet tijdens bracket generatie)
        if ($wedstrijd->volgende_wedstrijd_id && $wedstrijd->winnaar_naar_slot) {
            $volgende = Wedstrijd::find($wedstrijd->volgende_wedstrijd_id);
            if ($volgende) {
                $volgende->update(["judoka_{$wedstrijd->winnaar_naar_slot}_id" => $winnaarId]);
                \Log::info("schuifBWinnaarDoor: {$winnaarId} naar wedstrijd {$volgende->id} slot {$wedstrijd->winnaar_naar_slot}");
                return;
            }
        }

        // Fallback: zoek lege plek in volgende ronde (voor oude data zonder links)
        \Log::warning("schuifBWinnaarDoor: geen volgende_wedstrijd_id voor wedstrijd {$wedstrijd->id}, zoek lege plek");

        // Zoek de volgende B-ronde wedstrijd met een lege plek
        $volgendeWeds = Wedstrijd::where('poule_id', $wedstrijd->poule_id)
            ->where('groep', 'B')
            ->where('volgorde', '>', $wedstrijd->volgorde)
            ->where(function ($q) {
                $q->whereNull('judoka_wit_id')
                  ->orWhereNull('judoka_blauw_id');
            })
            ->orderBy('volgorde')
            ->first();

        if ($volgendeWeds) {
            if ($volgendeWeds->judoka_wit_id === null) {
                $volgendeWeds->update(['judoka_wit_id' => $winnaarId]);
            } else {
                $volgendeWeds->update(['judoka_blauw_id' => $winnaarId]);
            }
        }
    }
}
