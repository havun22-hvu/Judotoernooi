<?php

namespace App\Services\Eliminatie;

use App\Models\Wedstrijd;
use Illuminate\Support\Facades\Log;

/**
 * MatchScheduler - Round progression & match linking helper
 *
 * Responsible for "wiring up" an already-generated elimination bracket:
 *  - linking each match to its follow-up match (volgende_wedstrijd_id)
 *  - assigning the correct winnaar_naar_slot (wit/blauw) per round
 *  - routing A-group losers to the right B-group slots (Dubbel + IJF)
 *  - repairing broken links on an existing bracket
 *  - cleaning up empty B-matches and renumbering bracket_positie
 *
 * Extracted from EliminatieService (phase 3). All methods take
 * already-created Wedstrijd models (as arrays keyed by ronde) so the
 * scheduler stays independent of how the bracket was generated.
 */
class MatchScheduler
{
    // =========================================================================
    // A-GROEP koppeling
    // =========================================================================

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
     */
    public function koppelAGroepWedstrijden(array $wedstrijdenPerRonde, array $byeJudokas): void
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
    // B-GROEP koppeling (Dubbel)
    // =========================================================================

    /**
     * Koppel B-groep wedstrijden op basis van bracket_positie.
     *
     * @see docs/2-FEATURES/ELIMINATIE/SLOT-SYSTEEM.md voor volledige documentatie
     *
     * TWEE MAPPING TYPES:
     *
     * 1. NORMALE 2:1 MAPPING ((2) → volgende (1)):
     *    - Wed 1+2 → wed 1 | Wed 3+4 → wed 2 | etc.
     *    - Oneven bracket_positie → WIT, even → BLAUW
     *
     * 2. SPECIALE 1:1 MAPPING ((1) → (2)):
     *    - Wed N → wed N (zelfde positie)
     *    - Winnaar ALTIJD naar WIT (A-verliezer komt op BLAUW)
     */
    public function koppelBGroepWedstrijden(array $wedstrijdenPerRonde, bool $dubbelRondes): void
    {
        $rondes = array_keys($wedstrijdenPerRonde);
        Log::info("koppelBGroepWedstrijden: rondes=" . implode(', ', $rondes) . ", dubbelRondes=" . ($dubbelRondes ? 'ja' : 'nee'));

        $this->linkBRoundsSequentially($wedstrijdenPerRonde, $dubbelRondes);
    }

    /**
     * Walk een geordende array van B-rondes en koppel elke ronde aan de
     * volgende via volgende_wedstrijd_id / winnaar_naar_slot.
     *
     * Gedeelde kernlogica tussen koppelBGroepWedstrijden (fresh bracket)
     * en herstelBKoppelingen (repair op bestaande bracket).
     *
     * @return int Aantal succesvol gekoppelde wedstrijden
     */
    private function linkBRoundsSequentially(array $wedstrijdenPerRonde, bool $dubbelRondes): int
    {
        $rondes = array_keys($wedstrijdenPerRonde);
        $hersteld = 0;

        for ($r = 0; $r < count($rondes) - 1; $r++) {
            $huidigeRonde = $rondes[$r];
            $volgendeRonde = $rondes[$r + 1];

            $huidigeWedstrijden = $wedstrijdenPerRonde[$huidigeRonde];
            $volgendeWedstrijden = $wedstrijdenPerRonde[$volgendeRonde];

            // 1:1 mapping bij: (1) → (2) of naar b_halve_finale_2 (brons)
            $is1naar2 = $dubbelRondes && str_ends_with($huidigeRonde, '_1') && str_ends_with($volgendeRonde, '_2');
            $isBrons = $volgendeRonde === 'b_halve_finale_2';
            $useOneToOne = $is1naar2 || $isBrons;

            Log::info("Koppel {$huidigeRonde} (" . count($huidigeWedstrijden) . ") → {$volgendeRonde} (" . count($volgendeWedstrijden) . "): is1naar2={$is1naar2}, isBrons={$isBrons}");

            // Index volgendeWedstrijden op bracket_positie voor O(1) lookup
            $volgendePerPositie = [];
            foreach ($volgendeWedstrijden as $wed) {
                $volgendePerPositie[$wed->bracket_positie] = $wed;
            }

            foreach ($huidigeWedstrijden as $wedstrijd) {
                $bracketPos = $wedstrijd->bracket_positie;

                if ($useOneToOne) {
                    // 1:1 MAPPING: winnaar altijd naar WIT (A-verliezer op BLAUW)
                    $volgendeBracketPos = $bracketPos;
                    $slot = 'wit';
                } else {
                    // 2:1 MAPPING: standaard knockout
                    $volgendeBracketPos = (int) ceil($bracketPos / 2);
                    $slot = ($bracketPos % 2 === 1) ? 'wit' : 'blauw';
                }

                $volgendeWedstrijd = $volgendePerPositie[$volgendeBracketPos] ?? null;

                if ($volgendeWedstrijd) {
                    $wedstrijd->update([
                        'volgende_wedstrijd_id' => $volgendeWedstrijd->id,
                        'winnaar_naar_slot' => $slot,
                    ]);
                    $hersteld++;
                } else {
                    Log::warning("GEEN VOLGENDE GEVONDEN: Wed {$wedstrijd->id} pos {$bracketPos} zoekt pos {$volgendeBracketPos}");
                }
            }
        }

        return $hersteld;
    }

