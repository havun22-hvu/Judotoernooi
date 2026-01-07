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
     *
     * NIEUW ALGORITME: Gewicht eerst, dan check leeftijd
     * - Sorteer op gewicht
     * - Groepeer zodat gewichtsverschil ≤ max EN leeftijdsverschil ≤ max
     * - Een 9-jarige kan bij 8-9 OF 9-10, afhankelijk van gewicht
     */
    public function berekenIndeling(
        Collection $judokas,
        int $maxLeeftijdVerschil = 2,
        float $maxKgVerschil = 3.0
    ): array {
        // Stap 1: Groepeer op gewicht EN leeftijd tegelijk (gewicht prioriteit)
        $groepen = $this->groepeerOpGewichtEnLeeftijd($judokas, $maxKgVerschil, $maxLeeftijdVerschil);

        $poules = [];
        $totaalIngedeeld = 0;

        // Stap 2: Per groep, sorteer op band en maak poules
        foreach ($groepen as $groep) {
            $groepPoules = $this->maakPoules($groep['judokas']);

            foreach ($groepPoules as $poule) {
                $poules[] = [
                    'judokas' => $poule,
                    'leeftijd_range' => $this->berekenLeeftijdRange($poule),
                    'gewicht_range' => $this->berekenGewichtRange($poule),
                    'band_range' => $this->berekenBandRange($poule),
                    'leeftijd_groep' => $groep['leeftijd_range'],
                    'gewicht_groep' => $groep['gewicht_range'],
                ];
                $totaalIngedeeld += count($poule);
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
     * Groepeer op gewicht EN leeftijd tegelijk
     *
     * Sorteer op gewicht, maak groepen waarbij:
     * - Gewichtsverschil binnen groep ≤ maxKg
     * - Leeftijdsverschil binnen groep ≤ maxLeeftijd
     */
    private function groepeerOpGewichtEnLeeftijd(Collection $judokas, float $maxKg, int $maxLeeftijd): array
    {
        if ($judokas->isEmpty()) {
            return [];
        }

        // Sorteer op gewicht (primair)
        $gesorteerd = $judokas->sortBy('gewicht')->values();

        $groepen = [];
        $huidigeGroep = [$gesorteerd[0]];

        for ($i = 1; $i < $gesorteerd->count(); $i++) {
            $judoka = $gesorteerd[$i];

            // Check of deze judoka in de huidige groep past
            $pastInGroep = $this->pastInGroep($judoka, $huidigeGroep, $maxKg, $maxLeeftijd);

            if ($pastInGroep) {
                $huidigeGroep[] = $judoka;
            } else {
                // Sla huidige groep op en start nieuwe
                $groepen[] = $this->maakGroepData($huidigeGroep);
                $huidigeGroep = [$judoka];
            }
        }

        // Laatste groep
        if (!empty($huidigeGroep)) {
            $groepen[] = $this->maakGroepData($huidigeGroep);
        }

        // Balanceer kleine groepen
        $groepen = $this->balanceerGroepen($groepen, $maxKg, $maxLeeftijd);

        // Extra stap: voeg hele kleine groepen (2-3) samen met buurgroep
        $groepen = $this->voegKleineGroepenSamen($groepen, $maxKg, $maxLeeftijd);

        return $groepen;
    }

    /**
     * Voeg hele kleine groepen (2-3 judoka's) samen met de dichtstbijzijnde buurgroep
     */
    private function voegKleineGroepenSamen(array $groepen, float $maxKg, int $maxLeeftijd): array
    {
        if (count($groepen) < 2) {
            return $groepen;
        }

        $minGroepsGrootte = 4;
        $gewijzigd = true;
        $maxIteraties = 10;
        $iteratie = 0;

        while ($gewijzigd && $iteratie < $maxIteraties) {
            $gewijzigd = false;
            $iteratie++;

            for ($i = 0; $i < count($groepen); $i++) {
                $groep = $groepen[$i];
                $aantalInGroep = $groep['judokas']->count();

                // Skip als groep groot genoeg is
                if ($aantalInGroep >= $minGroepsGrootte) {
                    continue;
                }

                // Zoek beste buurgroep om mee samen te voegen
                $besteMatch = null;
                $besteMatchScore = PHP_INT_MAX;
                $besteMatchIndex = -1;

                // Check vorige groep
                if ($i > 0) {
                    $vorigeGroep = $groepen[$i - 1];
                    $score = $this->berekenSamenvoegScore($groep, $vorigeGroep, $maxKg, $maxLeeftijd);
                    if ($score < $besteMatchScore) {
                        $besteMatchScore = $score;
                        $besteMatch = $vorigeGroep;
                        $besteMatchIndex = $i - 1;
                    }
                }

                // Check volgende groep
                if ($i < count($groepen) - 1) {
                    $volgendeGroep = $groepen[$i + 1];
                    $score = $this->berekenSamenvoegScore($groep, $volgendeGroep, $maxKg, $maxLeeftijd);
                    if ($score < $besteMatchScore) {
                        $besteMatchScore = $score;
                        $besteMatch = $volgendeGroep;
                        $besteMatchIndex = $i + 1;
                    }
                }

                // Voeg samen als er een acceptabele match is (score < 1000 = binnen 2x tolerantie)
                if ($besteMatch && $besteMatchScore < 1000) {
                    // Merge judoka's
                    $samengevoegd = $besteMatch['judokas']->concat($groep['judokas'])->sortBy('gewicht')->values();

                    // Update de groep waar we naartoe mergen
                    $groepen[$besteMatchIndex]['judokas'] = $samengevoegd;
                    $groepen[$besteMatchIndex]['gewicht_range'] = $this->formatGewichtRange($samengevoegd->all());
                    $groepen[$besteMatchIndex]['leeftijd_range'] = $this->formatLeeftijdRange($samengevoegd->all());

                    // Verwijder de kleine groep
                    unset($groepen[$i]);
                    $groepen = array_values($groepen);

                    $gewijzigd = true;
                    break; // Start opnieuw na wijziging
                }
            }
        }

        return array_values($groepen);
    }

    /**
     * Bereken score voor samenvoegen van twee groepen
     * Lagere score = beter
     */
    private function berekenSamenvoegScore(array $groep1, array $groep2, float $maxKg, int $maxLeeftijd): int
    {
        $alleJudokas = $groep1['judokas']->concat($groep2['judokas']);

        $gewichten = $alleJudokas->pluck('gewicht');
        $leeftijden = $alleJudokas->pluck('leeftijd');

        $gewichtVerschil = $gewichten->max() - $gewichten->min();
        $leeftijdVerschil = $leeftijden->max() - $leeftijden->min();

        // Score berekening
        $score = 0;

        // Gewicht penalty
        if ($gewichtVerschil <= $maxKg) {
            $score += $gewichtVerschil * 10;
        } elseif ($gewichtVerschil <= $maxKg * 1.5) {
            $score += $gewichtVerschil * 50;
        } elseif ($gewichtVerschil <= $maxKg * 2) {
            $score += $gewichtVerschil * 100;
        } else {
            $score += 10000; // Te groot
        }

        // Leeftijd penalty
        if ($leeftijdVerschil <= $maxLeeftijd) {
            $score += $leeftijdVerschil * 10;
        } elseif ($leeftijdVerschil <= $maxLeeftijd + 1) {
            $score += $leeftijdVerschil * 100;
        } else {
            $score += 10000; // Te groot
        }

        return (int) $score;
    }

    /**
     * Check of een judoka in een bestaande groep past
     */
    private function pastInGroep($judoka, array $groep, float $maxKg, int $maxLeeftijd): bool
    {
        $gewichten = array_map(fn($j) => $j->gewicht, $groep);
        $leeftijden = array_map(fn($j) => $j->leeftijd, $groep);

        $minGewicht = min($gewichten);
        $maxGewichtInGroep = max($gewichten);
        $minLeeftijd = min($leeftijden);
        $maxLeeftijdInGroep = max($leeftijden);

        // Check gewicht: nieuwe judoka vs minimum in groep
        $gewichtOk = ($judoka->gewicht - $minGewicht) <= $maxKg;

        // Check leeftijd: nieuwe judoka moet passen bij ALLE bestaande leeftijden
        $nieuweMin = min($minLeeftijd, $judoka->leeftijd);
        $nieuweMax = max($maxLeeftijdInGroep, $judoka->leeftijd);
        $leeftijdOk = ($nieuweMax - $nieuweMin) <= $maxLeeftijd;

        return $gewichtOk && $leeftijdOk;
    }

    /**
     * Maak groep data array
     */
    private function maakGroepData(array $judokas): array
    {
        return [
            'judokas' => collect($judokas),
            'gewicht_range' => $this->formatGewichtRange($judokas),
            'leeftijd_range' => $this->formatLeeftijdRange($judokas),
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

        // Balanceer kleine groepen door judoka's te verplaatsen van aangrenzende groepen
        $groepen = $this->balanceerGroepen($groepen, $maxVerschil);

        return $groepen;
    }

    /**
     * Balanceer groepen: verplaats judoka's van grote naar kleine groepen
     *
     * Als een groep te klein is (2-3 judoka's), probeer:
     * 1. De zwaarste uit de vorige groep te verplaatsen (als die groep ≥4 blijft)
     * 2. OF de lichtste uit de volgende groep te verplaatsen (als die groep ≥4 blijft)
     * 3. Check ook dat leeftijdsverschil acceptabel blijft
     */
    private function balanceerGroepen(array $groepen, float $maxKgVerschil, int $maxLeeftijdVerschil = 2): array
    {
        if (count($groepen) < 2) {
            return $groepen;
        }

        $minGroepsGrootte = 4; // Minimum gewenste groepsgrootte
        $gewijzigd = true;
        $maxIteraties = 10; // Voorkom oneindige loop
        $iteratie = 0;

        // Blijf balanceren totdat er geen wijzigingen meer zijn
        while ($gewijzigd && $iteratie < $maxIteraties) {
            $gewijzigd = false;
            $iteratie++;

            for ($i = 0; $i < count($groepen); $i++) {
                $groep = $groepen[$i];
                $aantalInGroep = $groep['judokas']->count();

                // Skip als groep groot genoeg is
                if ($aantalInGroep >= $minGroepsGrootte) {
                    continue;
                }

                // Probeer van vorige groep te halen (zwaarste judoka)
                if ($i > 0) {
                    $vorigeGroep = $groepen[$i - 1];
                    if ($vorigeGroep['judokas']->count() >= $minGroepsGrootte) {
                        // Pak de zwaarste uit vorige groep
                        $zwaarste = $vorigeGroep['judokas']->sortByDesc('gewicht')->first();

                        // Check of deze judoka past in de huidige groep (gewicht + leeftijd)
                        if ($this->pastInGroep($zwaarste, $groep['judokas']->all(), $maxKgVerschil * 1.5, $maxLeeftijdVerschil)) {
                            // Verplaats judoka
                            $groepen[$i - 1]['judokas'] = $vorigeGroep['judokas']->reject(fn($j) => $j->id === $zwaarste->id)->values();
                            $groepen[$i]['judokas'] = $groep['judokas']->push($zwaarste)->sortBy('gewicht')->values();

                            // Update ranges
                            $groepen[$i - 1]['gewicht_range'] = $this->formatGewichtRange($groepen[$i - 1]['judokas']->all());
                            $groepen[$i - 1]['leeftijd_range'] = $this->formatLeeftijdRange($groepen[$i - 1]['judokas']->all());
                            $groepen[$i]['gewicht_range'] = $this->formatGewichtRange($groepen[$i]['judokas']->all());
                            $groepen[$i]['leeftijd_range'] = $this->formatLeeftijdRange($groepen[$i]['judokas']->all());

                            $gewijzigd = true;
                            continue;
                        }
                    }
                }

                // Probeer van volgende groep te halen (lichtste judoka)
                if ($i < count($groepen) - 1) {
                    $volgendeGroep = $groepen[$i + 1];
                    if ($volgendeGroep['judokas']->count() >= $minGroepsGrootte) {
                        // Pak de lichtste uit volgende groep
                        $lichtste = $volgendeGroep['judokas']->sortBy('gewicht')->first();

                        // Check of deze judoka past in de huidige groep (gewicht + leeftijd)
                        if ($this->pastInGroep($lichtste, $groep['judokas']->all(), $maxKgVerschil * 1.5, $maxLeeftijdVerschil)) {
                            // Verplaats judoka
                            $groepen[$i + 1]['judokas'] = $volgendeGroep['judokas']->reject(fn($j) => $j->id === $lichtste->id)->values();
                            $groepen[$i]['judokas'] = $groep['judokas']->push($lichtste)->sortBy('gewicht')->values();

                            // Update ranges
                            $groepen[$i + 1]['gewicht_range'] = $this->formatGewichtRange($groepen[$i + 1]['judokas']->all());
                            $groepen[$i + 1]['leeftijd_range'] = $this->formatLeeftijdRange($groepen[$i + 1]['judokas']->all());
                            $groepen[$i]['gewicht_range'] = $this->formatGewichtRange($groepen[$i]['judokas']->all());
                            $groepen[$i]['leeftijd_range'] = $this->formatLeeftijdRange($groepen[$i]['judokas']->all());

                            $gewijzigd = true;
                            continue;
                        }
                    }
                }
            }
        }

        // Verwijder lege groepen
        $groepen = array_filter($groepen, fn($g) => $g['judokas']->count() > 0);

        return array_values($groepen);
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
     *
     * Doel: vermijd poules van 2-3 judoka's waar mogelijk
     * Voorbeelden:
     * - 7 judoka's → [4, 3] of [3, 4], niet [5, 2]
     * - 8 judoka's → [4, 4], niet [5, 3]
     * - 11 judoka's → [4, 4, 3] of [5, 3, 3], niet [5, 4, 2]
     */
    private function berekenPouleGroottes(int $aantal): array
    {
        if ($aantal <= 0) {
            return [];
        }

        // Voor kleine aantallen, speciale gevallen
        if ($aantal <= 6) {
            return [$aantal]; // 1 poule
        }

        // Bereken beste verdeling: minimaliseer verschil tussen poules
        // en vermijd poules < 4 waar mogelijk
        $besteVerdeling = null;
        $besteScore = PHP_INT_MAX;

        // Probeer 2, 3, 4 poules
        for ($aantalPoules = 2; $aantalPoules <= min(4, ceil($aantal / 3)); $aantalPoules++) {
            $basisGrootte = intdiv($aantal, $aantalPoules);
            $rest = $aantal % $aantalPoules;

            $verdeling = array_fill(0, $aantalPoules, $basisGrootte);
            // Verdeel de rest over de eerste poules
            for ($i = 0; $i < $rest; $i++) {
                $verdeling[$i]++;
            }

            // Bereken score (lagere = beter)
            // Penalty voor poules < 4, grote penalty voor poules < 3
            $score = 0;
            foreach ($verdeling as $grootte) {
                if ($grootte < 3) $score += 100;
                elseif ($grootte < 4) $score += 10;
                elseif ($grootte > 6) $score += 5;
            }
            // Bonus voor gelijke groottes
            $score += (max($verdeling) - min($verdeling)) * 2;

            if ($score < $besteScore) {
                $besteScore = $score;
                $besteVerdeling = $verdeling;
            }
        }

        // Sorteer van groot naar klein
        rsort($besteVerdeling);

        return $besteVerdeling;
    }

    /**
     * OUDE versie - niet meer gebruikt
     */
    private function berekenPouleGroottesOud(int $aantal): array
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
