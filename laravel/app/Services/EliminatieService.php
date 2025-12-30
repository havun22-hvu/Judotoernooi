<?php

namespace App\Services;

use App\Models\Poule;
use App\Models\Wedstrijd;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * EliminatieService - Knockout Bracket Generator
 *
 * Ondersteunt twee systemen:
 *
 * 1. DUBBEL ELIMINATIE (type='dubbel')
 *    - Alle verliezers krijgen herkansing in B-groep
 *    - B-groep heeft dubbele rondes: (1) = B onderling, (2) = + nieuwe A verliezers
 *    - Formule: totaal = 2N - 5 wedstrijden
 *    - Aanbevolen voor jeugdtoernooien (iedereen minimaal 2x judoën)
 *
 * 2. IJF REPECHAGE (type='ijf')
 *    - Alleen verliezers van 1/4 finale krijgen herkansing
 *    - 2 repechage pools + 2 brons wedstrijden
 *    - Formule: totaal = N + 3 wedstrijden
 *    - Officieel systeem voor grote toernooien
 *
 * Formules A-groep (beide systemen):
 * - N = aantal judoka's
 * - D = grootste macht van 2 <= N
 * - Eerste ronde wedstrijden = N - D
 * - Byes = 2D - N
 * - Totaal A-wedstrijden = N - 1
 */
class EliminatieService
{
    /**
     * Bereken locatie_wit en locatie_blauw op basis van bracket_positie
     *
     * Locatie flow:
     * - bracket_positie 1: locatie_wit=1, locatie_blauw=2
     * - bracket_positie 2: locatie_wit=3, locatie_blauw=4
     * - etc.
     *
     * Winnaar van locatie X,X+1 → locatie ceil(X/2) in volgende ronde
     */
    private function berekenLocaties(int $bracketPositie): array
    {
        return [
            'locatie_wit' => ($bracketPositie - 1) * 2 + 1,
            'locatie_blauw' => ($bracketPositie - 1) * 2 + 2,
        ];
    }

    /**
     * Genereer complete eliminatie bracket
     *
     * @param Poule $poule De poule waarvoor bracket gemaakt wordt
     * @param array $judokaIds Array van judoka IDs
     * @param string $type 'dubbel' of 'ijf'
     * @param int $aantalBrons 1 of 2 bronzen medailles (default: lees uit toernooi)
     */
    public function genereerBracket(Poule $poule, array $judokaIds, string $type = 'dubbel', ?int $aantalBrons = null): array
    {
        // Verwijder bestaande wedstrijden
        $poule->wedstrijden()->delete();

        $n = count($judokaIds);
        if ($n < 2) {
            return ['totaal_wedstrijden' => 0];
        }

        // Lees aantal_brons uit toernooi indien niet meegegeven
        if ($aantalBrons === null) {
            $aantalBrons = $poule->mat?->blok?->toernooi?->aantal_brons ?? 2;
        }

        DB::transaction(function () use ($poule, $judokaIds, $n, $type, $aantalBrons) {
            // Genereer A-groep bracket (zelfde voor beide systemen)
            $aWedstrijden = $this->genereerAGroep($poule, $judokaIds);

            // Genereer B-groep bracket (verschilt per type)
            if ($n >= 5) {
                if ($type === 'ijf') {
                    $this->genereerBGroepIJF($poule, $n, $aWedstrijden);
                } else {
                    $this->genereerBGroepDubbel($poule, $n, $aantalBrons);
                }
            }
        });

        $bWedstrijden = ($type === 'ijf') ? 4 : max(0, $n - 4);

        return [
            'totaal_wedstrijden' => $poule->wedstrijden()->count(),
            'a_wedstrijden' => $n - 1,
            'b_wedstrijden' => $bWedstrijden,
            'type' => $type,
            'aantal_brons' => $aantalBrons,
        ];
    }

    // =========================================================================
    // A-GROEP (Winners Bracket) - Zelfde voor beide systemen
    // =========================================================================

    /**
     * Genereer A-groep (Winners Bracket)
     *
     * @return array Wedstrijden per ronde voor koppeling met B-groep
     */
    private function genereerAGroep(Poule $poule, array $judokaIds): array
    {
        $n = count($judokaIds);
        $d = $this->berekenDoel($n);

        // Shuffle judoka's voor willekeurige verdeling
        shuffle($judokaIds);

        $volgorde = 1;
        $wedstrijdenPerRonde = [];

        // SPECIAAL GEVAL: N is exacte macht van 2 (16, 32, etc.)
        // Dan is er geen voorronde, alle judoka's starten direct in eerste ronde
        if ($n == $d) {
            $eersteRonde = $this->getRondeNaamVoorAantal($n);  // 16 → achtste_finale

            // Maak eerste ronde met ALLE judoka's
            for ($i = 0; $i < $n / 2; $i++) {
                $bracketPositie = $i + 1;
                $wedstrijd = Wedstrijd::create([
                    'poule_id' => $poule->id,
                    'judoka_wit_id' => $judokaIds[$i * 2],
                    'judoka_blauw_id' => $judokaIds[$i * 2 + 1],
                    'volgorde' => $volgorde++,
                    'ronde' => $eersteRonde,
                    'groep' => 'A',
                    'bracket_positie' => $bracketPositie,
                    ...$this->berekenLocaties($bracketPositie),
                ]);
                $wedstrijdenPerRonde[$eersteRonde][] = $wedstrijd;
            }

            // Volgende rondes (lege slots)
            $huidigeAantal = $n / 2;  // Na eerste ronde
            while ($huidigeAantal > 1) {
                $volgendeAantal = $huidigeAantal / 2;
                $volgendeRonde = $this->getRondeNaamVoorAantal($huidigeAantal);

                for ($i = 0; $i < $volgendeAantal; $i++) {
                    $bracketPositie = $i + 1;
                    $wedstrijd = Wedstrijd::create([
                        'poule_id' => $poule->id,
                        'judoka_wit_id' => null,
                        'judoka_blauw_id' => null,
                        'volgorde' => $volgorde++,
                        'ronde' => $volgendeRonde,
                        'groep' => 'A',
                        'bracket_positie' => $bracketPositie,
                        ...$this->berekenLocaties($bracketPositie),
                    ]);
                    $wedstrijdenPerRonde[$volgendeRonde][] = $wedstrijd;
                }

                $huidigeAantal = $volgendeAantal;
            }

            // Koppel wedstrijden (geen byes)
            $this->koppelAGroepWedstrijden($wedstrijdenPerRonde, []);

            return $wedstrijdenPerRonde;
        }

        // NORMAAL GEVAL: N is niet exacte macht van 2, dus voorronde nodig
        $eersteRondeWedstrijden = $n - $d;
        $eersteRonde = $this->getEersteRondeNaam($n);

        // Verdeel: eerst de judoka's die moeten vechten, dan byes
        $wedstrijdJudokas = array_slice($judokaIds, 0, $eersteRondeWedstrijden * 2);
        $byeJudokas = array_slice($judokaIds, $eersteRondeWedstrijden * 2);

        // === EERSTE RONDE (voorronde) ===
        for ($i = 0; $i < $eersteRondeWedstrijden; $i++) {
            $bracketPositie = $i + 1;
            $wedstrijd = Wedstrijd::create([
                'poule_id' => $poule->id,
                'judoka_wit_id' => $wedstrijdJudokas[$i * 2],
                'judoka_blauw_id' => $wedstrijdJudokas[$i * 2 + 1],
                'volgorde' => $volgorde++,
                'ronde' => $eersteRonde,
                'groep' => 'A',
                'bracket_positie' => $bracketPositie,
                ...$this->berekenLocaties($bracketPositie),
            ]);
            $wedstrijdenPerRonde[$eersteRonde][] = $wedstrijd;
        }

        // === VOLGENDE RONDES ===
        $huidigeAantal = $d;

        while ($huidigeAantal > 1) {
            $volgendeAantal = $huidigeAantal / 2;
            $volgendeRonde = $this->getRondeNaamVoorAantal($huidigeAantal);

            for ($i = 0; $i < $volgendeAantal; $i++) {
                $bracketPositie = $i + 1;
                $wedstrijd = Wedstrijd::create([
                    'poule_id' => $poule->id,
                    'judoka_wit_id' => null,
                    'judoka_blauw_id' => null,
                    'volgorde' => $volgorde++,
                    'ronde' => $volgendeRonde,
                    'groep' => 'A',
                    'bracket_positie' => $bracketPositie,
                    ...$this->berekenLocaties($bracketPositie),
                ]);
                $wedstrijdenPerRonde[$volgendeRonde][] = $wedstrijd;
            }

            $huidigeAantal = $volgendeAantal;
        }

        // === KOPPEL WEDSTRIJDEN ===
        $this->koppelAGroepWedstrijden($wedstrijdenPerRonde, $byeJudokas);

        return $wedstrijdenPerRonde;
    }

