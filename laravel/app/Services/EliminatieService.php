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
     * @see docs/2-FEATURES/ELIMINATIE/SLOT-SYSTEEM.md voor volledige documentatie
     *
     * SLOT FORMULES (wedstrijd N):
     * - slot_wit = 2N - 1 (oneven)
     * - slot_blauw = 2N (even)
     *
     * Slots worden ALTIJD van boven naar beneden genummerd, ZONDER spiegeling!
     *
     * Voorbeeld 1/8 finale (8 wedstrijden, 16 slots):
     * - Wedstrijd 1: slot 1 (wit), slot 2 (blauw)
     * - Wedstrijd 2: slot 3 (wit), slot 4 (blauw)
     * - Wedstrijd 8: slot 15 (wit), slot 16 (blauw)
     *
     * DOORSCHUIF FORMULE:
     * - Winnaar van slot S → slot ceil(S/2) in volgende ronde
     * - Oneven doel-slot → wit positie, even doel-slot → blauw positie
     */
    private function berekenLocaties(int $bracketPositie): array
    {
        // Formule: slot_wit = 2N-1, slot_blauw = 2N (waar N = bracket_positie)
        return [
            'locatie_wit' => ($bracketPositie - 1) * 2 + 1,   // = 2N - 1 (oneven)
            'locatie_blauw' => ($bracketPositie - 1) * 2 + 2, // = 2N (even)
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
        // Alle wedstrijden in eerste ronde zijn echte wedstrijden (geen byes)
        if ($n == $d) {
            $eersteRonde = $this->getRondeNaam($n);  // 16 → achtste_finale

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
                $volgendeRonde = $this->getRondeNaam($huidigeAantal, true);

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

        // NORMAAL GEVAL: N is niet exacte macht van 2, dus eerste ronde heeft byes
        // Bracket grootte = D wedstrijden in eerste ronde
        // Echte wedstrijden = N - D (beide slots gevuld)
        // Bye wedstrijden = 2*D - N (alleen wit gevuld)
        $totaalEersteRonde = $d;              // Totaal wedstrijden in eerste ronde
        $echteWedstrijden = $n - $d;          // Wedstrijden met 2 judoka's
        $byeWedstrijden = 2 * $d - $n;        // Wedstrijden met 1 judoka (bye)
        $eersteRonde = $this->getRondeNaam($n);

        // Verdeel judoka's: eerst voor echte wedstrijden, dan byes
        $wedstrijdJudokas = array_slice($judokaIds, 0, $echteWedstrijden * 2);
        $byeJudokas = array_slice($judokaIds, $echteWedstrijden * 2);

        // === EERSTE RONDE: echte wedstrijden ===
        for ($i = 0; $i < $echteWedstrijden; $i++) {
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

        // === EERSTE RONDE: bye wedstrijden (alleen wit, blauw = null) ===
        for ($i = 0; $i < $byeWedstrijden; $i++) {
            $bracketPositie = $echteWedstrijden + $i + 1;
            $wedstrijd = Wedstrijd::create([
                'poule_id' => $poule->id,
                'judoka_wit_id' => $byeJudokas[$i],
                'judoka_blauw_id' => null,  // BYE - geen tegenstander
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
            $volgendeRonde = $this->getRondeNaam($huidigeAantal, true);

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
        // Bye judoka's staan al in eerste ronde, geen extra plaatsing nodig
        $this->koppelAGroepWedstrijden($wedstrijdenPerRonde, []);

        return $wedstrijdenPerRonde;
    }

    /**
     * Bepaal ronde naam op basis van aantal judoka's of deelnemers
     *
     * @param int $n Aantal judoka's (bepaalt eerste ronde) of deelnemers in ronde
     * @param bool $voorAantal True = deelnemers in ronde, False = totaal judoka's
     */
    private function getRondeNaam(int $n, bool $voorAantal = false): string
    {
        if ($voorAantal) {
            // Aantal deelnemers IN die ronde
            return match ($n) {
                32 => 'zestiende_finale',
                16 => 'achtste_finale',
                8 => 'kwartfinale',
                4 => 'halve_finale',
                2 => 'finale',
                default => 'achtste_finale',
            };
        }

        // Totaal aantal judoka's -> eerste ronde naam
        if ($n > 32) return 'tweeendertigste_finale';
        if ($n > 16) return 'zestiende_finale';
        if ($n > 8) return 'achtste_finale';
        if ($n > 4) return 'kwartfinale';
        if ($n > 2) return 'halve_finale';
        return 'finale';
    }

    /**
     * Koppel A-groep wedstrijden aan elkaar
     *
     * @see docs/SLOT_SYSTEEM.md voor volledige documentatie
     *
     * DOORSCHUIF LOGICA (2:1 mapping):
     * - Wedstrijd 1+2 → wedstrijd 1 in volgende ronde
     * - Wedstrijd 3+4 → wedstrijd 2 in volgende ronde
     *
     * SLOT BEPALING (op basis van wedstrijd index):
     * - Oneven index (0, 2, 4...) → winnaar_naar_slot = 'wit'
     * - Even index (1, 3, 5...)   → winnaar_naar_slot = 'blauw'
     *
     * Dit komt overeen met de slot formule:
     * - Slot S gaat naar slot ceil(S/2)
     * - Oneven doel-slot = wit positie, even doel-slot = blauw positie
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
                // 2:1 mapping: wedstrijd 0,1 → wed 0 | wedstrijd 2,3 → wed 1 | etc.
                $volgendeIdx = floor($idx / 2);

                // Slot bepaling: idx 0,2,4... → wit | idx 1,3,5... → blauw
                // (Dit komt overeen met: oneven bracket_positie → wit, even → blauw)
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

        // Gebruik centrale berekening
        $params = $this->berekenBracketParams($n);
        $bStartVerliezers = $params['dubbelRondes'] ? $params['a1Verliezers'] : $params['eersteGolf'];
        $bStartWedstrijden = $this->berekenMinimaleBWedstrijden($bStartVerliezers);

        if ($params['dubbelRondes']) {
            // DUBBELE RONDES: (1) en (2) per niveau
            $this->genereerDubbeleBRondes($poule, $bStartWedstrijden, $volgorde, $wedstrijdenPerRonde, $aantalBrons);
        } else {
            // ENKELE RONDES: standaard knockout
            $this->genereerEnkeleBRondes($poule, $bStartWedstrijden, $volgorde, $wedstrijdenPerRonde, $aantalBrons);
        }

        $this->koppelBGroepWedstrijden($wedstrijdenPerRonde, $params['dubbelRondes']);
    }

    /**
     * Genereer ENKELE B-rondes (V1 ≤ V2)
     *
     * aantalBrons = 2: B-start → ... → B-1/2 → B-1/2(2) = 2x BRONS
     * aantalBrons = 1: B-start → ... → B-1/2 → B-1/2(2) → B-finale = 1x BRONS
     *
     * Slots worden van boven naar beneden genummerd, ZONDER spiegeling!
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
     * Koppel B-groep wedstrijden op basis van bracket_positie
     *
     * @see docs/2-FEATURES/ELIMINATIE/SLOT-SYSTEEM.md voor volledige documentatie
     *
     * SLOT SYSTEEM B-GROEP (van boven naar beneden, GEEN spiegeling!):
     * - Wedstrijd N heeft: slot_wit = 2N-1, slot_blauw = 2N
     * - Winnaar van slot S → slot ceil(S/2) in volgende ronde
     *
     * TWEE MAPPING TYPES:
     *
     * 1. NORMALE 2:1 MAPPING ((2) → volgende (1)):
     *    - Wed 1+2 → wed 1 | Wed 3+4 → wed 2 | Wed 5+6 → wed 3 | etc.
     *    - Oneven bracket_positie (1,3,5...) → winnaar naar WIT
     *    - Even bracket_positie (2,4,6...)   → winnaar naar BLAUW
     *
     * 2. SPECIALE 1:1 MAPPING ((1) → (2)):
     *    - Wed N → wed N (zelfde positie)
     *    - Winnaar ALTIJD naar WIT (A-verliezer komt op BLAUW)
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
            // 1:1 mapping bij: (1) → (2) of naar b_halve_finale_2 (brons)
            $is1naar2 = $dubbelRondes && str_ends_with($huidigeRonde, '_1') && str_ends_with($volgendeRonde, '_2');
            $isBrons = $volgendeRonde === 'b_halve_finale_2';

            Log::info("Koppel {$huidigeRonde} (" . count($huidigeWedstrijden) . ") → {$volgendeRonde} (" . count($volgendeWedstrijden) . "): is1naar2={$is1naar2}, isBrons={$isBrons}");

            foreach ($huidigeWedstrijden as $wedstrijd) {
                $bracketPos = $wedstrijd->bracket_positie;

                if ($is1naar2 || $isBrons) {
                    // 1:1 MAPPING: (1) → (2) of laatste → brons
                    // Winnaar altijd naar WIT (A-verliezer komt op BLAUW)
                    $volgendeBracketPos = $bracketPos;
                    $slot = 'wit';
                } else {
                    // 2:1 MAPPING: standaard knockout
                    // Formule: doel_wedstrijd = ceil(bracket_positie / 2)
                    $volgendeBracketPos = (int) ceil($bracketPos / 2);

                    // Formule: oneven bracket_positie → WIT, even → BLAUW
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
     * Bereken alle bracket parameters in één keer
     *
     * @return array [d, v1, a1Verliezers, a2Verliezers, eersteGolf, dubbelRondes]
     */
    private function berekenBracketParams(int $n): array
    {
        $d = $this->berekenDoel($n);
        $v1 = $n - $d;

        // A1 verliezers = echte wedstrijden in eerste ronde
        $a1Verliezers = ($v1 > 0) ? $v1 : (int)($d / 2);

        // A2 verliezers = wedstrijden in tweede ronde
        $a2Verliezers = ($v1 > 0) ? (int)($d / 2) : (int)($d / 4);

        return [
            'd' => $d,
            'v1' => $v1,
            'a1Verliezers' => $a1Verliezers,
            'a2Verliezers' => $a2Verliezers,
            'eersteGolf' => $a1Verliezers + $a2Verliezers,
            'dubbelRondes' => $a1Verliezers > $a2Verliezers,
        ];
    }

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
     * Bereken minimale B-wedstrijden voor gegeven aantal verliezers
     */
    private function berekenMinimaleBWedstrijden(int $verliezers): int
    {
        if ($verliezers <= 4) return 2;
        if ($verliezers <= 8) return 4;
        if ($verliezers <= 16) return 8;
        if ($verliezers <= 32) return 16;
        return 32;
    }

    /**
     * Bereken statistieken voor bracket
     */
    public function berekenStatistieken(int $n, string $type = 'dubbel'): array
    {
        $params = $this->berekenBracketParams($n);
        $bStartWedstrijden = $this->berekenMinimaleBWedstrijden($params['eersteGolf']);
        $bCapaciteit = 2 * $bStartWedstrijden;

        $bWedstrijden = ($type === 'ijf') ? 4 : max(0, $n - 4);
        $totaalWedstrijden = ($type === 'ijf') ? ($n - 1 + 4) : max(0, 2 * $n - 5);

        return [
            'judokas' => $n,
            'type' => $type,
            'doel' => $params['d'],
            'v1' => $params['v1'],
            'a1_verliezers' => $params['a1Verliezers'],
            'a2_verliezers' => $params['a2Verliezers'],
            'eerste_golf' => $params['eersteGolf'],
            'b_start_wedstrijden' => $bStartWedstrijden,
            'a_wedstrijden' => $n - 1,
            'b_wedstrijden' => $bWedstrijden,
            'totaal_wedstrijden' => $totaalWedstrijden,
            'eerste_ronde' => $this->getRondeNaam($n),
            'eerste_ronde_wedstrijden' => $params['a1Verliezers'],
            'a_byes' => max(0, 2 * $params['d'] - $n),
            'b_byes' => max(0, $bCapaciteit - $params['eersteGolf']),
            'dubbel_rondes' => $params['dubbelRondes'],
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
            // 1. Vervang oude winnaar door nieuwe winnaar in volgende ronde
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
     * @see docs/SLOT_SYSTEEM.md voor volledige documentatie
     *
     * A→B VERLIEZER FLOW:
     * - Eerste A-ronde verliezers → B-start op WIT of samen met volgende
     * - Latere A-ronde verliezers → B-xxx(2) op BLAUW slot
     *   (B-winnaars staan al op WIT)
     *
     * BYE FAIRNESS:
     * - Judoka's die al een bye hadden worden NIET opnieuw met bye geplaatst
     * - Ze worden bij een tegenstander gezet indien mogelijk
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
     *
     * @see docs/SLOT_SYSTEEM.md voor volledige documentatie
     *
     * BELANGRIJK: De B-groep start niet altijd op hetzelfde niveau als A!
     * B-start = V2 wedstrijden, dus bij 20 judoka's: B-start = 1/8
     *
     * LOGICA:
     * 1. Check of de corresponderende B-ronde bestaat
     * 2. Zo ja: verliezers naar die B-ronde
     * 3. Zo nee: dit is de eerste A-ronde, verliezers naar B-start
     *
     * VOORBEELD (24 judoka's, D=16, V2=8):
     * - A-1/16 (eerste ronde) verliezers → B-1/8 WIT (want B-1/16 bestaat niet!)
     * - A-1/8 verliezers → B-1/8 BLAUW (samen met A-1/16 verliezers)
     * - A-1/4 verliezers → B-1/4(2)
     */
    private function bepaalBRondeVoorVerliezer(int $pouleId, string $aRonde): ?string
    {
        // Check of we dubbele rondes hebben (door te kijken of _1 rondes bestaan)
        $heeftDubbeleRondes = Wedstrijd::where('poule_id', $pouleId)
            ->where('groep', 'B')
            ->where('ronde', 'like', '%_1')
            ->exists();

        // Mapping A-ronde → B-ronde
        // Bij dubbele rondes: latere A-rondes → (2) rondes (A-verliezers op BLAUW)
        if ($aRonde === 'halve_finale') {
            return 'b_halve_finale_2';
        }

        if ($aRonde === 'kwartfinale') {
            return $heeftDubbeleRondes ? 'b_kwartfinale_2' : 'b_kwartfinale';
        }

        // 1/8 finale - check of B-1/8 rondes bestaan, anders is dit de eerste A-ronde
        if ($aRonde === 'achtste_finale') {
            $bAchtsteExists = Wedstrijd::where('poule_id', $pouleId)
                ->where('groep', 'B')
                ->where('ronde', 'like', 'b_achtste_finale%')
                ->exists();

            if ($bAchtsteExists) {
                return $heeftDubbeleRondes ? 'b_achtste_finale_2' : 'b_achtste_finale';
            }
            // B-1/8 bestaat niet = dit is de eerste A-ronde, val door naar B-start
        }

        // 1/16 finale - check of B-1/16 rondes bestaan, anders is dit de eerste A-ronde
        if ($aRonde === 'zestiende_finale') {
            $bZestiendeExists = Wedstrijd::where('poule_id', $pouleId)
                ->where('groep', 'B')
                ->where('ronde', 'like', 'b_zestiende_finale%')
                ->exists();

            if ($bZestiendeExists) {
                return $heeftDubbeleRondes ? 'b_zestiende_finale_2' : 'b_zestiende_finale';
            }
            // B-1/16 bestaat niet = dit is de eerste A-ronde, val door naar B-start
        }

        // Eerste A-ronde → B-start
        // Inclusief 1/8, 1/16, 1/32 als die de eerste ronde zijn en B-equivalent niet bestaat
        if (in_array($aRonde, ['eerste_ronde', 'tweeendertigste_finale', 'zestiende_finale', 'achtste_finale'])) {
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
     * Zoek beschikbaar B-slot met RANDOM verdeling
     *
     * @see docs/SLOT_SYSTEEM.md - "Plaatsing Algoritme (RANDOM)"
     *
     * ENKELE rondes (zonder _2 suffix):
     * - Verliezers op ODD wedstrijden (1, 3, 5, 7...) zodat winnaars naar WIT gaan
     * - RANDOM verdeling om te voorkomen dat A-volgorde B-tegenstanders bepaalt
     *
     * Prioriteit:
     * 1. LEGE ODD wedstrijden (random) - eerst alle wedstrijden 1 judoka geven
     * 2. HALF GEVULDE ODD wedstrijden (random) - dan tweede slots vullen
     * 3. Fallback naar EVEN wedstrijden als ODD vol zijn
     */
    private function zoekEersteLegeBSlot(int $pouleId, string $ronde): ?Wedstrijd
    {
        // Voor _2 rondes gelden andere regels (A-verliezers op BLAUW)
        $isRonde2 = str_ends_with($ronde, '_2');

        if (!$isRonde2) {
            // ENKELE rondes of (1) rondes: gebruik ODD wedstrijden met RANDOM verdeling

            // 1. LEGE ODD wedstrijden - RANDOM selectie
            // Prioriteit: eerst alle wedstrijden minimaal 1 judoka geven
            $legeOdd = Wedstrijd::where('poule_id', $pouleId)
                ->where('groep', 'B')
                ->where('ronde', $ronde)
                ->whereRaw('bracket_positie % 2 = 1')  // ODD: 1, 3, 5, 7...
                ->whereNull('judoka_wit_id')
                ->whereNull('judoka_blauw_id')
                ->get();

            if ($legeOdd->count() > 0) {
                return $legeOdd->random();  // RANDOM selectie
            }

            // 2. HALF GEVULDE ODD wedstrijden - RANDOM selectie
            // Nu tweede slots vullen
            $halfVol = Wedstrijd::where('poule_id', $pouleId)
                ->where('groep', 'B')
                ->where('ronde', $ronde)
                ->whereRaw('bracket_positie % 2 = 1')  // ODD: 1, 3, 5, 7...
                ->where(function ($q) {
                    $q->where(function ($q2) {
                        $q2->whereNotNull('judoka_wit_id')->whereNull('judoka_blauw_id');
                    })->orWhere(function ($q2) {
                        $q2->whereNull('judoka_wit_id')->whereNotNull('judoka_blauw_id');
                    });
                })
                ->get();

            if ($halfVol->count() > 0) {
                return $halfVol->random();  // RANDOM selectie
            }
        }

        // Fallback: voor _2 rondes of als ODD vol zijn
        // 3. Half gevulde wedstrijden (random)
        $halfBezet = Wedstrijd::where('poule_id', $pouleId)
            ->where('groep', 'B')
            ->where('ronde', $ronde)
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('judoka_wit_id')->whereNull('judoka_blauw_id');
                })->orWhere(function ($q2) {
                    $q2->whereNull('judoka_wit_id')->whereNotNull('judoka_blauw_id');
                });
            })
            ->inRandomOrder()
            ->first();

        if ($halfBezet) {
            return $halfBezet;
        }

        // 4. Volledig lege wedstrijden (random)
        return Wedstrijd::where('poule_id', $pouleId)
            ->where('groep', 'B')
            ->where('ronde', $ronde)
            ->whereNull('judoka_wit_id')
            ->whereNull('judoka_blauw_id')
            ->inRandomOrder()
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
     *
     * @see docs/2-FEATURES/ELIMINATIE/SLOT-SYSTEEM.md voor volledige documentatie
     *
     * Gebruikt dezelfde logica als koppelBGroepWedstrijden():
     * - 2:1 MAPPING: oneven bracket_positie → wit, even → blauw
     * - 1:1 MAPPING: (1) → (2) altijd naar wit
     * - GEEN spiegeling, slots van boven naar beneden!
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

            // Bepaal mapping type (zie koppelBGroepWedstrijden voor details)
            $is1naar2 = $dubbelRondes && str_ends_with($huidigeRonde, '_1') && str_ends_with($volgendeRonde, '_2');
            $isBrons = $volgendeRonde === 'b_halve_finale_2';

            Log::info("Koppel {$huidigeRonde} → {$volgendeRonde}: is1naar2={$is1naar2}, isBrons={$isBrons}");

            foreach ($huidigeWedstrijden as $wedstrijd) {
                $bracketPos = $wedstrijd->bracket_positie;

                if ($is1naar2 || $isBrons) {
                    // 1:1 MAPPING: winnaar altijd naar WIT
                    $volgendeBracketPos = $bracketPos;
                    $slot = 'wit';
                } else {
                    // 2:1 MAPPING: standaard knockout
                    // Formule: doel_wedstrijd = ceil(bracket_positie / 2)
                    $volgendeBracketPos = (int) ceil($bracketPos / 2);

                    // Formule: oneven bracket_positie → WIT, even → BLAUW
                    $slot = ($bracketPos % 2 === 1) ? 'wit' : 'blauw';
                }

                $volgendeWedstrijd = collect($volgendeWedstrijden)
                    ->firstWhere('bracket_positie', $volgendeBracketPos);

                if ($volgendeWedstrijd) {
                    $wedstrijd->update([
                        'volgende_wedstrijd_id' => $volgendeWedstrijd->id,
                        'winnaar_naar_slot' => $slot,
                    ]);
                    Log::debug("  Wed {$wedstrijd->id} (pos {$wedstrijd->bracket_positie}) → Wed {$volgendeWedstrijd->id} (pos {$volgendeWedstrijd->bracket_positie}), slot={$slot}");
                    $hersteld++;
                } else {
                    Log::warning("  Wed {$wedstrijd->id} (pos {$wedstrijd->bracket_positie}) → GEEN VOLGENDE GEVONDEN (zoekt pos {$volgendeBracketPos})");
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
