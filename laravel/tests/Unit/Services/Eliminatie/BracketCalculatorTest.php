<?php

namespace Tests\Unit\Services\Eliminatie;

use App\Services\Eliminatie\BracketCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit + mutation-killer tests for BracketCalculator. It is pure stateless math
 * (no DB) yet had no dedicated test — only indirect coverage via EliminatieService,
 * which left most of its arithmetic/boundary mutants alive. These pin every
 * function's exact output, including the round-name boundaries and power-of-two math.
 * See docs/3-DEVELOPMENT/MUTATION-TESTING.md.
 */
class BracketCalculatorTest extends TestCase
{
    private BracketCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new BracketCalculator();
    }

    // --- berekenLocaties: wit = 2N-1, blauw = 2N ---

    #[Test]
    public function bereken_locaties_maps_position_to_white_blue_slots(): void
    {
        $this->assertSame(['locatie_wit' => 1, 'locatie_blauw' => 2], $this->calc->berekenLocaties(1));
        $this->assertSame(['locatie_wit' => 3, 'locatie_blauw' => 4], $this->calc->berekenLocaties(2));
        $this->assertSame(['locatie_wit' => 5, 'locatie_blauw' => 6], $this->calc->berekenLocaties(3));
    }

    // --- getRondeNaam by participant count (match) ---

    public static function rondeNaamVoorAantal(): array
    {
        return [
            [32, 'zestiende_finale'],
            [16, 'achtste_finale'],
            [8, 'kwartfinale'],
            [4, 'halve_finale'],
            [2, 'finale'],
            [64, 'achtste_finale'], // default
            [3, 'achtste_finale'],  // default
        ];
    }

    #[Test]
    #[DataProvider('rondeNaamVoorAantal')]
    public function ronde_naam_for_participant_count(int $n, string $expected): void
    {
        $this->assertSame($expected, $this->calc->getRondeNaam($n, true));
    }

    // --- getRondeNaam by total judokas (boundary cascade) ---

    public static function rondeNaamVoorTotaal(): array
    {
        return [
            [33, 'tweeendertigste_finale'],
            [32, 'zestiende_finale'],   // 32 > 16
            [17, 'zestiende_finale'],
            [16, 'achtste_finale'],     // 16 > 8
            [9, 'achtste_finale'],
            [8, 'kwartfinale'],         // 8 > 4
            [5, 'kwartfinale'],
            [4, 'halve_finale'],        // 4 > 2
            [3, 'halve_finale'],
            [2, 'finale'],
            [1, 'finale'],
        ];
    }

    #[Test]
    #[DataProvider('rondeNaamVoorTotaal')]
    public function ronde_naam_for_total_judokas(int $n, string $expected): void
    {
        $this->assertSame($expected, $this->calc->getRondeNaam($n));
    }

    // --- getBRondeNaam (match) ---

    public static function bRondeNaam(): array
    {
        return [
            [16, 'b_zestiende_finale'],
            [8, 'b_achtste_finale'],
            [4, 'b_kwartfinale'],
            [2, 'b_halve_finale'],
            [6, 'b_kwartfinale'], // default
        ];
    }

    #[Test]
    #[DataProvider('bRondeNaam')]
    public function b_ronde_naam(int $wedstrijden, string $expected): void
    {
        $this->assertSame($expected, $this->calc->getBRondeNaam($wedstrijden));
    }

    // --- berekenDoel: largest power of two <= n ---

    public static function doelen(): array
    {
        return [
            [-5, 0], [0, 0], [1, 1], [2, 2], [3, 2], [4, 4],
            [7, 4], [8, 8], [15, 8], [16, 16], [31, 16], [32, 32],
        ];
    }

    #[Test]
    #[DataProvider('doelen')]
    public function bereken_doel(int $n, int $expected): void
    {
        $this->assertSame($expected, $this->calc->berekenDoel($n));
    }

    // --- berekenMinimaleBWedstrijden (boundary cascade) ---

    public static function minimaleB(): array
    {
        return [
            [4, 2], [5, 4], [8, 4], [9, 8], [16, 8], [17, 16], [32, 16], [33, 32],
        ];
    }

    #[Test]
    #[DataProvider('minimaleB')]
    public function bereken_minimale_b_wedstrijden(int $verliezers, int $expected): void
    {
        $this->assertSame($expected, $this->calc->berekenMinimaleBWedstrijden($verliezers));
    }

    // --- berekenBracketParams: both branches of v1 > 0 ---

    #[Test]
    public function bracket_params_when_no_first_wave_losers(): void
    {
        // n=8 → d=8, v1=0 → else-branch: a1=d/2=4, a2=d/4=2.
        $this->assertSame([
            'd' => 8, 'v1' => 0, 'a1Verliezers' => 4, 'a2Verliezers' => 2,
            'eersteGolf' => 6, 'dubbelRondes' => true,
        ], $this->calc->berekenBracketParams(8));
    }

    #[Test]
    public function bracket_params_with_first_wave_losers(): void
    {
        // n=6 → d=4, v1=2 → if-branch: a1=v1=2, a2=d/2=2 → dubbelRondes 2>2 = false.
        $this->assertSame([
            'd' => 4, 'v1' => 2, 'a1Verliezers' => 2, 'a2Verliezers' => 2,
            'eersteGolf' => 4, 'dubbelRondes' => false,
        ], $this->calc->berekenBracketParams(6));
    }

    // --- berekenStatistieken: dubbel vs ijf differ on b/totaal ---

    #[Test]
    public function statistieken_dubbel(): void
    {
        $this->assertSame([
            'judokas' => 6, 'type' => 'dubbel', 'doel' => 4, 'v1' => 2,
            'a1_verliezers' => 2, 'a2_verliezers' => 2, 'eerste_golf' => 4,
            'b_start_wedstrijden' => 2, 'a_wedstrijden' => 5, 'b_wedstrijden' => 2,
            'totaal_wedstrijden' => 7, 'eerste_ronde' => 'kwartfinale',
            'eerste_ronde_wedstrijden' => 2, 'a_byes' => 2, 'b_byes' => 0,
            'dubbel_rondes' => false,
        ], $this->calc->berekenStatistieken(6, 'dubbel'));
    }

    #[Test]
    public function statistieken_ijf_uses_fixed_b_and_total(): void
    {
        $stats = $this->calc->berekenStatistieken(6, 'ijf');
        $this->assertSame('ijf', $stats['type']);
        $this->assertSame(4, $stats['b_wedstrijden']);        // ijf fixed 4
        $this->assertSame(9, $stats['totaal_wedstrijden']);   // n-1+4
        $this->assertSame(5, $stats['a_wedstrijden']);        // n-1
    }
}
