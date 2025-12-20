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

            $wed = Wedstrijd::create([
                'poule_id' => $poule->id,
                'judoka_wit_id' => $witId,
                'judoka_blauw_id' => $blauwId,
                'volgorde' => $volgorde++,
                'ronde' => $rondeNaam,
                'groep' => 'A',
                'bracket_positie' => $i + 1,
            ]);

            if ($witVanVoorronde) {
                $witVanVoorronde->update([
                    'volgende_wedstrijd_id' => $wed->id,
                    'winnaar_naar_slot' => 'wit',
                ]);
            }
            if ($blauwVanVoorronde) {
                $blauwVanVoorronde->update([
                    'volgende_wedstrijd_id' => $wed->id,
                    'winnaar_naar_slot' => 'blauw',
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
     * Genereer B-poule structuur met lege plekken
     * B moet naar 2 komen, daarna bronswedstrijden met A 1/2 verliezers
     */
    private function genereerBPoule(Poule $poule, int $aCount, int $doelA, int $voorrondeA): array
    {
        $wedstrijden = [];
        $volgorde = $aCount + 1;

        // Bereken B-structuur gebaseerd op bracket grootte
        // 1/8 verliezers: doelA/2 judokas → doelA/4 wedstrijden
        // Plus voorronde verliezers (gespaard naar R2)
        $achtsteVerliezers = $doelA / 2;
        $kwartVerliezers = $doelA / 4;
        $halveVerliezers = 2;

        // B-ronde 1: helft van 1/8 verliezers (bye judokas)
        $bRonde1Weds = intdiv($achtsteVerliezers, 2);

        // B-ronde 2: R1 winnaars + voorronde verliezers + rest 1/8 verliezers
        $r2Deelnemers = $bRonde1Weds + $voorrondeA + intdiv($achtsteVerliezers, 2);
        $bRonde2Weds = intdiv($r2Deelnemers, 2);

        // B-ronde 3: R2 winnaars + kwartfinale verliezers
        $r3Deelnemers = $bRonde2Weds + $kwartVerliezers;
        $bRonde3Weds = intdiv($r3Deelnemers, 2);

        $rondeNr = 0;
        $huidigeWinnaars = 0;

        // Ronde 1
        if ($bRonde1Weds > 0) {
            $rondeNr++;
            for ($i = 0; $i < $bRonde1Weds; $i++) {
                $wedstrijden[] = Wedstrijd::create([
                    'poule_id' => $poule->id,
                    'volgorde' => $volgorde++,
                    'ronde' => 'b_ronde_1',
                    'groep' => 'B',
                    'bracket_positie' => $i + 1,
                ]);
            }
            $huidigeWinnaars = $bRonde1Weds;
        }

        // Ronde 2
        if ($bRonde2Weds > 0) {
            $rondeNr++;
            for ($i = 0; $i < $bRonde2Weds; $i++) {
                $wedstrijden[] = Wedstrijd::create([
                    'poule_id' => $poule->id,
                    'volgorde' => $volgorde++,
                    'ronde' => 'b_ronde_2',
                    'groep' => 'B',
                    'bracket_positie' => $i + 1,
                ]);
            }
            $huidigeWinnaars = $bRonde2Weds;
        }

        // Ronde 3
        if ($bRonde3Weds > 0) {
            $rondeNr++;
            for ($i = 0; $i < $bRonde3Weds; $i++) {
                $wedstrijden[] = Wedstrijd::create([
                    'poule_id' => $poule->id,
                    'volgorde' => $volgorde++,
                    'ronde' => 'b_ronde_3',
                    'groep' => 'B',
                    'bracket_positie' => $i + 1,
                ]);
            }
            $huidigeWinnaars = $bRonde3Weds;
        }

        // Extra rondes om naar 2 te komen (vóór brons)
        while ($huidigeWinnaars > 2) {
            $rondeNr++;
            $aantalWedstrijden = intdiv($huidigeWinnaars, 2);

            for ($i = 0; $i < $aantalWedstrijden; $i++) {
                $wedstrijden[] = Wedstrijd::create([
                    'poule_id' => $poule->id,
                    'volgorde' => $volgorde++,
                    'ronde' => "b_ronde_{$rondeNr}",
                    'groep' => 'B',
                    'bracket_positie' => $i + 1,
                ]);
            }
            $huidigeWinnaars = $aantalWedstrijden;
        }

        // Bronswedstrijden: 2 B-winnaars + 2 A 1/2 verliezers = 2 wedstrijden
        for ($i = 0; $i < 2; $i++) {
            $wedstrijden[] = Wedstrijd::create([
                'poule_id' => $poule->id,
                'volgorde' => $volgorde++,
                'ronde' => 'b_brons',
                'groep' => 'B',
                'bracket_positie' => $i + 1,
            ]);
        }

        return $wedstrijden;
    }

    /**
     * Verwerk uitslag en zet winnaar/verliezer door
     * Winnaar direct door, verliezers wachten tot ronde compleet
     */
    public function verwerkUitslag(Wedstrijd $wedstrijd, int $winnaarId): void
    {
        // Winnaar naar volgende A-wedstrijd (direct)
        if ($wedstrijd->volgende_wedstrijd_id && $wedstrijd->winnaar_naar_slot) {
            $volgende = Wedstrijd::find($wedstrijd->volgende_wedstrijd_id);
            if ($volgende) {
                $volgende->update(["judoka_{$wedstrijd->winnaar_naar_slot}_id" => $winnaarId]);
            }
        }

        // Check of A-ronde compleet is → batch-indelen verliezers in B-poule
        if ($wedstrijd->groep === 'A' && $wedstrijd->ronde !== 'finale') {
            $this->checkEnVulBPoule($wedstrijd->poule_id, $wedstrijd->ronde);
        }

        // B-wedstrijd winnaar naar volgende B-ronde (direct)
        if ($wedstrijd->groep === 'B' && $wedstrijd->ronde !== 'b_brons') {
            $this->schuifBWinnaarDoor($wedstrijd, $winnaarId);
        }
    }

    /**
     * Check of een A-ronde compleet is en vul dan B-poule
     */
    private function checkEnVulBPoule(int $pouleId, string $ronde): void
    {
        // Check of alle wedstrijden in deze ronde gespeeld zijn
        $rondeWedstrijden = Wedstrijd::where('poule_id', $pouleId)
            ->where('groep', 'A')
            ->where('ronde', $ronde)
            ->get();

        $alleGespeeld = $rondeWedstrijden->every(fn($w) => $w->is_gespeeld);
        if (!$alleGespeeld) {
            return; // Wacht tot ronde compleet is
        }

        // Verzamel verliezers
        $verliezers = [];
        foreach ($rondeWedstrijden as $wed) {
            if ($wed->winnaar_id) {
                $verliezerId = $wed->judoka_wit_id == $wed->winnaar_id
                    ? $wed->judoka_blauw_id
                    : $wed->judoka_wit_id;

                if ($verliezerId) {
                    // Check of verliezer al in voorronde speelde
                    $speeldeVoorronde = Wedstrijd::where('poule_id', $pouleId)
                        ->where('ronde', 'voorronde')
                        ->where('groep', 'A')
                        ->where(function ($q) use ($verliezerId) {
                            $q->where('judoka_wit_id', $verliezerId)
                              ->orWhere('judoka_blauw_id', $verliezerId);
                        })
                        ->exists();

                    $verliezers[] = [
                        'id' => $verliezerId,
                        'had_bye' => !$speeldeVoorronde,
                        'ronde' => $ronde,
                    ];
                }
            }
        }

        // Sorteer: bye-judokas eerst
        usort($verliezers, fn($a, $b) => $b['had_bye'] <=> $a['had_bye']);

        // Shuffle binnen elke groep voor randomness
        $byeVerliezers = array_filter($verliezers, fn($v) => $v['had_bye']);
        $voorrondeVerliezers = array_filter($verliezers, fn($v) => !$v['had_bye']);
        shuffle($byeVerliezers);
        shuffle($voorrondeVerliezers);
        $verliezers = array_merge(array_values($byeVerliezers), array_values($voorrondeVerliezers));

        // Bepaal target B-ronde
        $bRonde = $this->getBRondeVoorARonde($ronde);
        if (!$bRonde) {
            return;
        }

        // Vul B-poule: bye-judokas eerst, dan voorronde verliezers
        foreach ($verliezers as $verliezer) {
            // Check of deze judoka al in B-poule zit
            $alInB = Wedstrijd::where('poule_id', $pouleId)
                ->where('groep', 'B')
                ->where(function ($q) use ($verliezer) {
                    $q->where('judoka_wit_id', $verliezer['id'])
                      ->orWhere('judoka_blauw_id', $verliezer['id']);
                })
                ->exists();

            if ($alInB) {
                continue;
            }

            // Bepaal ronde: bye naar primaire, voorronde-spelers naar secundaire
            $targetRonde = $verliezer['had_bye'] ? $bRonde : $this->getSecundaireBRonde($bRonde);

            // Zoek lege plek in target ronde
            $legeWedstrijd = $this->zoekLegePlek($pouleId, $targetRonde);

            // Fallback naar andere ronde als vol
            if (!$legeWedstrijd && $verliezer['had_bye']) {
                $legeWedstrijd = $this->zoekLegePlek($pouleId, $this->getSecundaireBRonde($bRonde));
            } elseif (!$legeWedstrijd) {
                $legeWedstrijd = $this->zoekLegePlek($pouleId, $bRonde);
            }

            if ($legeWedstrijd) {
                if ($legeWedstrijd->judoka_wit_id === null) {
                    $legeWedstrijd->update(['judoka_wit_id' => $verliezer['id']]);
                } else {
                    $legeWedstrijd->update(['judoka_blauw_id' => $verliezer['id']]);
                }
            }
        }
    }

    private function getBRondeVoorARonde(string $aRonde): ?string
    {
        return match ($aRonde) {
            'voorronde' => 'b_ronde_2',      // Voorronde verliezers gespaard
            'achtste_finale' => 'b_ronde_1', // 1/8 verliezers
            'kwartfinale' => 'b_ronde_3',
            'halve_finale' => 'b_brons',
            default => null,
        };
    }

    private function getSecundaireBRonde(string $bRonde): string
    {
        return match ($bRonde) {
            'b_ronde_1' => 'b_ronde_2',
            'b_ronde_2' => 'b_ronde_1',
            default => $bRonde,
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
        if (preg_match('/b_ronde_(\d+)/', $huidigeRonde, $matches)) {
            return "b_ronde_" . ((int) $matches[1] + 1);
        }
        return null;
    }
}
