<?php

namespace App\Services\Eliminatie;

/**
 * Pure calculation helpers for elimination brackets.
 *
 * Stateless math only — no database, model, or framework access.
 * Kept separate from EliminatieService so it can be unit-tested
 * in isolation and reused from other bracket-aware services.
 */
class BracketCalculator
{
    /**
     * Bereken locatie_wit en locatie_blauw op basis van bracket_positie.
     *
     * Slot formules (wedstrijd N):
     *  - slot_wit   = 2N - 1 (oneven)
     *  - slot_blauw = 2N     (even)
     */
    public function berekenLocaties(int $bracketPositie): array
    {
        return [
            'locatie_wit' => ($bracketPositie - 1) * 2 + 1,
            'locatie_blauw' => ($bracketPositie - 1) * 2 + 2,
        ];
    }

    /**
     * Bepaal ronde naam op basis van aantal judoka's of deelnemers.
     *
     * @param int  $n          Aantal judoka's of deelnemers in ronde
     * @param bool $voorAantal True = deelnemers in ronde, False = totaal judoka's
     */
    public function getRondeNaam(int $n, bool $voorAantal = false): string
    {
        if ($voorAantal) {
            return match ($n) {
                32 => 'zestiende_finale',
                16 => 'achtste_finale',
                8 => 'kwartfinale',
                4 => 'halve_finale',
                2 => 'finale',
                default => 'achtste_finale',
            };
        }

        if ($n > 32) return 'tweeendertigste_finale';
        if ($n > 16) return 'zestiende_finale';
        if ($n > 8) return 'achtste_finale';
        if ($n > 4) return 'kwartfinale';
        if ($n > 2) return 'halve_finale';
        return 'finale';
    }

    /**
     * Get B-ronde naam voor aantal wedstrijden.
     */
    public function getBRondeNaam(int $wedstrijden): string
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
     * Bereken alle bracket parameters in één keer.
     *
     * @return array{d:int, v1:int, a1Verliezers:int, a2Verliezers:int, eersteGolf:int, dubbelRondes:bool}
     */
    public function berekenBracketParams(int $n): array
    {
        $d = $this->berekenDoel($n);
        $v1 = $n - $d;

        if ($v1 > 0) {
            $a1Verliezers = $v1;
            $a2Verliezers = (int)($d / 2);
        } else {
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
     * Bereken doel (grootste macht van 2 <= n).
     */
    public function berekenDoel(int $n): int
    {
        if ($n <= 0) return 0;
        if ($n == 1) return 1;
        return pow(2, floor(log($n, 2)));
    }

    /**
     * Bereken minimale B-wedstrijden voor gegeven aantal verliezers.
     */
    public function berekenMinimaleBWedstrijden(int $verliezers): int
    {
        if ($verliezers <= 4) return 2;
        if ($verliezers <= 8) return 4;
        if ($verliezers <= 16) return 8;
        if ($verliezers <= 32) return 16;
        return 32;
    }

    /**
     * Bereken statistieken voor bracket.
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
}