    // =========================================================================
    // A-verliezer → B-groep routing (Dubbel)
    // =========================================================================

    /**
     * Koppel A-wedstrijden aan B-wedstrijden via herkansing_wedstrijd_id
     *
     * Dit maakt de verliezer-plaatsing deterministisch (net als IJF).
     * Elke A-wedstrijd weet exact naar welke B-wedstrijd de verliezer gaat.
     */
    public function koppelAVerliezersAanB(array $aWedstrijden, array $bWedstrijden, array $params): void
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
     * @param array  $aWedstrijden Wedstrijden in deze A-ronde
     * @param array  $bWedstrijden Wedstrijden in de doel B-ronde
     * @param string $type         Type koppeling (eerste, samen_wit, samen_blauw,
     *                             samen_fairness, dubbel_blauw, brons_blauw)
     * @param int    $a1Count      Alleen voor samen_fairness: aantal a1 verliezers
     */
    public function koppelARondeAanBRonde(array $aWedstrijden, array $bWedstrijden, string $type, int $a1Count = 0): void
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
                    $bCapaciteit = count($bWedstrijden);
                    $totaalVerliezers = count($echteWedstrijden);
                    $volleWedstrijden = $totaalVerliezers - $bCapaciteit;

                    if ($volleWedstrijden <= 0) {
                        // Alle verliezers krijgen eigen B-wedstrijd (allemaal byes)
                        $bIdx = $idx;
                        $slot = 'wit';
                    } elseif ($idx < $volleWedstrijden * 2) {
                        // Eerste batch: 2:1 mapping (volle wedstrijden)
                        $bIdx = (int) floor($idx / 2);
                        $slot = ($idx % 2 === 0) ? 'wit' : 'blauw';
                    } else {
                        // Rest: 1:1 mapping op WIT (bye wedstrijden)
                        $bIdx = $volleWedstrijden + ($idx - $volleWedstrijden * 2);
                        $slot = 'wit';
                    }
                    $bWedstrijd = $bWedstrijden[$bIdx] ?? null;
                    break;

                case 'samen_wit':
                    $bWedstrijd = $bWedstrijden[$idx] ?? null;
                    $slot = 'wit';
                    break;

                case 'samen_blauw':
                    $bWedstrijd = $bWedstrijden[$idx] ?? null;
                    $slot = 'blauw';
                    break;

                case 'samen_fairness':
                    // SAMEN (a1 < a2): 4-stappen vulvolgorde (FORMULES.md §Fairness)
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
    // A-verliezer → B-groep routing (IJF)
    // =========================================================================

    /**
     * Koppel A-groep verliezers aan IJF B-groep
     */
    public function koppelIJFVerliezers(array $aWedstrijden, array $bWedstrijden): void
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
    // Bracket onderhoud
    // =========================================================================

    /**
     * Schrap lege B-wedstrijden na alle A-rondes.
     *
     * Verwijdert wedstrijden waar beide slots leeg zijn en past bracket_positie
     * aan voor correcte weergave.
     *
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
     * Hernummer bracket_positie per ronde na verwijderen wedstrijden.
     */
    public function hernummerBracketPosities(int $pouleId): void
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
     * Herstel B-groep koppelingen voor bestaande bracket.
     * Gebruik dit als de volgende_wedstrijd_id of winnaar_naar_slot fout staat.
     *
     * @see docs/2-FEATURES/ELIMINATIE/SLOT-SYSTEEM.md voor volledige documentatie
     */
    public function herstelBKoppelingen(int $pouleId): int
    {
        // Sorteer op volgorde zodat rondes in juiste sequence staan
        // (alfabetisch klopt niet: 1/8_1, 1/8_2, 1/4_1, 1/4_2, ...)
        $bWedstrijden = Wedstrijd::where('poule_id', $pouleId)
            ->where('groep', 'B')
            ->orderBy('volgorde')
            ->get();

        $wedstrijdenPerRonde = [];
        foreach ($bWedstrijden as $wed) {
            $wedstrijdenPerRonde[$wed->ronde][] = $wed;
        }

        $rondes = array_keys($wedstrijdenPerRonde);
        Log::info("herstelBKoppelingen poule {$pouleId}: rondes=" . implode(', ', $rondes));

        $dubbelRondes = collect($rondes)->contains(fn($r) => str_ends_with($r, '_1'));

        $hersteld = $this->linkBRoundsSequentially($wedstrijdenPerRonde, $dubbelRondes);

        Log::info("Hersteld {$hersteld} B-koppelingen voor poule {$pouleId}");

        return $hersteld;
    }
}
