<?php

namespace App\Services;

use App\Models\Poule;
use App\Models\Wedstrijd;
use App\Services\Eliminatie\BracketCalculator;
use App\Services\Eliminatie\MatchScheduler;
use App\Services\Eliminatie\WinnerCalculator;
use Illuminate\Support\Facades\DB;

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
    private BracketCalculator $calculator;
    private WinnerCalculator $winners;
    private MatchScheduler $scheduler;

    public function __construct(
        ?BracketCalculator $calculator = null,
        ?WinnerCalculator $winners = null,
        ?MatchScheduler $scheduler = null,
    ) {
        $this->calculator = $calculator ?? new BracketCalculator();
        $this->winners = $winners ?? new WinnerCalculator();
        $this->scheduler = $scheduler ?? new MatchScheduler();
    }

    /**
     * Bereken locatie_wit en locatie_blauw op basis van bracket_positie.
     *
     * @see BracketCalculator::berekenLocaties
     */
    private function berekenLocaties(int $bracketPositie): array
    {
        return $this->calculator->berekenLocaties($bracketPositie);
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
     * Bepaal ronde naam op basis van aantal judoka's of deelnemers.
     *
     * @see BracketCalculator::getRondeNaam
     */
    private function getRondeNaam(int $n, bool $voorAantal = false): string
    {
        return $this->calculator->getRondeNaam($n, $voorAantal);
    }

    /**
     * Koppel A-groep wedstrijden aan elkaar.
     *
     * @see MatchScheduler::koppelAGroepWedstrijden
     */
    private function koppelAGroepWedstrijden(array $wedstrijdenPerRonde, array $byeJudokas): void
    {
        $this->scheduler->koppelAGroepWedstrijden($wedstrijdenPerRonde, $byeJudokas);
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
     * Get B-ronde naam voor aantal wedstrijden.
     *
     * @see BracketCalculator::getBRondeNaam
     */
    private function getBRondeNaam(int $wedstrijden): string
    {
        return $this->calculator->getBRondeNaam($wedstrijden);
    }

    /**
     * Koppel B-groep wedstrijden op basis van bracket_positie.
     *
     * @see MatchScheduler::koppelBGroepWedstrijden
     */
    private function koppelBGroepWedstrijden(array $wedstrijdenPerRonde, bool $dubbelRondes): void
    {
        $this->scheduler->koppelBGroepWedstrijden($wedstrijdenPerRonde, $dubbelRondes);
    }

    /**
     * Koppel A-wedstrijden aan B-wedstrijden via herkansing_wedstrijd_id.
     *
     * @see MatchScheduler::koppelAVerliezersAanB
     */
    private function koppelAVerliezersAanB(array $aWedstrijden, array $bWedstrijden, array $params): void
    {
        $this->scheduler->koppelAVerliezersAanB($aWedstrijden, $bWedstrijden, $params);
    }

    /**
     * Koppel wedstrijden van één A-ronde aan één B-ronde.
     *
     * @see MatchScheduler::koppelARondeAanBRonde
     */
    private function koppelARondeAanBRonde(array $aWedstrijden, array $bWedstrijden, string $type, int $a1Count = 0): void
    {
        $this->scheduler->koppelARondeAanBRonde($aWedstrijden, $bWedstrijden, $type, $a1Count);
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
     * Koppel A-groep verliezers aan IJF B-groep.
     *
     * @see MatchScheduler::koppelIJFVerliezers
     */
    private function koppelIJFVerliezers(Poule $poule, array $aWedstrijden, array $bWedstrijden): void
    {
        $this->scheduler->koppelIJFVerliezers($aWedstrijden, $bWedstrijden);
    }

    // =========================================================================
    // HELPER METHODES
    // =========================================================================

    /**
     * Bereken alle bracket parameters in één keer.
     *
     * @see BracketCalculator::berekenBracketParams
     */
    private function berekenBracketParams(int $n): array
    {
        return $this->calculator->berekenBracketParams($n);
    }

    /**
     * Bereken doel (grootste macht van 2 <= n).
     *
     * @see BracketCalculator::berekenDoel
     */
    private function berekenDoel(int $n): int
    {
        return $this->calculator->berekenDoel($n);
    }

    /**
     * Bereken minimale B-wedstrijden voor gegeven aantal verliezers.
     *
     * @see BracketCalculator::berekenMinimaleBWedstrijden
     */
    private function berekenMinimaleBWedstrijden(int $verliezers): int
    {
        return $this->calculator->berekenMinimaleBWedstrijden($verliezers);
    }

    /**
     * Bereken statistieken voor bracket.
     *
     * @see BracketCalculator::berekenStatistieken
     */
    public function berekenStatistieken(int $n, string $type = 'dubbel'): array
    {
        return $this->calculator->berekenStatistieken($n, $type);
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
        $this->winners->plaatsVerliezerDubbel($wedstrijd, $verliezerId);
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
    private function plaatsVerliezerIJF(Wedstrijd $wedstrijd, int $verliezerId): void
    {
        $this->winners->plaatsVerliezerIJF($wedstrijd, $verliezerId);
    }

    /** @see WinnerCalculator::bepaalBRondeVoorVerliezer */
    private function bepaalBRondeVoorVerliezer(int $pouleId, string $aRonde): ?string
    {
        return $this->winners->bepaalBRondeVoorVerliezer($pouleId, $aRonde);
    }

    /** @see WinnerCalculator::vindBStartRonde */
    private function vindBStartRonde(int $pouleId): ?string
    {
        return $this->winners->vindBStartRonde($pouleId);
    }

    /** @see WinnerCalculator::zoekSlotMetTegenstander */
    private function zoekSlotMetTegenstander(int $pouleId, string $ronde): ?Wedstrijd
    {
        return $this->winners->zoekSlotMetTegenstander($pouleId, $ronde);
    }

    /** @see WinnerCalculator::zoekEersteLegeBSlot */
    private function zoekEersteLegeBSlot(int $pouleId, string $ronde): ?Wedstrijd
    {
        return $this->winners->zoekEersteLegeBSlot($pouleId, $ronde);
    }

    /** @see WinnerCalculator::heeftByeGehad */
    private function heeftByeGehad(int $pouleId, int $judokaId): bool
    {
        return $this->winners->heeftByeGehad($pouleId, $judokaId);
    }

    /**
     * Schrap lege B-wedstrijden na alle A-rondes.
     *
     * @see MatchScheduler::schrapLegeBWedstrijden
     */
    public function schrapLegeBWedstrijden(int $pouleId): int
    {
        return $this->scheduler->schrapLegeBWedstrijden($pouleId);
    }

    /**
     * Hernummer bracket_positie per ronde na verwijderen wedstrijden.
     *
     * @see MatchScheduler::hernummerBracketPosities
     */
    private function hernummerBracketPosities(int $pouleId): void
    {
        $this->scheduler->hernummerBracketPosities($pouleId);
    }

    /**
     * Herstel B-groep koppelingen voor bestaande bracket.
     *
     * @see MatchScheduler::herstelBKoppelingen
     */
    public function herstelBKoppelingen(int $pouleId): int
    {
        return $this->scheduler->herstelBKoppelingen($pouleId);
    }

    /**
     * Verwijder judoka uit B-groep wedstrijden.
     *
     * @see WinnerCalculator::verwijderUitB
     */
    public function verwijderUitB(int $pouleId, int $judokaId): void
    {
        $this->winners->verwijderUitB($pouleId, $judokaId);
    }

    /**
     * Verwijder judoka cascade uit alle latere rondes in dezelfde groep.
     *
     * @see WinnerCalculator::verwijderUitLatereRondes
     */
    public function verwijderUitLatereRondes(int $pouleId, string $groep, int $judokaId, int $bronWedstrijdId): void
    {
        $this->winners->verwijderUitLatereRondes($pouleId, $groep, $judokaId, $bronWedstrijdId);
    }
}