    /**
     * Bepaal eerste ronde naam op basis van aantal judoka's
     */
    private function getEersteRondeNaam(int $n): string
    {
        if ($n > 32) return 'tweeendertigste_finale';
        if ($n > 16) return 'zestiende_finale';
        if ($n > 8) return 'achtste_finale';
        if ($n > 4) return 'kwartfinale';
        if ($n > 2) return 'halve_finale';
        return 'finale';
    }

    /**
     * Bepaal ronde naam voor aantal deelnemers IN die ronde
     */
    private function getRondeNaamVoorAantal(int $aantalDeelnemers): string
    {
        return match ($aantalDeelnemers) {
            32 => 'zestiende_finale',
            16 => 'achtste_finale',
            8 => 'kwartfinale',
            4 => 'halve_finale',
            2 => 'finale',
            default => 'achtste_finale',
        };
    }

    /**
     * Koppel A-groep wedstrijden aan elkaar
     */
    private function koppelAGroepWedstrijden(array $wedstrijdenPerRonde, array $byeJudokas): void
    {
        $rondes = array_keys($wedstrijdenPerRonde);

        // Koppel wedstrijden aan volgende ronde
        for ($r = 0; $r < count($rondes) - 1; $r++) {
            $huidigeRonde = $rondes[$r];
            $volgendeRonde = $rondes[$r + 1];

            $huidigeWedstrijden = $wedstrijdenPerRonde[$huidigeRonde];
            $volgendeWedstrijden = $wedstrijdenPerRonde[$volgendeRonde];

            foreach ($huidigeWedstrijden as $idx => $wedstrijd) {
                $volgendeIdx = floor($idx / 2);
                $slot = ($idx % 2 == 0) ? 'wit' : 'blauw';

                if (isset($volgendeWedstrijden[$volgendeIdx])) {
                    $wedstrijd->update([
                        'volgende_wedstrijd_id' => $volgendeWedstrijden[$volgendeIdx]->id,
                        'winnaar_naar_slot' => $slot,
                    ]);
                }
            }
        }

        // Plaats bye judoka's direct in tweede ronde
        if (count($rondes) >= 2 && !empty($byeJudokas)) {
            $tweedeRonde = $rondes[1];
            $tweedeRondeWedstrijden = $wedstrijdenPerRonde[$tweedeRonde];
            $eersteRondeWedstrijden = $wedstrijdenPerRonde[$rondes[0]];

            // Bepaal welke slots gevuld worden door eerste ronde winnaars
            $gevuldeSlots = [];
            foreach ($eersteRondeWedstrijden as $idx => $wed) {
                $tweedePos = floor($idx / 2);
                $gevuldeSlots[$tweedePos][] = ($idx % 2 == 0) ? 'wit' : 'blauw';
            }

            // Vul lege slots met bye judoka's
            $byeIdx = 0;
            foreach ($tweedeRondeWedstrijden as $idx => $wed) {
                $heeftWit = isset($gevuldeSlots[$idx]) && in_array('wit', $gevuldeSlots[$idx]);
                $heeftBlauw = isset($gevuldeSlots[$idx]) && in_array('blauw', $gevuldeSlots[$idx]);

                if (!$heeftWit && $byeIdx < count($byeJudokas)) {
                    $wed->update(['judoka_wit_id' => $byeJudokas[$byeIdx++]]);
                }
                if (!$heeftBlauw && $byeIdx < count($byeJudokas)) {
                    $wed->update(['judoka_blauw_id' => $byeJudokas[$byeIdx++]]);
                }
            }
        }
    }

    // =========================================================================
    // B-GROEP: DUBBEL ELIMINATIE
    // =========================================================================

