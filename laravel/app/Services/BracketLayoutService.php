<?php

namespace App\Services;

/**
 * Berekent de visuele layout (posities, rondes, headers) voor eliminatie brackets.
 * Vertaling van de JS renderBracket() logica naar PHP voor server-side rendering.
 */
class BracketLayoutService
{
    // Slot height in pixels
    private const SLOT_HEIGHT = 28;
    private const POTJE_HEIGHT = 56; // 2 * SLOT_HEIGHT
    private const POTJE_GAP = 8;
    private const HORIZON_HEIGHT = 20; // Ruimte tussen B-bracket helften

    // Ronde volgorde lookup (A en B groep)
    private const RONDE_VOLGORDE = [
        // A-groep
        'tweeendertigste_finale' => 0,
        'zestiende_finale' => 1,
        'achtste_finale' => 2,
        'kwartfinale' => 3,
        'halve_finale' => 4,
        'finale' => 5,
        // B-groep
        'b_zestiende_finale_1' => 1,
        'b_zestiende_finale_2' => 2,
        'b_zestiende_finale' => 1,
        'b_achtste_finale_1' => 3,
        'b_achtste_finale_2' => 4,
        'b_achtste_finale' => 3,
        'b_kwartfinale_1' => 5,
        'b_kwartfinale_2' => 6,
        'b_kwartfinale' => 5,
        'b_halve_finale_1' => 7,
        'b_halve_finale_2' => 8,
        'b_halve_finale' => 7,
        'b_brons' => 8,
    ];

    // Leesbare namen per ronde
    private const RONDE_NAMEN = [
        'tweeendertigste_finale' => '1/32',
        'zestiende_finale' => '1/16',
        'achtste_finale' => '1/8',
        'kwartfinale' => '1/4',
        'halve_finale' => '1/2',
        'finale' => 'Finale',
        'b_zestiende_finale_1' => '1/16 (1)',
        'b_zestiende_finale_2' => '1/16 (2)',
        'b_zestiende_finale' => '1/16',
        'b_achtste_finale_1' => '1/8 (1)',
        'b_achtste_finale_2' => '1/8 (2)',
        'b_achtste_finale' => '1/8',
        'b_kwartfinale_1' => '1/4 (1)',
        'b_kwartfinale_2' => '1/4 (2)',
        'b_kwartfinale' => '1/4',
        'b_halve_finale_1' => '1/2 (1)',
        'b_halve_finale_2' => '1/2 (2)',
        'b_halve_finale' => '1/2',
        'b_brons' => 'Brons',
    ];

    /**
     * Bereken de volledige A-bracket layout.
     *
     * @param array $wedstrijden Array van wedstrijd-arrays (uit getSchemaVoorMat)
     * @return array ['rondes' => [...], 'totale_hoogte' => int, 'medaille_data' => [...]]
     */
    public function berekenABracketLayout(array $wedstrijden): array
    {
        $rondes = $this->groepeerPerRonde($wedstrijden);
        if (empty($rondes)) {
            return ['rondes' => [], 'totale_hoogte' => 300, 'medaille_data' => []];
        }

        // Bereken posities per wedstrijd
        $eersteRonde = $rondes[0];
        $aantalSlots = count($eersteRonde['wedstrijden']) * 2;
        $totaleHoogte = max($aantalSlots * (self::POTJE_HEIGHT + self::POTJE_GAP), 300);

        foreach ($rondes as $rondeIdx => &$ronde) {
            foreach ($ronde['wedstrijden'] as $wedIdx => &$wed) {
                $topPos = $this->berekenPotjeTop($rondeIdx, $wedIdx);
                $isLastRound = $rondeIdx === count($rondes) - 1;

                $wed['_layout'] = [
                    'top' => $topPos,
                    'is_last_round' => $isLastRound,
                    'visual_slot_wit' => $wedIdx * 2 + 1,
                    'visual_slot_blauw' => $wedIdx * 2 + 2,
                ];
            }
            unset($wed);
        }
        unset($ronde);

        // Medaille data
        $laatsteRonde = end($rondes);
        $laatsteRondeNiveau = count($rondes) - 1;
        $medailleData = $this->berekenAMedailles($laatsteRonde, $laatsteRondeNiveau);

        return [
            'rondes' => $rondes,
            'totale_hoogte' => $totaleHoogte,
            'medaille_data' => $medailleData,
        ];
    }

