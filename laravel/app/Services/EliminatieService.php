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
     * Structuur:
     * - B voorronde: voor ALLE A 1/8 verliezers (bye + voorronde-spelers)
     * - B 1/8: B voorronde winnaars + A voorronde verliezers
     * - B 1/4: B 1/8 winnaars + A 1/4 verliezers
     * - B 1/2: B 1/4 winnaars -> 2 winnaars
     * - Brons: 2 B-winnaars vs 2 A 1/2 verliezers
     *
     * Key insight: A 1/8 verliezers (8) gaan naar B voorronde,
     * A voorronde verliezers (13) gaan naar B 1/8
     */
    private function genereerBPoule(Poule $poule, int $aCount, int $doelA, int $voorrondeA): array
    {
        $wedstrijden = [];
        $volgorde = $aCount + 1;

        // Bereken verliezers per A-ronde
        $achtsteVerliezers = $doelA / 2;  // 8 bij doel=16
        $kwartVerliezers = $doelA / 4;    // 4 bij doel=16

        // === B VOORRONDE ===
        // Alle A 1/8 verliezers gaan naar B voorronde
        // Dit geeft ze een eerlijke extra wedstrijd
        $bVoorrondeAantal = ceil($achtsteVerliezers / 2);  // 8 / 2 = 4 wedstrijden
        $bVoorrondeWinnaars = $bVoorrondeAantal;  // 4 winnaars

        // === B 1/8 capaciteit ===
        // B voorronde winnaars + A voorronde verliezers
        $naarB18 = $bVoorrondeWinnaars + $voorrondeA;  // 4 + 13 = 17
        $doelB18 = $this->berekenDoelGrootte($naarB18);  // 16
        $extraVoorB = $naarB18 - $doelB18;  // 17 - 16 = 1 extra

        // Als er meer zijn dan B 1/8 aankan, maak B voorronde groter
        if ($extraVoorB > 0) {
            $bVoorrondeAantal += ceil($extraVoorB / 2);  // +1 wedstrijd voor de overflow
        }

        $bVoorrondeWeds = [];

        if ($bVoorrondeAantal > 0) {
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
        }

        // === B BRACKET ===
        // Bereken de B 1/8 grootte: moet passen met B voorronde winnaars + voorronde verliezers + overflow
        $b18Wedstrijden = max(4, $doelB18 / 2);  // Minimaal 4 (voor 8 plekken)

        $huidigeRonde = [];
        $aantalInRonde = $b18Wedstrijden * 2;  // Aantal judoka's voor deze ronde

        // B eindigt bij 2 halve finales (niet 1 finale)
        while ($aantalInRonde >= 4) {
            $aantalWedstrijden = $aantalInRonde / 2;
            $rondeNaam = $this->getBRondeNaam($aantalWedstrijden);

            $nieuweRonde = [];
            for ($i = 0; $i < $aantalWedstrijden; $i++) {
                $wed = Wedstrijd::create([
                    'poule_id' => $poule->id,
                    'volgorde' => $volgorde++,
                    'ronde' => $rondeNaam,
                    'groep' => 'B',
                    'bracket_positie' => $i + 1,
                ]);
                $nieuweRonde[] = $wed;
                $wedstrijden[] = $wed;
            }

            // Koppel vorige ronde aan deze ronde
            if (count($huidigeRonde) > 0) {
                for ($i = 0; $i < count($huidigeRonde); $i++) {
                    $volgendeIdx = intdiv($i, 2);
                    if (isset($nieuweRonde[$volgendeIdx])) {
                        $huidigeRonde[$i]->update([
                            'volgende_wedstrijd_id' => $nieuweRonde[$volgendeIdx]->id,
                            'winnaar_naar_slot' => ($i % 2 === 0) ? 'wit' : 'blauw',
                        ]);
                    }
                }
            }

            // Koppel B voorronde aan eerste ronde (B 1/8)
            if ($aantalInRonde === $b18Wedstrijden * 2 && count($bVoorrondeWeds) > 0) {
                // Voorronde wedstrijden koppelen aan eerste echte ronde
                foreach ($bVoorrondeWeds as $idx => $voorrondeWed) {
                    $volgendeIdx = intdiv($idx, 2);
                    if (isset($nieuweRonde[$volgendeIdx])) {
                        $voorrondeWed->update([
                            'volgende_wedstrijd_id' => $nieuweRonde[$volgendeIdx]->id,
                            'winnaar_naar_slot' => ($idx % 2 === 0) ? 'wit' : 'blauw',
                        ]);
                    }
                }
            }

            $huidigeRonde = $nieuweRonde;
            $aantalInRonde = $aantalWedstrijden;

            // Stop als we bij 2 halve finales zijn (B eindigt hier, geen finale)
            if ($aantalWedstrijden === 2) {
                break;
            }
        }

        // === BRONSWEDSTRIJDEN ===
        // 2 B-winnaars vs 2 A 1/2 verliezers = 2 wedstrijden
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

        // Koppel B 1/2 finale aan bronswedstrijden
        if (count($huidigeRonde) === 2) {
            $huidigeRonde[0]->update([
                'volgende_wedstrijd_id' => $bronsWeds[0]->id,
                'winnaar_naar_slot' => 'blauw', // B-winnaar is blauw in brons
            ]);
            $huidigeRonde[1]->update([
                'volgende_wedstrijd_id' => $bronsWeds[1]->id,
                'winnaar_naar_slot' => 'blauw',
            ]);
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
        $correcties = [];
        $verliezerId = $wedstrijd->judoka_wit_id == $winnaarId
            ? $wedstrijd->judoka_blauw_id
            : $wedstrijd->judoka_wit_id;

        // Bij correctie: verwijder oude winnaar uit volgende ronde en oude verliezer uit B
        if ($oudeWinnaarId && $oudeWinnaarId != $winnaarId) {
            $correcties = $this->verwijderOudePlaatsingen($wedstrijd, $oudeWinnaarId);
        }

        // Winnaar naar volgende A-wedstrijd (direct)
        if ($wedstrijd->volgende_wedstrijd_id && $wedstrijd->winnaar_naar_slot) {
            $volgende = Wedstrijd::find($wedstrijd->volgende_wedstrijd_id);
            if ($volgende) {
                $volgende->update(["judoka_{$wedstrijd->winnaar_naar_slot}_id" => $winnaarId]);
            }
        }

        // Verliezer direct naar B (niet wachten op ronde compleet)
        if ($wedstrijd->groep === 'A' && $wedstrijd->ronde !== 'finale' && $verliezerId) {
            $this->plaatsVerliezerInB($wedstrijd, $verliezerId);
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
        // Check of verliezer al in B zit
        $alInB = Wedstrijd::where('poule_id', $wedstrijd->poule_id)
            ->where('groep', 'B')
            ->where(function ($q) use ($verliezerId) {
                $q->where('judoka_wit_id', $verliezerId)
                  ->orWhere('judoka_blauw_id', $verliezerId);
            })
            ->exists();

        if ($alInB) {
            return;
        }

        // Bepaal target B-ronde op basis van A-ronde
        $targetRonde = $this->getBRondeVoorARonde($wedstrijd->ronde);
        if (!$targetRonde) {
            return;
        }

        // Zoek lege plek in target ronde
        $legeWedstrijd = $this->zoekLegePlek($wedstrijd->poule_id, $targetRonde);

        // Als target ronde vol is, probeer ALLEEN de vorige ronde (niet verdere rondes!)
        // Dit voorkomt dat verliezers in een te late ronde terechtkomen
        if (!$legeWedstrijd) {
            $fallback = $this->getVorigeRonde($targetRonde);
            if ($fallback) {
                $legeWedstrijd = $this->zoekLegePlek($wedstrijd->poule_id, $fallback);
            }
        }

        if ($legeWedstrijd) {
            if ($legeWedstrijd->judoka_wit_id === null) {
                $legeWedstrijd->update(['judoka_wit_id' => $verliezerId]);
            } else {
                $legeWedstrijd->update(['judoka_blauw_id' => $verliezerId]);
            }
        } else {
            // Log warning - er is geen plek in de B-groep
            \Log::warning("Geen plek in B-groep voor verliezer {$verliezerId} uit {$wedstrijd->ronde}");
        }
    }

    /**
     * Geef vorige ronde (eerder in het toernooi) als fallback
     * Verliezers mogen alleen naar een eerdere ronde, nooit naar een latere
     */
    private function getVorigeRonde(string $ronde): ?string
    {
        return match ($ronde) {
            'b_achtste_finale' => 'b_voorronde',
            'b_kwartfinale' => 'b_achtste_finale',
            'b_halve_finale' => 'b_kwartfinale',
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
        $rondeVolgorde = ['b_voorronde', 'b_achtste_finale', 'b_kwartfinale', 'b_halve_finale'];

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
            'b_kwartfinale' => 'B 1/4',
            'b_halve_finale' => 'B 1/2',
            'b_brons' => 'Brons',
            default => $ronde,
        };
    }

    /**
     * Bepaal B-ronde voor A-ronde verliezers
     *
     * Alle A 1/8 verliezers → B voorronde (eerlijke extra wedstrijd)
     * A voorronde verliezers → B 1/8 (gespaard, hebben al 1 wedstrijd)
     */
    private function getBRondeVoorARonde(string $aRonde): ?string
    {
        return match ($aRonde) {
            'voorronde' => 'b_achtste_finale',    // Voorronde verliezers gespaard naar B 1/8
            'achtste_finale' => 'b_voorronde',    // ALLE 1/8 verliezers naar B voorronde
            'zestiende_finale' => 'b_voorronde',  // 1/16 verliezers naar B voorronde
            'kwartfinale' => 'b_kwartfinale',     // 1/4 verliezers naar B 1/4
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
            'b_achtste_finale' => 'b_kwartfinale',
            'b_kwartfinale' => 'b_halve_finale',
            'b_halve_finale' => 'b_brons',
            default => null,
        };
    }
}
