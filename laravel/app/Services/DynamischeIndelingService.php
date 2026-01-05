<?php

namespace App\Services;

use App\Models\Judoka;
use App\Models\Toernooi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DynamischeIndelingService
{
    /**
     * Band volgorde (0 = wit, 6 = zwart)
     */
    private const BAND_VOLGORDE = [
        'wit' => 0,
        'geel' => 1,
        'oranje' => 2,
        'groen' => 3,
        'blauw' => 4,
        'bruin' => 5,
        'zwart' => 6,
    ];

    /**
     * Default configuratie
     */
    private array $config = [
        'max_leeftijd_verschil' => 2,
        'max_kg_verschil' => 3.0,
        'poule_grootte_voorkeur' => [5, 4, 6, 3],
        'weight_leeftijd' => 0.4,
        'weight_gewicht' => 0.4,
        'weight_band' => 0.2,
    ];

    /**
     * Genereer varianten voor dynamische indeling
     */
    public function genereerVarianten(
        Collection $judokas,
        array $config = []
    ): array {
        $this->config = array_merge($this->config, $config);

        $startTime = microtime(true);
        $varianten = [];

        // Test verschillende parameters
        $testParams = [
            ['max_kg' => 3.0, 'max_leeftijd' => 2],
            ['max_kg' => 2.5, 'max_leeftijd' => 2],
            ['max_kg' => 3.5, 'max_leeftijd' => 2],
            ['max_kg' => 3.0, 'max_leeftijd' => 3],
            ['max_kg' => 2.0, 'max_leeftijd' => 2],
        ];

        foreach ($testParams as $params) {
            $variant = $this->berekenIndeling(
                $judokas,
                $params['max_leeftijd'],
                $params['max_kg']
            );
            $varianten[] = $variant;
        }

        // Sorteer op score (laagste eerst)
        usort($varianten, fn($a, $b) => $a['score'] <=> $b['score']);

        // Neem top 5
        $varianten = array_slice($varianten, 0, 5);

        $elapsed = round((microtime(true) - $startTime) * 1000);

        Log::info('DynamischeIndeling varianten gegenereerd', [
            'judokas' => $judokas->count(),
            'varianten' => count($varianten),
            'beste_score' => $varianten[0]['score'] ?? null,
            'tijd_ms' => $elapsed,
        ]);

        return [
            'varianten' => $varianten,
            'tijdMs' => $elapsed,
        ];
    }

    /**
     * Bereken een specifieke indeling
     */
    public function berekenIndeling(
        Collection $judokas,
        int $maxLeeftijdVerschil = 2,
        float $maxKgVerschil = 3.0
    ): array {
        // Stap 1: Groepeer op leeftijd
        $leeftijdGroepen = $this->groepeerOpLeeftijd($judokas, $maxLeeftijdVerschil);

        $poules = [];
        $totaalIngedeeld = 0;

        // Stap 2: Per leeftijdsgroep, groepeer op gewicht
        foreach ($leeftijdGroepen as $leeftijdGroep) {
            $gewichtGroepen = $this->groepeerOpGewicht($leeftijdGroep['judokas'], $maxKgVerschil);

            // Stap 3: Per gewichtsgroep, sorteer op band en maak poules
            foreach ($gewichtGroepen as $gewichtGroep) {
                $groepPoules = $this->maakPoules($gewichtGroep['judokas']);

                foreach ($groepPoules as $poule) {
                    $poules[] = [
                        'judokas' => $poule,
                        'leeftijd_range' => $this->berekenLeeftijdRange($poule),
                        'gewicht_range' => $this->berekenGewichtRange($poule),
                        'band_range' => $this->berekenBandRange($poule),
                        'leeftijd_groep' => $leeftijdGroep['range'],
                        'gewicht_groep' => $gewichtGroep['range'],
                    ];
                    $totaalIngedeeld += count($poule);
                }
            }
        }

        // Bereken totale score
        $score = $this->berekenTotaleScore($poules, $maxLeeftijdVerschil, $maxKgVerschil);

        return [
            'poules' => $poules,
            'score' => round($score, 1),
            'totaal_ingedeeld' => $totaalIngedeeld,
            'totaal_judokas' => $judokas->count(),
            'aantal_poules' => count($poules),
            'params' => [
                'max_leeftijd_verschil' => $maxLeeftijdVerschil,
                'max_kg_verschil' => $maxKgVerschil,
            ],
            'stats' => $this->berekenStatistieken($poules),
        ];
    }

    /**
     * Groepeer judoka's op leeftijd met breekpunten
     */
    private function groepeerOpLeeftijd(Collection $judokas, int $maxVerschil): array
    {
        if ($judokas->isEmpty()) {
            return [];
        }

        // Sorteer op leeftijd
        $gesorteerd = $judokas->sortBy('leeftijd')->values();

        $groepen = [];
        $huidigeGroep = [$gesorteerd[0]];
        $minLeeftijd = $gesorteerd[0]->leeftijd;

        for ($i = 1; $i < $gesorteerd->count(); $i++) {
            $judoka = $gesorteerd[$i];

            if ($judoka->leeftijd - $minLeeftijd <= $maxVerschil) {
                $huidigeGroep[] = $judoka;
            } else {
                $groepen[] = [
                    'judokas' => collect($huidigeGroep),
                    'range' => $this->formatLeeftijdRange($huidigeGroep),
                ];
                $huidigeGroep = [$judoka];
                $minLeeftijd = $judoka->leeftijd;
            }
        }

        // Laatste groep
        if (!empty($huidigeGroep)) {
            $groepen[] = [
                'judokas' => collect($huidigeGroep),
                'range' => $this->formatLeeftijdRange($huidigeGroep),
            ];
        }

        return $groepen;
    }

    /**
     * Groepeer judoka's op gewicht met breekpunten
     */
    private function groepeerOpGewicht(Collection $judokas, float $maxVerschil): array
    {
        if ($judokas->isEmpty()) {
            return [];
        }

        // Sorteer op gewicht
        $gesorteerd = $judokas->sortBy('gewicht')->values();

        $groepen = [];
        $huidigeGroep = [$gesorteerd[0]];
        $minGewicht = $gesorteerd[0]->gewicht;

        for ($i = 1; $i < $gesorteerd->count(); $i++) {
            $judoka = $gesorteerd[$i];

            if ($judoka->gewicht - $minGewicht <= $maxVerschil) {
                $huidigeGroep[] = $judoka;
            } else {
                $groepen[] = [
                    'judokas' => collect($huidigeGroep),
                    'range' => $this->formatGewichtRange($huidigeGroep),
                ];
                $huidigeGroep = [$judoka];
                $minGewicht = $judoka->gewicht;
            }
        }

        // Laatste groep
        if (!empty($huidigeGroep)) {
            $groepen[] = [
                'judokas' => collect($huidigeGroep),
                'range' => $this->formatGewichtRange($huidigeGroep),
            ];
        }

        return $groepen;
    }

    /**
     * Maak poules van een groep judoka's, gesorteerd op band
     */
    private function maakPoules(Collection $judokas): array
    {
        if ($judokas->count() < 2) {
            return [];
        }

        // Sorteer op band (wit eerst)
        $gesorteerd = $judokas->sortBy(function ($judoka) {
            return self::BAND_VOLGORDE[$judoka->band] ?? 99;
        })->values();

        $poules = [];
        $pouleGroottes = $this->berekenPouleGroottes($gesorteerd->count());

        $offset = 0;
        foreach ($pouleGroottes as $grootte) {
            $poule = $gesorteerd->slice($offset, $grootte)->values()->all();
            if (count($poule) >= 2) {
                $poules[] = $poule;
            }
            $offset += $grootte;
        }

        return $poules;
    }

    /**
     * Bereken optimale poule groottes
     */
    private function berekenPouleGroottes(int $aantal): array
    {
        if ($aantal <= 0) {
            return [];
        }

        $voorkeur = $this->config['poule_grootte_voorkeur'];
        $groottes = [];

        while ($aantal > 0) {
            $gevonden = false;

            foreach ($voorkeur as $grootte) {
                if ($aantal >= $grootte) {
                    $groottes[] = $grootte;
                    $aantal -= $grootte;
                    $gevonden = true;
                    break;
                }
            }

            // Als geen enkele voorkeur past, neem wat over is
            if (!$gevonden) {
                if ($aantal >= 2) {
                    $groottes[] = $aantal;
                }
                break;
            }
        }

        return $groottes;
    }

    /**
     * Bereken totale score voor alle poules
     */
    private function berekenTotaleScore(array $poules, int $maxLeeftijd, float $maxGewicht): float
    {
        $totaalScore = 0;

        foreach ($poules as $poule) {
            $score = 0;

            // Leeftijd penalty
            if ($poule['leeftijd_range'] > $maxLeeftijd) {
                $score += ($poule['leeftijd_range'] - $maxLeeftijd) * 10 * $this->config['weight_leeftijd'];
            } else {
                $score += $poule['leeftijd_range'] * $this->config['weight_leeftijd'];
            }

            // Gewicht penalty
            if ($poule['gewicht_range'] > $maxGewicht) {
                $score += ($poule['gewicht_range'] - $maxGewicht) * 10 * $this->config['weight_gewicht'];
            } else {
                $score += $poule['gewicht_range'] * $this->config['weight_gewicht'];
            }

            // Band penalty
            $maxBand = 2;
            if ($poule['band_range'] > $maxBand) {
                $score += ($poule['band_range'] - $maxBand) * 5 * $this->config['weight_band'];
            } else {
                $score += $poule['band_range'] * $this->config['weight_band'];
            }

            $totaalScore += $score;
        }

        return $totaalScore;
    }

    /**
     * Bereken statistieken voor de indeling
     */
    private function berekenStatistieken(array $poules): array
    {
        if (empty($poules)) {
            return [
                'leeftijd_gem' => 0,
                'leeftijd_max' => 0,
                'gewicht_gem' => 0,
                'gewicht_max' => 0,
                'band_gem' => 0,
                'band_max' => 0,
            ];
        }

        $leeftijdRanges = array_column($poules, 'leeftijd_range');
        $gewichtRanges = array_column($poules, 'gewicht_range');
        $bandRanges = array_column($poules, 'band_range');

        return [
            'leeftijd_gem' => round(array_sum($leeftijdRanges) / count($poules), 1),
            'leeftijd_max' => max($leeftijdRanges),
            'gewicht_gem' => round(array_sum($gewichtRanges) / count($poules), 1),
            'gewicht_max' => max($gewichtRanges),
            'band_gem' => round(array_sum($bandRanges) / count($poules), 1),
            'band_max' => max($bandRanges),
        ];
    }

    /**
     * Helper: Bereken leeftijd range van een poule
     */
    private function berekenLeeftijdRange(array $judokas): int
    {
        if (empty($judokas)) {
            return 0;
        }
        $leeftijden = array_map(fn($j) => $j->leeftijd, $judokas);
        return max($leeftijden) - min($leeftijden);
    }

    /**
     * Helper: Bereken gewicht range van een poule
     */
    private function berekenGewichtRange(array $judokas): float
    {
        if (empty($judokas)) {
            return 0;
        }
        $gewichten = array_map(fn($j) => $j->gewicht, $judokas);
        return round(max($gewichten) - min($gewichten), 1);
    }

    /**
     * Helper: Bereken band range van een poule
     */
    private function berekenBandRange(array $judokas): int
    {
        if (empty($judokas)) {
            return 0;
        }
        $banden = array_map(fn($j) => self::BAND_VOLGORDE[$j->band] ?? 99, $judokas);
        return max($banden) - min($banden);
    }

    /**
     * Helper: Format leeftijd range string
     */
    private function formatLeeftijdRange(array $judokas): string
    {
        if (empty($judokas)) {
            return '';
        }
        $leeftijden = array_map(fn($j) => $j->leeftijd, $judokas);
        $min = min($leeftijden);
        $max = max($leeftijden);
        return $min === $max ? "{$min}j" : "{$min}-{$max}j";
    }

    /**
     * Helper: Format gewicht range string
     */
    private function formatGewichtRange(array $judokas): string
    {
        if (empty($judokas)) {
            return '';
        }
        $gewichten = array_map(fn($j) => $j->gewicht, $judokas);
        $min = min($gewichten);
        $max = max($gewichten);
        return $min === $max ? "{$min}kg" : "{$min}-{$max}kg";
    }
}
