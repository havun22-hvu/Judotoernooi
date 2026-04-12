<?php

namespace App\Services\PouleIndeling;

use App\Helpers\BandHelper;
use App\Models\Toernooi;
use Illuminate\Support\Facades\DB;

/**
 * Finds judokas that were not assigned to any pool after generation
 * and explains why.
 *
 * Extracted from PouleIndelingService so the validation/reporting logic
 * can be unit-tested and reused independently from the generation flow.
 */
class UnassignedJudokaFinder
{
    /**
     * Return unassigned judokas for a tournament with a reason per judoka.
     *
     * @return array<int, array{id:int,naam:string,leeftijdsklasse:?string,gewichtsklasse:?string,band:?string,gewicht:?float,reden:string}>
     */
    public function find(Toernooi $toernooi): array
    {
        $ingedeeldeIds = DB::table('poule_judoka')
            ->whereIn('poule_id', $toernooi->poules()->pluck('id'))
            ->pluck('judoka_id')
            ->toArray();

        return $toernooi->judokas()
            ->whereNotIn('id', $ingedeeldeIds)
            ->get(['id', 'naam', 'leeftijdsklasse', 'gewichtsklasse', 'band', 'gewicht', 'geboortejaar'])
            ->map(fn($judoka) => [
                'id' => $judoka->id,
                'naam' => $judoka->naam,
                'leeftijdsklasse' => $judoka->leeftijdsklasse,
                'gewichtsklasse' => $judoka->gewichtsklasse,
                'band' => $judoka->band,
                'gewicht' => $judoka->gewicht,
                'reden' => $this->determineReason($judoka, $toernooi),
            ])
            ->toArray();
    }

    /**
     * Determine why a specific judoka was not assigned.
     */
    public function determineReason($judoka, Toernooi $toernooi): string
    {
        $config = $toernooi->getAlleGewichtsklassen();
        $toernooiJaar = $toernooi->datum?->year ?? (int) date('Y');
        $leeftijd = $toernooiJaar - $judoka->geboortejaar;

        $leeftijdMatch = false;
        $bandMatch = false;
        $gewichtMatch = false;

        foreach ($config as $cat) {
            $maxLeeftijd = $cat['max_leeftijd'] ?? 99;
            if ($leeftijd > $maxLeeftijd) {
                continue;
            }

            $leeftijdMatch = true;

            $bandFilter = $cat['band_filter'] ?? '';
            if (empty($bandFilter) || BandHelper::pastInFilter($judoka->band, $bandFilter)) {
                $bandMatch = true;
            }

            $gewichten = $cat['gewichten'] ?? [];
            if (!empty($gewichten) && $judoka->gewicht) {
                foreach ($gewichten as $g) {
                    $klasse = (float) str_replace(['-', '+'], '', $g);
                    if (str_starts_with($g, '+')) {
                        if ($judoka->gewicht >= $klasse) {
                            $gewichtMatch = true;
                            break;
                        }
                    } else {
                        if ($judoka->gewicht <= $klasse) {
                            $gewichtMatch = true;
                            break;
                        }
                    }
                }
            } else {
                $gewichtMatch = true;
            }
        }

        if (!$leeftijdMatch) {
            return "Geen categorie voor leeftijd {$leeftijd} jaar";
        }
        if (!$bandMatch) {
            return "Geen categorie voor band '{$judoka->band}' bij deze leeftijd";
        }
        if (!$gewichtMatch) {
            return "Geen gewichtsklasse voor {$judoka->gewicht}kg";
        }

        return "Te groot gewichtsverschil met andere judoka's in de groep";
    }
}
