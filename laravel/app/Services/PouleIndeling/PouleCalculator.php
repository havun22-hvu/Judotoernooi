<?php

namespace App\Services\PouleIndeling;

use Illuminate\Support\Collection;

/**
 * Pure calculation helpers for pool division math.
 *
 * Stateless math only — no database, model, or framework access.
 * Extracted from PouleIndelingService so the rules can be unit-tested
 * in isolation and reused from other pool-aware code.
 */
class PouleCalculator
{
    /**
     * Calculate how many places qualify for kruisfinale based on number of poules.
     * Goal: kruisfinale of 4-6 judokas (ideal pool size).
     *
     * 2 poules → top 3 (6 judokas)
     * 3 poules → top 2 (6 judokas)
     * 4+ poules → top 1 (4+ judokas)
     */
    public function kruisfinalePlaatsen(int $aantalPoules): int
    {
        if ($aantalPoules <= 2) {
            return 3;
        }
        if ($aantalPoules === 3) {
            return 2;
        }
        return 1;
    }

    /**
     * Calculate number of matches for a given number of judokas (round-robin).
     * Special case: 3 judokas uses double round (6 matches).
     */
    public function aantalWedstrijden(int $aantal): int
    {
        if ($aantal <= 1) {
            return 0;
        }
        if ($aantal === 3) {
            return 6;
        }
        return intval(($aantal * ($aantal - 1)) / 2);
    }

    /**
     * Calculate score for a division based on preference order.
     * Lower score = better division.
     *
     * Uses exponential scoring so 2x preferred size beats 1x less preferred size:
     * 2 pools of position 2 (score 2*3=6) beats 1 pool of position 3 (score 7).
     *
     * @param  int[]  $pouleGroottes  Pool sizes to score.
     * @param  int[]  $voorkeur       Preference list (lower index = preferred).
     */
    public function verdelingScore(array $pouleGroottes, array $voorkeur): int
    {
        $score = 0;

        foreach ($pouleGroottes as $grootte) {
            $positie = array_search($grootte, $voorkeur);

            if ($positie === false) {
                // Size not in preference list - heavy penalty
                $score += 1000;
            } else {
                // Exponential scoring: 2^(position+1) - 1
                $score += pow(2, $positie + 1) - 1;
            }
        }

        return $score;
    }

    /**
     * Create optimal pool division based on preference order.
     * Uses the configured preference list (e.g., [5, 4, 6, 3]) to score divisions.
     *
     * @param  Collection  $judokas     Judokas sorted by the caller.
     * @param  int         $minJudokas  Minimum judokas per pool.
     * @param  int         $maxJudokas  Maximum judokas per pool.
     * @param  int[]       $voorkeur    Preference list for pool sizes.
     * @return array<int, array>        List of pools, each pool is an ordered array of judokas.
     */
    public function optimalePoules(Collection $judokas, int $minJudokas, int $maxJudokas, array $voorkeur): array
    {
        $aantal = $judokas->count();
        $judokasArray = $judokas->values()->all();

        if ($aantal <= $minJudokas) {
            return [$judokasArray];
        }

        $bestePouleGroottes = [];
        $besteScore = PHP_INT_MAX;

        if ($aantal >= $minJudokas && $aantal <= $maxJudokas) {
            $bestePouleGroottes = [$aantal];
            $besteScore = $this->verdelingScore([$aantal], $voorkeur);
        }

        $maxPoules = (int) floor($aantal / $minJudokas);

        for ($aantalPoules = 2; $aantalPoules <= $maxPoules; $aantalPoules++) {
            $basisGrootte = (int) floor($aantal / $aantalPoules);
            $rest = $aantal % $aantalPoules;

            $pouleGroottes = array_fill(0, $aantalPoules, $basisGrootte);
            for ($i = 0; $i < $rest; $i++) {
                $pouleGroottes[$i]++;
            }

            $valid = true;
            foreach ($pouleGroottes as $grootte) {
                if ($grootte < $minJudokas || $grootte > $maxJudokas) {
                    $valid = false;
                    break;
                }
            }
            if (!$valid) {
                continue;
            }

            $score = $this->verdelingScore($pouleGroottes, $voorkeur);

            if ($score < $besteScore) {
                $besteScore = $score;
                $bestePouleGroottes = $pouleGroottes;
            }
        }

        if (empty($bestePouleGroottes)) {
            return [$judokasArray];
        }

        $verdeling = [];
        $index = 0;
        foreach ($bestePouleGroottes as $grootte) {
            $verdeling[] = array_slice($judokasArray, $index, $grootte);
            $index += $grootte;
        }

        return $verdeling;
    }
}
