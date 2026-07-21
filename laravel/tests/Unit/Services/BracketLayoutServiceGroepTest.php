<?php

namespace Tests\Unit\Services;

use App\Services\BracketLayoutService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * rondeGroep() bepaalt of een ronde in de A- (winnaars) of B-bracket (verliezers) valt.
 * Gebruikt in de publieke favorieten-tab: "A · 1/4 finale" vs "B · 1/8 finale".
 */
class BracketLayoutServiceGroepTest extends TestCase
{
    #[Test]
    public function a_rondes_geven_groep_A(): void
    {
        foreach (['kwartfinale', 'halve_finale', 'finale', 'achtste_finale', 'zestiende_finale'] as $ronde) {
            $this->assertSame('A', BracketLayoutService::rondeGroep($ronde), $ronde);
        }
    }

    #[Test]
    public function b_rondes_geven_groep_B(): void
    {
        foreach (['b_kwartfinale', 'b_achtste_finale_1', 'b_halve_finale_2', 'b_brons', 'b_finale'] as $ronde) {
            $this->assertSame('B', BracketLayoutService::rondeGroep($ronde), $ronde);
        }
    }

    #[Test]
    public function lege_ronde_valt_terug_op_A(): void
    {
        $this->assertSame('A', BracketLayoutService::rondeGroep(''));
    }
}
