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
     * Haal bracket structuur op voor weergave (alleen A-groep)
     */
    public function getBracketStructuur(Poule $poule): array
    {
        $wedstrijden = $poule->wedstrijden()
            ->with(['judokaWit', 'judokaBlauw', 'winnaar'])
            ->where('groep', 'A')
            ->orderBy('bracket_positie')
            ->get();

        // A-groep (hoofdboom) - sorteer rondes in juiste volgorde
        $hoofdboom = $wedstrijden->groupBy('ronde')
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

        return [
            'hoofdboom' => $hoofdboom,
            'herkansing' => collect(),  // B-groep uitgeschakeld
            'brons' => collect(),        // B-groep uitgeschakeld
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

            // Genereer A-bracket (alleen A-groep, B-groep komt later)
            $aWedstrijden = $this->genereerABracket($poule, $geseededJudokas, $doelA, $voorrondeA);

            $totaal = count($aWedstrijden);
            $poule->update(['aantal_wedstrijden' => $totaal]);

            return [
                'aantal_judokas' => $aantal,
                'doel_a' => $doelA,
                'voorronde_a' => $voorrondeA,
                'a_wedstrijden' => count($aWedstrijden),
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
     * Verwerk uitslag en zet winnaar door naar volgende ronde
     */
    public function verwerkUitslag(Wedstrijd $wedstrijd, int $winnaarId, ?int $oudeWinnaarId = null): array
    {
        \Log::info("verwerkUitslag: wedstrijd={$wedstrijd->id}, winnaar={$winnaarId}, oudeWinnaar={$oudeWinnaarId}, ronde={$wedstrijd->ronde}");

        $correcties = [];

        // Bij correctie: verwijder oude winnaar uit volgende ronde
        if ($oudeWinnaarId && $oudeWinnaarId != $winnaarId) {
            \Log::info("verwerkUitslag: correctie detecteerd, oude winnaar verwijderen");
            $correcties = $this->verwijderOudePlaatsingen($wedstrijd, $oudeWinnaarId);
        }

        // Winnaar naar volgende wedstrijd
        if ($wedstrijd->volgende_wedstrijd_id && $wedstrijd->winnaar_naar_slot) {
            $volgende = Wedstrijd::find($wedstrijd->volgende_wedstrijd_id);
            if ($volgende) {
                $volgende->update(["judoka_{$wedstrijd->winnaar_naar_slot}_id" => $winnaarId]);
                \Log::info("verwerkUitslag: winnaar {$winnaarId} naar wedstrijd {$volgende->id} slot {$wedstrijd->winnaar_naar_slot}");
            }
        }

        return $correcties;
    }

    /**
     * Verwijder oude plaatsingen bij correctie van uitslag
     */
    private function verwijderOudePlaatsingen(Wedstrijd $wedstrijd, int $oudeWinnaarId): array
    {
        $correcties = [];
        $oudeWinnaar = \App\Models\Judoka::find($oudeWinnaarId);

        // Verwijder oude winnaar uit volgende ronde
        if ($wedstrijd->volgende_wedstrijd_id) {
            $volgende = Wedstrijd::find($wedstrijd->volgende_wedstrijd_id);
            if ($volgende) {
                if ($volgende->judoka_wit_id == $oudeWinnaarId) {
                    $volgende->update(['judoka_wit_id' => null]);
                    if ($oudeWinnaar) {
                        $correcties[] = "{$oudeWinnaar->naam} verwijderd uit {$volgende->ronde}";
                    }
                }
                if ($volgende->judoka_blauw_id == $oudeWinnaarId) {
                    $volgende->update(['judoka_blauw_id' => null]);
                    if ($oudeWinnaar) {
                        $correcties[] = "{$oudeWinnaar->naam} verwijderd uit {$volgende->ronde}";
                    }
                }
            }
        }

        return $correcties;
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

}
