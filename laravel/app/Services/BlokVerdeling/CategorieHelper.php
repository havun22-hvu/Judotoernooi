<?php

namespace App\Services\BlokVerdeling;

use App\Models\Toernooi;
use Illuminate\Support\Collection;

/**
 * Helper for category grouping and sorting
 */
class CategorieHelper
{
    /**
     * Get "grote" leeftijdsklassen (primary structure)
     * Based on gender: M, gemengd, or no gender = groot
     */
    public function getGroteLeeftijden(Toernooi $toernooi): array
    {
        return $this->filterLeeftijdenOpGeslacht($toernooi, false);
    }

    /**
     * Get "kleine" leeftijdsklassen (used as filler)
     * Based on gender: V = klein
     */
    public function getKleineLeeftijden(Toernooi $toernooi): array
    {
        return $this->filterLeeftijdenOpGeslacht($toernooi, true);
    }

    /**
     * Filter age classes by gender
     *
     * @param bool $vrouwelijk True = V only, False = M/gemengd
     */
    private function filterLeeftijdenOpGeslacht(Toernooi $toernooi, bool $vrouwelijk): array
    {
        $config = $toernooi->getAlleGewichtsklassen();
        $result = [];

        foreach ($config as $key => $data) {
            $geslacht = $data['geslacht'] ?? 'gemengd';
            $isVrouwelijk = ($geslacht === 'V');

            if ($isVrouwelijk === $vrouwelijk) {
                $result[] = $data['label'] ?? $key;
            }
        }

        return $result;
    }

    /**
     * Get all categories with their block assignment
     */
    public function getCategoriesMetToewijzing(Toernooi $toernooi): Collection
    {
        return $toernooi->poules()
            ->reorder()
            ->select('leeftijdsklasse', 'gewichtsklasse', 'blok_id', 'blok_vast')
            ->selectRaw('SUM(aantal_wedstrijden) as wedstrijden')
            ->groupBy('leeftijdsklasse', 'gewichtsklasse', 'blok_id', 'blok_vast')
            ->orderBy('leeftijdsklasse')
            ->orderBy('gewichtsklasse')
            ->get()
            ->groupBy(fn($p) => $p->leeftijdsklasse . '|' . $p->gewichtsklasse)
            ->map(fn($g) => [
                'leeftijd' => $g->first()->leeftijdsklasse,
                'gewicht' => $g->first()->gewichtsklasse,
                'gewicht_num' => $this->parseGewicht($g->first()->gewichtsklasse),
                'wedstrijden' => $g->sum('wedstrijden'),
                'blok_id' => $g->first()->blok_id,
                'blok_vast' => (bool) $g->first()->blok_vast,
            ]);
    }

    /**
     * Group categories by age class, sort weights ascending
     */
    public function groepeerPerLeeftijd(Collection $categories): array
    {
        $perLeeftijd = [];

        foreach ($categories as $cat) {
            $lk = $cat['leeftijd'];
            if (!isset($perLeeftijd[$lk])) {
                $perLeeftijd[$lk] = [];
            }
            $perLeeftijd[$lk][] = $cat;
        }

        // Sort each group by weight
        foreach ($perLeeftijd as $lk => &$cats) {
            usort($cats, fn($a, $b) => $a['gewicht_num'] <=> $b['gewicht_num']);
        }

        return $perLeeftijd;
    }

    /**
     * Parse weight class to numeric value for sorting
     * -50 = up to 50kg, +50 = over 50kg
     */
    public function parseGewicht(string $gewichtsklasse): float
    {
        if (preg_match('/([+-]?)(\d+)/', $gewichtsklasse, $match)) {
            $sign = $match[1];
            $num = (int) $match[2];
            return $sign === '+' ? $num + 1000 : $num;
        }
        return 999;
    }

    /**
     * Get pinned block numbers per age class
     */
    public function getVastgezetteBloknummersPerLeeftijd(Collection $alleCategorieen, array $blokken): array
    {
        $result = [];

        foreach ($alleCategorieen as $cat) {
            if (!$cat['blok_vast'] || !$cat['blok_id']) continue;

            $blokNummer = null;
            foreach ($blokken as $blok) {
                if ($blok->id == $cat['blok_id']) {
                    $blokNummer = $blok->nummer;
                    break;
                }
            }

            if ($blokNummer) {
                $leeftijd = $cat['leeftijd'];
                if (!isset($result[$leeftijd])) {
                    $result[$leeftijd] = [];
                }
                $result[$leeftijd][] = $blokNummer;
            }
        }

        return $result;
    }

    /**
     * Split categories into fixed (max_kg=0) and variable (max_kg>0)
     *
     * @return array [$vasteCategorieen, $variabelePoules]
     */
    public function splitsCategorieenOpType(Toernooi $toernooi, $variabeleService): array
    {
        $config = $toernooi->getAlleGewichtsklassen();

        $variabeleKeys = [];
        $vasteKeys = [];

        foreach ($config as $key => $data) {
            $maxKgVerschil = (float) ($data['max_kg_verschil'] ?? 0);
            $maxLftVerschil = (int) ($data['max_leeftijd_verschil'] ?? 0);
            $label = $data['label'] ?? $key;

            if ($maxKgVerschil > 0 || $maxLftVerschil > 0) {
                $variabeleKeys[$label] = true;
            } else {
                $vasteKeys[$label] = true;
            }
        }

        $alleCategorieen = $this->getCategoriesMetToewijzing($toernooi);

        $vaste = $alleCategorieen->filter(function ($cat) use ($vasteKeys) {
            return isset($vasteKeys[$cat['leeftijd']]) && !$cat['blok_vast'];
        });

        $alleVariabelePoules = $variabeleService->getVariabelePoules($toernooi);
        $variabele = $alleVariabelePoules->filter(function ($poule) use ($variabeleKeys) {
            return isset($variabeleKeys[$poule['leeftijdsklasse']]);
        });

        return [$vaste, $variabele];
    }
}
