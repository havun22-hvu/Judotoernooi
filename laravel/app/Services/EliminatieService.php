<?php

namespace App\Services;

use App\Models\Poule;
use App\Models\Wedstrijd;
use Illuminate\Support\Facades\DB;

/**
 * EliminatieService - Double Elimination Bracket Generator
 *
 * Systeem:
 * - A-groep: Iedereen begint hier, winnaar = goud, verliezer finale = zilver
 * - B-groep: Verliezers uit A krijgen herkansing, 2x brons
 *
 * Formules:
 * - A-groep wedstrijden = N - 1
 * - B-groep wedstrijden = N - 4
 * - Totaal = 2N - 5
 * - D = grootste macht van 2 <= N
 *
 * A-groep structuur (29 judoka's voorbeeld):
 * - 1/16: 13 wedstrijden (26 vechten) + 3 byes = 16 door naar 1/8
 * - 1/8:  8 wedstrijden (16 judoka's) = 8 door naar 1/4
 * - 1/4:  4 wedstrijden = 4 door naar 1/2
 * - 1/2:  2 wedstrijden = 2 door naar finale
 * - F:    1 wedstrijd = goud + zilver
 * - Totaal: 13+8+4+2+1 = 28 = N-1 ✓
 */
class EliminatieService
{
    /**
     * Genereer complete eliminatie bracket (A + B groep)
     */
    public function genereerBracket(Poule $poule, array $judokaIds): array
    {
        // Verwijder bestaande wedstrijden
        $poule->wedstrijden()->delete();

        $n = count($judokaIds);
        if ($n < 2) {
            return ['totaal_wedstrijden' => 0];
        }

        DB::transaction(function () use ($poule, $judokaIds, $n) {
            // Genereer A-groep bracket
            $this->genereerAGroep($poule, $judokaIds);

            // Genereer B-groep bracket (lege slots, worden gevuld door verliezers)
            if ($n >= 5) {
                $this->genereerBGroep($poule, $n);
            }
        });

        return [
            'totaal_wedstrijden' => $poule->wedstrijden()->count(),
            'a_wedstrijden' => $n - 1,
            'b_wedstrijden' => max(0, $n - 4),
        ];
    }

    /**
     * Genereer A-groep (Winners Bracket)
     *
     * Voor 29 judoka's:
     * - D = 16 (doel voor 1/8)
     * - 1/16 finale: 29-16 = 13 wedstrijden, 3 byes
     * - Daarna: 1/8 (8), 1/4 (4), 1/2 (2), F (1)
     */
    private function genereerAGroep(Poule $poule, array $judokaIds): void
    {
        $n = count($judokaIds);
        $d = $this->berekenDoel($n);  // Grootste macht van 2 <= n

        // Bereken eerste ronde
        $eersteRondeWedstrijden = $n - $d;  // Wedstrijden nodig om naar D te komen
        $aantalByes = $d - $eersteRondeWedstrijden;  // = 2D - N

        // Bepaal eerste ronde naam op basis van N (niet D!)
        $eersteRonde = $this->getEersteRondeNaam($n);

        // Shuffle judoka's voor willekeurige verdeling
        shuffle($judokaIds);

        // Verdeel: eerst de judoka's die moeten vechten, dan byes
        $wedstrijdJudokas = array_slice($judokaIds, 0, $eersteRondeWedstrijden * 2);
        $byeJudokas = array_slice($judokaIds, $eersteRondeWedstrijden * 2);

        $volgorde = 1;
        $wedstrijdenPerRonde = [];

        // === EERSTE RONDE (1/16 voor N>16, 1/8 voor N>8, etc.) ===
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
        $huidigeAantal = $d;  // Na eerste ronde hebben we D judoka's

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
    }

    /**
     * Bepaal eerste ronde naam op basis van aantal judoka's
     */
    private function getEersteRondeNaam(int $n): string
    {
        if ($n > 16) return 'zestiende_finale';  // Meer dan 16 → start bij 1/16
        if ($n > 8) return 'achtste_finale';     // 9-16 → start bij 1/8
        if ($n > 4) return 'kwartfinale';        // 5-8 → start bij 1/4
        if ($n > 2) return 'halve_finale';       // 3-4 → start bij 1/2
        return 'finale';                          // 2 → alleen finale
    }

