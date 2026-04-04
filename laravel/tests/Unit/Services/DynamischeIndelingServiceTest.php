<?php

namespace Tests\Unit\Services;

use App\Services\DynamischeIndelingService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class DynamischeIndelingServiceTest extends TestCase
{
    private DynamischeIndelingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DynamischeIndelingService();
    }

    private function callPrivate(string $method, array $args): mixed
    {
        $ref = new ReflectionMethod(DynamischeIndelingService::class, $method);
        return $ref->invoke($this->service, ...$args);
    }

    private function setPrivateProperty(string $property, mixed $value): void
    {
        $ref = new ReflectionProperty(DynamischeIndelingService::class, $property);
        $ref->setValue($this->service, $value);
    }

    // ========================================================================
    // bandNaarNummer
    // ========================================================================

    #[Test]
    public function band_naar_nummer_wit_is_0(): void
    {
        $this->assertEquals(0, $this->callPrivate('bandNaarNummer', ['wit']));
    }

    #[Test]
    public function band_naar_nummer_zwart_is_6(): void
    {
        $this->assertEquals(6, $this->callPrivate('bandNaarNummer', ['zwart']));
    }

    #[Test]
    public function band_naar_nummer_alle_kleuren(): void
    {
        $verwacht = [
            'wit' => 0, 'geel' => 1, 'oranje' => 2,
            'groen' => 3, 'blauw' => 4, 'bruin' => 5, 'zwart' => 6,
        ];

        foreach ($verwacht as $band => $nummer) {
            $this->assertEquals($nummer, $this->callPrivate('bandNaarNummer', [$band]),
                "Band '{$band}' zou {$nummer} moeten zijn");
        }
    }

    #[Test]
    public function band_naar_nummer_null_geeft_0(): void
    {
        $this->assertEquals(0, $this->callPrivate('bandNaarNummer', [null]));
    }

    #[Test]
    public function band_naar_nummer_met_spatie_pakt_eerste_woord(): void
    {
        // "Oranje Groen" → eerste woord "oranje" → 2
        $this->assertEquals(2, $this->callPrivate('bandNaarNummer', ['Oranje Groen']));
    }

    #[Test]
    public function band_naar_nummer_case_insensitive(): void
    {
        $this->assertEquals(4, $this->callPrivate('bandNaarNummer', ['BLAUW']));
        $this->assertEquals(1, $this->callPrivate('bandNaarNummer', ['Geel']));
    }

    #[Test]
    public function band_naar_nummer_onbekend_geeft_0(): void
    {
        $this->assertEquals(0, $this->callPrivate('bandNaarNummer', ['paars']));
    }

    // ========================================================================
    // berekenBandRange
    // ========================================================================

    #[Test]
    public function bereken_band_range_zelfde_band(): void
    {
        $judokas = [
            (object) ['band' => 'wit'],
            (object) ['band' => 'wit'],
        ];

        $this->assertEquals(0, $this->callPrivate('berekenBandRange', [$judokas]));
    }

    #[Test]
    public function bereken_band_range_verschil(): void
    {
        $judokas = [
            (object) ['band' => 'wit'],
            (object) ['band' => 'groen'],
        ];

        // groen=3, wit=0 → range=3
        $this->assertEquals(3, $this->callPrivate('berekenBandRange', [$judokas]));
    }

    #[Test]
    public function bereken_band_range_leeg(): void
    {
        $this->assertEquals(0, $this->callPrivate('berekenBandRange', [[]]));
    }

    // ========================================================================
    // berekenScore
    // ========================================================================

    #[Test]
    public function bereken_score_eerste_voorkeur_is_0(): void
    {
        $this->setPrivateProperty('config', ['poule_grootte_voorkeur' => [5, 4, 6, 3]]);

        $poules = [
            ['judokas' => array_fill(0, 5, 'j')], // grootte 5 = eerste voorkeur
        ];

        $this->assertEquals(0.0, $this->callPrivate('berekenScore', [$poules]));
    }

    #[Test]
    public function bereken_score_tweede_voorkeur_is_5(): void
    {
        $this->setPrivateProperty('config', ['poule_grootte_voorkeur' => [5, 4, 6, 3]]);

        $poules = [
            ['judokas' => array_fill(0, 4, 'j')], // grootte 4 = tweede voorkeur
        ];

        $this->assertEquals(5.0, $this->callPrivate('berekenScore', [$poules]));
    }

    #[Test]
    public function bereken_score_orphan_is_100(): void
    {
        $this->setPrivateProperty('config', ['poule_grootte_voorkeur' => [5, 4, 6, 3]]);

        $poules = [
            ['judokas' => ['j']], // grootte 1 = orphan
        ];

        $this->assertEquals(100.0, $this->callPrivate('berekenScore', [$poules]));
    }

    #[Test]
    public function bereken_score_meerdere_poules_sommeren(): void
    {
        $this->setPrivateProperty('config', ['poule_grootte_voorkeur' => [5, 4, 6, 3]]);

        $poules = [
            ['judokas' => array_fill(0, 5, 'j')], // 0 punten
            ['judokas' => array_fill(0, 4, 'j')], // 5 punten
            ['judokas' => ['j']],                   // 100 punten
        ];

        $this->assertEquals(105.0, $this->callPrivate('berekenScore', [$poules]));
    }

    // ========================================================================
    // berekenStatistieken
    // ========================================================================

    #[Test]
    public function bereken_statistieken_leeg_geeft_nullen(): void
    {
        $result = $this->callPrivate('berekenStatistieken', [[]]);

        $this->assertEquals(0, $result['leeftijd_gem']);
        $this->assertEquals(0, $result['gewicht_max']);
    }

    #[Test]
    public function bereken_statistieken_berekent_gem_en_max(): void
    {
        $poules = [
            ['leeftijd_range' => 2, 'gewicht_range' => 3.0],
            ['leeftijd_range' => 4, 'gewicht_range' => 5.0],
        ];

        $result = $this->callPrivate('berekenStatistieken', [$poules]);

        $this->assertEquals(3.0, $result['leeftijd_gem']); // (2+4)/2
        $this->assertEquals(4, $result['leeftijd_max']);
        $this->assertEquals(4.0, $result['gewicht_gem']); // (3+5)/2
        $this->assertEquals(5.0, $result['gewicht_max']);
    }
}
