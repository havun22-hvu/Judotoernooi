<?php

namespace App\Services;

use App\Enums\Leeftijdsklasse;
use App\Models\Judoka;
use App\Models\Poule;
use App\Models\Toernooi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PouleIndelingService
{
    private int $minJudokas;
    private int $optimalJudokas;
    private int $maxJudokas;

    public function __construct()
    {
        $this->minJudokas = config('toernooi.min_judokas_poule', 3);
        $this->optimalJudokas = config('toernooi.optimal_judokas_poule', 5);
        $this->maxJudokas = config('toernooi.max_judokas_poule', 6);
    }

    /**
     * Generate pool division for a tournament
     */
    public function genereerPouleIndeling(Toernooi $toernooi): array
    {
        return DB::transaction(function () use ($toernooi) {
            // Delete existing pools
            $toernooi->poules()->delete();

            // Get all judokas grouped by category
            $groepen = $this->groepeerJudokas($toernooi);

            $pouleNummer = 1;
            $statistieken = [
                'totaal_poules' => 0,
                'totaal_wedstrijden' => 0,
                'per_leeftijdsklasse' => [],
            ];

            foreach ($groepen as $sleutel => $judokas) {
                if ($judokas->isEmpty()) continue;

                $eersteJudoka = $judokas->first();
                $leeftijdsklasse = $eersteJudoka->leeftijdsklasse;
                $gewichtsklasse = $eersteJudoka->gewichtsklasse;

                // Split into optimal pools
                $pouleVerdelingen = $this->maakOptimalePoules($judokas);

                foreach ($pouleVerdelingen as $pouleJudokas) {
                    $titel = $this->maakPouleTitel($leeftijdsklasse, $gewichtsklasse, $pouleNummer);

                    $poule = Poule::create([
                        'toernooi_id' => $toernooi->id,
                        'nummer' => $pouleNummer,
                        'titel' => $titel,
                        'leeftijdsklasse' => $leeftijdsklasse,
                        'gewichtsklasse' => $gewichtsklasse,
                        'aantal_judokas' => count($pouleJudokas),
                    ]);

                    // Attach judokas to pool
                    $positie = 1;
                    foreach ($pouleJudokas as $judoka) {
                        $poule->judokas()->attach($judoka->id, ['positie' => $positie++]);
                    }

                    // Calculate matches
                    $poule->updateStatistieken();

                    $statistieken['totaal_poules']++;
                    $statistieken['totaal_wedstrijden'] += $poule->aantal_wedstrijden;

                    if (!isset($statistieken['per_leeftijdsklasse'][$leeftijdsklasse])) {
                        $statistieken['per_leeftijdsklasse'][$leeftijdsklasse] = [
                            'poules' => 0,
                            'wedstrijden' => 0,
                        ];
                    }
                    $statistieken['per_leeftijdsklasse'][$leeftijdsklasse]['poules']++;
                    $statistieken['per_leeftijdsklasse'][$leeftijdsklasse]['wedstrijden'] += $poule->aantal_wedstrijden;

                    $pouleNummer++;
                }
            }

            $toernooi->update(['poules_gegenereerd_op' => now()]);

            return $statistieken;
        });
    }

    /**
     * Group judokas by age class, weight class, and (for -15+) gender
     */
    private function groepeerJudokas(Toernooi $toernooi): Collection
    {
        $judokas = $toernooi->judokas()
            ->orderBy('judoka_code')
            ->get();

        return $judokas->groupBy(function (Judoka $judoka) {
            $leeftijdCode = substr($judoka->judoka_code, 0, 2);
            $gewichtCode = substr($judoka->judoka_code, 2, 2);
            $geslachtCode = substr($judoka->judoka_code, 5, 1);

            // For -15 and older, separate by gender
            if (in_array($leeftijdCode, ['15', '18', '21'])) {
                $sleutel = "{$leeftijdCode}-{$gewichtCode}-{$geslachtCode}";
            } else {
                $sleutel = "{$leeftijdCode}-{$gewichtCode}";
            }

            // Handle +/- weight classes separately
            $gewichtsklasse = $judoka->gewichtsklasse;
            if (str_contains($gewichtsklasse, '+')) {
                $sleutel .= '-P';
            } elseif (str_contains($gewichtsklasse, '-')) {
                $sleutel .= '-M';
            }

            return $sleutel;
        });
    }

    /**
     * Create optimal pool division
     * Aims for pools of optimal size (5), avoids pools of 1-2
     */
    private function maakOptimalePoules(Collection $judokas): array
    {
        $aantal = $judokas->count();
        $judokasArray = $judokas->values()->all();

        // Less than minimum: single pool
        if ($aantal <= $this->minJudokas) {
            return [$judokasArray];
        }

        // Up to optimal+1: single pool
        if ($aantal <= $this->optimalJudokas + 1) {
            return [$judokasArray];
        }

        // Special cases for common numbers
        if ($aantal === 7) return [array_slice($judokasArray, 0, 3), array_slice($judokasArray, 3)];
        if ($aantal === 8) return [array_slice($judokasArray, 0, 4), array_slice($judokasArray, 4)];
        if ($aantal === 9) return [array_slice($judokasArray, 0, 4), array_slice($judokasArray, 4)];
        if ($aantal === 10) return [array_slice($judokasArray, 0, 5), array_slice($judokasArray, 5)];

        // Find best division for larger numbers
        $besteVerdeling = [];
        $besteScore = PHP_INT_MAX;

        $maxPoules = (int) floor($aantal / $this->minJudokas);

        for ($aantalPoules = 2; $aantalPoules <= $maxPoules; $aantalPoules++) {
            $basisGrootte = (int) floor($aantal / $aantalPoules);
            $rest = $aantal % $aantalPoules;

            $score = 0;
            $pouleGroottes = array_fill(0, $aantalPoules, $basisGrootte);

            for ($i = 0; $i < $rest; $i++) {
                $pouleGroottes[$i]++;
            }

            foreach ($pouleGroottes as $grootte) {
                if ($grootte <= 2) {
                    $score += 1000; // Heavy penalty for too small
                } elseif ($grootte === 3) {
                    $score += 100;
                } elseif ($grootte >= 7) {
                    $score += 50 + ($grootte - 7) * 20;
                } else {
                    $score += abs($grootte - $this->optimalJudokas) * 10;
                }
            }

            if ($score < $besteScore) {
                $besteScore = $score;
                $besteVerdeling = [];
                $index = 0;
                for ($i = 0; $i < $aantalPoules; $i++) {
                    $pouleGrootte = $basisGrootte + ($i < $rest ? 1 : 0);
                    $besteVerdeling[] = array_slice($judokasArray, $index, $pouleGrootte);
                    $index += $pouleGrootte;
                }
            }
        }

        return empty($besteVerdeling) ? [$judokasArray] : $besteVerdeling;
    }

    /**
     * Create standardized pool title
     */
    private function maakPouleTitel(string $leeftijdsklasse, string $gewichtsklasse, int $pouleNr): string
    {
        $lk = $leeftijdsklasse ?: 'Onbekend';
        $gk = $gewichtsklasse ?: 'Onbekend gewicht';

        // Format weight class
        if (!str_contains($gk, 'kg')) {
            $gk .= ' kg';
        }

        return "{$lk} {$gk} Poule {$pouleNr}";
    }

    /**
     * Move judoka to different pool
     */
    public function verplaatsJudoka(Judoka $judoka, Poule $nieuwePoule): void
    {
        DB::transaction(function () use ($judoka, $nieuwePoule) {
            // Remove from current pool(s)
            $huidigePoules = $judoka->poules;
            foreach ($huidigePoules as $poule) {
                $poule->judokas()->detach($judoka->id);
                $poule->updateStatistieken();
            }

            // Add to new pool
            $positie = $nieuwePoule->judokas()->count() + 1;
            $nieuwePoule->judokas()->attach($judoka->id, ['positie' => $positie]);
            $nieuwePoule->updateStatistieken();

            // Update judoka's weight class if needed
            if ($judoka->gewichtsklasse !== $nieuwePoule->gewichtsklasse) {
                $judoka->update(['gewichtsklasse' => $nieuwePoule->gewichtsklasse]);
            }
        });
    }

    /**
     * Calculate total matches for tournament
     */
    public function berekenTotaalWedstrijden(Toernooi $toernooi): int
    {
        return $toernooi->poules()->sum('aantal_wedstrijden');
    }
}
