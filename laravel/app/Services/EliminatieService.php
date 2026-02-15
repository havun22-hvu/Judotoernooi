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
                    $this->genereerBGroepIJF($poule, $n, $aantalBrons, $aWedstrijden);
                } else {
                    $this->genereerBGroepDubbel($poule, $n, $aantalBrons, $aWedstrijden);
                }
            }
        });

        $bWedstrijden = ($type === 'ijf') ? ($aantalBrons === 1 ? 5 : 4) : max(0, $n - 4);

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
     * KERNLOGICA (zie docs/2-FEATURES/ELIMINATIE/FORMULES.md):
     *
     * B-start = a2 wedstrijden (zelfde niveau als tweede A-ronde)
     * - SAMEN (a1 ≤ a2): a1 op WIT, a2 op BLAUW, (a2 - a1) byes op WIT
     * - DUBBEL (a1 > a2): extra (1) ronde voor a1 onderling, winnaars + a2 in (2)
     *
     * BELANGRIJK:
     * - aantalBrons = 2: Eindigt met 2x B-1/2(2), GEEN finale! (2x brons)
     * - aantalBrons = 1: Eindigt met B-finale (1x brons)
     * - B-byes NIET aan A-bye judoka's geven (fairness)
     */
    private function genereerBGroepDubbel(Poule $poule, int $n, int $aantalBrons = 2, array $aWedstrijden = []): void
    {
        $volgorde = 1000;
        $wedstrijdenPerRonde = [];

        // Gebruik centrale berekening
        $params = $this->berekenBracketParams($n);
        // B-start = a2 wedstrijden (zie FORMULES.md §B-Start Ronde Bepalen)
        // SAMEN: a1 op WIT, a2 op BLAUW, (a2 - a1) byes op WIT
        // DUBBEL: extra (1) ronde ervoor voor a1 onderling
        $bStartWedstrijden = $params['a2Verliezers'];

        if ($params['dubbelRondes']) {
            // DUBBELE RONDES: (1) en (2) per niveau
            $this->genereerDubbeleBRondes($poule, $bStartWedstrijden, $volgorde, $wedstrijdenPerRonde, $aantalBrons);
        } else {
            // ENKELE RONDES: standaard knockout
            $this->genereerEnkeleBRondes($poule, $bStartWedstrijden, $volgorde, $wedstrijdenPerRonde, $aantalBrons);
        }

        $this->koppelBGroepWedstrijden($wedstrijdenPerRonde, $params['dubbelRondes']);

        // Koppel A-wedstrijden aan B-wedstrijden voor deterministische verliezer-plaatsing
        if (!empty($aWedstrijden)) {
            $this->koppelAVerliezersAanB($aWedstrijden, $wedstrijdenPerRonde, $params);
        }
    }

    /**
     * Genereer ENKELE B-rondes (V1 == V2)
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
     * Genereer DUBBELE B-rondes (V1 != V2)
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

    /**
     * Koppel A-wedstrijden aan B-wedstrijden via herkansing_wedstrijd_id
     *
     * Dit maakt de verliezer-plaatsing deterministisch (net als IJF).
     * Elke A-wedstrijd weet exact naar welke B-wedstrijd de verliezer gaat.
     */
    private function koppelAVerliezersAanB(array $aWedstrijden, array $bWedstrijden, array $params): void
    {
        $aRondes = array_keys($aWedstrijden);
        if (count($aRondes) < 2) return;

        $eersteARonde = $aRondes[0];
        $tweedeARonde = $aRondes[1];
        $dubbelRondes = $params['dubbelRondes'];

        // Vind B-start ronde (eerste B-ronde)
        $bRondes = array_keys($bWedstrijden);
        if (empty($bRondes)) return;

        $bStartRonde = $bRondes[0]; // Eerste B-ronde

        Log::info("koppelAVerliezersAanB: eersteA={$eersteARonde}, tweedeA={$tweedeARonde}, bStart={$bStartRonde}, dubbel=" . ($dubbelRondes ? 'ja' : 'nee'));

        // === EERSTE A-RONDE VERLIEZERS ===
        if ($dubbelRondes) {
            // DUBBEL: eerste A-ronde verliezers → B-start(1)
            // (1) ronde = eerste B-ronde (bv. b_kwartfinale_1)
            $this->koppelARondeAanBRonde($aWedstrijden[$eersteARonde], $bWedstrijden[$bStartRonde] ?? [], 'eerste');
        } else {
            // SAMEN: eerste A-ronde verliezers → B-start WIT slots bovenaan
            $this->koppelARondeAanBRonde($aWedstrijden[$eersteARonde], $bWedstrijden[$bStartRonde] ?? [], 'samen_wit');
        }

        // === TWEEDE A-RONDE VERLIEZERS ===
        if ($dubbelRondes) {
            // DUBBEL: tweede A-ronde verliezers → B-start(2) BLAUW
            $bStart2Ronde = str_replace('_1', '_2', $bStartRonde);
            if (isset($bWedstrijden[$bStart2Ronde])) {
                $this->koppelARondeAanBRonde($aWedstrijden[$tweedeARonde], $bWedstrijden[$bStart2Ronde], 'dubbel_blauw');
            }
        } else {
            // SAMEN: tweede A-ronde verliezers → B-start
            // Fairness vulvolgorde (zie FORMULES.md §Fairness Regel):
            //   a1 = a2: alle a2 → BLAUW (1:1)
            //   a1 < a2: extra (a2-a1) a2 → overige WIT + hun BLAUW (a2 vs a2),
            //            rest a2 → BLAUW van a1 (LAATST vullen)
            $a1 = $params['a1Verliezers'];
            $a2 = $params['a2Verliezers'];
            if ($a1 < $a2) {
                $this->koppelARondeAanBRonde($aWedstrijden[$tweedeARonde], $bWedstrijden[$bStartRonde] ?? [], 'samen_fairness', $a1);
            } else {
                $this->koppelARondeAanBRonde($aWedstrijden[$tweedeARonde], $bWedstrijden[$bStartRonde] ?? [], 'samen_blauw');
            }
        }

        // === LATERE A-RONDES (kwartfinale, halve finale) → corresponderende B-(2) rondes ===
        for ($r = 2; $r < count($aRondes); $r++) {
            $aRonde = $aRondes[$r];
            $aRondeNaam = $aRonde; // bv. 'kwartfinale', 'halve_finale'

            if ($aRonde === 'finale') continue; // Finale verliezer = zilver, geen B-groep

            if ($aRonde === 'halve_finale') {
                // Halve finale verliezers → b_halve_finale_2 BLAUW
                if (isset($bWedstrijden['b_halve_finale_2'])) {
                    $this->koppelARondeAanBRonde($aWedstrijden[$aRonde], $bWedstrijden['b_halve_finale_2'], 'brons_blauw');
                }
            } else {
                // Andere latere rondes → corresponderende B-ronde(2)
                $bRondeNaam = $dubbelRondes ? "b_{$aRonde}_2" : "b_{$aRonde}";
                if (isset($bWedstrijden[$bRondeNaam])) {
                    $this->koppelARondeAanBRonde($aWedstrijden[$aRonde], $bWedstrijden[$bRondeNaam], 'dubbel_blauw');
                }
            }
        }
    }

    /**
     * Koppel wedstrijden van één A-ronde aan één B-ronde
     *
     * @param array $aWedstrijden Wedstrijden in deze A-ronde
     * @param array $bWedstrijden Wedstrijden in de doel B-ronde
     * @param string $type Type koppeling:
     *   - 'eerste': eerste batch, 2:1 mapping naar WIT/BLAUW (voor (1) rondes)
     *   - 'samen_wit': SAMEN mode, A-verliezers → WIT
     *   - 'samen_blauw': SAMEN mode, A-verliezers → BLAUW
     *   - 'samen_fairness': SAMEN mode a1<a2, fairness vulvolgorde (zie FORMULES.md)
     *   - 'dubbel_blauw': latere rondes, A-verliezers → BLAUW (1:1 mapping)
     *   - 'brons_blauw': halve finale verliezers → brons matching BLAUW (1:1 mapping)
     * @param int $a1Count Alleen voor samen_fairness: aantal a1 verliezers
     */
    private function koppelARondeAanBRonde(array $aWedstrijden, array $bWedstrijden, string $type, int $a1Count = 0): void
    {
        if (empty($bWedstrijden)) return;

        // Filter echte wedstrijden (skip byes: wit gevuld, blauw leeg)
        $echteWedstrijden = [];
        foreach ($aWedstrijden as $aWedstrijd) {
            if (is_null($aWedstrijd->judoka_blauw_id) && $aWedstrijd->judoka_wit_id) {
                continue; // Bye
            }
            $echteWedstrijden[] = $aWedstrijd;
        }

        foreach ($echteWedstrijden as $idx => $aWedstrijd) {
            $bWedstrijd = null;
            $slot = 'wit';

            switch ($type) {
                case 'eerste':
                    // DUBBEL: verliezers spreiden over alle B(1) wedstrijden
                    // Als er minder verliezers zijn dan 2x B-capaciteit,
                    // krijgen sommige B-wedstrijden maar 1 judoka (bye)
                    $bCapaciteit = count($bWedstrijden);
                    $totaalVerliezers = count($echteWedstrijden);
                    $volleWedstrijden = $totaalVerliezers - $bCapaciteit; // B-weds met 2 judoka's

                    if ($volleWedstrijden <= 0) {
                        // Alle verliezers krijgen eigen B-wedstrijd (allemaal byes)
                        $bIdx = $idx;
                        $slot = 'wit';
                    } elseif ($idx < $volleWedstrijden * 2) {
                        // Eerste batch: 2:1 mapping (volle wedstrijden)
                        $bIdx = (int) floor($idx / 2);
                        $slot = ($idx % 2 === 0) ? 'wit' : 'blauw';
                    } else {
                        // Rest: 1:1 mapping op WIT (bye wedstrijden, blauw blijft null)
                        $bIdx = $volleWedstrijden + ($idx - $volleWedstrijden * 2);
                        $slot = 'wit';
                    }
                    $bWedstrijd = $bWedstrijden[$bIdx] ?? null;
                    break;

                case 'samen_wit':
                    // SAMEN: 1:1 mapping op WIT slots
                    $bWedstrijd = $bWedstrijden[$idx] ?? null;
                    $slot = 'wit';
                    break;

                case 'samen_blauw':
                    // SAMEN: 1:1 mapping op BLAUW slots
                    $bWedstrijd = $bWedstrijden[$idx] ?? null;
                    $slot = 'blauw';
                    break;

                case 'samen_fairness':
                    // SAMEN (a1 < a2): 4-stappen vulvolgorde (FORMULES.md §Fairness)
                    // a1 verliezers zitten al op WIT slots 0..(a1-1) (via samen_wit)
                    //
                    // Stap 2: a2 → ALLE overige WIT (elke wed minstens 1 judoka!)
                    // Stap 3: rest a2 → BLAUW van a2-weds (a2 vs a2)
                    // Stap 4: rest a2 → BLAUW van a1-weds (LAATST)
                    $overigeWeds = count($bWedstrijden) - $a1Count; // = a2 - a1

                    if ($idx < $overigeWeds) {
                        // Stap 2: a2 → overige WIT slots (idx a1..a2-1)
                        $bWedstrijd = $bWedstrijden[$a1Count + $idx] ?? null;
                        $slot = 'wit';
                    } elseif ($idx < $overigeWeds * 2) {
                        // Stap 3: a2 → BLAUW van a2-wedstrijden (a2 vs a2)
                        $blauwIdx = $idx - $overigeWeds;
                        $bWedstrijd = $bWedstrijden[$a1Count + $blauwIdx] ?? null;
                        $slot = 'blauw';
                    } else {
                        // Stap 4: rest a2 → BLAUW van a1-wedstrijden (LAATST)
                        $restIdx = $idx - ($overigeWeds * 2);
                        $bWedstrijd = $bWedstrijden[$restIdx] ?? null;
                        $slot = 'blauw';
                    }
                    break;

                case 'dubbel_blauw':
                    // DUBBEL: 1:1 mapping op BLAUW in (2) rondes
                    $bWedstrijd = $bWedstrijden[$idx] ?? null;
                    $slot = 'blauw';
                    break;

                case 'brons_blauw':
                    // Halve finale verliezers → brons op BLAUW
                    $bWedstrijd = $bWedstrijden[$idx] ?? null;
                    $slot = 'blauw';
                    break;
            }

            if ($bWedstrijd) {
                $aWedstrijd->update([
                    'herkansing_wedstrijd_id' => $bWedstrijd->id,
                    'verliezer_naar_slot' => $slot,
                ]);
                Log::info("A-koppeling: {$aWedstrijd->ronde} pos {$aWedstrijd->bracket_positie} → B-wed {$bWedstrijd->id} ({$bWedstrijd->ronde} pos {$bWedstrijd->bracket_positie}) slot={$slot}");
            }
        }
    }

    // =========================================================================
    // B-GROEP: IJF (Quarter-Final Repechage)
    // =========================================================================

    /**
     * Genereer B-groep voor IJF systeem (vereenvoudigd)
     *
     * Structuur (2x brons):
     * b_halve_finale_1 pos 1: Verliezer A-1/4(1) vs Verliezer A-1/4(3)
     * b_halve_finale_1 pos 2: Verliezer A-1/4(2) vs Verliezer A-1/4(4)
     * b_halve_finale_2 pos 1: Winnaar B-1/2(1) vs Verliezer A-1/2(1) → BRONS
     * b_halve_finale_2 pos 2: Winnaar B-1/2(2) vs Verliezer A-1/2(2) → BRONS
     *
     * Structuur (1x brons): zelfde + b_brons (winnaars B-1/2(2) tegen elkaar)
     */
    private function genereerBGroepIJF(Poule $poule, int $n, int $aantalBrons, array $aWedstrijden): void
    {
        $volgorde = 1000;
        $wedstrijdenPerRonde = [];

        // === B-1/2 (1): 2 wedstrijden met verliezers uit A-1/4 ===
        // Verliezers 1/4 finale pos 1+3 → B-1/2(1), pos 2+4 → B-1/2(2)
        for ($i = 1; $i <= 2; $i++) {
            $bracketPositie = $i;
            $wedstrijd = Wedstrijd::create([
                'poule_id' => $poule->id,
                'judoka_wit_id' => null,
                'judoka_blauw_id' => null,
                'volgorde' => $volgorde++,
                'ronde' => 'b_halve_finale_1',
                'groep' => 'B',
                'bracket_positie' => $bracketPositie,
                ...$this->berekenLocaties($bracketPositie),
            ]);
            $wedstrijdenPerRonde['b_halve_finale_1'][] = $wedstrijd;
        }

        // === B-1/2 (2): B-winnaar vs A-1/2 verliezer (= brons wedstrijden) ===
        for ($i = 1; $i <= 2; $i++) {
            $bracketPositie = $i;
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

        // Koppel B-1/2(1) winnaar → B-1/2(2) wit slot
        foreach ($wedstrijdenPerRonde['b_halve_finale_1'] as $idx => $halveFinale1) {
            if (isset($wedstrijdenPerRonde['b_halve_finale_2'][$idx])) {
                $halveFinale1->update([
                    'volgende_wedstrijd_id' => $wedstrijdenPerRonde['b_halve_finale_2'][$idx]->id,
                    'winnaar_naar_slot' => 'wit',
                ]);
            }
        }

        // Bij 1 brons: voeg B-finale toe (winnaars B-1/2(2) tegen elkaar)
        if ($aantalBrons === 1) {
            $wedstrijd = Wedstrijd::create([
                'poule_id' => $poule->id,
                'judoka_wit_id' => null,
                'judoka_blauw_id' => null,
                'volgorde' => $volgorde++,
                'ronde' => 'b_brons',
                'groep' => 'B',
                'bracket_positie' => 1,
                ...$this->berekenLocaties(1),
            ]);
            $wedstrijdenPerRonde['b_brons'][] = $wedstrijd;

            // Koppel B-1/2(2) winnaars → brons
            foreach ($wedstrijdenPerRonde['b_halve_finale_2'] as $idx => $halveFinale2) {
                $halveFinale2->update([
                    'volgende_wedstrijd_id' => $wedstrijd->id,
                    'winnaar_naar_slot' => $idx === 0 ? 'wit' : 'blauw',
                ]);
            }
        }

        // Koppel A-verliezers aan B-wedstrijden
        $this->koppelIJFVerliezers($poule, $aWedstrijden, $wedstrijdenPerRonde);
    }

    /**
     * Koppel A-groep verliezers aan IJF B-groep
     */
    private function koppelIJFVerliezers(Poule $poule, array $aWedstrijden, array $bWedstrijden): void
    {
        // Zoek kwartfinale wedstrijden in A
        $kwartfinales = $aWedstrijden['kwartfinale'] ?? [];

        // Koppel 1/4 verliezers aan B-1/2(1)
        // Pos 1+3 → B-1/2(1) wed 1, Pos 2+4 → B-1/2(1) wed 2
        if (count($kwartfinales) >= 4) {
            $bHalveFinale1 = $bWedstrijden['b_halve_finale_1'] ?? [];
            if (count($bHalveFinale1) >= 2) {
                $kwartfinales[0]->update(['herkansing_wedstrijd_id' => $bHalveFinale1[0]->id, 'verliezer_naar_slot' => 'wit']);
                $kwartfinales[2]->update(['herkansing_wedstrijd_id' => $bHalveFinale1[0]->id, 'verliezer_naar_slot' => 'blauw']);

                $kwartfinales[1]->update(['herkansing_wedstrijd_id' => $bHalveFinale1[1]->id, 'verliezer_naar_slot' => 'wit']);
                $kwartfinales[3]->update(['herkansing_wedstrijd_id' => $bHalveFinale1[1]->id, 'verliezer_naar_slot' => 'blauw']);
            }
        }

        // Zoek halve finale wedstrijden in A
        $halveFinales = $aWedstrijden['halve_finale'] ?? [];

        // Koppel A-1/2 verliezers aan B-1/2(2) blauw slot
        if (count($halveFinales) >= 2) {
            $bHalveFinale2 = $bWedstrijden['b_halve_finale_2'] ?? [];
            if (count($bHalveFinale2) >= 2) {
                $halveFinales[0]->update(['herkansing_wedstrijd_id' => $bHalveFinale2[0]->id, 'verliezer_naar_slot' => 'blauw']);
                $halveFinales[1]->update(['herkansing_wedstrijd_id' => $bHalveFinale2[1]->id, 'verliezer_naar_slot' => 'blauw']);
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

        if ($v1 > 0) {
            // Niet-exacte macht van 2 (N=12,24,etc.)
            // Eerste ronde heeft V1 echte wedstrijden + byes
            // Tweede ronde = eerste VOLLE ronde met D/2 wedstrijden
            $a1Verliezers = $v1;
            $a2Verliezers = (int)($d / 2);
        } else {
            // Exacte macht van 2 (N=8,16,32)
            // Alle wedstrijden in eerste ronde zijn echt
            // Eerste ronde = D/2 wedstrijden, tweede ronde = D/4 wedstrijden
            $a1Verliezers = (int)($d / 2);
            $a2Verliezers = (int)($d / 4);
        }

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
        $bStartWedstrijden = $params['a2Verliezers'];
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
            // 1. Verwijder oude winnaar cascade uit ALLE latere rondes in dezelfde groep
            // Dit voorkomt corrupte data wanneer de oude winnaar al verder was doorgeschoven
            $this->verwijderUitLatereRondes($wedstrijd->poule_id, $wedstrijd->groep, $oudeWinnaarId, $wedstrijd->id);
            $correcties[] = "{$oudeWinnaarNaam} verwijderd uit latere rondes";

            // 2. Plaats nieuwe winnaar in het volgende slot
            if ($wedstrijd->volgende_wedstrijd_id) {
                $volgendeWedstrijd = Wedstrijd::find($wedstrijd->volgende_wedstrijd_id);
                if ($volgendeWedstrijd) {
                    $slot = $wedstrijd->winnaar_naar_slot ?? 'wit';
                    $veld = ($slot === 'wit') ? 'judoka_wit_id' : 'judoka_blauw_id';

                    $volgendeWedstrijd->update([$veld => $winnaarId]);
                    $correcties[] = "{$winnaarNaam} geplaatst in volgende ronde";
                }
            }

            // 3. Verwijder nieuwe winnaar (=oude verliezer) uit B-groep
            // Want die was daar geplaatst als verliezer, maar is nu winnaar
            // ALLEEN bij A-groep! Bij B-groep correcties blijft de winnaar in B-groep
            if ($wedstrijd->groep === 'A') {
                $this->verwijderUitB($wedstrijd->poule_id, $winnaarId);
                $correcties[] = "{$winnaarNaam} verwijderd uit B-groep (is nu winnaar)";
            }

            // 4. Plaats oude winnaar (=nieuwe verliezer) in B-groep
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
        // === NIEUW: Deterministische plaatsing via herkansing_wedstrijd_id ===
        // Net als IJF systeem (lijn 1349-1357): bij generatie al gekoppeld
        if ($wedstrijd->herkansing_wedstrijd_id) {
            $bWedstrijd = Wedstrijd::find($wedstrijd->herkansing_wedstrijd_id);
            if ($bWedstrijd) {
                $slot = ($wedstrijd->verliezer_naar_slot === 'blauw') ? 'judoka_blauw_id' : 'judoka_wit_id';
                $bWedstrijd->update([$slot => $verliezerId]);
                Log::info("Verliezer {$verliezerId} deterministisch geplaatst: {$wedstrijd->ronde} → {$bWedstrijd->ronde} pos {$bWedstrijd->bracket_positie} ({$wedstrijd->verliezer_naar_slot})");
                return;
            }
        }

        // === FALLBACK: Oude runtime-lookup voor bestaande brackets ===
        $pouleId = $wedstrijd->poule_id;
        $aRonde = $wedstrijd->ronde;

        $bRonde = $this->bepaalBRondeVoorVerliezer($pouleId, $aRonde);

        if (!$bRonde) {
            Log::warning("Geen B-ronde bepaald voor A-ronde {$aRonde} in poule {$pouleId}");
            return;
        }

        Log::info("Verliezer {$verliezerId} van {$aRonde} → {$bRonde} (fallback)");

        $hadAlBye = $this->heeftByeGehad($pouleId, $verliezerId);
        $bWedstrijd = null;

        if ($hadAlBye) {
            $bWedstrijd = $this->zoekSlotMetTegenstander($pouleId, $bRonde);
        }

        if (!$bWedstrijd) {
            $bWedstrijd = $this->zoekEersteLegeBSlot($pouleId, $bRonde);
        }

        if ($bWedstrijd) {
            if (str_ends_with($bRonde, '_2')) {
                $slot = 'judoka_blauw_id';
                if (!is_null($bWedstrijd->judoka_blauw_id)) {
                    $slot = is_null($bWedstrijd->judoka_wit_id) ? 'judoka_wit_id' : null;
                }
            } else {
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

    /**
     * Verwijder judoka cascade uit alle latere rondes in dezelfde groep.
     * Volgt de volgende_wedstrijd_id keten vanaf de bronwedstrijd.
     * Reset ook winnaar_id en is_gespeeld als de judoka daar de winnaar was.
     */
    public function verwijderUitLatereRondes(int $pouleId, string $groep, int $judokaId, int $bronWedstrijdId): void
    {
        // Volg de keten: bronwedstrijd → volgende → volgende → ...
        $huidigeWedstrijdId = $bronWedstrijdId;
        $maxStappen = 20; // Veiligheidsgrens tegen oneindige loops

        while ($huidigeWedstrijdId && $maxStappen > 0) {
            $maxStappen--;
            $wedstrijd = Wedstrijd::find($huidigeWedstrijdId);
            if (!$wedstrijd || !$wedstrijd->volgende_wedstrijd_id) {
                break;
            }

            $volgende = Wedstrijd::find($wedstrijd->volgende_wedstrijd_id);
            if (!$volgende || $volgende->groep !== $groep) {
                break;
            }

            $verwijderd = false;
            if ($volgende->judoka_wit_id == $judokaId) {
                $volgende->judoka_wit_id = null;
                $verwijderd = true;
            }
            if ($volgende->judoka_blauw_id == $judokaId) {
                $volgende->judoka_blauw_id = null;
                $verwijderd = true;
            }

            // Reset uitslag als deze judoka de winnaar was
            if ($volgende->winnaar_id == $judokaId) {
                $volgende->winnaar_id = null;
                $volgende->is_gespeeld = false;
                $volgende->gespeeld_op = null;
                Log::info("Correctie cascade: uitslag gereset voor wed {$volgende->id} ({$volgende->ronde})");
            }

            if ($verwijderd) {
                $volgende->save();
                Log::info("Correctie cascade: judoka {$judokaId} verwijderd uit wed {$volgende->id} ({$volgende->ronde})");
            }

            $huidigeWedstrijdId = $volgende->id;
        }
    }
}