    /**
     * Genereer B-groep voor Dubbel Eliminatie
     *
     * KERNLOGICA (zie ELIMINATIE_BEREKENING.md):
     *
     * N  = aantal judoka's
     * D  = grootste macht van 2 ≤ N
     * V1 = verliezers A-1e ronde = N - D
     * V2 = verliezers A-2e ronde = D / 2 (altijd vast!)
     *
     * Als V1 ≤ V2: ENKELE B-rondes (V1 + V2 passen samen)
     * Als V1 > V2: DUBBELE B-rondes met (1) en (2)
     *
     * B-start capaciteit = 2 × V2
     * B-byes = capaciteit - instroom
     *
     * BELANGRIJK:
     * - aantalBrons = 2: Eindigt met 2x B-1/2(2), GEEN finale! (2x brons)
     * - aantalBrons = 1: Eindigt met B-finale (1x brons)
     * - B-byes NIET aan A-bye judoka's geven (fairness)
     */
    private function genereerBGroepDubbel(Poule $poule, int $n, int $aantalBrons = 2): void
    {
        $volgorde = 1000;
        $wedstrijdenPerRonde = [];

        // === STAP 1: Bereken V1 en V2 ===
        $d = $this->berekenDoel($n);
        $v1 = $n - $d;      // Verliezers A-1e ronde
        $v2 = $d / 2;       // Verliezers A-2e ronde (altijd vast!)

        // === STAP 2: Bepaal ENKELE of DUBBELE rondes ===
        $dubbelRondes = ($v1 > $v2);

        // === STAP 3: Bepaal B-start niveau ===
        // B-start heeft evenveel wedstrijden als V2
        $bStartWedstrijden = (int) $v2;
        $bStartRonde = $this->getBRondeNaam($bStartWedstrijden);

        // === STAP 4: Bereken B-byes ===
        $bCapaciteit = 2 * $v2;
        if ($dubbelRondes) {
            // Dubbele rondes: alleen V1 in B-start(1)
            $bByes = $bCapaciteit - $v1;
        } else {
            // Enkele rondes: V1 + V2 samen in B-start
            $bByes = $bCapaciteit - ($v1 + $v2);
        }

        Log::info("B-groep generatie: N={$n}, D={$d}, V1={$v1}, V2={$v2}, Dubbel=" . ($dubbelRondes ? 'Ja' : 'Nee') . ", B-byes={$bByes}, aantalBrons={$aantalBrons}");

        // === STAP 5: Genereer B-bracket structuur ===

        if ($dubbelRondes) {
            // DUBBELE RONDES: (1) en (2) per niveau
            $this->genereerDubbeleBRondes($poule, $bStartWedstrijden, $volgorde, $wedstrijdenPerRonde, $aantalBrons);
        } else {
            // ENKELE RONDES: standaard knockout
            $this->genereerEnkeleBRondes($poule, $bStartWedstrijden, $volgorde, $wedstrijdenPerRonde, $aantalBrons);
        }

        // === STAP 6: Koppel B-groep wedstrijden ===
        $this->koppelBGroepWedstrijden($wedstrijdenPerRonde, $dubbelRondes);
    }

    /**
     * Genereer ENKELE B-rondes (V1 ≤ V2)
     *
     * aantalBrons = 2: B-start → ... → B-1/2 → B-1/2(2) = 2x BRONS
     * aantalBrons = 1: B-start → ... → B-1/2 → B-1/2(2) → B-finale = 1x BRONS
     */
    private function genereerEnkeleBRondes(Poule $poule, int $startWedstrijden, int &$volgorde, array &$wedstrijdenPerRonde, int $aantalBrons = 2): void
    {
        $huidigeWedstrijden = $startWedstrijden;

        // Genereer rondes van groot naar klein
        while ($huidigeWedstrijden >= 2) {
            $rondeNaam = $this->getBRondeNaam($huidigeWedstrijden);

            for ($i = 0; $i < $huidigeWedstrijden; $i++) {
                $bracketPositie = $i + 1;
                $wedstrijd = Wedstrijd::create([
                    'poule_id' => $poule->id,
                    'judoka_wit_id' => null,
                    'judoka_blauw_id' => null,
                    'volgorde' => $volgorde++,
                    'ronde' => $rondeNaam,
                    'groep' => 'B',
                    'bracket_positie' => $bracketPositie,
                    ...$this->berekenLocaties($bracketPositie),
                ]);
                $wedstrijdenPerRonde[$rondeNaam][] = $wedstrijd;
            }

            $huidigeWedstrijden = $huidigeWedstrijden / 2;
        }

        // B-1/2(2): 2 wedstrijden (B-1/2 winnaars op WIT + A-1/2 verliezers op BLAUW)
        for ($i = 0; $i < 2; $i++) {
            $bracketPositie = $i + 1;
            $wedstrijd = Wedstrijd::create([
                'poule_id' => $poule->id,
                'judoka_wit_id' => null,
                'judoka_blauw_id' => null,
                'volgorde' => $volgorde++,
                'ronde' => 'b_halve_finale_2',
                'groep' => 'B',
                'bracket_positie' => $bracketPositie,
                ...$this->berekenLocaties($bracketPositie),
            ]);
            $wedstrijdenPerRonde['b_halve_finale_2'][] = $wedstrijd;
        }

        // Bij 1 brons: voeg B-finale toe (winnaars b_halve_finale_2 tegen elkaar)
        if ($aantalBrons === 1) {
            $bracketPositie = 1;
            $wedstrijd = Wedstrijd::create([
                'poule_id' => $poule->id,
                'judoka_wit_id' => null,
                'judoka_blauw_id' => null,
                'volgorde' => $volgorde++,
                'ronde' => 'b_finale',
                'groep' => 'B',
                'bracket_positie' => $bracketPositie,
                ...$this->berekenLocaties($bracketPositie),
            ]);
            $wedstrijdenPerRonde['b_finale'][] = $wedstrijd;
        }
    }

