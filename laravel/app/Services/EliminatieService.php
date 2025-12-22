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

    public function genereerBracket(Poule $poule, ?Collection $judokas = null): array
    {
        $judokas = $judokas ?? $poule->judokas;
        $aantal = $judokas->count();

        if ($aantal < 2) {
            return ['error' => 'Minimaal 2 judoka\'s nodig'];
        }

        return DB::transaction(function () use ($poule, $judokas, $aantal) {
            $poule->wedstrijden()->delete();

            $geseededJudokas = $judokas->shuffle()->values()->all();

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
     * Genereer B-poule structuur met correcte capaciteit
     *
     * Structuur (zie docs/ELIMINATIE_BEREKENING.md):
     * - B voorronde: overflow als (voorronde + 1/8 verliezers) > 16
     * - B 1/8: 8 wedstrijden (16 plekken)
     * - B 1/4 deel 1: 4 wedstrijden (B 1/8 winnaars)
     * - B 1/4 deel 2: 4 wedstrijden (deel 1 winnaars + A 1/4 verliezers)
     * - B 1/2: 2 wedstrijden
     * - Brons: 2 wedstrijden (B-winnaars vs A 1/2 verliezers)
     */
    private function genereerBPoule(Poule $poule, int $aCount, int $doelA, int $voorrondeA): array
    {
        $wedstrijden = [];
        $volgorde = $aCount + 1;

        // === BEREKENING B-VOORRONDE ===
        $a18Verliezers = $doelA / 2;  // 8 bij doel=16
        $totaalVerliezers = $voorrondeA + $a18Verliezers;  // bijv. 13 + 8 = 21
        $b18Capaciteit = 16;

        $bVoorrondeAantal = max(0, $totaalVerliezers - $b18Capaciteit);
        \Log::info("B-groep: {$totaalVerliezers} verliezers, {$bVoorrondeAantal} B-voorronde wedstrijden");

        // === B VOORRONDE ===
        $bVoorrondeWeds = [];
        for ($i = 0; $i < $bVoorrondeAantal; $i++) {
            $wed = Wedstrijd::create([
                'poule_id' => $poule->id,
                'volgorde' => $volgorde++,
                'ronde' => 'b_voorronde',
                'groep' => 'B',
                'bracket_positie' => $i + 1,
            ]);
            $bVoorrondeWeds[] = $wed;
            $wedstrijden[] = $wed;
        }

        // === B 1/8 FINALE (8 wedstrijden) ===
        $b18Weds = [];
        for ($i = 0; $i < 8; $i++) {
            $wed = Wedstrijd::create([
                'poule_id' => $poule->id,
                'volgorde' => $volgorde++,
                'ronde' => 'b_achtste_finale',
                'groep' => 'B',
                'bracket_positie' => $i + 1,
            ]);
            $b18Weds[] = $wed;
            $wedstrijden[] = $wed;
        }

        // Koppel B voorronde → B 1/8
        foreach ($bVoorrondeWeds as $idx => $voorrondeWed) {
            $volgendeIdx = intdiv($idx, 2);
            if (isset($b18Weds[$volgendeIdx])) {
                $voorrondeWed->update([
                    'volgende_wedstrijd_id' => $b18Weds[$volgendeIdx]->id,
                    'winnaar_naar_slot' => ($idx % 2 === 0) ? 'wit' : 'blauw',
                ]);
            }
        }

        // === B 1/4 DEEL 1 (4 wedstrijden) ===
        $b14Deel1Weds = [];
        for ($i = 0; $i < 4; $i++) {
            $wed = Wedstrijd::create([
                'poule_id' => $poule->id,
                'volgorde' => $volgorde++,
                'ronde' => 'b_kwartfinale_1',
                'groep' => 'B',
                'bracket_positie' => $i + 1,
            ]);
            $b14Deel1Weds[] = $wed;
            $wedstrijden[] = $wed;
        }

        // Koppel B 1/8 → B 1/4 deel 1
        foreach ($b18Weds as $idx => $wed) {
            $volgendeIdx = intdiv($idx, 2);
            if (isset($b14Deel1Weds[$volgendeIdx])) {
                $wed->update([
                    'volgende_wedstrijd_id' => $b14Deel1Weds[$volgendeIdx]->id,
                    'winnaar_naar_slot' => ($idx % 2 === 0) ? 'wit' : 'blauw',
                ]);
            }
        }

        // === B 1/4 DEEL 2 (4 wedstrijden) ===
        // Hier stromen A 1/4 verliezers in (als WIT), B 1/4 deel 1 winnaars als BLAUW
        $b14Deel2Weds = [];
        for ($i = 0; $i < 4; $i++) {
            $wed = Wedstrijd::create([
                'poule_id' => $poule->id,
                'volgorde' => $volgorde++,
                'ronde' => 'b_kwartfinale_2',
                'groep' => 'B',
                'bracket_positie' => $i + 1,
            ]);
            $b14Deel2Weds[] = $wed;
            $wedstrijden[] = $wed;
        }

        // Koppel B 1/4 deel 1 → B 1/4 deel 2 (winnaars als BLAUW)
        foreach ($b14Deel1Weds as $idx => $wed) {
            if (isset($b14Deel2Weds[$idx])) {
                $wed->update([
                    'volgende_wedstrijd_id' => $b14Deel2Weds[$idx]->id,
                    'winnaar_naar_slot' => 'blauw',  // B-winnaar is blauw, A-verliezer is wit
                ]);
            }
        }

        // === B 1/2 FINALE (2 wedstrijden) ===
        $b12Weds = [];
        for ($i = 0; $i < 2; $i++) {
            $wed = Wedstrijd::create([
                'poule_id' => $poule->id,
                'volgorde' => $volgorde++,
                'ronde' => 'b_halve_finale',
                'groep' => 'B',
                'bracket_positie' => $i + 1,
            ]);
            $b12Weds[] = $wed;
            $wedstrijden[] = $wed;
        }

        // Koppel B 1/4 deel 2 → B 1/2
        foreach ($b14Deel2Weds as $idx => $wed) {
            $volgendeIdx = intdiv($idx, 2);
            if (isset($b12Weds[$volgendeIdx])) {
                $wed->update([
                    'volgende_wedstrijd_id' => $b12Weds[$volgendeIdx]->id,
                    'winnaar_naar_slot' => ($idx % 2 === 0) ? 'wit' : 'blauw',
                ]);
            }
        }

        // === BRONSWEDSTRIJDEN (2 wedstrijden) ===
        // A 1/2 verliezers (WIT) vs B 1/2 winnaars (BLAUW)
        $bronsWeds = [];
        for ($i = 0; $i < 2; $i++) {
            $wed = Wedstrijd::create([
                'poule_id' => $poule->id,
                'volgorde' => $volgorde++,
                'ronde' => 'b_brons',
                'groep' => 'B',
                'bracket_positie' => $i + 1,
            ]);
            $bronsWeds[] = $wed;
            $wedstrijden[] = $wed;
        }

        // Koppel B 1/2 → Brons (winnaars als BLAUW)
        foreach ($b12Weds as $idx => $wed) {
            if (isset($bronsWeds[$idx])) {
                $wed->update([
                    'volgende_wedstrijd_id' => $bronsWeds[$idx]->id,
                    'winnaar_naar_slot' => 'blauw',
                ]);
            }
        }

        return $wedstrijden;
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
     * Plaats verliezer direct in B-groep
     *
     * Routing:
     * - A voorronde verliezers → B 1/8 (gespaard)
     * - ALLE A 1/8 verliezers → B voorronde (extra wedstrijd)
     * - A 1/4 verliezers → B 1/4
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

        // Bepaal target B-ronde op basis van A-ronde
        $targetRonde = $this->getBRondeVoorARonde($wedstrijd->ronde);
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

        // Normale verliezer OF geen plek gevonden voor bye-verliezer
        if (!$legeWedstrijd) {
            // Eerst WIT plekken vullen in target ronde
            $legeWedstrijd = $this->zoekLegePlekMetKleur($wedstrijd->poule_id, $targetRonde, 'wit');
            $plaatsKleur = 'wit';
            \Log::info("plaatsVerliezerInB: zoek WIT in {$targetRonde} = " . ($legeWedstrijd ? $legeWedstrijd->id : 'GEEN'));
        }

        // Dan BLAUW plekken
        if (!$legeWedstrijd) {
            $legeWedstrijd = $this->zoekLegePlekMetKleur($wedstrijd->poule_id, $targetRonde, 'blauw');
            $plaatsKleur = 'blauw';
            \Log::info("plaatsVerliezerInB: zoek BLAUW in {$targetRonde} = " . ($legeWedstrijd ? $legeWedstrijd->id : 'GEEN'));
        }

        // Fallback naar B-voorronde (als target vol)
        if (!$legeWedstrijd && $targetRonde !== 'b_voorronde') {
            $legeWedstrijd = $this->zoekLegePlekMetKleur($wedstrijd->poule_id, 'b_voorronde', 'wit');
            $plaatsKleur = 'wit';
            if (!$legeWedstrijd) {
                $legeWedstrijd = $this->zoekLegePlekMetKleur($wedstrijd->poule_id, 'b_voorronde', 'blauw');
                $plaatsKleur = 'blauw';
            }
            \Log::info("plaatsVerliezerInB: fallback naar b_voorronde = " . ($legeWedstrijd ? $legeWedstrijd->id : 'GEEN'));
        }

        if ($legeWedstrijd && $plaatsKleur) {
            $legeWedstrijd->update(["judoka_{$plaatsKleur}_id" => $verliezerId]);
            \Log::info("plaatsVerliezerInB: verliezer {$verliezerId} geplaatst als {$plaatsKleur} in wedstrijd {$legeWedstrijd->id} ({$legeWedstrijd->ronde})");
        } else {
            \Log::warning("Geen plek in B-groep voor verliezer {$verliezerId} uit {$wedstrijd->ronde}");
        }
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
     */
    private function zoekLegePlekMetKleur(int $pouleId, string $ronde, string $kleur): ?Wedstrijd
    {
        return Wedstrijd::where('poule_id', $pouleId)
            ->where('groep', 'B')
            ->where('ronde', $ronde)
            ->whereNull("judoka_{$kleur}_id")
            ->orderBy('bracket_positie')
            ->first();
    }

    /**
     * Geef vorige ronde (eerder in het toernooi) als fallback
     * Verliezers mogen alleen naar een eerdere ronde, nooit naar een latere
     */
    private function getVorigeRonde(string $ronde): ?string
    {
        return match ($ronde) {
            'b_achtste_finale' => 'b_voorronde',
            'b_kwartfinale_1' => 'b_achtste_finale',
            'b_kwartfinale_2' => 'b_kwartfinale_1',
            'b_halve_finale' => 'b_kwartfinale_2',
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
        $rondeVolgorde = ['b_voorronde', 'b_achtste_finale', 'b_kwartfinale_1', 'b_kwartfinale_2', 'b_halve_finale'];

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
            'b_kwartfinale_1' => 'B 1/4 (1)',
            'b_kwartfinale_2' => 'B 1/4 (2)',
            'b_halve_finale' => 'B 1/2',
            'b_brons' => 'Brons',
            default => $ronde,
        };
    }

    /**
     * Bepaal B-ronde voor A-ronde verliezers
     *
     * A-voorronde verliezers → B 1/8 (hebben al 1 wedstrijd)
     * A 1/8 verliezers → B 1/8 (default, bye-verliezers gaan via speciale logica naar B-voorronde)
     * A 1/4 verliezers → B 1/4 deel 2 (instroom naast B 1/4 deel 1 winnaars)
     */
    private function getBRondeVoorARonde(string $aRonde): ?string
    {
        return match ($aRonde) {
            'voorronde' => 'b_achtste_finale',       // Voorronde verliezers naar B 1/8
            'achtste_finale' => 'b_achtste_finale',  // 1/8 verliezers naar B 1/8 (bye-verliezers gaan eerst naar b_voorronde)
            'zestiende_finale' => 'b_achtste_finale',
            'kwartfinale' => 'b_kwartfinale_2',      // 1/4 verliezers naar B 1/4 deel 2 (als WIT)
            'halve_finale' => 'b_brons',
            default => null,
        };
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

    private function schuifBWinnaarDoor(Wedstrijd $wedstrijd, int $winnaarId): void
    {
        $volgendeRonde = $this->getVolgendeBRonde($wedstrijd->ronde);

        $legeWedstrijd = $this->zoekLegePlek($wedstrijd->poule_id, $volgendeRonde);

        // Fallback naar b_brons
        if (!$legeWedstrijd) {
            $legeWedstrijd = $this->zoekLegePlek($wedstrijd->poule_id, 'b_brons');
        }

        if ($legeWedstrijd) {
            if ($legeWedstrijd->judoka_wit_id === null) {
                $legeWedstrijd->update(['judoka_wit_id' => $winnaarId]);
            } else {
                $legeWedstrijd->update(['judoka_blauw_id' => $winnaarId]);
            }
        }
    }

    private function getVolgendeBRonde(string $huidigeRonde): ?string
    {
        return match ($huidigeRonde) {
            'b_voorronde' => 'b_achtste_finale',
            'b_zestiende_finale' => 'b_achtste_finale',
            'b_achtste_finale' => 'b_kwartfinale_1',
            'b_kwartfinale_1' => 'b_kwartfinale_2',
            'b_kwartfinale_2' => 'b_halve_finale',
            'b_halve_finale' => 'b_brons',
            default => null,
        };
    }
}
