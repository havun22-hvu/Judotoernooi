<?php

namespace Tests\Unit\Services;

use App\Models\Club;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Toernooi;
use App\Services\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class ImportServiceExtraTest extends TestCase
{
    use RefreshDatabase;

    private function callPrivate(string $method, object $instance, array $args): mixed
    {
        $ref = new ReflectionMethod(ImportService::class, $method);
        return $ref->invoke($instance, ...$args);
    }

    private function createToernooiWithOrganisator(array $overrides = []): Toernooi
    {
        $organisator = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->dynamischeKlassen()->create(array_merge([
            'organisator_id' => $organisator->id,
        ], $overrides));
        $toernooi->organisatoren()->attach($organisator->id, ['rol' => 'eigenaar']);

        return $toernooi;
    }

    // =========================================================================
    // getWaarde (private) — multi-column
    // =========================================================================

    #[Test]
    public function get_waarde_multi_column(): void
    {
        $service = app(ImportService::class);
        $rij = ['Jan', 'van der', 'Berg'];

        $result = $this->callPrivate('getWaarde', $service, [$rij, '0,1,2']);
        $this->assertEquals('Jan van der Berg', $result);
    }

    #[Test]
    public function get_waarde_single_numeric_index(): void
    {
        $service = app(ImportService::class);
        $rij = ['Jan', 'Club A', '2015'];

        $result = $this->callPrivate('getWaarde', $service, [$rij, '1']);
        $this->assertEquals('Club A', $result);
    }

    #[Test]
    public function get_waarde_by_column_name(): void
    {
        $service = app(ImportService::class);
        $rij = ['naam' => 'Jan', 'club' => 'Club A'];

        $result = $this->callPrivate('getWaarde', $service, [$rij, 'naam']);
        $this->assertEquals('Jan', $result);
    }

    #[Test]
    public function get_waarde_case_insensitive(): void
    {
        $service = app(ImportService::class);
        $rij = ['Naam' => 'Jan', 'Club' => 'Club A'];

        $result = $this->callPrivate('getWaarde', $service, [$rij, 'naam']);
        $this->assertEquals('Jan', $result);
    }

    #[Test]
    public function get_waarde_missing_returns_null(): void
    {
        $service = app(ImportService::class);
        $rij = ['naam' => 'Jan'];

        $result = $this->callPrivate('getWaarde', $service, [$rij, 'nonexistent']);
        $this->assertNull($result);
    }

    // =========================================================================
    // isEmptyRow (private)
    // =========================================================================

    #[Test]
    public function is_empty_row_all_null(): void
    {
        $service = app(ImportService::class);
        $this->assertTrue($this->callPrivate('isEmptyRow', $service, [[null, null, null]]));
    }

    #[Test]
    public function is_empty_row_all_empty_strings(): void
    {
        $service = app(ImportService::class);
        $this->assertTrue($this->callPrivate('isEmptyRow', $service, [['', '', '']]));
    }

    #[Test]
    public function is_empty_row_whitespace_only(): void
    {
        $service = app(ImportService::class);
        $this->assertTrue($this->callPrivate('isEmptyRow', $service, [['  ', "\t", '']]));
    }

    #[Test]
    public function is_empty_row_with_data(): void
    {
        $service = app(ImportService::class);
        $this->assertFalse($this->callPrivate('isEmptyRow', $service, [['Jan', '', '']]));
    }

    // =========================================================================
    // parseGewichtsklasse (private)
    // =========================================================================

    #[Test]
    public function parse_gewichtsklasse_standard(): void
    {
        $service = app(ImportService::class);
        $this->assertEquals('-38', $this->callPrivate('parseGewichtsklasse', $service, ['-38']));
    }

    #[Test]
    public function parse_gewichtsklasse_with_kg_suffix(): void
    {
        $service = app(ImportService::class);
        $this->assertEquals('-38', $this->callPrivate('parseGewichtsklasse', $service, ['-38 kg']));
        $this->assertEquals('-38', $this->callPrivate('parseGewichtsklasse', $service, ['-38kg']));
    }

    #[Test]
    public function parse_gewichtsklasse_with_apostrophe(): void
    {
        $service = app(ImportService::class);
        $this->assertEquals('-38', $this->callPrivate('parseGewichtsklasse', $service, ["'-38"]));
    }

    #[Test]
    public function parse_gewichtsklasse_empty(): void
    {
        $service = app(ImportService::class);
        $this->assertNull($this->callPrivate('parseGewichtsklasse', $service, ['']));
        $this->assertNull($this->callPrivate('parseGewichtsklasse', $service, [null]));
    }

    // =========================================================================
    // gewichtVanKlasse (private)
    // =========================================================================

    #[Test]
    public function gewicht_van_klasse_minus(): void
    {
        $service = app(ImportService::class);
        $this->assertEquals(34.0, $this->callPrivate('gewichtVanKlasse', $service, ['-34']));
    }

    #[Test]
    public function gewicht_van_klasse_plus(): void
    {
        $service = app(ImportService::class);
        $this->assertEquals(63.0, $this->callPrivate('gewichtVanKlasse', $service, ['+63']));
    }

    #[Test]
    public function gewicht_van_klasse_no_match(): void
    {
        $service = app(ImportService::class);
        $this->assertNull($this->callPrivate('gewichtVanKlasse', $service, ['onbekend']));
    }

    // =========================================================================
    // maakFoutLeesbaar (private)
    // =========================================================================

    #[Test]
    public function maak_fout_leesbaar_null_leeftijdsklasse(): void
    {
        $service = app(ImportService::class);
        $result = $this->callPrivate('maakFoutLeesbaar', $service, ['leeftijdsklasse cannot be null', 'Jan']);
        $this->assertStringContainsString('leeftijdsklasse', $result);
    }

    #[Test]
    public function maak_fout_leesbaar_null_geslacht(): void
    {
        $service = app(ImportService::class);
        $result = $this->callPrivate('maakFoutLeesbaar', $service, ['geslacht cannot be null', 'Jan']);
        $this->assertStringContainsString('Geslacht', $result);
    }

    #[Test]
    public function maak_fout_leesbaar_duplicate(): void
    {
        $service = app(ImportService::class);
        $result = $this->callPrivate('maakFoutLeesbaar', $service, ['UNIQUE constraint failed', 'Jan']);
        $this->assertStringContainsString('Dubbele invoer', $result);
    }

    #[Test]
    public function maak_fout_leesbaar_invalid_year(): void
    {
        $service = app(ImportService::class);
        $result = $this->callPrivate('maakFoutLeesbaar', $service, ['Ongeldig geboortejaar: xyz', 'Jan']);
        $this->assertStringContainsString('geboortejaar', $result);
    }

    #[Test]
    public function maak_fout_leesbaar_data_too_long(): void
    {
        $service = app(ImportService::class);
        $result = $this->callPrivate('maakFoutLeesbaar', $service, ['Data too long for column naam', 'Jan']);
        $this->assertStringContainsString('te lang', $result);
    }

    // =========================================================================
    // parseGeboortejaar — more formats
    // =========================================================================

    #[Test]
    public function parse_geboortejaar_backslash_separator(): void
    {
        $this->assertEquals(2015, ImportService::parseGeboortejaar('24\\01\\2015'));
    }

    #[Test]
    public function parse_geboortejaar_space_separator(): void
    {
        $this->assertEquals(2015, ImportService::parseGeboortejaar('24 01 2015'));
    }

    #[Test]
    public function parse_geboortejaar_iso8601(): void
    {
        $this->assertEquals(2015, ImportService::parseGeboortejaar('2015-01-24T12:00:00Z'));
    }

    #[Test]
    public function parse_geboortejaar_from_compact_ddmmyy(): void
    {
        $this->assertEquals(2015, ImportService::parseGeboortejaar('240115'));
    }

    // =========================================================================
    // importeerDeelnemers — coaches creation
    // =========================================================================

    #[Test]
    public function importeer_deelnemers_creates_coaches(): void
    {
        $toernooi = $this->createToernooiWithOrganisator();
        $service = app(ImportService::class);

        $geboortejaar = date('Y') - 6;
        $data = [
            ['naam' => 'Jan Jansen', 'club' => 'TestClub', 'geboortejaar' => (string)$geboortejaar, 'geslacht' => 'M', 'gewicht' => '22.5', 'band' => 'wit'],
            ['naam' => 'Piet Berg', 'club' => 'TestClub', 'geboortejaar' => (string)$geboortejaar, 'geslacht' => 'M', 'gewicht' => '24.0', 'band' => 'geel'],
        ];

        $result = $service->importeerDeelnemers($toernooi, $data);

        $this->assertArrayHasKey('coaches_aangemaakt', $result);
        $this->assertEquals(1, $result['coaches_aangemaakt']); // 1 club = 1 coach
    }

    // =========================================================================
    // importeerDeelnemers — incomplete data
    // =========================================================================

    #[Test]
    public function importeer_deelnemers_marks_incomplete_judoka(): void
    {
        $toernooi = $this->createToernooiWithOrganisator();
        $service = app(ImportService::class);

        // Missing geboortejaar and gewicht → onvolledig
        $data = [
            ['naam' => 'Jan Jansen', 'club' => 'Club A', 'geboortejaar' => '', 'geslacht' => 'M', 'gewicht' => '', 'band' => 'wit'],
        ];

        $result = $service->importeerDeelnemers($toernooi, $data);

        $this->assertEquals(1, $result['geimporteerd']);

        $judoka = Judoka::where('toernooi_id', $toernooi->id)->first();
        $this->assertTrue((bool) $judoka->is_onvolledig);
    }

    // =========================================================================
    // importeerDeelnemers — classification warnings
    // =========================================================================

    #[Test]
    public function importeer_deelnemers_warns_high_weight(): void
    {
        $toernooi = $this->createToernooiWithOrganisator();
        $service = app(ImportService::class);

        $geboortejaar = date('Y') - 6;
        $data = [
            ['naam' => 'Grote Jan', 'club' => 'Club A', 'geboortejaar' => (string)$geboortejaar, 'geslacht' => 'M', 'gewicht' => '120', 'band' => 'wit'],
        ];

        $result = $service->importeerDeelnemers($toernooi, $data);

        $judoka = Judoka::where('toernooi_id', $toernooi->id)->first();
        $this->assertNotNull($judoka->import_warnings);
        $this->assertStringContainsString('hoog', $judoka->import_warnings);
    }

    #[Test]
    public function importeer_deelnemers_warns_low_weight(): void
    {
        $toernooi = $this->createToernooiWithOrganisator();
        $service = app(ImportService::class);

        $geboortejaar = date('Y') - 6;
        $data = [
            ['naam' => 'Kleine Jan', 'club' => 'Club A', 'geboortejaar' => (string)$geboortejaar, 'geslacht' => 'M', 'gewicht' => '12', 'band' => 'wit'],
        ];

        $result = $service->importeerDeelnemers($toernooi, $data);

        $judoka = Judoka::where('toernooi_id', $toernooi->id)->first();
        $this->assertNotNull($judoka->import_warnings);
        $this->assertStringContainsString('laag', $judoka->import_warnings);
    }

    // =========================================================================
    // normaliseerNaam — more edge cases
    // =========================================================================

    #[Test]
    public function normaliseer_naam_het_prefix(): void
    {
        $this->assertEquals('Kees het Mannetje', ImportService::normaliseerNaam('kees het mannetje'));
    }

    #[Test]
    public function normaliseer_naam_mixed_case_prefix(): void
    {
        // 'VAN' in all caps → should become 'van'
        $this->assertEquals('Jan van Berg', ImportService::normaliseerNaam('JAN VAN BERG'));
    }

    // =========================================================================
    // parseGeslacht — additional
    // =========================================================================

    #[Test]
    public function parse_geslacht_j_for_jongen(): void
    {
        $this->assertEquals('M', ImportService::parseGeslacht('J'));
    }

    // =========================================================================
    // parseBand — additional
    // =========================================================================

    #[Test]
    public function parse_band_with_space_suffix(): void
    {
        $this->assertEquals('groen', ImportService::parseBand('groen band'));
    }

    // =========================================================================
    // analyseerCsvData — validation warnings
    // =========================================================================

    #[Test]
    public function analyseer_csv_data_valid_data_no_warnings(): void
    {
        $service = app(ImportService::class);

        $header = ['Naam', 'Geboortejaar', 'Geslacht', 'Gewicht'];
        $data = [
            ['Jan', '2015', 'M', '25.5'],
        ];

        $result = $service->analyseerCsvData($header, $data);

        $this->assertNull($result['detectie']['geboortejaar']['waarschuwing']);
        $this->assertNull($result['detectie']['geslacht']['waarschuwing']);
        $this->assertNull($result['detectie']['gewicht']['waarschuwing']);
    }
}