    /**
     * Genereer DUBBELE B-rondes (V1 > V2)
     *
     * Structuur per niveau:
     * - (1): B onderling (V1 of vorige winnaars)
     * - (2): winnaars (1) + A-verliezers van dat niveau
     *
     * aantalBrons = 2: Eindigt met 2x B-1/2(2) = 2x BRONS (GEEN finale!)
     * aantalBrons = 1: Eindigt met B-finale = 1x BRONS
     */
    private function genereerDubbeleBRondes(Poule $poule, int $startWedstrijden, int &$volgorde, array &$wedstrijdenPerRonde, int $aantalBrons = 2): void
    {
        $huidigeWedstrijden = $startWedstrijden;

        // Genereer dubbele rondes van groot naar klein
        while ($huidigeWedstrijden >= 2) {
            $baseRondeNaam = $this->getBRondeNaam($huidigeWedstrijden);

            // Ronde (1): B onderling - WIT slots alleen (B-winnaars komen hier)
            $ronde1Naam = $baseRondeNaam . '_1';
            for ($i = 0; $i < $huidigeWedstrijden; $i++) {
                $bracketPositie = $i + 1;
                $wedstrijd = Wedstrijd::create([
                    'poule_id' => $poule->id,
                    'judoka_wit_id' => null,
                    'judoka_blauw_id' => null,
                    'volgorde' => $volgorde++,
                    'ronde' => $ronde1Naam,
                    'groep' => 'B',
                    'bracket_positie' => $bracketPositie,
                    ...$this->berekenLocaties($bracketPositie),
                ]);
                $wedstrijdenPerRonde[$ronde1Naam][] = $wedstrijd;
            }

            // Ronde (2): winnaars (1) op WIT + A-verliezers op BLAUW (even locaties)
            $ronde2Naam = $baseRondeNaam . '_2';
            for ($i = 0; $i < $huidigeWedstrijden; $i++) {
                $bracketPositie = $i + 1;
                $wedstrijd = Wedstrijd::create([
                    'poule_id' => $poule->id,
                    'judoka_wit_id' => null,
                    'judoka_blauw_id' => null,
                    'volgorde' => $volgorde++,
                    'ronde' => $ronde2Naam,
                    'groep' => 'B',
                    'bracket_positie' => $bracketPositie,
                    ...$this->berekenLocaties($bracketPositie),
                ]);
                $wedstrijdenPerRonde[$ronde2Naam][] = $wedstrijd;
            }

            $huidigeWedstrijden = $huidigeWedstrijden / 2;
        }

        // Bij 1 brons: voeg B-finale toe (winnaars b_halve_finale_2 tegen elkaar)
        if ($aantalBrons === 1) {
            $bracketPositie = 1;
            $wedstrijd = Wedstrijd::create([
                'poule_id' => $poule->id,
                'judoka_wit_id' => null,
                'judoka_blauw_id' => null,
                'volgorde' => $volgorde++,
                'ronde' => 'b_finale',
                'groep' => 'B',
                'bracket_positie' => $bracketPositie,
                ...$this->berekenLocaties($bracketPositie),
            ]);
            $wedstrijdenPerRonde['b_finale'][] = $wedstrijd;
        }
        // aantalBrons = 2: GEEN B-finale! Eindigt met 2x B-1/2(2) = BRONS
    }

    /**
     * Get B-ronde naam voor aantal wedstrijden
     */
    private function getBRondeNaam(int $wedstrijden): string
    {
        return match ($wedstrijden) {
            16 => 'b_zestiende_finale',
            8 => 'b_achtste_finale',
            4 => 'b_kwartfinale',
            2 => 'b_halve_finale',
            default => 'b_kwartfinale',
        };
    }

    /**
     * Koppel B-groep wedstrijden op basis van locatie
     *
     * Locatie flow (2:1 mapping):
     * - Locatie 1,2 (wed 1) → winnaar naar WIT (locatie 1 volgende ronde)
     * - Locatie 3,4 (wed 2) → winnaar naar BLAUW (locatie 2 volgende ronde)
     * - Locatie 5,6 (wed 3) → winnaar naar WIT (locatie 3 volgende ronde)
     * - etc.
     *
     * Bij DUBBELE rondes: (1) → (2) is 1:1 mapping (winnaar altijd naar WIT)
     * B-1/2(2) is speciaal: B-winnaar op WIT + A-verliezer op BLAUW → BRONS
     */
    private function koppelBGroepWedstrijden(array $wedstrijdenPerRonde, bool $dubbelRondes): void
    {
        $rondes = array_keys($wedstrijdenPerRonde);
        Log::info("koppelBGroepWedstrijden: rondes=" . implode(', ', $rondes) . ", dubbelRondes=" . ($dubbelRondes ? 'ja' : 'nee'));

        for ($r = 0; $r < count($rondes) - 1; $r++) {
            $huidigeRonde = $rondes[$r];
            $volgendeRonde = $rondes[$r + 1];

            $huidigeWedstrijden = $wedstrijdenPerRonde[$huidigeRonde];
            $volgendeWedstrijden = $wedstrijdenPerRonde[$volgendeRonde];

            // Bepaal mapping type
            $is1naar2 = $dubbelRondes && str_ends_with($huidigeRonde, '_1') && str_ends_with($volgendeRonde, '_2');
            $isBrons = $volgendeRonde === 'b_halve_finale_2';

            Log::info("Koppel {$huidigeRonde} (" . count($huidigeWedstrijden) . ") → {$volgendeRonde} (" . count($volgendeWedstrijden) . "): is1naar2={$is1naar2}, isBrons={$isBrons}");

            foreach ($huidigeWedstrijden as $wedstrijd) {
                $bracketPos = $wedstrijd->bracket_positie;

                if ($is1naar2 || $isBrons) {
                    // 1:1 mapping: (1) → (2) of laatste → brons
                    // Winnaar altijd naar WIT (A-verliezer gaat naar BLAUW)
                    $volgendeBracketPos = $bracketPos;
                    $slot = 'wit';
                } else {
                    // 2:1 mapping: standaard knockout
                    // Wed 1+2 → wed 1 | Wed 3+4 → wed 2 | etc.
                    $volgendeBracketPos = (int) ceil($bracketPos / 2);
                    // Oneven bracket_positie → WIT, even → BLAUW
                    $slot = ($bracketPos % 2 === 1) ? 'wit' : 'blauw';
                }

                // Zoek volgende wedstrijd op bracket_positie
                $volgendeWedstrijd = collect($volgendeWedstrijden)
                    ->firstWhere('bracket_positie', $volgendeBracketPos);

                if ($volgendeWedstrijd) {
                    $wedstrijd->update([
                        'volgende_wedstrijd_id' => $volgendeWedstrijd->id,
                        'winnaar_naar_slot' => $slot,
                    ]);
                } else {
                    Log::warning("GEEN VOLGENDE GEVONDEN: Wed {$wedstrijd->id} pos {$bracketPos} zoekt pos {$volgendeBracketPos}");
                }
            }
        }
    }

    // =========================================================================
    // B-GROEP: IJF REPECHAGE
    // =========================================================================

