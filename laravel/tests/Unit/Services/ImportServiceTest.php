<?php

namespace Tests\Unit\Services;

use App\Services\ImportService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ImportServiceTest extends TestCase
{
    // =========================================================================
    // NORMALISEER NAAM
    // =========================================================================

    #[Test]
    public function normaliseer_naam_capitalizes_correctly(): void
    {
        $this->assertEquals('Jan Jansen', ImportService::normaliseerNaam('jan jansen'));
        $this->assertEquals('Jan Jansen', ImportService::normaliseerNaam('JAN JANSEN'));
    }

    #[Test]
    public function normaliseer_naam_handles_dutch_prefixes(): void
    {
        $this->assertEquals('Jan van der Berg', ImportService::normaliseerNaam('jan van der berg'));
        $this->assertEquals('Piet de Groot', ImportService::normaliseerNaam('PIET DE GROOT'));
        $this->assertEquals('Anna den Boer', ImportService::normaliseerNaam('anna den boer'));
    }

    #[Test]
    public function normaliseer_naam_trims_whitespace(): void
    {
        $this->assertEquals('Jan Jansen', ImportService::normaliseerNaam('  jan jansen  '));
    }

    // =========================================================================
    // PARSE GEBOORTEJAAR
    // =========================================================================

    #[Test]
    public function parse_geboortejaar_from_4_digit_year(): void
    {
        $this->assertEquals(2015, ImportService::parseGeboortejaar('2015'));
        $this->assertEquals(2010, ImportService::parseGeboortejaar(2010));
    }

    #[Test]
    public function parse_geboortejaar_from_2_digit_year(): void
    {
        $this->assertEquals(2015, ImportService::parseGeboortejaar('15'));
        $this->assertEquals(1998, ImportService::parseGeboortejaar('98'));
    }

    #[Test]
    public function parse_geboortejaar_from_date_string(): void
    {
        $this->assertEquals(2015, ImportService::parseGeboortejaar('24-01-2015'));
        $this->assertEquals(2015, ImportService::parseGeboortejaar('2015-01-24'));
    }

    #[Test]
    public function parse_geboortejaar_from_date_with_slashes(): void
    {
        $this->assertEquals(2010, ImportService::parseGeboortejaar('15/03/2010'));
    }

    #[Test]
    public function parse_geboortejaar_from_parenthesized(): void
    {
        $this->assertEquals(2015, ImportService::parseGeboortejaar('(2015)'));
    }

    #[Test]
    public function parse_geboortejaar_throws_for_invalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ImportService::parseGeboortejaar('onzin');
    }

    // =========================================================================
    // PARSE GESLACHT
    // =========================================================================

    #[Test]
    public function parse_geslacht_recognizes_male(): void
    {
        $this->assertEquals('M', ImportService::parseGeslacht('M'));
        $this->assertEquals('M', ImportService::parseGeslacht('m'));
        $this->assertEquals('M', ImportService::parseGeslacht('Man'));
        $this->assertEquals('M', ImportService::parseGeslacht('Jongen'));
    }

    #[Test]
    public function parse_geslacht_recognizes_female(): void
    {
        $this->assertEquals('V', ImportService::parseGeslacht('V'));
        $this->assertEquals('V', ImportService::parseGeslacht('v'));
        $this->assertEquals('V', ImportService::parseGeslacht('Vrouw'));
        $this->assertEquals('V', ImportService::parseGeslacht('Meisje'));
    }

    #[Test]
    public function parse_geslacht_defaults_to_m(): void
    {
        $this->assertEquals('M', ImportService::parseGeslacht(''));
        $this->assertEquals('M', ImportService::parseGeslacht('X'));
    }

    // =========================================================================
    // PARSE BAND
    // =========================================================================

    #[Test]
    public function parse_band_recognizes_colors(): void
    {
        $this->assertEquals('wit', ImportService::parseBand('wit'));
        $this->assertEquals('geel', ImportService::parseBand('Geel'));
        $this->assertEquals('oranje', ImportService::parseBand('ORANJE'));
    }

    #[Test]
    public function parse_band_defaults_to_wit(): void
    {
        $this->assertEquals('wit', ImportService::parseBand(''));
        $this->assertEquals('wit', ImportService::parseBand(null));
    }

    // =========================================================================
    // PARSE GEWICHT
    // =========================================================================

    #[Test]
    public function parse_gewicht_handles_point_decimal(): void
    {
        $this->assertEquals(25.5, ImportService::parseGewicht('25.5'));
    }

    #[Test]
    public function parse_gewicht_handles_comma_decimal(): void
    {
        $this->assertEquals(25.5, ImportService::parseGewicht('25,5'));
    }

    #[Test]
    public function parse_gewicht_returns_null_for_empty(): void
    {
        $this->assertNull(ImportService::parseGewicht(''));
        $this->assertNull(ImportService::parseGewicht(null));
    }

    #[Test]
    public function parse_gewicht_extracts_number(): void
    {
        $this->assertEquals(30.0, ImportService::parseGewicht('30 kg'));
    }
}
