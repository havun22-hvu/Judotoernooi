<?php

namespace App\Services\Eliminatie;

use App\Models\Wedstrijd;
use Illuminate\Support\Facades\Log;

/**
 * WinnerCalculator - Winner / Bye / Loser progression helper
 *
 * Handles everything around determining what happens to winners and losers
 * of a match within an elimination bracket:
 *  - placing losers into the B-bracket (Dubbel + IJF)
 *  - bye detection and fairness (judoka's that skip a round)
 *  - cascade removal of a judoka from later rounds when a result is corrected
 *  - slot finding (first empty B-slot, slot with existing opponent, ...)
 *
 * Extracted from EliminatieService (phase 2) so the winner/bye logic can be
 * tested, reasoned about and reused independently from bracket generation.
 */
class WinnerCalculator
{
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
    public function plaatsVerliezerDubbel(Wedstrijd $wedstrijd, int $verliezerId): void
    {
        // === NIEUW: Deterministische plaatsing via herkansing_wedstrijd_id ===
        // Net als IJF systeem: bij generatie al gekoppeld
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
     * Plaats verliezer in B-groep (IJF Repechage)
     * Alleen kwartfinale en halve finale verliezers krijgen herkansing.
     */
    public function plaatsVerliezerIJF(Wedstrijd $wedstrijd, int $verliezerId): void
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
     * Bepaal naar welke B-ronde een A-verliezer moet.
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
    public function bepaalBRondeVoorVerliezer(int $pouleId, string $aRonde): ?string
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
     * Vind de B-start ronde voor een poule.
     */
    public function vindBStartRonde(int $pouleId): ?string
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
     * Zoek slot waar al een tegenstander staat (voor bye-judoka's).
     */
    public function zoekSlotMetTegenstander(int $pouleId, string $ronde): ?Wedstrijd
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
     * Zoek beschikbaar B-slot met RANDOM verdeling.
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
    public function zoekEersteLegeBSlot(int $pouleId, string $ronde): ?Wedstrijd
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
     * Check of judoka al een bye heeft gehad in deze poule.
     */
    public function heeftByeGehad(int $pouleId, int $judokaId): bool
    {
        return Wedstrijd::where('poule_id', $pouleId)
            ->where('uitslag_type', 'bye')
            ->where('winnaar_id', $judokaId)
            ->exists();
    }

    /**
     * Verwijder judoka uit B-groep wedstrijden.
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