    /**
     * Genereer B-groep voor IJF Repechage
     *
     * Eenvoudiger systeem:
     * - Alleen verliezers van 1/4 finale krijgen repechage
     * - 2 repechage pools + 2 brons wedstrijden = 4 wedstrijden totaal
     *
     * Structuur:
     * b_repechage_1: Verliezer A-1/4(1) vs Verliezer A-1/4(3) → winnaar naar brons
     * b_repechage_2: Verliezer A-1/4(2) vs Verliezer A-1/4(4) → winnaar naar brons
     * b_brons_1: Winnaar repechage 1 vs Verliezer A-1/2(1) → BRONS
     * b_brons_2: Winnaar repechage 2 vs Verliezer A-1/2(2) → BRONS
     */
    private function genereerBGroepIJF(Poule $poule, int $n, array $aWedstrijden): void
    {
        $volgorde = 1000;
        $wedstrijdenPerRonde = [];

        // === REPECHAGE POOLS (2 wedstrijden) ===
        // Verliezers 1/4 finale pos 1+3 → pool 1
        // Verliezers 1/4 finale pos 2+4 → pool 2
        for ($i = 1; $i <= 2; $i++) {
            $bracketPositie = $i;
            $wedstrijd = Wedstrijd::create([
                'poule_id' => $poule->id,
                'judoka_wit_id' => null,
                'judoka_blauw_id' => null,
                'volgorde' => $volgorde++,
                'ronde' => 'b_repechage_' . $i,
                'groep' => 'B',
                'bracket_positie' => $bracketPositie,
                ...$this->berekenLocaties($bracketPositie),
            ]);
            $wedstrijdenPerRonde['b_repechage'][] = $wedstrijd;
        }

        // === BRONS WEDSTRIJDEN (2 wedstrijden) ===
        // Winnaar repechage op WIT vs Verliezer 1/2 finale op BLAUW
        for ($i = 1; $i <= 2; $i++) {
            $bracketPositie = $i;
            $wedstrijd = Wedstrijd::create([
                'poule_id' => $poule->id,
                'judoka_wit_id' => null,
                'judoka_blauw_id' => null,
                'volgorde' => $volgorde++,
                'ronde' => 'b_brons_' . $i,
                'groep' => 'B',
                'bracket_positie' => $bracketPositie,
                ...$this->berekenLocaties($bracketPositie),
            ]);
            $wedstrijdenPerRonde['b_brons'][] = $wedstrijd;
        }

        // Koppel repechage → brons
        foreach ($wedstrijdenPerRonde['b_repechage'] as $idx => $repechage) {
            if (isset($wedstrijdenPerRonde['b_brons'][$idx])) {
                $repechage->update([
                    'volgende_wedstrijd_id' => $wedstrijdenPerRonde['b_brons'][$idx]->id,
                    'winnaar_naar_slot' => 'wit',  // Repechage winnaar op wit
                ]);
            }
        }

        // Koppel A-1/4 verliezers aan repechage
        // En A-1/2 verliezers aan brons
        $this->koppelIJFVerliezers($poule, $aWedstrijden, $wedstrijdenPerRonde);
    }

    /**
     * Koppel A-groep verliezers aan IJF repechage
     */
    private function koppelIJFVerliezers(Poule $poule, array $aWedstrijden, array $bWedstrijden): void
    {
        // Zoek kwartfinale wedstrijden in A
        $kwartfinales = $aWedstrijden['kwartfinale'] ?? [];

        // Koppel 1/4 verliezers aan repechage
        // Pos 1+3 → repechage 1, Pos 2+4 → repechage 2
        if (count($kwartfinales) >= 4) {
            $repechage = $bWedstrijden['b_repechage'] ?? [];
            if (count($repechage) >= 2) {
                // 1/4(1) en 1/4(3) → repechage 1
                $kwartfinales[0]->update(['herkansing_wedstrijd_id' => $repechage[0]->id, 'verliezer_naar_slot' => 'wit']);
                $kwartfinales[2]->update(['herkansing_wedstrijd_id' => $repechage[0]->id, 'verliezer_naar_slot' => 'blauw']);

                // 1/4(2) en 1/4(4) → repechage 2
                $kwartfinales[1]->update(['herkansing_wedstrijd_id' => $repechage[1]->id, 'verliezer_naar_slot' => 'wit']);
                $kwartfinales[3]->update(['herkansing_wedstrijd_id' => $repechage[1]->id, 'verliezer_naar_slot' => 'blauw']);
            }
        }

        // Zoek halve finale wedstrijden in A
        $halveFinales = $aWedstrijden['halve_finale'] ?? [];

        // Koppel 1/2 verliezers aan brons wedstrijden
        if (count($halveFinales) >= 2) {
            $brons = $bWedstrijden['b_brons'] ?? [];
            if (count($brons) >= 2) {
                // 1/2(1) verliezer → brons 1 (blauw slot, wit is repechage winnaar)
                $halveFinales[0]->update(['herkansing_wedstrijd_id' => $brons[0]->id, 'verliezer_naar_slot' => 'blauw']);
                // 1/2(2) verliezer → brons 2
                $halveFinales[1]->update(['herkansing_wedstrijd_id' => $brons[1]->id, 'verliezer_naar_slot' => 'blauw']);
            }
        }
    }

    // =========================================================================
    // HELPER METHODES
    // =========================================================================

    /**
     * Bereken doel (grootste macht van 2 <= n)
     */
    private function berekenDoel(int $n): int
    {
        if ($n <= 0) return 0;
        if ($n == 1) return 1;
        return pow(2, floor(log($n, 2)));
    }

    /**
     * Bereken statistieken voor bracket
     *
     * Zie ELIMINATIE_BEREKENING.md voor volledige documentatie.
     */
    public function berekenStatistieken(int $n, string $type = 'dubbel'): array
    {
        $d = $this->berekenDoel($n);
        $v1 = $n - $d;          // Verliezers A-1e ronde
        $v2 = (int) ($d / 2);   // Verliezers A-2e ronde

        $bWedstrijden = ($type === 'ijf') ? 4 : max(0, $n - 4);
        $totaalWedstrijden = ($type === 'ijf') ? ($n - 1 + 4) : max(0, 2 * $n - 5);

        // B-structuur bepalen
        $dubbelRondes = ($v1 > $v2);
        $bCapaciteit = 2 * $v2;
        $bByes = $dubbelRondes ? ($bCapaciteit - $v1) : ($bCapaciteit - ($v1 + $v2));

        return [
            'judokas' => $n,
            'type' => $type,
            'doel' => $d,
            'v1' => $v1,                              // Verliezers A-1e ronde
            'v2' => $v2,                              // Verliezers A-2e ronde
            'a_wedstrijden' => $n - 1,
            'b_wedstrijden' => $bWedstrijden,
            'totaal_wedstrijden' => $totaalWedstrijden,
            'eerste_ronde' => $this->getEersteRondeNaam($n),
            'eerste_ronde_wedstrijden' => $v1,
            'a_byes' => max(0, 2 * $d - $n),          // A-groep byes
            'b_byes' => max(0, $bByes),               // B-groep byes
            'dubbel_rondes' => $dubbelRondes,         // true als V1 > V2
        ];
    }