    /**
     * Bepaal ronde naam voor aantal deelnemers IN die ronde
     * 16 deelnemers = 1/8 finale (8 wedstrijden)
     */
    private function getRondeNaamVoorAantal(int $aantalDeelnemers): string
    {
        return match ($aantalDeelnemers) {
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
            $eersteRonde = $rondes[0];
            $tweedeRonde = $rondes[1];
            $tweedeRondeWedstrijden = $wedstrijdenPerRonde[$tweedeRonde];
            $eersteRondeWedstrijden = $wedstrijdenPerRonde[$eersteRonde];
            $eersteRondeAantal = count($eersteRondeWedstrijden);

            // Bye judoka's vullen de "lege" posities in tweede ronde
            // Positie $eersteRondeAantal t/m ($eersteRondeAantal + $aantalByes - 1)
            $byeIdx = 0;
            $tweedeRondeAantal = count($tweedeRondeWedstrijden);

            // Bereken welke slots in tweede ronde NIET gevuld worden door eerste ronde
            // Eerste ronde wedstrijd i → tweede ronde positie floor(i/2), slot i%2
            $gevuldeSlots = [];
            foreach ($eersteRondeWedstrijden as $idx => $wed) {
                $tweedePos = floor($idx / 2);
                $gevuldeSlots[$tweedePos][] = ($idx % 2 == 0) ? 'wit' : 'blauw';
            }

            // Vul lege slots met bye judoka's
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

    /**
     * Genereer B-groep (Losers Bracket / Herkansing)
     *
     * B-groep structuur hangt af van N:
     * - N >= 17: B heeft 1/8 rondes (instroom >= 16)
     * - N >= 9:  B heeft 1/4 rondes
     * - N >= 5:  B bestaat (minimaal voor brons)
     */
    private function genereerBGroep(Poule $poule, int $n): void
    {
        $d = $this->berekenDoel($n);
        $volgorde = 1000;

        // Bereken instroom naar B
        $verliezersEersteRonde = $n - $d;  // Uit A 1/16 (of eerste ronde)
        $verliezersAchtste = ($d >= 16) ? 8 : ($d >= 8 ? $d / 2 : 0);
        $verliezersKwart = 4;
        $verliezersHalf = 2;

        // Bepaal B-groep structuur
        $rondes = [];

        // Eerste instroom naar B = verliezers eerste A-ronde
        $eersteInstroom = $verliezersEersteRonde;

        // B-start: als eerste instroom niet macht van 2 is
        $bStartDoel = $this->berekenDoel($eersteInstroom);
        if ($bStartDoel < $eersteInstroom && $eersteInstroom > 0) {
            $bStartWedstrijden = $eersteInstroom - $bStartDoel;
            if ($bStartWedstrijden > 0) {
                $rondes[] = ['naam' => 'b_start', 'wedstrijden' => $bStartWedstrijden];
            }
            $eersteInstroom = $bStartDoel;  // Na b_start
        }

        // B 1/8 rondes (alleen als we genoeg judoka's hebben)
        // 1/8(1): B onderling, 1/8(2): + A 1/8 verliezers
        if ($eersteInstroom >= 8 && $d >= 16) {
            // 1/8(1): eerste instroom / 2 wedstrijden
            $rondes[] = ['naam' => 'b_achtste_finale', 'wedstrijden' => $eersteInstroom / 2];
            // Na 1/8(1): eersteInstroom/2 winnaars + verliezersAchtste nieuwe
            // 1/8(2): combineert deze
            $rondes[] = ['naam' => 'b_achtste_finale_2', 'wedstrijden' => max(4, ($eersteInstroom / 2 + $verliezersAchtste) / 2)];
        }

        // B 1/4 rondes
        $rondes[] = ['naam' => 'b_kwartfinale_1', 'wedstrijden' => 4];
        $rondes[] = ['naam' => 'b_kwartfinale_2', 'wedstrijden' => 4];

        // B 1/2 rondes (1/2(2) = brons wedstrijden)
        $rondes[] = ['naam' => 'b_halve_finale_1', 'wedstrijden' => 2];
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
     * Koppel B-groep wedstrijden
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
    public function berekenStatistieken(int $n): array
    {
        $d = $this->berekenDoel($n);

        return [
            'judokas' => $n,
            'doel' => $d,
            'a_wedstrijden' => $n - 1,
            'b_wedstrijden' => max(0, $n - 4),
            'totaal_wedstrijden' => max(0, 2 * $n - 5),
            'eerste_ronde' => $this->getEersteRondeNaam($n),
            'eerste_ronde_wedstrijden' => $n - $d,
            'byes' => $d - ($n - $d),  // = 2D - N
        ];
    }

    /**
     * Verwerk uitslag van een wedstrijd
     * Handelt correcties en verliezer naar B-groep af
     *
     * LET OP: Winnaar wordt NIET geplaatst hier - dat doet de MatController al via drag-drop!
     *
     * @param Wedstrijd $wedstrijd De gespeelde wedstrijd
     * @param int $winnaarId ID van de winnaar
     * @param int|null $oudeWinnaarId Vorige winnaar (voor correcties)
     * @return array Correcties die zijn uitgevoerd
     */
    public function verwerkUitslag(Wedstrijd $wedstrijd, int $winnaarId, ?int $oudeWinnaarId = null): array
    {
        $correcties = [];

        // Bepaal verliezer
        $verliezerId = ($wedstrijd->judoka_wit_id == $winnaarId)
            ? $wedstrijd->judoka_blauw_id
            : $wedstrijd->judoka_wit_id;

        // Als er een oude winnaar was, moet die gecorrigeerd worden
        if ($oudeWinnaarId && $oudeWinnaarId != $winnaarId) {
            // Verwijder oude winnaar uit volgende wedstrijd
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
        }

        // NIET de winnaar plaatsen - dat doet MatController al!
        // De drag-drop actie plaatst de winnaar in het juiste vak.

        // Verliezer naar B-groep (alleen bij A-groep wedstrijden)
        if ($wedstrijd->groep === 'A' && $verliezerId) {
            $this->plaatsVerliezerInBGroep($wedstrijd, $verliezerId);
        }

        return $correcties;
    }

    /**
     * Plaats verliezer in B-groep
     */
    private function plaatsVerliezerInBGroep(Wedstrijd $wedstrijd, int $verliezerId): void
    {
        $poule = $wedstrijd->poule;

        // Bepaal target B-ronde op basis van A-ronde
        $targetRonde = match ($wedstrijd->ronde) {
            'zestiende_finale' => 'b_start',
            'achtste_finale' => 'b_achtste_finale_2',
            'kwartfinale' => 'b_kwartfinale_2',
            'halve_finale' => 'b_brons',
            default => 'b_start',
        };

        // Zoek eerste lege slot in target ronde
        $bWedstrijd = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'B')
            ->where('ronde', $targetRonde)
            ->where(function ($q) {
                $q->whereNull('judoka_wit_id')
                  ->orWhereNull('judoka_blauw_id');
            })
            ->orderBy('bracket_positie')
            ->first();

        // Fallback naar andere B-ronde als target vol is
        if (!$bWedstrijd) {
            $bWedstrijd = Wedstrijd::where('poule_id', $poule->id)
                ->where('groep', 'B')
                ->where(function ($q) {
                    $q->whereNull('judoka_wit_id')
                      ->orWhereNull('judoka_blauw_id');
                })
                ->orderBy('volgorde')
                ->first();
        }

        if ($bWedstrijd) {
            $slot = is_null($bWedstrijd->judoka_wit_id) ? 'judoka_wit_id' : 'judoka_blauw_id';
            $bWedstrijd->update([$slot => $verliezerId]);
        }
    }

    /**
     * Verwijder judoka uit B-groep wedstrijden
     */
    public function verwijderUitB(int $pouleId, int $judokaId): void
    {
        // Zoek alle B-groep wedstrijden waar deze judoka in staat
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
