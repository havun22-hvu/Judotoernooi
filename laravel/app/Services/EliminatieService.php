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
     * Genereer complete eliminatie bracket
     *
     * @param Poule $poule De poule waarvoor bracket gemaakt wordt
     * @param array $judokaIds Array van judoka IDs
     * @param string $type 'dubbel' of 'ijf'
     */
    public function genereerBracket(Poule $poule, array $judokaIds, string $type = 'dubbel'): array
    {
        // Verwijder bestaande wedstrijden
        $poule->wedstrijden()->delete();

        $n = count($judokaIds);
        if ($n < 2) {
            return ['totaal_wedstrijden' => 0];
        }

        DB::transaction(function () use ($poule, $judokaIds, $n, $type) {
            // Genereer A-groep bracket (zelfde voor beide systemen)
            $aWedstrijden = $this->genereerAGroep($poule, $judokaIds);

            // Genereer B-groep bracket (verschilt per type)
            if ($n >= 5) {
                if ($type === 'ijf') {
                    $this->genereerBGroepIJF($poule, $n, $aWedstrijden);
                } else {
                    $this->genereerBGroepDubbel($poule, $n);
                }
            }
        });

        $bWedstrijden = ($type === 'ijf') ? 4 : max(0, $n - 4);

        return [
            'totaal_wedstrijden' => $poule->wedstrijden()->count(),
            'a_wedstrijden' => $n - 1,
            'b_wedstrijden' => $bWedstrijden,
            'type' => $type,
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
                $wedstrijd = Wedstrijd::create([
                    'poule_id' => $poule->id,
                    'judoka_wit_id' => $judokaIds[$i * 2],
                    'judoka_blauw_id' => $judokaIds[$i * 2 + 1],
                    'volgorde' => $volgorde++,
                    'ronde' => $eersteRonde,
                    'groep' => 'A',
                    'bracket_positie' => $i + 1,
                ]);
                $wedstrijdenPerRonde[$eersteRonde][] = $wedstrijd;
            }

            // Volgende rondes (lege slots)
            $huidigeAantal = $n / 2;  // Na eerste ronde
            while ($huidigeAantal > 1) {
                $volgendeAantal = $huidigeAantal / 2;
                $volgendeRonde = $this->getRondeNaamVoorAantal($huidigeAantal);

                for ($i = 0; $i < $volgendeAantal; $i++) {
                    $wedstrijd = Wedstrijd::create([
                        'poule_id' => $poule->id,
                        'judoka_wit_id' => null,
                        'judoka_blauw_id' => null,
                        'volgorde' => $volgorde++,
                        'ronde' => $volgendeRonde,
                        'groep' => 'A',
                        'bracket_positie' => $i + 1,
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
            $wedstrijd = Wedstrijd::create([
                'poule_id' => $poule->id,
                'judoka_wit_id' => $wedstrijdJudokas[$i * 2],
                'judoka_blauw_id' => $wedstrijdJudokas[$i * 2 + 1],
                'volgorde' => $volgorde++,
                'ronde' => $eersteRonde,
                'groep' => 'A',
                'bracket_positie' => $i + 1,
            ]);
            $wedstrijdenPerRonde[$eersteRonde][] = $wedstrijd;
        }

        // === VOLGENDE RONDES ===
        $huidigeAantal = $d;

        while ($huidigeAantal > 1) {
            $volgendeAantal = $huidigeAantal / 2;
            $volgendeRonde = $this->getRondeNaamVoorAantal($huidigeAantal);

            for ($i = 0; $i < $volgendeAantal; $i++) {
                $wedstrijd = Wedstrijd::create([
                    'poule_id' => $poule->id,
                    'judoka_wit_id' => null,
                    'judoka_blauw_id' => null,
                    'volgorde' => $volgorde++,
                    'ronde' => $volgendeRonde,
                    'groep' => 'A',
                    'bracket_positie' => $i + 1,
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
     * Regels:
     * - Ronde naam = aantal wedstrijden (1/8=8wed, 1/4=4wed, 1/2=2wed)
     * - A-verliezers verzamelen tot ≥5 voor eerste B-ronde
     * - B-ronde bepalen: 5-8→1/4, 9-16→1/8, 17-32→1/16
     * - (1)/(2) suffix alleen als dezelfde ronde 2x gespeeld wordt
     *
     * Voorbeelden:
     * - 12j: 4 verl A-1/8 + 4 verl A-1/4 = 8 → B-1/4 (geen suffix)
     * - 15j: 7 verl A-1/8 → B-1/4(1), 4 win + 4 verl A-1/4 → B-1/4(2)
     * - 25j: 9 verl A-1/16 → B-1/8(1), 8 win + 8 verl A-1/8 → B-1/8(2)
     */
    private function genereerBGroepDubbel(Poule $poule, int $n): void
    {
        $volgorde = 1000;

        // Bereken verliezers per A-ronde (ronde naam = aantal wedstrijden)
        // A-1/16: 16 wed, verliezers = n - 16 (als n > 16)
        // A-1/8: 8 wed, verliezers = 8 (altijd, als we 1/8 hebben)
        // A-1/4: 4 wed, verliezers = 4
        // A-1/2: 2 wed, verliezers = 2
        $verliezers = [];

        if ($n > 16) {
            $verliezers['a_zestiende'] = $n - 16;  // A-1/16 verliezers
            $verliezers['a_achtste'] = 8;          // A-1/8 verliezers (altijd 8)
        } else {
            $verliezers['a_achtste'] = $n - 8;    // A-1/8 verliezers = n - 8
        }
        $verliezers['a_kwart'] = 4;               // A-1/4 verliezers (altijd 4)
        $verliezers['a_halve'] = 2;               // A-1/2 verliezers (altijd 2)

        $rondes = [];

        // === Bepaal B-groep structuur ===

        // Stap 1: Verzamel vroege verliezers (A-1/16 + A-1/8)
        $vroegeVerliezers = ($verliezers['a_zestiende'] ?? 0) + ($verliezers['a_achtste'] ?? 0);
        $alleenA16Verliezers = $verliezers['a_zestiende'] ?? 0;
        $a8Verliezers = $verliezers['a_achtste'] ?? 0;

        // Stap 2: Bepaal eerste B-ronde
        if ($alleenA16Verliezers >= 9) {
            // Genoeg A-1/16 verliezers voor B-1/8(1)
            $rondes[] = ['naam' => 'b_achtste_finale_1', 'wedstrijden' => 8];
            $rondes[] = ['naam' => 'b_achtste_finale_2', 'wedstrijden' => 8];
            $rondes[] = ['naam' => 'b_kwartfinale_1', 'wedstrijden' => 4];
            $rondes[] = ['naam' => 'b_kwartfinale_2', 'wedstrijden' => 4];
        } elseif ($vroegeVerliezers >= 9) {
            // A-1/16 + A-1/8 samen ≥9 → B-1/8 (geen suffix, komen samen)
            $rondes[] = ['naam' => 'b_achtste_finale', 'wedstrijden' => 8];
            $rondes[] = ['naam' => 'b_kwartfinale', 'wedstrijden' => 4];  // + A-1/4 verliezers
            // Hier geen (1)/(2) want B-1/8 winnaars gaan direct naar B-1/4 met A-1/4 verl
        } elseif ($a8Verliezers >= 5) {
            // 5-8 A-1/8 verliezers → B-1/4(1)
            $rondes[] = ['naam' => 'b_kwartfinale_1', 'wedstrijden' => 4];
            $rondes[] = ['naam' => 'b_kwartfinale_2', 'wedstrijden' => 4];
        } else {
            // ≤4 A-1/8 verliezers, wacht op A-1/4 → samen B-1/4 (geen suffix)
            $rondes[] = ['naam' => 'b_kwartfinale', 'wedstrijden' => 4];
        }

        // Stap 3: B-halve finale en B-brons
        // Check of we al b_kwartfinale_2 hebben (dan b_halve_finale_1 nodig)
        $heeftKwart2 = collect($rondes)->contains(fn($r) => str_contains($r['naam'], 'kwartfinale_2'));

        if ($heeftKwart2) {
            $rondes[] = ['naam' => 'b_halve_finale_1', 'wedstrijden' => 2];
        }
        // B-brons: B-halve winnaars + A-halve verliezers → 2 bronzen
        $rondes[] = ['naam' => 'b_brons', 'wedstrijden' => 2];

        // Maak wedstrijden
        $wedstrijdenPerRonde = [];
        foreach ($rondes as $rondeInfo) {
            for ($i = 0; $i < $rondeInfo['wedstrijden']; $i++) {
                $wedstrijd = Wedstrijd::create([
                    'poule_id' => $poule->id,
                    'judoka_wit_id' => null,
                    'judoka_blauw_id' => null,
                    'volgorde' => $volgorde++,
                    'ronde' => $rondeInfo['naam'],
                    'groep' => 'B',
                    'bracket_positie' => $i + 1,
                ]);
                $wedstrijdenPerRonde[$rondeInfo['naam']][] = $wedstrijd;
            }
        }

        // Koppel B-groep wedstrijden
        $this->koppelBGroepWedstrijden($wedstrijdenPerRonde);
    }

    /**
     * Koppel B-groep wedstrijden (dubbel eliminatie)
     */
    private function koppelBGroepWedstrijden(array $wedstrijdenPerRonde): void
    {
        $rondes = array_keys($wedstrijdenPerRonde);

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
            $wedstrijd = Wedstrijd::create([
                'poule_id' => $poule->id,
                'judoka_wit_id' => null,
                'judoka_blauw_id' => null,
                'volgorde' => $volgorde++,
                'ronde' => 'b_repechage_' . $i,
                'groep' => 'B',
                'bracket_positie' => $i,
            ]);
            $wedstrijdenPerRonde['b_repechage'][] = $wedstrijd;
        }

        // === BRONS WEDSTRIJDEN (2 wedstrijden) ===
        // Winnaar repechage vs Verliezer 1/2 finale
        for ($i = 1; $i <= 2; $i++) {
            $wedstrijd = Wedstrijd::create([
                'poule_id' => $poule->id,
                'judoka_wit_id' => null,
                'judoka_blauw_id' => null,
                'volgorde' => $volgorde++,
                'ronde' => 'b_brons_' . $i,
                'groep' => 'B',
                'bracket_positie' => $i,
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
     */
    public function berekenStatistieken(int $n, string $type = 'dubbel'): array
    {
        $d = $this->berekenDoel($n);

        $bWedstrijden = ($type === 'ijf') ? 4 : max(0, $n - 4);
        $totaalWedstrijden = ($type === 'ijf') ? ($n - 1 + 4) : max(0, 2 * $n - 5);

        return [
            'judokas' => $n,
            'type' => $type,
            'doel' => $d,
            'a_wedstrijden' => $n - 1,
            'b_wedstrijden' => $bWedstrijden,
            'totaal_wedstrijden' => $totaalWedstrijden,
            'eerste_ronde' => $this->getEersteRondeNaam($n),
            'eerste_ronde_wedstrijden' => $n - $d,
            'byes' => $d - ($n - $d),  // = 2D - N
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

        // Als er een oude winnaar was, moet die gecorrigeerd worden
        if ($oudeWinnaarId && $oudeWinnaarId != $winnaarId) {
            // 1. Verwijder oude winnaar uit volgende ronde
            if ($wedstrijd->volgende_wedstrijd_id) {
                $volgendeWedstrijd = Wedstrijd::find($wedstrijd->volgende_wedstrijd_id);
                if ($volgendeWedstrijd) {
                    $slot = $wedstrijd->winnaar_naar_slot ?? 'wit';
                    $veld = ($slot === 'wit') ? 'judoka_wit_id' : 'judoka_blauw_id';

                    if ($volgendeWedstrijd->$veld == $oudeWinnaarId) {
                        $volgendeWedstrijd->update([$veld => null]);
                        $correcties[] = "Oude winnaar verwijderd uit volgende ronde";
                    }
                }
            }

            // 2. Verwijder nieuwe winnaar (=oude verliezer) uit B-groep
            // Want die was daar geplaatst als verliezer, maar is nu winnaar
            $this->verwijderUitB($wedstrijd->poule_id, $winnaarId);
            $correcties[] = "Nieuwe winnaar verwijderd uit B-groep";

            // 3. Plaats oude winnaar (=nieuwe verliezer) in B-groep
            // De reguliere code hieronder doet dit al
        }

        // Verliezer naar B-groep (alleen bij A-groep wedstrijden)
        if ($wedstrijd->groep === 'A' && $verliezerId) {
            if ($type === 'ijf') {
                $this->plaatsVerliezerIJF($wedstrijd, $verliezerId);
            } else {
                $this->plaatsVerliezerDubbel($wedstrijd, $verliezerId);
            }
        }

        return $correcties;
    }

    /**
     * Plaats verliezer in B-groep (Dubbel Eliminatie)
     *
     * Mapping A-ronde → B-ronde:
     * - A-1/16 verliezers → B-1/8(1) of B-1/8
     * - A-1/8 verliezers → B-1/8(2) of B-1/4(1) of B-1/4
     * - A-1/4 verliezers → B-1/4(2) of B-1/4
     * - A-1/2 verliezers → B-brons
     */
    private function plaatsVerliezerDubbel(Wedstrijd $wedstrijd, int $verliezerId): void
    {
        $pouleId = $wedstrijd->poule_id;

        // Bepaal target B-rondes op basis van A-ronde (in volgorde van voorkeur)
        $targetRondes = match ($wedstrijd->ronde) {
            'zestiende_finale' => ['b_achtste_finale_1', 'b_achtste_finale'],
            'achtste_finale' => ['b_achtste_finale_2', 'b_kwartfinale_1', 'b_kwartfinale', 'b_achtste_finale'],
            'kwartfinale' => ['b_kwartfinale_2', 'b_kwartfinale'],
            'halve_finale' => ['b_brons'],
            default => ['b_kwartfinale_1', 'b_kwartfinale', 'b_achtste_finale'],
        };

        // Zoek eerste beschikbare slot
        $bWedstrijd = null;
        foreach ($targetRondes as $ronde) {
            $bWedstrijd = $this->zoekLegeBSlot($pouleId, $ronde);
            if ($bWedstrijd) {
                break;
            }
        }

        // Fallback: zoek in alle B-rondes
        if (!$bWedstrijd) {
            $bWedstrijd = $this->zoekLegeBSlot($pouleId, null);
        }

        if ($bWedstrijd) {
            $slot = is_null($bWedstrijd->judoka_wit_id) ? 'judoka_wit_id' : 'judoka_blauw_id';
            $bWedstrijd->update([$slot => $verliezerId]);
        }
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
     * Zoek een lege slot in B-groep
     */
    private function zoekLegeBSlot(int $pouleId, ?string $ronde): ?Wedstrijd
    {
        $query = Wedstrijd::where('poule_id', $pouleId)
            ->where('groep', 'B')
            ->where(function ($q) {
                $q->whereNull('judoka_wit_id')
                  ->orWhereNull('judoka_blauw_id');
            })
            ->orderBy('bracket_positie');

        if ($ronde) {
            $query->where('ronde', $ronde);
        }

        return $query->first();
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