    // =========================================================================
    // UITSLAG VERWERKING
    // =========================================================================

    /**
     * Verwerk uitslag van een wedstrijd
     *
     * @param Wedstrijd $wedstrijd De gespeelde wedstrijd
     * @param int $winnaarId ID van de winnaar
     * @param int|null $oudeWinnaarId Vorige winnaar (voor correcties)
     * @param string $type 'dubbel' of 'ijf'
     * @return array Correcties die zijn uitgevoerd
     */
    public function verwerkUitslag(Wedstrijd $wedstrijd, int $winnaarId, ?int $oudeWinnaarId = null, string $type = 'dubbel'): array
    {
        $correcties = [];

        // Bepaal verliezer
        $verliezerId = ($wedstrijd->judoka_wit_id == $winnaarId)
            ? $wedstrijd->judoka_blauw_id
            : $wedstrijd->judoka_wit_id;

        // Haal namen op voor duidelijke meldingen
        $winnaarNaam = \App\Models\Judoka::find($winnaarId)?->naam ?? 'Onbekend';
        $verliezerNaam = $verliezerId ? (\App\Models\Judoka::find($verliezerId)?->naam ?? 'Onbekend') : null;
        $oudeWinnaarNaam = $oudeWinnaarId ? (\App\Models\Judoka::find($oudeWinnaarId)?->naam ?? 'Onbekend') : null;

        // Als er een oude winnaar was, moet die gecorrigeerd worden
        if ($oudeWinnaarId && $oudeWinnaarId != $winnaarId) {
            // 1. Verwijder oude winnaar uit volgende ronde EN plaats nieuwe winnaar
            if ($wedstrijd->volgende_wedstrijd_id) {
                $volgendeWedstrijd = Wedstrijd::find($wedstrijd->volgende_wedstrijd_id);
                if ($volgendeWedstrijd) {
                    $slot = $wedstrijd->winnaar_naar_slot ?? 'wit';
                    $veld = ($slot === 'wit') ? 'judoka_wit_id' : 'judoka_blauw_id';

                    // Verwijder oude winnaar en plaats nieuwe winnaar in één keer
                    $volgendeWedstrijd->update([$veld => $winnaarId]);
                    $correcties[] = "{$oudeWinnaarNaam} vervangen door {$winnaarNaam} in volgende ronde";
                }
            }

            // 2. Verwijder nieuwe winnaar (=oude verliezer) uit B-groep
            // Want die was daar geplaatst als verliezer, maar is nu winnaar
            // ALLEEN bij A-groep! Bij B-groep correcties blijft de winnaar in B-groep
            if ($wedstrijd->groep === 'A') {
                $this->verwijderUitB($wedstrijd->poule_id, $winnaarId);
                $correcties[] = "{$winnaarNaam} verwijderd uit B-groep (is nu winnaar)";
            }

            // 3. Plaats oude winnaar (=nieuwe verliezer) in B-groep
            // De reguliere code hieronder doet dit al
            $correcties[] = "Winnaar gecorrigeerd: {$winnaarNaam} (was: {$oudeWinnaarNaam})";
        }

        // Verliezer naar B-groep (alleen bij A-groep wedstrijden)
        if ($wedstrijd->groep === 'A' && $verliezerId) {
            if ($type === 'ijf') {
                $this->plaatsVerliezerIJF($wedstrijd, $verliezerId);
            } else {
                $this->plaatsVerliezerDubbel($wedstrijd, $verliezerId);
            }

            // Alleen melding als dit een correctie was
            if ($oudeWinnaarId && $oudeWinnaarId != $winnaarId) {
                $correcties[] = "{$verliezerNaam} geplaatst in B-groep";
            }
        }

        return $correcties;
    }

    /**
     * Plaats verliezer in B-groep (Dubbel Eliminatie)
     *
     * Bij dubbele rondes gaan verliezers naar specifieke B-rondes:
     * - A eerste ronde verliezers → B-xxx(1) (onderling uitvechten)
     * - A-1/8 verliezers → B-1/8(2) (tegen B(1) winnaars)
     * - A-1/4 verliezers → B-1/4(2)
     * - A-1/2 verliezers → B-1/2(2) / B-brons
     */
    private function plaatsVerliezerDubbel(Wedstrijd $wedstrijd, int $verliezerId): void
    {
        $pouleId = $wedstrijd->poule_id;
        $aRonde = $wedstrijd->ronde;

        // Bepaal naar welke B-ronde de verliezer moet
        $bRonde = $this->bepaalBRondeVoorVerliezer($pouleId, $aRonde);

        if (!$bRonde) {
            Log::warning("Geen B-ronde bepaald voor A-ronde {$aRonde} in poule {$pouleId}");
            return;
        }

        Log::info("Verliezer {$verliezerId} van {$aRonde} → {$bRonde}");

        // Check of verliezer al een bye heeft gehad in A-groep
        $hadAlBye = $this->heeftByeGehad($pouleId, $verliezerId);

        // Zoek beschikbare slot in de juiste B-ronde
        $bWedstrijd = null;

        if ($hadAlBye) {
            // Bye-judoka: zoek slot waar al iemand staat (geen nieuwe bye)
            $bWedstrijd = $this->zoekSlotMetTegenstander($pouleId, $bRonde);
        }

        if (!$bWedstrijd) {
            // Zoek eerste beschikbare lege slot
            $bWedstrijd = $this->zoekEersteLegeBSlot($pouleId, $bRonde);
        }

        if ($bWedstrijd) {
            // Voor (2) rondes: A-verliezers komen op BLAUW slot
            // Voor (1) rondes: eerste beschikbare slot
            if (str_ends_with($bRonde, '_2')) {
                // (2) ronde: A-verliezers op blauw
                $slot = 'judoka_blauw_id';
                if (!is_null($bWedstrijd->judoka_blauw_id)) {
                    // Blauw bezet, probeer wit
                    $slot = is_null($bWedstrijd->judoka_wit_id) ? 'judoka_wit_id' : null;
                }
            } else {
                // (1) ronde: eerste beschikbare
                $slot = is_null($bWedstrijd->judoka_wit_id) ? 'judoka_wit_id' : 'judoka_blauw_id';
            }

            if ($slot) {
                $bWedstrijd->update([$slot => $verliezerId]);
            } else {
                Log::warning("Geen vrij slot in wedstrijd {$bWedstrijd->id} voor verliezer {$verliezerId}");
            }
        } else {
            Log::warning("Geen B-slot beschikbaar voor verliezer {$verliezerId} in {$bRonde} van poule {$pouleId}");
        }
    }

