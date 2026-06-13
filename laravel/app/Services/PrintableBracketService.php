<?php

namespace App\Services;

use App\Models\Poule;
use App\Services\Eliminatie\BracketCalculator;
use InvalidArgumentException;

/**
 * Builds bracket render data for the Noodplan print views.
 *
 * Bridges between three input shapes (N, poule pre-match, poule live) and the
 * existing BracketLayoutService. Returns one consistent shape consumed by the
 * SVG print views.
 */
class PrintableBracketService
{
    public function __construct(
        private BracketLayoutService $bracketLayout,
        private BracketCalculator $bracketCalc,
    ) {}

    /**
     * Variant 1: empty bracket for N participants (no poule).
     *
     * Produces synthetic A + B wedstrijden so the existing layout service
     * computes the same geometry as a real poule of size N would.
     */
    public function buildLeegOpMaat(int $aantal): array
    {
        $this->guardAantal($aantal);

        $aWedstrijden = $this->maakSyntheseAWedstrijden($aantal);
        $bWedstrijden = $this->maakSyntheseBWedstrijden($aantal);

        return [
            'a_bracket' => $this->bracketLayout->berekenABracketLayout($aWedstrijden),
            'b_bracket' => $this->bracketLayout->berekenBBracketLayout($bWedstrijden),
            'meta' => [
                'variant' => 'leeg',
                'aantal_deelnemers' => $aantal,
                'titel' => __("Leeg template – :n judoka's", ['n' => $aantal]),
                'stempel' => __('Leeg template'),
            ],
        ];
    }

    /**
     * Variant 2: starting positions for an existing poule (names in, scores out).
     */
    public function buildStartposities(Poule $poule): array
    {
        $this->guardEliminatiePoule($poule);

        $wedstrijden = $this->loadPouleWedstrijden($poule);
        $cleared = array_map(fn(array $w): array => $this->stripUitslag($w), $wedstrijden);

        [$a, $b] = $this->splitsAB($cleared);

        return [
            'a_bracket' => $this->bracketLayout->berekenABracketLayout($a),
            'b_bracket' => $this->bracketLayout->berekenBBracketLayout($b),
            'meta' => [
                'variant' => 'startposities',
                'aantal_deelnemers' => $poule->judokas->count(),
                'titel' => $poule->getDisplayTitel(),
                'stempel' => __('Startposities'),
            ],
        ];
    }

    /**
     * Variant 3: live snapshot at request time.
     */
    public function buildLive(Poule $poule): array
    {
        $this->guardEliminatiePoule($poule);

        $wedstrijden = $this->loadPouleWedstrijden($poule);
        [$a, $b] = $this->splitsAB($wedstrijden);

        return [
            'a_bracket' => $this->bracketLayout->berekenABracketLayout($a),
            'b_bracket' => $this->bracketLayout->berekenBBracketLayout($b),
            'meta' => [
                'variant' => 'live',
                'aantal_deelnemers' => $poule->judokas->count(),
                'titel' => $poule->getDisplayTitel(),
                'stempel' => __('Live snapshot om :tijd', ['tijd' => now()->format('H:i')]),
            ],
        ];
    }

    private function guardAantal(int $aantal): void
    {
        if ($aantal < 2 || $aantal > 64) {
            throw new InvalidArgumentException("Aantal deelnemers moet tussen 2 en 64 liggen (kreeg: {$aantal}).");
        }
    }

    private function guardEliminatiePoule(Poule $poule): void
    {
        if ($poule->type !== 'eliminatie') {
            throw new InvalidArgumentException("Poule {$poule->id} is geen eliminatie (type: {$poule->type}).");
        }
    }

    /**
     * Map a poule's wedstrijden into the array shape that BracketLayoutService expects.
     *
     * Mirrors WedstrijdSchemaService::getSchemaVoorMat() output for a single poule.
     */
    private function loadPouleWedstrijden(Poule $poule): array
    {
        $poule->loadMissing([
            'wedstrijden.judokaWit.club',
            'wedstrijden.judokaBlauw.club',
            'wedstrijden.winnaar',
        ]);

        return $poule->wedstrijden
            ->sortBy('volgorde')
            ->values()
            ->map(fn($w) => [
                'id' => $w->id,
                'volgorde' => $w->volgorde,
                'ronde' => $w->ronde,
                'bracket_positie' => $w->bracket_positie,
                'wit' => $w->judokaWit ? [
                    'id' => $w->judokaWit->id,
                    'naam' => $w->judokaWit->naam,
                    'club' => $w->judokaWit->club?->naam,
                ] : null,
                'blauw' => $w->judokaBlauw ? [
                    'id' => $w->judokaBlauw->id,
                    'naam' => $w->judokaBlauw->naam,
                    'club' => $w->judokaBlauw?->club?->naam,
                ] : null,
                'uitslag_wit' => $w->uitslag_wit,
                'uitslag_blauw' => $w->uitslag_blauw,
                'winnaar_id' => $w->winnaar_id,
                'is_gespeeld' => $w->winnaar_id !== null,
            ])
            ->all();
    }

