<?php

namespace Tests\Unit\Services;

use App\Services\BlokMatVerdelingService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class BlokMatVerdelingServiceTest extends TestCase
{
    private BlokMatVerdelingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BlokMatVerdelingService::class);
    }

    private function callPrivate(string $method, array $args): mixed
    {
        $ref = new ReflectionMethod(BlokMatVerdelingService::class, $method);
        return $ref->invoke($this->service, ...$args);
    }

    // ========================================================================
    // extractLeeftijdUitCategorieKey
    // ========================================================================

    #[Test]
    public function extract_leeftijd_u7_geeft_7(): void
    {
        $this->assertEquals(7, $this->callPrivate('extractLeeftijdUitCategorieKey', ['u7']));
    }

    #[Test]
    public function extract_leeftijd_u11_geeft_11(): void
    {
        $this->assertEquals(11, $this->callPrivate('extractLeeftijdUitCategorieKey', ['u11']));
    }

    #[Test]
    public function extract_leeftijd_met_suffix(): void
    {
        $this->assertEquals(9, $this->callPrivate('extractLeeftijdUitCategorieKey', ['u9_geel_plus']));
        $this->assertEquals(13, $this->callPrivate('extractLeeftijdUitCategorieKey', ['u13_d']));
    }

    #[Test]
    public function extract_leeftijd_null_geeft_999(): void
    {
        $this->assertEquals(999, $this->callPrivate('extractLeeftijdUitCategorieKey', [null]));
    }

    #[Test]
    public function extract_leeftijd_zonder_getal_geeft_999(): void
    {
        $this->assertEquals(999, $this->callPrivate('extractLeeftijdUitCategorieKey', ['minis']));
    }

    // ========================================================================
    // extractGewichtVoorSortering
    // ========================================================================

    #[Test]
    public function extract_gewicht_min_format(): void
    {
        $this->assertEquals(24.0, $this->callPrivate('extractGewichtVoorSortering', ['-24']));
        $this->assertEquals(60.0, $this->callPrivate('extractGewichtVoorSortering', ['-60']));
    }

    #[Test]
    public function extract_gewicht_min_format_met_kg(): void
    {
        $this->assertEquals(24.0, $this->callPrivate('extractGewichtVoorSortering', ['-24kg']));
    }

    #[Test]
    public function extract_gewicht_plus_format(): void
    {
        // +90 → 1090 (90 + 1000 zodat + klassen achteraan komen)
        $this->assertEquals(1090.0, $this->callPrivate('extractGewichtVoorSortering', ['+90']));
    }

    #[Test]
    public function extract_gewicht_range_format(): void
    {
        // "24-27" → eerste waarde = 24
        $this->assertEquals(24.0, $this->callPrivate('extractGewichtVoorSortering', ['24-27']));
    }

    #[Test]
    public function extract_gewicht_null_geeft_0(): void
    {
        $this->assertEquals(0.0, $this->callPrivate('extractGewichtVoorSortering', [null]));
    }

    #[Test]
    public function extract_gewicht_leeg_geeft_0(): void
    {
        $this->assertEquals(0.0, $this->callPrivate('extractGewichtVoorSortering', ['']));
    }

    // ========================================================================
    // hashToewijzingen
    // ========================================================================

    #[Test]
    public function hash_is_deterministisch(): void
    {
        $toewijzingen = ['cat_a' => 0, 'cat_b' => 1, 'cat_c' => 0];

        $hash1 = $this->callPrivate('hashToewijzingen', [$toewijzingen]);
        $hash2 = $this->callPrivate('hashToewijzingen', [$toewijzingen]);

        $this->assertEquals($hash1, $hash2);
    }

    #[Test]
    public function hash_verschilt_bij_andere_input(): void
    {
        $hash1 = $this->callPrivate('hashToewijzingen', [['a' => 0, 'b' => 1]]);
        $hash2 = $this->callPrivate('hashToewijzingen', [['a' => 1, 'b' => 0]]);

        $this->assertNotEquals($hash1, $hash2);
    }
}