    /**
     * Bepaal naar welke B-ronde een A-verliezer moet
     */
    private function bepaalBRondeVoorVerliezer(int $pouleId, string $aRonde): ?string
    {
        // Check of we dubbele rondes hebben (door te kijken of _1 rondes bestaan)
        $heeftDubbeleRondes = Wedstrijd::where('poule_id', $pouleId)
            ->where('groep', 'B')
            ->where('ronde', 'like', '%_1')
            ->exists();

        // Mapping A-ronde → B-ronde
        // Bij dubbele rondes: 1e ronde A → (1), 2e ronde A → (2)
        if ($aRonde === 'halve_finale') {
            return 'b_halve_finale_2';
        }

        if ($aRonde === 'kwartfinale') {
            return $heeftDubbeleRondes ? 'b_kwartfinale_2' : 'b_kwartfinale';
        }

        if ($aRonde === 'achtste_finale') {
            return $heeftDubbeleRondes ? 'b_achtste_finale_2' : 'b_achtste_finale';
        }

        // Eerste ronde (voorronde) → B-start(1)
        if (in_array($aRonde, ['eerste_ronde', 'voorronde'])) {
            // Zoek de eerste B-ronde met _1 suffix
            $bStart1 = Wedstrijd::where('poule_id', $pouleId)
                ->where('groep', 'B')
                ->where('ronde', 'like', '%_1')
                ->orderBy('volgorde')
                ->first();

            if ($bStart1) {
                return $bStart1->ronde;
            }

            // Fallback: zoek eerste B-ronde
            return $this->vindBStartRonde($pouleId);
        }

        // Fallback
        return $this->vindBStartRonde($pouleId);
    }

    /**
     * Plaats A-1/2 verliezer in B-1/2(2) wedstrijd
     */
    private function plaatsInBBrons(int $pouleId, int $verliezerId): void
    {
        $bHalve2 = Wedstrijd::where('poule_id', $pouleId)
            ->where('groep', 'B')
            ->where('ronde', 'b_halve_finale_2')
            ->whereNull('judoka_blauw_id')  // A-verliezers op blauw
            ->orderBy('bracket_positie')
            ->first();

        if ($bHalve2) {
            $bHalve2->update(['judoka_blauw_id' => $verliezerId]);
        }
    }

    /**
     * Vind de B-start ronde voor een poule
     */
    private function vindBStartRonde(int $pouleId): ?string
    {
        // Zoek eerste B-ronde (niet b_halve_finale_2, dat is voor A-1/2 verliezers)
        $bRonde = Wedstrijd::where('poule_id', $pouleId)
            ->where('groep', 'B')
            ->where('ronde', '!=', 'b_halve_finale_2')
            ->orderBy('volgorde')
            ->first();

        return $bRonde?->ronde;
    }

    /**
     * Zoek slot waar al een tegenstander staat (voor bye-judoka's)
     */
    private function zoekSlotMetTegenstander(int $pouleId, string $ronde): ?Wedstrijd
    {
        return Wedstrijd::where('poule_id', $pouleId)
            ->where('groep', 'B')
            ->where('ronde', $ronde)
            ->where(function ($q) {
                // Eén slot bezet, één leeg
                $q->where(function ($q2) {
                    $q2->whereNotNull('judoka_wit_id')->whereNull('judoka_blauw_id');
                })->orWhere(function ($q2) {
                    $q2->whereNull('judoka_wit_id')->whereNotNull('judoka_blauw_id');
                });
            })
            ->orderBy('bracket_positie')
            ->first();
    }

    /**
     * Zoek eerste lege B-slot (beide slots leeg)
     */
    private function zoekEersteLegeBSlot(int $pouleId, string $ronde): ?Wedstrijd
    {
        // Eerst: volledig lege wedstrijden
        $volledigLeeg = Wedstrijd::where('poule_id', $pouleId)
            ->where('groep', 'B')
            ->where('ronde', $ronde)
            ->whereNull('judoka_wit_id')
            ->whereNull('judoka_blauw_id')
            ->orderBy('bracket_positie')
            ->first();

        if ($volledigLeeg) {
            return $volledigLeeg;
        }

        // Dan: wedstrijden met 1 leeg slot
        return Wedstrijd::where('poule_id', $pouleId)
            ->where('groep', 'B')
            ->where('ronde', $ronde)
            ->where(function ($q) {
                $q->whereNull('judoka_wit_id')->orWhereNull('judoka_blauw_id');
            })
            ->orderBy('bracket_positie')
            ->first();
    }

    /**
     * Check of judoka al een bye heeft gehad in deze poule
     */
    private function heeftByeGehad(int $pouleId, int $judokaId): bool
    {
        return Wedstrijd::where('poule_id', $pouleId)
            ->where('uitslag_type', 'bye')
            ->where('winnaar_id', $judokaId)
            ->exists();
    }

    /**
     * Plaats verliezer in B-groep (IJF Repechage)
     * Alleen kwartfinale en halve finale verliezers krijgen herkansing
     */
    private function plaatsVerliezerIJF(Wedstrijd $wedstrijd, int $verliezerId): void
    {
        // Bij IJF systeem is de koppeling al gedaan via herkansing_wedstrijd_id
        if ($wedstrijd->herkansing_wedstrijd_id) {
            $bWedstrijd = Wedstrijd::find($wedstrijd->herkansing_wedstrijd_id);
            if ($bWedstrijd) {
                $slot = ($wedstrijd->verliezer_naar_slot === 'blauw') ? 'judoka_blauw_id' : 'judoka_wit_id';
                $bWedstrijd->update([$slot => $verliezerId]);
            }
        }
    }