    /**
     * Bereken de volledige B-bracket layout (mirrored: bovenste + onderste helft).
     *
     * @param array $wedstrijden Array van B-groep wedstrijd-arrays
     * @return array ['niveaus' => [...], 'totale_hoogte' => int, 'medaille_data' => [...]]
     */
    public function berekenBBracketLayout(array $wedstrijden): array
    {
        $rondes = $this->groepeerPerRonde($wedstrijden);
        if (empty($rondes)) {
            return ['niveaus' => [], 'totale_hoogte' => 300, 'medaille_data' => [], 'rondes_flat' => []];
        }

        // Groepeer rondes per niveau: b_achtste_finale_1 en _2 samen
        $niveaus = [];
        $niveauMap = [];

        foreach ($rondes as $ronde) {
            $basisNiveau = preg_replace('/_[12]$/', '', $ronde['ronde']);
            if (!isset($niveauMap[$basisNiveau])) {
                $niveauMap[$basisNiveau] = ['naam' => $basisNiveau, 'sub_rondes' => []];
                $niveaus[] = &$niveauMap[$basisNiveau];
            }
            $niveauMap[$basisNiveau]['sub_rondes'][] = $ronde;
        }
        // Reset references
        unset($niveauMap);

        // Bereken hoogte
        $eersteNiveau = $niveaus[0] ?? null;
        $wedsPerHelft = $eersteNiveau
            ? (int) ceil(count($eersteNiveau['sub_rondes'][0]['wedstrijden']) / 2)
            : 4;

        $helftHoogte = $wedsPerHelft * (self::POTJE_HEIGHT + self::POTJE_GAP);
        $totaleHoogte = 2 * $helftHoogte + self::HORIZON_HEIGHT;

        // Bereken posities per wedstrijd in elk niveau
        $rondesFlat = []; // Platte lijst voor header rendering
        foreach ($niveaus as $niveauIdx => &$niveau) {
            $isLastNiveau = $niveauIdx === count($niveaus) - 1;

            foreach ($niveau['sub_rondes'] as $subRondeIdx => &$ronde) {
                $rondesFlat[] = &$ronde;
                $sortedWeds = $ronde['wedstrijden'];
                usort($sortedWeds, fn($a, $b) => ($a['bracket_positie'] ?? 0) - ($b['bracket_positie'] ?? 0));

                $halfCount = (int) ceil(count($sortedWeds) / 2);
                $isLastSubRonde = $subRondeIdx === count($niveau['sub_rondes']) - 1;
                $isLastColumn = $isLastNiveau && $isLastSubRonde;
                $isRonde1 = str_ends_with($ronde['ronde'], '_1');
                $isRonde2 = str_ends_with($ronde['ronde'], '_2');

                $verticalOffset = $isRonde1 ? -(self::SLOT_HEIGHT / 2) : 0;

                // Bepaal A-ronde naam voor placeholder
                $aRondeNaam = '';
                if ($isRonde2) {
                    if (str_contains($ronde['ronde'], 'zestiende')) $aRondeNaam = 'A-1/16';
                    elseif (str_contains($ronde['ronde'], 'achtste')) $aRondeNaam = 'A-1/8';
                    elseif (str_contains($ronde['ronde'], 'kwart')) $aRondeNaam = 'A-1/4';
                    elseif (str_contains($ronde['ronde'], 'halve')) $aRondeNaam = 'A-1/2';
                }

                foreach ($sortedWeds as $i => &$wed) {
                    if ($i < $halfCount) {
                        // Bovenste helft
                        $spacing = $helftHoogte / $halfCount;
                        $topPos = $i * $spacing + ($spacing - self::POTJE_HEIGHT) / 2 + $verticalOffset;
                    } else {
                        // Onderste helft
                        $lowerIdx = $i - $halfCount;
                        $spacing = $helftHoogte / $halfCount;
                        $mirroredOffset = $isRonde1 ? self::SLOT_HEIGHT / 2 : 0;
                        $topPos = $helftHoogte + self::HORIZON_HEIGHT + $lowerIdx * $spacing + ($spacing - self::POTJE_HEIGHT) / 2 + $mirroredOffset;
                    }

                    $wed['_layout'] = [
                        'top' => $topPos,
                        'is_last_column' => $isLastColumn,
                        'is_ronde2' => $isRonde2,
                        'a_ronde_naam' => $aRondeNaam,
                        'is_mirrored' => $i >= $halfCount,
                        'visual_slot_wit' => $i * 2 + 1,
                        'visual_slot_blauw' => $i * 2 + 2,
                    ];
                }
                unset($wed);

                // Sla gesorteerde wedstrijden terug op
                $ronde['wedstrijden'] = $sortedWeds;
                $ronde['_is_last_column'] = $isLastColumn;
            }
            unset($ronde);
        }
        unset($niveau);

        // Medaille data
        $medailleData = $this->berekenBMedailles($niveaus, $helftHoogte, $wedsPerHelft);

        return [
            'niveaus' => $niveaus,
            'totale_hoogte' => $totaleHoogte,
            'medaille_data' => $medailleData,
            'rondes_flat' => $rondesFlat,
        ];
    }

    /**
     * Groepeer wedstrijden per ronde en sorteer op volgorde.
     */
    private function groepeerPerRonde(array $wedstrijden): array
    {
        if (empty($wedstrijden)) {
            return [];
        }

        // Groepeer op ronde
        $rondesMap = [];
        foreach ($wedstrijden as $wed) {
            $ronde = $wed['ronde'] ?? 'onbekend';
            $rondesMap[$ronde][] = $wed;
        }

        // Sorteer rondes op volgorde
        $rondes = [];
        foreach ($rondesMap as $ronde => $weds) {
            // Sorteer wedstrijden op bracket_positie
            usort($weds, fn($a, $b) => ($a['bracket_positie'] ?? 0) - ($b['bracket_positie'] ?? 0));

            $rondes[] = [
                'naam' => self::RONDE_NAMEN[$ronde] ?? str_replace(['b_', '_'], ['B ', ' '], $ronde),
                'ronde' => $ronde,
                'wedstrijden' => $weds,
                '_volgorde' => self::RONDE_VOLGORDE[$ronde] ?? 99,
            ];
        }

        usort($rondes, fn($a, $b) => $a['_volgorde'] - $b['_volgorde']);

        return $rondes;
    }