    private function stripUitslag(array $wedstrijd): array
    {
        $wedstrijd['uitslag_wit'] = null;
        $wedstrijd['uitslag_blauw'] = null;
        $wedstrijd['winnaar_id'] = null;
        $wedstrijd['is_gespeeld'] = false;

        if (! str_starts_with($wedstrijd['ronde'] ?? '', 'tweeendertigste_finale')
            && ! str_starts_with($wedstrijd['ronde'] ?? '', 'zestiende_finale')
            && ! str_starts_with($wedstrijd['ronde'] ?? '', 'achtste_finale')
            && ! str_starts_with($wedstrijd['ronde'] ?? '', 'kwartfinale')
            && ! str_starts_with($wedstrijd['ronde'] ?? '', 'halve_finale')
            && ! str_starts_with($wedstrijd['ronde'] ?? '', 'finale')
            && ! str_starts_with($wedstrijd['ronde'] ?? '', 'b_')) {
            return $wedstrijd;
        }

        return $wedstrijd;
    }

    /**
     * Split a list of wedstrijden into A-group (hoofdtoernooi) and B-group (herkansing).
     *
     * @return array{0: array, 1: array}
     */
    private function splitsAB(array $wedstrijden): array
    {
        $a = [];
        $b = [];
        foreach ($wedstrijden as $w) {
            if (str_starts_with($w['ronde'] ?? '', 'b_')) {
                $b[] = $w;
            } else {
                $a[] = $w;
            }
        }
        return [$a, $b];
    }

    /**
     * Generate synthetic A-bracket wedstrijden for N participants (no names, no scores).
     *
     * Uses the same round-naming convention as real eliminations so BracketLayoutService
     * recognises and orders the rondes correctly.
     */
    private function maakSyntheseAWedstrijden(int $aantal): array
    {
        $doel = $this->bracketCalc->berekenDoel($aantal);
        $v1 = $aantal - $doel;

        $wedstrijden = [];
        $volgorde = 1;
        $bracketPositie = 1;

        if ($v1 > 0) {
            $rondeNaamRonde1 = $this->bracketCalc->getRondeNaam($aantal);
            for ($i = 0; $i < $v1; $i++) {
                $wedstrijden[] = $this->maakLeegWedstrijd(
                    id: null,
                    ronde: $rondeNaamRonde1,
                    bracketPositie: $bracketPositie++,
                    volgorde: $volgorde++,
                );
            }
        }

        $deelnemersInVolleRonde = $doel;
        while ($deelnemersInVolleRonde >= 2) {
            $rondeNaam = $this->bracketCalc->getRondeNaam($deelnemersInVolleRonde, voorAantal: true);
            $wedstrijdenInRonde = (int) ($deelnemersInVolleRonde / 2);
            for ($i = 0; $i < $wedstrijdenInRonde; $i++) {
                $wedstrijden[] = $this->maakLeegWedstrijd(
                    id: null,
                    ronde: $rondeNaam,
                    bracketPositie: $bracketPositie++,
                    volgorde: $volgorde++,
                );
            }
            $deelnemersInVolleRonde = (int) ($deelnemersInVolleRonde / 2);
        }

        return $wedstrijden;
    }

    /**
     * Generate synthetic B-bracket wedstrijden for N participants.
     *
     * Approach: ask BracketCalculator for the minimum number of B-wedstrijden and
     * spread them across b_ rondes from the deepest level upwards. Empty
     * wedstrijden suffice — layout cares about ronde + bracket_positie only.
     */
    private function maakSyntheseBWedstrijden(int $aantal): array
    {
        $params = $this->bracketCalc->berekenBracketParams($aantal);
        $totaalBVerliezers = $params['a1Verliezers'] + $params['a2Verliezers'];

        if ($totaalBVerliezers < 2) {
            return [];
        }

        $bWedstrijden = (int) max(1, ceil($totaalBVerliezers / 2));
        $wedstrijden = [];
        $bracketPositie = 1;
        $volgorde = 1000;

        $rondeNaam = $this->bracketCalc->getBRondeNaam($bWedstrijden);
        for ($i = 0; $i < $bWedstrijden; $i++) {
            $wedstrijden[] = $this->maakLeegWedstrijd(
                id: null,
                ronde: $rondeNaam,
                bracketPositie: $bracketPositie++,
                volgorde: $volgorde++,
            );
        }

        $resterend = (int) ($bWedstrijden / 2);
        $rondeIndex = 1;
        while ($resterend >= 1 && $rondeIndex < 4) {
            $rondeNaamX = "b_halve_finale_" . min(2, $rondeIndex + 1);
            for ($i = 0; $i < $resterend; $i++) {
                $wedstrijden[] = $this->maakLeegWedstrijd(
                    id: null,
                    ronde: $rondeNaamX,
                    bracketPositie: $bracketPositie++,
                    volgorde: $volgorde++,
                );
            }
            $resterend = (int) ($resterend / 2);
            $rondeIndex++;
        }

        return $wedstrijden;
    }

    private function maakLeegWedstrijd(?int $id, string $ronde, int $bracketPositie, int $volgorde): array
    {
        return [
            'id' => $id,
            'volgorde' => $volgorde,
            'ronde' => $ronde,
            'bracket_positie' => $bracketPositie,
            'wit' => null,
            'blauw' => null,
            'uitslag_wit' => null,
            'uitslag_blauw' => null,
            'winnaar_id' => null,
            'is_gespeeld' => false,
        ];
    }
}
