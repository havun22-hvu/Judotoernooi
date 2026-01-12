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
     * Get effectief gewicht: gewogen > ingeschreven > gewichtsklasse
     * @return float The weight in kg, or 0 if no weight available
     */
    private function getEffectiefGewicht($judoka): float
    {
        // Prioriteit: gewogen gewicht > ingeschreven gewicht > gewichtsklasse
        if ($judoka->gewicht_gewogen !== null) {
            return (float) $judoka->gewicht_gewogen;
        }
        if ($judoka->gewicht !== null) {
            return (float) $judoka->gewicht;
        }
        // Gewichtsklasse is bijv. "-38" of "+73" - extract getal
        if ($judoka->gewichtsklasse && preg_match('/(\d+)/', $judoka->gewichtsklasse, $m)) {
            return (float) $m[1];
        }
        return 0.0;
    }

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
        'groepsgrootte_prioriteit' => 3, // 1 = hoogste prioriteit (strikt 5), 4 = laagste (flexibel)
        'verdeling_prioriteiten' => ['gewicht', 'band', 'groepsgrootte', 'clubspreiding'],
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
        float $maxKgVerschil = 3.0,
        array $config = []
    ): array {
        // Merge config met defaults
        if (!empty($config)) {
            $this->config = array_merge($this->config, $config);
        }
        // Stap 1: Groepeer op gewicht EN leeftijd tegelijk (gewicht prioriteit)
        $groepen = $this->groepeerOpGewichtEnLeeftijd($judokas, $maxKgVerschil, $maxLeeftijdVerschil);

        $poules = [];
        $totaalIngedeeld = 0;

        // Stap 2: Per groep, sorteer op band en maak poules (met gewichtslimiet check)
        foreach ($groepen as $groep) {
            $groepPoules = $this->maakPoules($groep['judokas'], $maxKgVerschil);

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

        // FINALE VALIDATIE: check en fix alle poules die de harde limiet overschrijden
        $poules = $this->valideerEnFixAllePoules($poules, $maxKgVerschil);

        // Herbereken totaal ingedeeld na finale fix
        $totaalIngedeeld = array_sum(array_map(fn($p) => count($p['judokas']), $poules));

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
     * Finale validatie: check alle poules en split/herverdeel waar nodig
     */
    private function valideerEnFixAllePoules(array $poules, float $maxKgVerschil): array
    {
        $gefixtPoules = [];

        foreach ($poules as $poule) {
            $judokas = $poule['judokas'];
            $gewichten = array_map(fn($j) => $this->getEffectiefGewicht($j), $judokas);
            $verschil = max($gewichten) - min($gewichten);

            if ($verschil <= $maxKgVerschil) {
                // Poule is OK
                $gefixtPoules[] = $poule;
            } else {
                // Poule overschrijdt limiet - split op gewicht
                $gesplitst = $this->splitPouleOpGewicht($judokas, $maxKgVerschil, $poule);
                foreach ($gesplitst as $nieuwePoule) {
                    $gefixtPoules[] = $nieuwePoule;
                }
            }
        }

        return $gefixtPoules;
    }

    /**
     * Split een poule die te groot gewichtsverschil heeft
     */
    private function splitPouleOpGewicht(array $judokas, float $maxKgVerschil, array $origPoule): array
    {
        // Sorteer op gewicht
        usort($judokas, fn($a, $b) => $this->getEffectiefGewicht($a) <=> $this->getEffectiefGewicht($b));

        $nieuwePoules = [];
        $huidigeJudokas = [];
        $minGewicht = null;

        foreach ($judokas as $judoka) {
            $judokaGewicht = $this->getEffectiefGewicht($judoka);
            if (empty($huidigeJudokas)) {
                $huidigeJudokas[] = $judoka;
                $minGewicht = $judokaGewicht;
            } elseif ($judokaGewicht - $minGewicht <= $maxKgVerschil) {
                $huidigeJudokas[] = $judoka;
            } else {
                // Breekpunt: maak poule van huidige judoka's
                if (count($huidigeJudokas) >= 2) {
                    $nieuwePoules[] = [
                        'judokas' => $huidigeJudokas,
                        'leeftijd_range' => $this->berekenLeeftijdRange($huidigeJudokas),
                        'gewicht_range' => $this->berekenGewichtRange($huidigeJudokas),
                        'band_range' => $this->berekenBandRange($huidigeJudokas),
                        'leeftijd_groep' => $origPoule['leeftijd_groep'] ?? '',
                        'gewicht_groep' => $this->formatGewichtRange($huidigeJudokas),
                    ];
                }
                $huidigeJudokas = [$judoka];
                $minGewicht = $judokaGewicht;
            }
        }

        // Laatste groep
        if (count($huidigeJudokas) >= 2) {
            $nieuwePoules[] = [
                'judokas' => $huidigeJudokas,
                'leeftijd_range' => $this->berekenLeeftijdRange($huidigeJudokas),
                'gewicht_range' => $this->berekenGewichtRange($huidigeJudokas),
                'band_range' => $this->berekenBandRange($huidigeJudokas),
                'leeftijd_groep' => $origPoule['leeftijd_groep'] ?? '',
                'gewicht_groep' => $this->formatGewichtRange($huidigeJudokas),
            ];
        } elseif (!empty($huidigeJudokas) && !empty($nieuwePoules)) {
            // 1 judoka over: voeg toe aan laatste poule als het past
            $laatstePoule = array_pop($nieuwePoules);
            $laatsteGewichten = array_map(fn($j) => $this->getEffectiefGewicht($j), $laatstePoule['judokas']);
            if ($this->getEffectiefGewicht($huidigeJudokas[0]) - min($laatsteGewichten) <= $maxKgVerschil) {
                $laatstePoule['judokas'][] = $huidigeJudokas[0];
                $laatstePoule['gewicht_range'] = $this->berekenGewichtRange($laatstePoule['judokas']);
            }
            $nieuwePoules[] = $laatstePoule;
        }

        return $nieuwePoules;
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
     * ALLEEN als het resultaat binnen de HARDE LIMIETEN valt
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

                // Zoek beste buurgroep om mee samen te voegen (alleen binnen harde limieten)
                $besteMatch = null;
                $besteMatchScore = PHP_INT_MAX;
                $besteMatchIndex = -1;

                // Check vorige groep
                if ($i > 0) {
                    $vorigeGroep = $groepen[$i - 1];
                    if ($this->kanSamenvoegen($groep, $vorigeGroep, $maxKg, $maxLeeftijd)) {
                        $score = $this->berekenSamenvoegScore($groep, $vorigeGroep, $maxKg, $maxLeeftijd);
                        if ($score < $besteMatchScore) {
                            $besteMatchScore = $score;
                            $besteMatch = $vorigeGroep;
                            $besteMatchIndex = $i - 1;
                        }
                    }
                }

                // Check volgende groep
                if ($i < count($groepen) - 1) {
                    $volgendeGroep = $groepen[$i + 1];
                    if ($this->kanSamenvoegen($groep, $volgendeGroep, $maxKg, $maxLeeftijd)) {
                        $score = $this->berekenSamenvoegScore($groep, $volgendeGroep, $maxKg, $maxLeeftijd);
                        if ($score < $besteMatchScore) {
                            $besteMatchScore = $score;
                            $besteMatch = $volgendeGroep;
                            $besteMatchIndex = $i + 1;
                        }
                    }
                }

                // Voeg samen als er een match is binnen de harde limieten
                if ($besteMatch) {
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
     * Check of twee groepen samengevoegd kunnen worden binnen de HARDE LIMIETEN
     */
    private function kanSamenvoegen(array $groep1, array $groep2, float $maxKg, int $maxLeeftijd): bool
    {
        $alleJudokas = $groep1['judokas']->concat($groep2['judokas']);

        $gewichten = $alleJudokas->pluck('gewicht');
        $leeftijden = $alleJudokas->pluck('leeftijd');

        $gewichtVerschil = $gewichten->max() - $gewichten->min();
        $leeftijdVerschil = $leeftijden->max() - $leeftijden->min();

        // HARDE LIMIETEN: beide moeten binnen de grenzen zijn
        return $gewichtVerschil <= $maxKg && $leeftijdVerschil <= $maxLeeftijd;
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
        $gewichten = array_map(fn($j) => $this->getEffectiefGewicht($j), $groep);
        $leeftijden = array_map(fn($j) => $j->leeftijd, $groep);

        $minGewicht = min($gewichten);
        $maxGewichtInGroep = max($gewichten);
        $minLeeftijd = min($leeftijden);
        $maxLeeftijdInGroep = max($leeftijden);

        // Check gewicht: nieuwe judoka vs minimum in groep
        $gewichtOk = ($this->getEffectiefGewicht($judoka) - $minGewicht) <= $maxKg;

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

        // Sorteer op gewicht (met fallback naar gewichtsklasse)
        $gesorteerd = $judokas->sortBy(fn($j) => $this->getEffectiefGewicht($j))->values();

        $groepen = [];
        $huidigeGroep = [$gesorteerd[0]];
        $minGewicht = $this->getEffectiefGewicht($gesorteerd[0]);

        for ($i = 1; $i < $gesorteerd->count(); $i++) {
            $judoka = $gesorteerd[$i];
            $judokaGewicht = $this->getEffectiefGewicht($judoka);

            if ($judokaGewicht - $minGewicht <= $maxVerschil) {
                $huidigeGroep[] = $judoka;
            } else {
                $groepen[] = [
                    'judokas' => collect($huidigeGroep),
                    'range' => $this->formatGewichtRange($huidigeGroep),
                ];
                $huidigeGroep = [$judoka];
                $minGewicht = $judokaGewicht;
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
                        // HARDE LIMIET: geen tolerantie, alleen swap als binnen max verschil
                        if ($this->pastInGroep($zwaarste, $groep['judokas']->all(), $maxKgVerschil, $maxLeeftijdVerschil)) {
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
                        // HARDE LIMIET: geen tolerantie, alleen swap als binnen max verschil
                        if ($this->pastInGroep($lichtste, $groep['judokas']->all(), $maxKgVerschil, $maxLeeftijdVerschil)) {
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
     * Maak poules van een groep judoka's
     *
     * Sortering op band: lagere banden in poule 1, hogere in poule 2 (wit bij wit, bruin bij bruin)
     * NA het splitsen: check gewichtslimiet per poule, swap indien nodig
     */
    /**
     * Maak poules van een groep judoka's
     *
     * ALGORITME (zie docs/4-PLANNING/PLANNING_DYNAMISCHE_INDELING.md):
     * 1. Sorteer op basis van prioriteit (gewicht of band eerst)
     * 2. Verdeel van boven naar beneden, nieuwe poule als max kg verschil overschreden zou worden
     * 3. Max kg verschil is HARD constraint - wordt NOOIT overschreden
     */
    private function maakPoules(Collection $judokas, float $maxKgVerschil = 3.0): array
    {
        if ($judokas->count() < 2) {
            return [];
        }

        // STAP 1: Sorteer op basis van prioriteit instelling
        $prioriteiten = $this->config['verdeling_prioriteiten'] ?? ['gewicht', 'band', 'groepsgrootte', 'clubspreiding'];
        $gewichtIndex = array_search('gewicht', $prioriteiten);
        $bandIndex = array_search('band', $prioriteiten);
        $gewichtEerst = $gewichtIndex !== false && ($bandIndex === false || $gewichtIndex < $bandIndex);

        $gesorteerd = $judokas->sortBy(function ($judoka) use ($gewichtEerst) {
            $gewicht = $this->getEffectiefGewicht($judoka);
            if ($gewichtEerst) {
                // Primair op gewicht, secundair op band
                return [$gewicht, self::BAND_VOLGORDE[$judoka->band] ?? 99];
            } else {
                // Primair op band, secundair op gewicht
                return [self::BAND_VOLGORDE[$judoka->band] ?? 99, $gewicht];
            }
        })->values()->all();

        // STAP 2: Verdeel van boven naar beneden met max kg verschil als HARD constraint
        $poules = [];
        $huidigePoule = [];
        $minGewichtInPoule = null;
        $maxGewichtInPoule = null;

        foreach ($gesorteerd as $judoka) {
            $gewicht = $this->getEffectiefGewicht($judoka);

            // Check of judoka in huidige poule past (max kg verschil)
            $pastInPoule = true;
            if (!empty($huidigePoule)) {
                $nieuwMin = min($minGewichtInPoule, $gewicht);
                $nieuwMax = max($maxGewichtInPoule, $gewicht);
                if (($nieuwMax - $nieuwMin) > $maxKgVerschil) {
                    $pastInPoule = false;
                }
            }

            if ($pastInPoule) {
                // Voeg toe aan huidige poule
                $huidigePoule[] = $judoka;
                $minGewichtInPoule = $minGewichtInPoule === null ? $gewicht : min($minGewichtInPoule, $gewicht);
                $maxGewichtInPoule = $maxGewichtInPoule === null ? $gewicht : max($maxGewichtInPoule, $gewicht);
            } else {
                // Start nieuwe poule
                if (count($huidigePoule) >= 2) {
                    $poules[] = $huidigePoule;
                } elseif (count($huidigePoule) === 1 && !empty($poules)) {
                    // 1 judoka: probeer toe te voegen aan vorige poule als het past
                    $this->probeerToeTeVoegenAanLaatstePoule($poules, $huidigePoule[0], $maxKgVerschil);
                }
                $huidigePoule = [$judoka];
                $minGewichtInPoule = $gewicht;
                $maxGewichtInPoule = $gewicht;
            }
        }

        // Laatste poule toevoegen
        if (count($huidigePoule) >= 2) {
            $poules[] = $huidigePoule;
        } elseif (count($huidigePoule) === 1 && !empty($poules)) {
            $this->probeerToeTeVoegenAanLaatstePoule($poules, $huidigePoule[0], $maxKgVerschil);
        }

        // STAP 3: Pas clubspreiding toe indien geconfigureerd
        if (in_array('clubspreiding', $prioriteiten) && count($poules) > 1) {
            $poules = $this->pasClubspreidingToe($poules, $maxKgVerschil);
        }

        return $poules;
    }

    /**
     * Probeer een enkele judoka toe te voegen aan de laatste poule als het past
     */
    private function probeerToeTeVoegenAanLaatstePoule(array &$poules, $judoka, float $maxKgVerschil): void
    {
        if (empty($poules)) return;
        
        $laatstePoule = &$poules[count($poules) - 1];
        $laatsteGewichten = array_map(fn($j) => $this->getEffectiefGewicht($j), $laatstePoule);
        $judokaGewicht = $this->getEffectiefGewicht($judoka);
        
        $nieuwMin = min(min($laatsteGewichten), $judokaGewicht);
        $nieuwMax = max(max($laatsteGewichten), $judokaGewicht);
        
        if (($nieuwMax - $nieuwMin) <= $maxKgVerschil) {
            $laatstePoule[] = $judoka;
        }
        // Anders: judoka niet ingedeeld (wordt later gerapporteerd in validatie)
    }


    /**
     * Fix gewichtslimiet overschrijdingen door judoka's te swappen tussen poules
     * Behoud zo veel mogelijk de band-balans (lagere banden in lagere poules)
     * Fallback: als swaps niet helpen, herverdeel op gewicht
     */
    private function fixGewichtLimietInPoules(array $poules, float $maxKgVerschil): array
    {
        $maxIteraties = 20;
        $iteratie = 0;

        while ($iteratie < $maxIteraties) {
            $iteratie++;
            $gewijzigd = false;

            // Check elke poule op gewichtslimiet overschrijding
            for ($p = 0; $p < count($poules); $p++) {
                $poule = $poules[$p];
                $gewichten = array_map(fn($j) => $this->getEffectiefGewicht($j), $poule);
                $verschil = max($gewichten) - min($gewichten);

                if ($verschil <= $maxKgVerschil) {
                    continue; // Poule is OK
                }

                // Poule overschrijdt limiet - probeer te swappen
                // Zoek de judoka met het hoogste of laagste gewicht om te swappen
                $minIdx = array_search(min($gewichten), $gewichten);
                $maxIdx = array_search(max($gewichten), $gewichten);

                // Probeer de "uitschieter" te swappen naar een andere poule
                $uitschieters = [
                    ['idx' => $maxIdx, 'judoka' => $poule[$maxIdx], 'type' => 'max'],
                    ['idx' => $minIdx, 'judoka' => $poule[$minIdx], 'type' => 'min'],
                ];

                foreach ($uitschieters as $uitschieter) {
                    $judoka = $uitschieter['judoka'];
                    $judokaIdx = $uitschieter['idx'];

                    // Zoek een swap kandidaat in een andere poule
                    for ($q = 0; $q < count($poules); $q++) {
                        if ($q === $p) continue;

                        foreach ($poules[$q] as $kandidaatIdx => $kandidaat) {
                            // Check of swap beide poules binnen limiet brengt
                            if ($this->swapVerbetert($poules[$p], $judokaIdx, $poules[$q], $kandidaatIdx, $maxKgVerschil)) {
                                // Doe de swap
                                $poules[$p][$judokaIdx] = $kandidaat;
                                $poules[$q][$kandidaatIdx] = $judoka;
                                $gewijzigd = true;
                                break 3; // Start opnieuw
                            }
                        }
                    }
                }
            }

            if (!$gewijzigd) {
                break; // Geen verbeteringen meer mogelijk via swaps
            }
        }

        // Fallback: als er nog steeds overschrijdingen zijn, herverdeel op gewicht
        if ($this->heeftOverschrijdingen($poules, $maxKgVerschil)) {
            $poules = $this->herverdeelOpGewicht($poules, $maxKgVerschil);
        }

        return $poules;
    }

    /**
     * Check of er nog poules zijn die de gewichtslimiet overschrijden
     */
    private function heeftOverschrijdingen(array $poules, float $maxKgVerschil): bool
    {
        foreach ($poules as $poule) {
            $gewichten = array_map(fn($j) => $this->getEffectiefGewicht($j), $poule);
            if (max($gewichten) - min($gewichten) > $maxKgVerschil) {
                return true;
            }
        }
        return false;
    }

    /**
     * Herverdeel alle judoka's over poules op basis van gewicht (fallback)
     * Garandeert gewichtslimiet door dynamisch te splitsen waar nodig
     */
    private function herverdeelOpGewicht(array $poules, float $maxKgVerschil): array
    {
        // Verzamel alle judoka's en sorteer op gewicht
        $alleJudokas = [];
        foreach ($poules as $poule) {
            foreach ($poule as $judoka) {
                $alleJudokas[] = $judoka;
            }
        }
        usort($alleJudokas, fn($a, $b) => $this->getEffectiefGewicht($a) <=> $this->getEffectiefGewicht($b));

        // Verdeel dynamisch: breek af waar gewichtsverschil te groot wordt
        $nieuwePoules = [];
        $huidigePoule = [];
        $minGewicht = null;

        foreach ($alleJudokas as $judoka) {
            $judokaGewicht = $this->getEffectiefGewicht($judoka);
            if (empty($huidigePoule)) {
                $huidigePoule[] = $judoka;
                $minGewicht = $judokaGewicht;
            } elseif ($judokaGewicht - $minGewicht <= $maxKgVerschil) {
                // Past binnen de limiet
                $huidigePoule[] = $judoka;
            } else {
                // Breekpunt: start nieuwe poule
                if (count($huidigePoule) >= 2) {
                    $nieuwePoules[] = $huidigePoule;
                }
                $huidigePoule = [$judoka];
                $minGewicht = $judokaGewicht;
            }
        }

        // Laatste poule
        if (count($huidigePoule) >= 2) {
            $nieuwePoules[] = $huidigePoule;
        } elseif (!empty($huidigePoule) && !empty($nieuwePoules)) {
            // 1 judoka over: voeg toe aan laatste poule als het past
            $laatstePoule = array_pop($nieuwePoules);
            $laatsteGewichten = array_map(fn($j) => $this->getEffectiefGewicht($j), $laatstePoule);
            if ($this->getEffectiefGewicht($huidigePoule[0]) - min($laatsteGewichten) <= $maxKgVerschil) {
                $laatstePoule[] = $huidigePoule[0];
            }
            $nieuwePoules[] = $laatstePoule;
        }

        return $nieuwePoules;
    }

    /**
     * Check of een swap beide poules binnen de HARDE LIMIET brengt
     */
    private function swapVerbetert(array $poule1, int $idx1, array $poule2, int $idx2, float $maxKg): bool
    {
        // Simuleer swap
        $nieuwGewichten1 = array_map(fn($j) => $this->getEffectiefGewicht($j), $poule1);
        $nieuwGewichten2 = array_map(fn($j) => $this->getEffectiefGewicht($j), $poule2);
        $nieuwGewichten1[$idx1] = $this->getEffectiefGewicht($poule2[$idx2]);
        $nieuwGewichten2[$idx2] = $this->getEffectiefGewicht($poule1[$idx1]);

        $nieuw1 = max($nieuwGewichten1) - min($nieuwGewichten1);
        $nieuw2 = max($nieuwGewichten2) - min($nieuwGewichten2);

        // HARDE LIMIET: beide poules MOETEN binnen de limiet komen
        return $nieuw1 <= $maxKg && $nieuw2 <= $maxKg;
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
    /**
     * Bereken optimale poule groottes
     *
     * Als groepsgrootte prioriteit = 1: strikt 5 aanhouden, rest 4, desnoods 3/6
     * Als groepsgrootte prioriteit = 4: flexibeler, 3-6 acceptabel
     *
     * Voorkeur: 5 > 4 > 6 > 3
     */
    private function berekenPouleGroottes(int $aantal): array
    {
        if ($aantal <= 0) {
            return [];
        }

        // Minimum pool size is 3, so can't split below 6
        if ($aantal <= 3) {
            return [$aantal]; // 1 poule
        }

        $prioriteit = $this->config['groepsgrootte_prioriteit'] ?? 3;

        // Prioriteit 1 = zeer strikt (5 gewenst), 4 = flexibel
        // Penalty multiplier: prioriteit 1 = 10x, prioriteit 4 = 1x
        $striktheid = max(1, 5 - $prioriteit); // 4, 3, 2, 1

        $besteVerdeling = null;
        $besteScore = PHP_INT_MAX;

        // Probeer verschillende aantal poules
        $maxPoules = min(ceil($aantal / 3), 10);

        for ($aantalPoules = 2; $aantalPoules <= $maxPoules; $aantalPoules++) {
            // Genereer alle mogelijke verdelingen voor dit aantal poules
            $verdelingen = $this->genereerVerdelingen($aantal, $aantalPoules);

            foreach ($verdelingen as $verdeling) {
                $score = $this->scoreVerdeling($verdeling, $striktheid);

                if ($score < $besteScore) {
                    $besteScore = $score;
                    $besteVerdeling = $verdeling;
                }
            }
        }

        // Sorteer van groot naar klein
        if ($besteVerdeling) {
            rsort($besteVerdeling);
        }

        return $besteVerdeling ?? [$aantal];
    }

    /**
     * Genereer mogelijke verdelingen voor N judoka's in M poules
     * Alleen verdelingen waarbij elke poule 3-6 judoka's heeft
     */
    private function genereerVerdelingen(int $aantal, int $aantalPoules): array
    {
        $verdelingen = [];

        // Basis verdeling
        $basis = intdiv($aantal, $aantalPoules);
        $rest = $aantal % $aantalPoules;

        // Check of basis verdeling geldig is (3-6 per poule)
        if ($basis < 3 || $basis > 6) {
            // Probeer aangrenzende waardes
            if ($basis < 3) {
                $basis = 3;
            } elseif ($basis > 6) {
                $basis = 6;
            }
        }

        // Genereer variaties rond de basis
        $variaties = $this->genereerVariaties($aantal, $aantalPoules, 3, 6);

        return $variaties;
    }

    /**
     * Recursief variaties genereren
     */
    private function genereerVariaties(int $rest, int $poulesOver, int $min, int $max, array $huidig = []): array
    {
        if ($poulesOver === 0) {
            return $rest === 0 ? [$huidig] : [];
        }

        $resultaten = [];

        // Bepaal range voor deze poule
        $minVoorDeze = max($min, $rest - ($poulesOver - 1) * $max);
        $maxVoorDeze = min($max, $rest - ($poulesOver - 1) * $min);

        for ($grootte = $minVoorDeze; $grootte <= $maxVoorDeze; $grootte++) {
            $nieuweHuidig = array_merge($huidig, [$grootte]);
            $subResultaten = $this->genereerVariaties(
                $rest - $grootte,
                $poulesOver - 1,
                $min,
                $max,
                $nieuweHuidig
            );
            $resultaten = array_merge($resultaten, $subResultaten);
        }

        return $resultaten;
    }

    /**
     * Score een verdeling (lager = beter)
     *
     * Gebruikt poule_grootte_voorkeur uit config (ingesteld door organisator)
     * Bijv. [5, 4, 3, 6] betekent: 5 is beste, dan 4, dan 3, dan 6
     *
     * Penalties zijn exponentieel zodat laatste voorkeur zwaar weegt:
     * - Positie 0: penalty 0
     * - Positie 1: penalty 1
     * - Positie 2: penalty 3
     * - Positie 3: penalty 7
     *
     * Voorbeeld met [5, 4, 3, 6] en 11 judoka's:
     * - [6, 5] = 7×1 + 0×1 = 7  (6 is laatste voorkeur!)
     * - [5, 3, 3] = 0×1 + 3×2 = 6
     * - [4, 4, 3] = 1×2 + 3×1 = 5
     * → [4, 4, 3] wint
     */
    private function scoreVerdeling(array $verdeling, int $striktheid): float
    {
        // Haal voorkeur volgorde uit config (organisator instelling)
        $voorkeur = $this->config['poule_grootte_voorkeur'] ?? [5, 4, 6, 3];

        // Exponentiële penalties: 0, 1, 3, 7 (2^n - 1)
        $penalties = [0, 1, 3, 7];

        // Bouw penalty map
        $penaltyMap = [];
        foreach ($voorkeur as $index => $grootte) {
            $penaltyMap[$grootte] = $penalties[$index] ?? 15;
        }

        $score = 0;

        foreach ($verdeling as $grootte) {
            if (isset($penaltyMap[$grootte])) {
                $score += $penaltyMap[$grootte] * $striktheid;
            } else {
                // Grootte niet in voorkeur = grote penalty
                $score += 100 * $striktheid;
            }
        }

        // Kleine penalty voor niet-uniforme groottes
        $verschil = max($verdeling) - min($verdeling);
        $score += $verschil * 0.5;

        // Minimale voorkeur voor minder poules (bij gelijke score)
        $score += count($verdeling) * 0.01;

        return $score;
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
        $gewichten = array_map(fn($j) => $this->getEffectiefGewicht($j), $judokas);
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
        $gewichten = array_map(fn($j) => $this->getEffectiefGewicht($j), $judokas);
        $min = min($gewichten);
        $max = max($gewichten);
        return $min === $max ? "{$min}kg" : "{$min}-{$max}kg";
    }

    /**
     * Pas clubspreiding toe: probeer judoka's van dezelfde club te verdelen over poules
     * Alleen swappen als het binnen de gewichtslimiet blijft (HARD constraint)
     */
    private function pasClubspreidingToe(array $poules, float $maxKgVerschil): array
    {
        $aantalPoules = count($poules);
        if ($aantalPoules < 2) {
            return $poules;
        }

        // Voor elke poule, check voor club duplicaten
        for ($p = 0; $p < $aantalPoules; $p++) {
            $clubCount = [];
            foreach ($poules[$p] as $idx => $judoka) {
                $clubId = $judoka->club_id ?? 0;
                if (!isset($clubCount[$clubId])) {
                    $clubCount[$clubId] = [];
                }
                $clubCount[$clubId][] = $idx;
            }

            // Voor clubs met meerdere judoka's, probeer te swappen
            foreach ($clubCount as $clubId => $indices) {
                if (count($indices) <= 1) continue;

                // Probeer de tweede (en verdere) judoka(s) te swappen naar andere poules
                for ($i = 1; $i < count($indices); $i++) {
                    $judokaIdx = $indices[$i];
                    $judoka = $poules[$p][$judokaIdx];

                    // Zoek een swap kandidaat in een andere poule
                    for ($q = 0; $q < $aantalPoules; $q++) {
                        if ($q === $p) continue;

                        foreach ($poules[$q] as $kandidaatIdx => $kandidaat) {
                            // Check of swap beide poules binnen gewichtslimiet houdt
                            if ($this->swapVerbetert($poules[$p], $judokaIdx, $poules[$q], $kandidaatIdx, $maxKgVerschil) &&
                                $kandidaat->club_id !== $clubId &&
                                !$this->clubInPoule($poules[$p], $kandidaat->club_id, $judokaIdx)) {

                                if (!$this->clubInPoule($poules[$q], $judoka->club_id, $kandidaatIdx)) {
                                    // Swap
                                    $poules[$p][$judokaIdx] = $kandidaat;
                                    $poules[$q][$kandidaatIdx] = $judoka;
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $poules;
    }

    /**
     * Check of een club al in een poule zit (exclusief bepaalde index)
     */
    private function clubInPoule(array $poule, ?int $clubId, int $excludeIdx): bool
    {
        foreach ($poule as $idx => $judoka) {
            if ($idx !== $excludeIdx && ($judoka->club_id ?? 0) === $clubId) {
                return true;
            }
        }
        return false;
    }
}