    /**
     * Recursieve positie-berekening voor A-bracket.
     * Identiek aan de JS berekenPotjeTop().
     */
    private function berekenPotjeTop(int $niveau, int $potjeIdx): float
    {
        if ($niveau <= 0) {
            return $potjeIdx * (self::POTJE_HEIGHT + self::POTJE_GAP);
        }

        // Gecentreerd tussen 2 potjes van vorige niveau
        $prevPotje1 = $potjeIdx * 2;
        $prevPotje2 = $potjeIdx * 2 + 1;
        $top1 = $this->berekenPotjeTop($niveau - 1, $prevPotje1);
        $top2 = $this->berekenPotjeTop($niveau - 1, $prevPotje2);
        $center1 = $top1 + self::POTJE_HEIGHT / 2;
        $center2 = $top2 + self::POTJE_HEIGHT / 2;
        $center = ($center1 + $center2) / 2;

        return $center - self::POTJE_HEIGHT / 2;
    }

    /**
     * Bereken medaille posities voor A-bracket (goud + zilver).
     */
    private function berekenAMedailles(array $laatsteRonde, int $laatsteRondeNiveau): array
    {
        $finale = $laatsteRonde['wedstrijden'][0] ?? null;
        $finaleTop = $this->berekenPotjeTop($laatsteRondeNiveau, 0);

        $winnaar = null;
        $verliezer = null;
        if ($finale && ($finale['is_gespeeld'] ?? false) && $finale['winnaar_id']) {
            if ($finale['winnaar_id'] == ($finale['wit']['id'] ?? null)) {
                $winnaar = $finale['wit'];
                $verliezer = $finale['blauw'];
            } else {
                $winnaar = $finale['blauw'];
                $verliezer = $finale['wit'];
            }
        }

        return [
            'goud' => [
                'top' => $finaleTop,
                'winnaar' => $winnaar,
                'finale_id' => $finale['id'] ?? null,
            ],
            'zilver' => [
                'top' => $finaleTop + self::SLOT_HEIGHT,
                'verliezer' => $verliezer,
                'finale_id' => $finale['id'] ?? null,
            ],
        ];
    }

    /**
     * Bereken medaille posities voor B-bracket (brons 1 + brons 2).
     */
    private function berekenBMedailles(array $niveaus, float $helftHoogte, int $wedsPerHelft): array
    {
        $laatsteNiveau = end($niveaus);
        if (!$laatsteNiveau) {
            return [];
        }

        $laatsteRonde = end($laatsteNiveau['sub_rondes']);
        if (!$laatsteRonde) {
            return [];
        }

        $sortedWeds = $laatsteRonde['wedstrijden'];
        usort($sortedWeds, fn($a, $b) => ($a['bracket_positie'] ?? 0) - ($b['bracket_positie'] ?? 0));
        $halfLaatste = (int) ceil(count($sortedWeds) / 2);
        $spacing = $halfLaatste > 0 ? $helftHoogte / $halfLaatste : 0;

        $medailles = [];

        // Brons 1 (bovenste helft winnaar)
        if (count($sortedWeds) > 0) {
            $wed1 = $sortedWeds[0];
            $winnaar1 = null;
            if (($wed1['is_gespeeld'] ?? false) && $wed1['winnaar_id']) {
                $winnaar1 = $wed1['winnaar_id'] == ($wed1['wit']['id'] ?? null) ? $wed1['wit'] : $wed1['blauw'];
            }
            $bronPos1 = 0 * $spacing + ($spacing - self::POTJE_HEIGHT) / 2 + self::SLOT_HEIGHT / 2;

            $medailles['brons_1'] = [
                'top' => $bronPos1,
                'winnaar' => $winnaar1,
                'wedstrijd_id' => $wed1['id'] ?? null,
            ];
        }

        // Brons 2 (onderste helft winnaar)
        if (count($sortedWeds) > 1) {
            $wed2 = end($sortedWeds);
            $winnaar2 = null;
            if (($wed2['is_gespeeld'] ?? false) && $wed2['winnaar_id']) {
                $winnaar2 = $wed2['winnaar_id'] == ($wed2['wit']['id'] ?? null) ? $wed2['wit'] : $wed2['blauw'];
            }
            $bronPos2 = $helftHoogte + self::HORIZON_HEIGHT + 0 * $spacing + ($spacing - self::POTJE_HEIGHT) / 2 + self::SLOT_HEIGHT / 2;

            $medailles['brons_2'] = [
                'top' => $bronPos2,
                'winnaar' => $winnaar2,
                'wedstrijd_id' => $wed2['id'] ?? null,
            ];
        }

        return $medailles;
    }
}