    /**
     * Schrap lege B-wedstrijden na alle A-rondes
     *
     * Verwijdert wedstrijden waar beide slots leeg zijn.
     * Past bracket_positie aan voor correcte weergave.
     *
     * @param int $pouleId De poule om op te schonen
     * @return int Aantal verwijderde wedstrijden
     */
    public function schrapLegeBWedstrijden(int $pouleId): int
    {
        $verwijderd = 0;

        // Vind alle lege B-wedstrijden (beide slots leeg)
        $legeWedstrijden = Wedstrijd::where('poule_id', $pouleId)
            ->where('groep', 'B')
            ->whereNull('judoka_wit_id')
            ->whereNull('judoka_blauw_id')
            ->whereNull('winnaar_id')  // Nog niet gespeeld
            ->get();

        foreach ($legeWedstrijden as $wedstrijd) {
            // Update wedstrijden die naar deze verwezen
            Wedstrijd::where('volgende_wedstrijd_id', $wedstrijd->id)
                ->update(['volgende_wedstrijd_id' => $wedstrijd->volgende_wedstrijd_id]);

            // Verwijder de wedstrijd
            $wedstrijd->delete();
            $verwijderd++;
        }

        // Hernummer bracket_positie per ronde
        $this->hernummerBracketPosities($pouleId);

        Log::info("Verwijderd {$verwijderd} lege B-wedstrijden voor poule {$pouleId}");

        return $verwijderd;
    }

    /**
     * Hernummer bracket_positie per ronde na verwijderen wedstrijden
     */
    private function hernummerBracketPosities(int $pouleId): void
    {
        $rondes = Wedstrijd::where('poule_id', $pouleId)
            ->where('groep', 'B')
            ->distinct()
            ->pluck('ronde');

        foreach ($rondes as $ronde) {
            $wedstrijden = Wedstrijd::where('poule_id', $pouleId)
                ->where('groep', 'B')
                ->where('ronde', $ronde)
                ->orderBy('bracket_positie')
                ->get();

            $positie = 1;
            foreach ($wedstrijden as $wed) {
                if ($wed->bracket_positie !== $positie) {
                    $wed->update(['bracket_positie' => $positie]);
                }
                $positie++;
            }
        }
    }

    /**
     * Herstel B-groep koppelingen voor bestaande bracket
     * Gebruik dit als de volgende_wedstrijd_id of winnaar_naar_slot fout staat
     */
    public function herstelBKoppelingen(int $pouleId): int
    {
        $hersteld = 0;

        // Haal alle B-groep wedstrijden op, gegroepeerd per ronde
        // BELANGRIJK: sorteer op volgorde (niet op ronde-naam, want alfabetisch klopt niet!)
        $wedstrijdenPerRonde = [];
        $bWedstrijden = Wedstrijd::where('poule_id', $pouleId)
            ->where('groep', 'B')
            ->orderBy('volgorde')  // Correcte volgorde: 1/8_1, 1/8_2, 1/4_1, 1/4_2, etc.
            ->get();

        foreach ($bWedstrijden as $wed) {
            $wedstrijdenPerRonde[$wed->ronde][] = $wed;
        }

        // Sorteer rondes in correcte volgorde (invoegvolgorde is nu correct door orderBy volgorde)
        $rondes = array_keys($wedstrijdenPerRonde);

        Log::info("herstelBKoppelingen poule {$pouleId}: rondes=" . implode(', ', $rondes));

        // Bepaal of dit dubbele rondes zijn
        $dubbelRondes = collect($rondes)->contains(fn($r) => str_ends_with($r, '_1'));

        // Koppel elke ronde aan de volgende
        for ($r = 0; $r < count($rondes) - 1; $r++) {
            $huidigeRonde = $rondes[$r];
            $volgendeRonde = $rondes[$r + 1];

            $huidigeWedstrijden = $wedstrijdenPerRonde[$huidigeRonde];
            $volgendeWedstrijden = $wedstrijdenPerRonde[$volgendeRonde];

            $is1naar2 = $dubbelRondes && str_ends_with($huidigeRonde, '_1') && str_ends_with($volgendeRonde, '_2');
            $isBrons = $volgendeRonde === 'b_halve_finale_2';

            Log::info("Koppel {$huidigeRonde} → {$volgendeRonde}: is1naar2={$is1naar2}, isBrons={$isBrons}");

            foreach ($huidigeWedstrijden as $wedstrijd) {
                $pos = $wedstrijd->bracket_positie - 1;

                if ($is1naar2 || $isBrons) {
                    $volgendePos = $pos;
                    $slot = 'wit';
                } else {
                    $volgendePos = (int) floor($pos / 2);
                    $slot = ($pos % 2 == 0) ? 'wit' : 'blauw';
                }

                $volgendeWedstrijd = collect($volgendeWedstrijden)
                    ->firstWhere('bracket_positie', $volgendePos + 1);

                if ($volgendeWedstrijd) {
                    $wedstrijd->update([
                        'volgende_wedstrijd_id' => $volgendeWedstrijd->id,
                        'winnaar_naar_slot' => $slot,
                    ]);
                    Log::debug("  Wed {$wedstrijd->id} (pos {$wedstrijd->bracket_positie}) → Wed {$volgendeWedstrijd->id} (pos {$volgendeWedstrijd->bracket_positie}), slot={$slot}");
                    $hersteld++;
                } else {
                    Log::warning("  Wed {$wedstrijd->id} (pos {$wedstrijd->bracket_positie}) → GEEN VOLGENDE GEVONDEN (zoekt pos " . ($volgendePos + 1) . ")");
                }
            }
        }

        Log::info("Hersteld {$hersteld} B-koppelingen voor poule {$pouleId}");

        return $hersteld;
    }

    /**
     * Verwijder judoka uit B-groep wedstrijden
     */
    public function verwijderUitB(int $pouleId, int $judokaId): void
    {
        $bWedstrijden = Wedstrijd::where('poule_id', $pouleId)
            ->where('groep', 'B')
            ->where(function ($q) use ($judokaId) {
                $q->where('judoka_wit_id', $judokaId)
                  ->orWhere('judoka_blauw_id', $judokaId);
            })
            ->get();

        foreach ($bWedstrijden as $wed) {
            if ($wed->judoka_wit_id == $judokaId) {
                $wed->update(['judoka_wit_id' => null]);
            }
            if ($wed->judoka_blauw_id == $judokaId) {
                $wed->update(['judoka_blauw_id' => null]);
            }
        }
    }
}
