<?php

namespace Tests\Unit\Services;

use App\Models\Club;
use App\Models\Coach;
use App\Models\CoachKaart;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Toernooi;
use App\Services\ImportService;
use App\Services\PouleIndelingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ImportServiceTest extends TestCase
{
    use RefreshDatabase;
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

    // =========================================================================
    // PARSE GEWICHT — EDGE CASES
    // =========================================================================

    #[Test]
    public function parse_gewicht_returns_null_for_non_numeric(): void
    {
        $this->assertNull(ImportService::parseGewicht('abc'));
    }

    #[Test]
    public function parse_gewicht_handles_integer(): void
    {
        $this->assertEquals(30.0, ImportService::parseGewicht(30));
    }

    // =========================================================================
    // NORMALISEER NAAM — EXTRA EDGE CASES
    // =========================================================================

    #[Test]
    public function normaliseer_naam_handles_tussenvoegsel_ter(): void
    {
        $this->assertEquals('Jan ter Horst', ImportService::normaliseerNaam('JAN TER HORST'));
    }

    #[Test]
    public function normaliseer_naam_handles_tussenvoegsel_ten(): void
    {
        $this->assertEquals('Piet ten Broeke', ImportService::normaliseerNaam('piet ten broeke'));
    }

    #[Test]
    public function normaliseer_naam_handles_vd_abbreviation(): void
    {
        $this->assertEquals('Jan vd Berg', ImportService::normaliseerNaam('jan vd berg'));
    }

    #[Test]
    public function normaliseer_naam_handles_single_word(): void
    {
        $this->assertEquals('Jan', ImportService::normaliseerNaam('jan'));
    }

    // =========================================================================
    // PARSE GEBOORTEJAAR — EXTRA FORMATS
    // =========================================================================

    #[Test]
    public function parse_geboortejaar_from_dot_separated_date(): void
    {
        $this->assertEquals(2015, ImportService::parseGeboortejaar('24.01.2015'));
    }

    #[Test]
    public function parse_geboortejaar_from_compact_yyyymmdd(): void
    {
        $this->assertEquals(2015, ImportService::parseGeboortejaar('20150124'));
    }

    #[Test]
    public function parse_geboortejaar_from_compact_ddmmyyyy(): void
    {
        $this->assertEquals(2015, ImportService::parseGeboortejaar('24012015'));
    }

    #[Test]
    public function parse_geboortejaar_from_dutch_month_name(): void
    {
        $this->assertEquals(2015, ImportService::parseGeboortejaar('24 januari 2015'));
    }

    #[Test]
    public function parse_geboortejaar_from_dutch_month_abbreviation(): void
    {
        $this->assertEquals(2010, ImportService::parseGeboortejaar('15 mrt 2010'));
    }

    #[Test]
    public function parse_geboortejaar_from_bracketed_date(): void
    {
        $this->assertEquals(2015, ImportService::parseGeboortejaar('[24-01-2015]'));
    }

    #[Test]
    public function parse_geboortejaar_from_2_digit_year_above_50(): void
    {
        $this->assertEquals(1998, ImportService::parseGeboortejaar('98'));
    }

    #[Test]
    public function parse_geboortejaar_throws_for_empty_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ImportService::parseGeboortejaar('');
    }

    // =========================================================================
    // PARSE GESLACHT — EXTRA VALUES
    // =========================================================================

    #[Test]
    public function parse_geslacht_recognizes_heren(): void
    {
        $this->assertEquals('M', ImportService::parseGeslacht('Heren'));
    }

    #[Test]
    public function parse_geslacht_recognizes_dames(): void
    {
        $this->assertEquals('V', ImportService::parseGeslacht('Dames'));
    }

    // =========================================================================
    // PARSE BAND — EXTRA VALUES
    // =========================================================================

    #[Test]
    public function parse_band_recognizes_all_colors(): void
    {
        $this->assertEquals('groen', ImportService::parseBand('Groen'));
        $this->assertEquals('blauw', ImportService::parseBand('BLAUW'));
        $this->assertEquals('bruin', ImportService::parseBand('bruin'));
        $this->assertEquals('zwart', ImportService::parseBand('Zwart'));
    }

    #[Test]
    public function parse_band_handles_unknown_value_fallback(): void
    {
        // Unknown values fallback to first word lowercased
        $result = ImportService::parseBand('paars band');
        $this->assertEquals('paars', $result);
    }

    // =========================================================================
    // ANALYSEER CSV DATA — COLUMN DETECTION
    // =========================================================================

    #[Test]
    public function analyseer_csv_data_detects_standard_columns(): void
    {
        $service = app(ImportService::class);

        $header = ['Naam', 'Club', 'Geboortejaar', 'Geslacht', 'Gewicht', 'Band'];
        $data = [
            ['Jan Jansen', 'Judo Club A', '2015', 'M', '25.5', 'wit'],
        ];

        $result = $service->analyseerCsvData($header, $data);

        $this->assertEquals('Naam', $result['detectie']['naam']['csv_kolom']);
        $this->assertEquals('Club', $result['detectie']['club']['csv_kolom']);
        $this->assertEquals('Geboortejaar', $result['detectie']['geboortejaar']['csv_kolom']);
        $this->assertEquals('Geslacht', $result['detectie']['geslacht']['csv_kolom']);
        $this->assertEquals('Gewicht', $result['detectie']['gewicht']['csv_kolom']);
        $this->assertEquals('Band', $result['detectie']['band']['csv_kolom']);
    }

    #[Test]
    public function analyseer_csv_data_detects_english_columns(): void
    {
        $service = app(ImportService::class);

        $header = ['Name', 'Weight', 'Gender', 'Birth Year', 'Belt'];
        $data = [
            ['John', '30', 'M', '2015', 'white'],
        ];

        $result = $service->analyseerCsvData($header, $data);

        $this->assertEquals('Name', $result['detectie']['naam']['csv_kolom']);
        $this->assertEquals('Weight', $result['detectie']['gewicht']['csv_kolom']);
        $this->assertEquals('Gender', $result['detectie']['geslacht']['csv_kolom']);
        $this->assertEquals('Birth Year', $result['detectie']['geboortejaar']['csv_kolom']);
        $this->assertEquals('Belt', $result['detectie']['band']['csv_kolom']);
    }

    #[Test]
    public function analyseer_csv_data_detects_alternative_dutch_columns(): void
    {
        $service = app(ImportService::class);

        $header = ['Deelnemer', 'Vereniging', 'Geb.jaar', 'M/V', 'Kg', 'Gordel'];
        $data = [
            ['Piet', 'Club B', '2012', 'V', '35', 'geel'],
        ];

        $result = $service->analyseerCsvData($header, $data);

        $this->assertEquals('Deelnemer', $result['detectie']['naam']['csv_kolom']);
        $this->assertEquals('Vereniging', $result['detectie']['club']['csv_kolom']);
        $this->assertEquals('Geb.jaar', $result['detectie']['geboortejaar']['csv_kolom']);
        $this->assertEquals('M/V', $result['detectie']['geslacht']['csv_kolom']);
        $this->assertEquals('Kg', $result['detectie']['gewicht']['csv_kolom']);
        $this->assertEquals('Gordel', $result['detectie']['band']['csv_kolom']);
    }

    #[Test]
    public function analyseer_csv_data_returns_null_for_undetectable_columns(): void
    {
        $service = app(ImportService::class);

        $header = ['kolom1', 'kolom2', 'kolom3'];
        $data = [['a', 'b', 'c']];

        $result = $service->analyseerCsvData($header, $data);

        $this->assertNull($result['detectie']['naam']['csv_kolom']);
        $this->assertNull($result['detectie']['club']['csv_kolom']);
    }

    #[Test]
    public function analyseer_csv_data_returns_preview_and_count(): void
    {
        $service = app(ImportService::class);

        $header = ['Naam'];
        $data = [];
        for ($i = 0; $i < 10; $i++) {
            $data[] = ["Judoka {$i}"];
        }

        $result = $service->analyseerCsvData($header, $data);

        $this->assertCount(5, $result['preview_data']); // Max 5 preview rows
        $this->assertEquals(10, $result['totaal_rijen']);
    }

    #[Test]
    public function analyseer_csv_data_warns_invalid_geboortejaar(): void
    {
        $service = app(ImportService::class);

        $header = ['Naam', 'Geboortejaar'];
        $data = [
            ['Jan', 'onzin'],
        ];

        $result = $service->analyseerCsvData($header, $data);

        $this->assertNotNull($result['detectie']['geboortejaar']['waarschuwing']);
        $this->assertStringContainsString('Verwacht jaar of datum', $result['detectie']['geboortejaar']['waarschuwing']);
    }

    #[Test]
    public function analyseer_csv_data_warns_invalid_geslacht(): void
    {
        $service = app(ImportService::class);

        $header = ['Naam', 'Geslacht'];
        $data = [
            ['Jan', 'X'],
        ];

        $result = $service->analyseerCsvData($header, $data);

        $this->assertNotNull($result['detectie']['geslacht']['waarschuwing']);
        $this->assertStringContainsString('Verwacht M/V', $result['detectie']['geslacht']['waarschuwing']);
    }

    #[Test]
    public function analyseer_csv_data_warns_invalid_gewicht(): void
    {
        $service = app(ImportService::class);

        $header = ['Naam', 'Gewicht'];
        $data = [
            ['Jan', '5'], // Too low (< 10)
        ];

        $result = $service->analyseerCsvData($header, $data);

        $this->assertNotNull($result['detectie']['gewicht']['waarschuwing']);
        $this->assertStringContainsString('Verwacht gewicht', $result['detectie']['gewicht']['waarschuwing']);
    }

    #[Test]
    public function analyseer_csv_data_warns_empty_column(): void
    {
        $service = app(ImportService::class);

        $header = ['Naam', 'Gewicht'];
        $data = [
            ['Jan', ''],
            ['Piet', ''],
        ];

        $result = $service->analyseerCsvData($header, $data);

        $this->assertEquals('Kolom bevat geen data', $result['detectie']['gewicht']['waarschuwing']);
    }

    #[Test]
    public function analyseer_csv_data_skips_gewichtsklasse_for_dynamic_tournaments(): void
    {
        $service = app(ImportService::class);

        $header = ['Naam', 'Gewichtsklasse'];
        $data = [['Jan', '-30']];

        // With heeftVasteGewichtsklassen = false, gewichtsklasse column should not be detected
        $result = $service->analyseerCsvData($header, $data, false);

        $this->assertArrayNotHasKey('gewichtsklasse', $result['detectie']);
    }

    #[Test]
    public function analyseer_csv_data_detects_gewichtsklasse_for_fixed_tournaments(): void
    {
        $service = app(ImportService::class);

        $header = ['Naam', 'Gewichtsklasse'];
        $data = [['Jan', '-30']];

        $result = $service->analyseerCsvData($header, $data, true);

        $this->assertArrayHasKey('gewichtsklasse', $result['detectie']);
        $this->assertEquals('Gewichtsklasse', $result['detectie']['gewichtsklasse']['csv_kolom']);
    }

    #[Test]
    public function analyseer_csv_data_detects_jbn_lidnummer(): void
    {
        $service = app(ImportService::class);

        $header = ['Naam', 'JBN Nummer'];
        $data = [['Jan', '123456']];

        $result = $service->analyseerCsvData($header, $data);

        $this->assertEquals('JBN Nummer', $result['detectie']['jbn_lidnummer']['csv_kolom']);
    }

    // =========================================================================
    // IMPORTEER DEELNEMERS — BASIC IMPORT
    // =========================================================================

    private function createToernooiWithOrganisator(): Toernooi
    {
        $organisator = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->dynamischeKlassen()->create([
            'organisator_id' => $organisator->id,
        ]);
        $toernooi->organisatoren()->attach($organisator->id, ['rol' => 'eigenaar']);

        return $toernooi;
    }

    #[Test]
    public function importeer_deelnemers_returns_empty_result_for_empty_data(): void
    {
        $toernooi = $this->createToernooiWithOrganisator();
        $service = app(ImportService::class);

        $result = $service->importeerDeelnemers($toernooi, []);

        $this->assertEquals(0, $result['geimporteerd']);
        $this->assertEquals(0, $result['overgeslagen']);
        $this->assertContains('Geen data om te importeren', $result['fouten']);
    }

    #[Test]
    public function importeer_deelnemers_imports_single_judoka(): void
    {
        $toernooi = $this->createToernooiWithOrganisator();
        $service = app(ImportService::class);

        $geboortejaar = date('Y') - 5; // Age 5 = mini
        $data = [
            ['naam' => 'Jan Jansen', 'club' => 'Judo Club Test', 'geboortejaar' => (string)$geboortejaar, 'geslacht' => 'M', 'gewicht' => '22.5', 'band' => 'wit'],
        ];

        $result = $service->importeerDeelnemers($toernooi, $data);

        $this->assertEquals(1, $result['geimporteerd']);
        $this->assertEquals(0, $result['overgeslagen']);
        $this->assertEmpty($result['fouten']);

        // Verify judoka in database
        $judoka = Judoka::where('toernooi_id', $toernooi->id)->first();
        $this->assertNotNull($judoka);
        $this->assertEquals('Jan Jansen', $judoka->naam);
        $this->assertEquals($geboortejaar, $judoka->geboortejaar);
        $this->assertEquals('M', $judoka->geslacht);
        $this->assertEquals(22.5, $judoka->gewicht);
        $this->assertEquals('wit', $judoka->band);
    }

    #[Test]
    public function importeer_deelnemers_imports_multiple_judokas(): void
    {
        $toernooi = $this->createToernooiWithOrganisator();
        $service = app(ImportService::class);

        $geboortejaar = date('Y') - 8;
        $data = [
            ['naam' => 'Jan Jansen', 'club' => 'Club A', 'geboortejaar' => (string)$geboortejaar, 'geslacht' => 'M', 'gewicht' => '25', 'band' => 'wit'],
            ['naam' => 'Piet de Groot', 'club' => 'Club B', 'geboortejaar' => (string)$geboortejaar, 'geslacht' => 'M', 'gewicht' => '30', 'band' => 'geel'],
            ['naam' => 'Anna Visser', 'club' => 'Club A', 'geboortejaar' => (string)$geboortejaar, 'geslacht' => 'V', 'gewicht' => '28', 'band' => 'oranje'],
        ];

        $result = $service->importeerDeelnemers($toernooi, $data);

        $this->assertEquals(3, $result['geimporteerd']);
        $this->assertEquals(3, Judoka::where('toernooi_id', $toernooi->id)->count());
    }

    // =========================================================================
    // IMPORTEER DEELNEMERS — SKIP EMPTY ROWS
    // =========================================================================

    #[Test]
    public function importeer_deelnemers_skips_empty_rows(): void
    {
        $toernooi = $this->createToernooiWithOrganisator();
        $service = app(ImportService::class);

        $geboortejaar = date('Y') - 6;
        $data = [
            ['naam' => 'Jan Jansen', 'club' => 'Club A', 'geboortejaar' => (string)$geboortejaar, 'geslacht' => 'M', 'gewicht' => '22', 'band' => 'wit'],
            ['naam' => '', 'club' => '', 'geboortejaar' => '', 'geslacht' => '', 'gewicht' => '', 'band' => ''],
            ['naam' => null, 'club' => null, 'geboortejaar' => null, 'geslacht' => null, 'gewicht' => null, 'band' => null],
        ];

        $result = $service->importeerDeelnemers($toernooi, $data);

        $this->assertEquals(1, $result['geimporteerd']);
        // Rows without names are returned as null from verwerkRij → counted as skipped
        $this->assertEquals(0, $result['overgeslagen']); // Empty rows are skipped entirely, not counted
    }

    #[Test]
    public function importeer_deelnemers_skips_rows_without_name(): void
    {
        $toernooi = $this->createToernooiWithOrganisator();
        $service = app(ImportService::class);

        $data = [
            ['naam' => '', 'club' => 'Club A', 'geboortejaar' => '2015', 'geslacht' => 'M', 'gewicht' => '22', 'band' => 'wit'],
        ];

        $result = $service->importeerDeelnemers($toernooi, $data);

        $this->assertEquals(0, $result['geimporteerd']);
        $this->assertEquals(1, $result['overgeslagen']);
    }

    // =========================================================================
    // IMPORTEER DEELNEMERS — DUPLICATE DETECTION
    // =========================================================================

    #[Test]
    public function importeer_deelnemers_updates_existing_judoka_same_club(): void
    {
        $toernooi = $this->createToernooiWithOrganisator();
        $organisator = $toernooi->organisatoren()->first();
        $service = app(ImportService::class);

        $geboortejaar = date('Y') - 7;
        $club = Club::create([
            'organisator_id' => $organisator->id,
            'naam' => 'Club A',
        ]);

        // Create existing judoka
        Judoka::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'naam' => 'Jan Jansen',
            'geboortejaar' => $geboortejaar,
            'geslacht' => 'M',
            'band' => 'wit',
            'gewicht' => 22.0,
            'leeftijdsklasse' => 'pupillen',
            'gewichtsklasse' => '-24',
        ]);

        // Import same name+year+club = update (counted as skipped/overgeslagen)
        $data = [
            ['naam' => 'Jan Jansen', 'club' => 'Club A', 'geboortejaar' => (string)$geboortejaar, 'geslacht' => 'M', 'gewicht' => '25', 'band' => 'geel'],
        ];

        $result = $service->importeerDeelnemers($toernooi, $data);

        // Update returns null → counted as overgeslagen
        $this->assertEquals(0, $result['geimporteerd']);
        $this->assertEquals(1, $result['overgeslagen']);

        // Verify judoka was updated
        $judoka = Judoka::where('toernooi_id', $toernooi->id)->where('naam', 'Jan Jansen')->first();
        $this->assertEquals(25.0, $judoka->gewicht);
        $this->assertEquals('geel', $judoka->band);
    }

    #[Test]
    public function importeer_deelnemers_creates_new_for_namesake_different_club(): void
    {
        $toernooi = $this->createToernooiWithOrganisator();
        $organisator = $toernooi->organisatoren()->first();
        $service = app(ImportService::class);

        $geboortejaar = date('Y') - 8;
        $clubA = Club::create([
            'organisator_id' => $organisator->id,
            'naam' => 'Club A',
        ]);

        // Create existing judoka from Club A
        Judoka::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $clubA->id,
            'naam' => 'Jan Jansen',
            'geboortejaar' => $geboortejaar,
            'geslacht' => 'M',
            'band' => 'wit',
            'gewicht' => 22.0,
            'leeftijdsklasse' => 'pupillen',
            'gewichtsklasse' => '-24',
        ]);

        // Import same name+year but different club = new judoka with warning
        $data = [
            ['naam' => 'Jan Jansen', 'club' => 'Club B', 'geboortejaar' => (string)$geboortejaar, 'geslacht' => 'M', 'gewicht' => '25', 'band' => 'geel'],
        ];

        $result = $service->importeerDeelnemers($toernooi, $data);

        $this->assertEquals(1, $result['geimporteerd']);
        $this->assertEquals(2, Judoka::where('toernooi_id', $toernooi->id)->where('naam', 'Jan Jansen')->count());

        // New judoka should have namesake warning
        $newJudoka = Judoka::where('toernooi_id', $toernooi->id)
            ->where('naam', 'Jan Jansen')
            ->where('gewicht', 25.0)
            ->first();
        $this->assertNotNull($newJudoka->import_warnings);
        $this->assertStringContainsString('Naamgenoot', $newJudoka->import_warnings);
    }

    // =========================================================================
    // IMPORTEER DEELNEMERS — INCOMPLETE DATA
    // =========================================================================

    #[Test]
    public function importeer_deelnemers_marks_incomplete_judoka(): void
    {
        $toernooi = $this->createToernooiWithOrganisator();
        $service = app(ImportService::class);

        // Missing geboortejaar and gewicht
        $data = [
            ['naam' => 'Jan Jansen', 'club' => 'Club A', 'geboortejaar' => '', 'geslacht' => 'M', 'gewicht' => '', 'band' => 'wit'],
        ];

        $result = $service->importeerDeelnemers($toernooi, $data);

        $this->assertEquals(1, $result['geimporteerd']);

        $judoka = Judoka::where('toernooi_id', $toernooi->id)->first();
        $this->assertTrue((bool) $judoka->is_onvolledig);
    }

    #[Test]
    public function importeer_deelnemers_warns_high_weight(): void
    {
        $toernooi = $this->createToernooiWithOrganisator();
        $service = app(ImportService::class);

        $geboortejaar = date('Y') - 8;
        $data = [
            ['naam' => 'Jan Jansen', 'club' => 'Club A', 'geboortejaar' => (string)$geboortejaar, 'geslacht' => 'M', 'gewicht' => '120', 'band' => 'wit'],
        ];

        $result = $service->importeerDeelnemers($toernooi, $data);

        $judoka = Judoka::where('toernooi_id', $toernooi->id)->first();
        $this->assertNotNull($judoka->import_warnings);
        $this->assertStringContainsString('lijkt hoog', $judoka->import_warnings);
    }

    #[Test]
    public function importeer_deelnemers_warns_low_weight(): void
    {
        $toernooi = $this->createToernooiWithOrganisator();
        $service = app(ImportService::class);

        $geboortejaar = date('Y') - 5;
        $data = [
            ['naam' => 'Jan Jansen', 'club' => 'Club A', 'geboortejaar' => (string)$geboortejaar, 'geslacht' => 'M', 'gewicht' => '12', 'band' => 'wit'],
        ];

        $result = $service->importeerDeelnemers($toernooi, $data);

        $judoka = Judoka::where('toernooi_id', $toernooi->id)->first();
        $this->assertNotNull($judoka->import_warnings);
        $this->assertStringContainsString('lijkt laag', $judoka->import_warnings);
    }

    #[Test]
    public function importeer_deelnemers_warns_unrecognized_geslacht(): void
    {
        $toernooi = $this->createToernooiWithOrganisator();
        $service = app(ImportService::class);

        $geboortejaar = date('Y') - 6;
        $data = [
            ['naam' => 'Jan Jansen', 'club' => 'Club A', 'geboortejaar' => (string)$geboortejaar, 'geslacht' => 'X', 'gewicht' => '22', 'band' => 'wit'],
        ];

        $result = $service->importeerDeelnemers($toernooi, $data);

        $judoka = Judoka::where('toernooi_id', $toernooi->id)->first();
        $this->assertEquals('M', $judoka->geslacht); // Default to M
        $this->assertNotNull($judoka->import_warnings);
        $this->assertStringContainsString('niet herkend', $judoka->import_warnings);
    }

    // =========================================================================
    // IMPORTEER DEELNEMERS — CLUB CREATION
    // =========================================================================

    #[Test]
    public function importeer_deelnemers_creates_club_when_not_exists(): void
    {
        $toernooi = $this->createToernooiWithOrganisator();
        $service = app(ImportService::class);

        $geboortejaar = date('Y') - 5;
        $data = [
            ['naam' => 'Jan Jansen', 'club' => 'Nieuwe Judo Club', 'geboortejaar' => (string)$geboortejaar, 'geslacht' => 'M', 'gewicht' => '22', 'band' => 'wit'],
        ];

        $result = $service->importeerDeelnemers($toernooi, $data);

        $this->assertEquals(1, $result['geimporteerd']);

        // Club should be created
        $club = Club::where('naam', 'Nieuwe Judo Club')->first();
        $this->assertNotNull($club);

        // Judoka should be linked to the club
        $judoka = Judoka::where('toernooi_id', $toernooi->id)->first();
        $this->assertEquals($club->id, $judoka->club_id);
    }

    #[Test]
    public function importeer_deelnemers_reuses_existing_club(): void
    {
        $toernooi = $this->createToernooiWithOrganisator();
        $organisator = $toernooi->organisatoren()->first();
        $service = app(ImportService::class);

        $club = Club::create([
            'organisator_id' => $organisator->id,
            'naam' => 'Bestaande Club',
        ]);

        $geboortejaar = date('Y') - 6;
        $data = [
            ['naam' => 'Jan Jansen', 'club' => 'Bestaande Club', 'geboortejaar' => (string)$geboortejaar, 'geslacht' => 'M', 'gewicht' => '22', 'band' => 'wit'],
            ['naam' => 'Piet Pietersen', 'club' => 'Bestaande Club', 'geboortejaar' => (string)$geboortejaar, 'geslacht' => 'M', 'gewicht' => '24', 'band' => 'geel'],
        ];

        $result = $service->importeerDeelnemers($toernooi, $data);

        $this->assertEquals(2, $result['geimporteerd']);

        // Both judokas should be linked to the same club
        $judokas = Judoka::where('toernooi_id', $toernooi->id)->get();
        $this->assertTrue($judokas->every(fn ($j) => $j->club_id === $club->id));

        // No new clubs should have been created
        $this->assertEquals(1, Club::where('naam', 'Bestaande Club')->count());
    }

    #[Test]
    public function importeer_deelnemers_handles_no_club(): void
    {
        $toernooi = $this->createToernooiWithOrganisator();
        $service = app(ImportService::class);

        $geboortejaar = date('Y') - 5;
        $data = [
            ['naam' => 'Jan Jansen', 'club' => '', 'geboortejaar' => (string)$geboortejaar, 'geslacht' => 'M', 'gewicht' => '22', 'band' => 'wit'],
        ];

        $result = $service->importeerDeelnemers($toernooi, $data);

        $this->assertEquals(1, $result['geimporteerd']);
        $this->assertEquals(1, $result['zonder_club']);

        $judoka = Judoka::where('toernooi_id', $toernooi->id)->first();
        $this->assertNull($judoka->club_id);
    }

    // =========================================================================
    // IMPORTEER DEELNEMERS — COACHES
    // =========================================================================

    #[Test]
    public function importeer_deelnemers_creates_coaches_for_clubs(): void
    {
        $toernooi = $this->createToernooiWithOrganisator();
        $service = app(ImportService::class);

        $geboortejaar = date('Y') - 7;
        $data = [
            ['naam' => 'Jan', 'club' => 'Club Alpha', 'geboortejaar' => (string)$geboortejaar, 'geslacht' => 'M', 'gewicht' => '25', 'band' => 'wit'],
            ['naam' => 'Piet', 'club' => 'Club Beta', 'geboortejaar' => (string)$geboortejaar, 'geslacht' => 'M', 'gewicht' => '28', 'band' => 'geel'],
        ];

        $result = $service->importeerDeelnemers($toernooi, $data);

        $this->assertEquals(2, $result['coaches_aangemaakt']);

        // Verify coaches exist
        $this->assertEquals(2, Coach::where('toernooi_id', $toernooi->id)->count());

        // Verify coach cards exist
        $this->assertEquals(2, CoachKaart::where('toernooi_id', $toernooi->id)->count());
    }

    // =========================================================================
    // IMPORTEER DEELNEMERS — COLUMN MAPPING (NUMERIC INDICES)
    // =========================================================================

    #[Test]
    public function importeer_deelnemers_with_numeric_column_mapping(): void
    {
        $toernooi = $this->createToernooiWithOrganisator();
        $service = app(ImportService::class);

        $geboortejaar = date('Y') - 6;
        // Array with numeric indices (simulating CSV without headers)
        $data = [
            [0 => 'Jan Jansen', 1 => 'Club X', 2 => (string)$geboortejaar, 3 => 'M', 4 => '22', 5 => 'wit'],
        ];

        $mapping = [
            'naam' => '0',
            'club' => '1',
            'geboortejaar' => '2',
            'geslacht' => '3',
            'gewicht' => '4',
            'band' => '5',
            'gewichtsklasse' => 'gewichtsklasse',
            'jbn_lidnummer' => 'jbn_lidnummer',
        ];

        $result = $service->importeerDeelnemers($toernooi, $data, $mapping);

        $this->assertEquals(1, $result['geimporteerd']);
    }

    #[Test]
    public function importeer_deelnemers_with_multi_column_name_mapping(): void
    {
        $toernooi = $this->createToernooiWithOrganisator();
        $service = app(ImportService::class);

        $geboortejaar = date('Y') - 5;
        // First name, prefix, last name in separate columns
        $data = [
            [0 => 'Jan', 1 => 'van', 2 => 'Berg', 3 => 'Club Y', 4 => (string)$geboortejaar, 5 => 'M', 6 => '20', 7 => 'wit'],
        ];

        $mapping = [
            'naam' => '0,1,2', // Combine columns 0, 1, 2
            'club' => '3',
            'geboortejaar' => '4',
            'geslacht' => '5',
            'gewicht' => '6',
            'band' => '7',
            'gewichtsklasse' => 'gewichtsklasse',
            'jbn_lidnummer' => 'jbn_lidnummer',
        ];

        $result = $service->importeerDeelnemers($toernooi, $data, $mapping);

        $this->assertEquals(1, $result['geimporteerd']);

        $judoka = Judoka::where('toernooi_id', $toernooi->id)->first();
        // normaliseerNaam should capitalize 'Jan' and keep 'van' lowercase
        $this->assertEquals('Jan van Berg', $judoka->naam);
    }

    // =========================================================================
    // IMPORTEER DEELNEMERS — CATEGORY CLASSIFICATION
    // =========================================================================

    #[Test]
    public function importeer_deelnemers_classifies_mini(): void
    {
        $toernooi = $this->createToernooiWithOrganisator();
        $service = app(ImportService::class);

        $geboortejaar = date('Y') - 5; // Age 5 = mini (4-6)
        $data = [
            ['naam' => 'Jan Mini', 'club' => 'Club A', 'geboortejaar' => (string)$geboortejaar, 'geslacht' => 'M', 'gewicht' => '20', 'band' => 'wit'],
        ];

        $result = $service->importeerDeelnemers($toernooi, $data);

        $judoka = Judoka::where('toernooi_id', $toernooi->id)->first();
        $this->assertNotNull($judoka->leeftijdsklasse);
        $this->assertNotNull($judoka->categorie_key);
        // Should match the minis category from dynamischeKlassen factory
        $this->assertStringContainsString('Mini', $judoka->leeftijdsklasse);
        $this->assertEquals('minis', $judoka->categorie_key);
    }

    #[Test]
    public function importeer_deelnemers_classifies_pupil(): void
    {
        $toernooi = $this->createToernooiWithOrganisator();
        $service = app(ImportService::class);

        $geboortejaar = date('Y') - 8; // Age 8 = pupillen (7-9)
        $data = [
            ['naam' => 'Jan Pupil', 'club' => 'Club A', 'geboortejaar' => (string)$geboortejaar, 'geslacht' => 'M', 'gewicht' => '30', 'band' => 'oranje'],
        ];

        $result = $service->importeerDeelnemers($toernooi, $data);

        $judoka = Judoka::where('toernooi_id', $toernooi->id)->first();
        $this->assertStringContainsString('Pupillen', $judoka->leeftijdsklasse);
        $this->assertEquals('pupillen', $judoka->categorie_key);
    }

    #[Test]
    public function importeer_deelnemers_marks_out_of_category_as_niet_in_categorie(): void
    {
        $toernooi = $this->createToernooiWithOrganisator();
        $service = app(ImportService::class);

        // Age 15 = does not match mini (4-6) or pupillen (7-9) from dynamischeKlassen factory
        $geboortejaar = date('Y') - 15;
        $data = [
            ['naam' => 'Jan Senior', 'club' => 'Club A', 'geboortejaar' => (string)$geboortejaar, 'geslacht' => 'M', 'gewicht' => '60', 'band' => 'blauw'],
        ];

        $result = $service->importeerDeelnemers($toernooi, $data);

        $judoka = Judoka::where('toernooi_id', $toernooi->id)->first();
        $this->assertEquals('niet_in_categorie', $judoka->import_status);
        $this->assertNotNull($judoka->import_warnings);
    }

    // =========================================================================
    // IMPORTEER DEELNEMERS — IMPORT STATUS
    // =========================================================================

    #[Test]
    public function importeer_deelnemers_sets_compleet_status_for_valid_data(): void
    {
        $toernooi = $this->createToernooiWithOrganisator();
        $service = app(ImportService::class);

        $geboortejaar = date('Y') - 5;
        $data = [
            ['naam' => 'Jan Jansen', 'club' => 'Club A', 'geboortejaar' => (string)$geboortejaar, 'geslacht' => 'M', 'gewicht' => '22', 'band' => 'wit'],
        ];

        $result = $service->importeerDeelnemers($toernooi, $data);

        $judoka = Judoka::where('toernooi_id', $toernooi->id)->first();
        $this->assertEquals('compleet', $judoka->import_status);
        $this->assertNull($judoka->import_warnings);
    }

    #[Test]
    public function importeer_deelnemers_sets_te_corrigeren_status_for_warnings(): void
    {
        $toernooi = $this->createToernooiWithOrganisator();
        $service = app(ImportService::class);

        $geboortejaar = date('Y') - 5;
        $data = [
            ['naam' => 'Jan Jansen', 'club' => 'Club A', 'geboortejaar' => (string)$geboortejaar, 'geslacht' => 'X', 'gewicht' => '22', 'band' => 'wit'],
        ];

        $result = $service->importeerDeelnemers($toernooi, $data);

        $judoka = Judoka::where('toernooi_id', $toernooi->id)->first();
        $this->assertEquals('te_corrigeren', $judoka->import_status);
    }

    // =========================================================================
    // IMPORTEER DEELNEMERS — JBN LIDNUMMER
    // =========================================================================

    #[Test]
    public function importeer_deelnemers_stores_jbn_lidnummer(): void
    {
        $toernooi = $this->createToernooiWithOrganisator();
        $service = app(ImportService::class);

        $geboortejaar = date('Y') - 6;
        $data = [
            ['naam' => 'Jan Jansen', 'club' => 'Club A', 'geboortejaar' => (string)$geboortejaar, 'geslacht' => 'M', 'gewicht' => '22', 'band' => 'wit', 'jbn_lidnummer' => '123456'],
        ];

        $result = $service->importeerDeelnemers($toernooi, $data);

        $judoka = Judoka::where('toernooi_id', $toernooi->id)->first();
        $this->assertEquals('123456', $judoka->jbn_lidnummer);
    }

    // =========================================================================
    // IMPORTEER DEELNEMERS — COMMA-SEPARATED WEIGHT
    // =========================================================================

    #[Test]
    public function importeer_deelnemers_handles_comma_weight(): void
    {
        $toernooi = $this->createToernooiWithOrganisator();
        $service = app(ImportService::class);

        $geboortejaar = date('Y') - 5;
        $data = [
            ['naam' => 'Jan Jansen', 'club' => 'Club A', 'geboortejaar' => (string)$geboortejaar, 'geslacht' => 'M', 'gewicht' => '22,5', 'band' => 'wit'],
        ];

        $result = $service->importeerDeelnemers($toernooi, $data);

        $judoka = Judoka::where('toernooi_id', $toernooi->id)->first();
        $this->assertEquals(22.5, $judoka->gewicht);
    }

    // =========================================================================
    // IMPORTEER DEELNEMERS — NAAM NORMALISATIE
    // =========================================================================

    #[Test]
    public function importeer_deelnemers_normalizes_names(): void
    {
        $toernooi = $this->createToernooiWithOrganisator();
        $service = app(ImportService::class);

        $geboortejaar = date('Y') - 5;
        $data = [
            ['naam' => 'JAN VAN DER BERG', 'club' => 'Club A', 'geboortejaar' => (string)$geboortejaar, 'geslacht' => 'M', 'gewicht' => '20', 'band' => 'wit'],
        ];

        $result = $service->importeerDeelnemers($toernooi, $data);

        $judoka = Judoka::where('toernooi_id', $toernooi->id)->first();
        $this->assertEquals('Jan van der Berg', $judoka->naam);
    }

    // =========================================================================
    // IMPORTEER DEELNEMERS — FALLBACK CLASSIFICATION (NO CONFIG)
    // =========================================================================

    #[Test]
    public function importeer_deelnemers_uses_fallback_classification_without_config(): void
    {
        $organisator = Organisator::factory()->create();
        // Tournament WITHOUT gewichtsklassen config
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $organisator->id,
            'gewichtsklassen' => null,
        ]);
        $toernooi->organisatoren()->attach($organisator->id, ['rol' => 'eigenaar']);

        $service = app(ImportService::class);

        $geboortejaar = date('Y') - 5;
        $data = [
            ['naam' => 'Jan Mini', 'club' => 'Club A', 'geboortejaar' => (string)$geboortejaar, 'geslacht' => 'M', 'gewicht' => '20', 'band' => 'wit'],
        ];

        $result = $service->importeerDeelnemers($toernooi, $data);

        $judoka = Judoka::where('toernooi_id', $toernooi->id)->first();
        // Fallback: age 5 → Mini's
        $this->assertEquals("Mini's", $judoka->leeftijdsklasse);
    }

    #[Test]
    public function importeer_deelnemers_fallback_onbekend_without_geboortejaar(): void
    {
        $organisator = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $organisator->id,
            'gewichtsklassen' => null,
        ]);
        $toernooi->organisatoren()->attach($organisator->id, ['rol' => 'eigenaar']);

        $service = app(ImportService::class);

        $data = [
            ['naam' => 'Jan Onbekend', 'club' => 'Club A', 'geboortejaar' => '', 'geslacht' => 'M', 'gewicht' => '20', 'band' => 'wit'],
        ];

        $result = $service->importeerDeelnemers($toernooi, $data);

        $judoka = Judoka::where('toernooi_id', $toernooi->id)->first();
        $this->assertEquals('Onbekend', $judoka->leeftijdsklasse);
    }

    // =========================================================================
    // IMPORTEER DEELNEMERS — FIXED WEIGHT CLASSES
    // =========================================================================

    #[Test]
    public function importeer_deelnemers_assigns_weight_class_from_config(): void
    {
        $organisator = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->vasteKlassen()->create([
            'organisator_id' => $organisator->id,
        ]);
        $toernooi->organisatoren()->attach($organisator->id, ['rol' => 'eigenaar']);

        $service = app(ImportService::class);

        $geboortejaar = date('Y') - 5; // Age 5 = mini (4-6)
        $data = [
            ['naam' => 'Jan Jansen', 'club' => 'Club A', 'geboortejaar' => (string)$geboortejaar, 'geslacht' => 'M', 'gewicht' => '19', 'band' => 'wit'],
        ];

        $result = $service->importeerDeelnemers($toernooi, $data);

        $judoka = Judoka::where('toernooi_id', $toernooi->id)->first();
        // Weight 19 should fall into -20 class
        $this->assertEquals('-20', $judoka->gewichtsklasse);
    }

    // =========================================================================
    // IMPORTEER DEELNEMERS — ERROR HANDLING
    // =========================================================================

    #[Test]
    public function importeer_deelnemers_catches_row_errors_and_continues(): void
    {
        $toernooi = $this->createToernooiWithOrganisator();
        $service = app(ImportService::class);

        $geboortejaar = date('Y') - 5;
        // First row has invalid geboortejaar that throws, second is valid
        $data = [
            ['naam' => 'Fout Judoka', 'club' => 'Club A', 'geboortejaar' => 'onzin!@#', 'geslacht' => 'M', 'gewicht' => '22', 'band' => 'wit'],
            ['naam' => 'Goed Judoka', 'club' => 'Club A', 'geboortejaar' => (string)$geboortejaar, 'geslacht' => 'M', 'gewicht' => '22', 'band' => 'wit'],
        ];

        $result = $service->importeerDeelnemers($toernooi, $data);

        // First row errors, second succeeds
        $this->assertEquals(1, $result['geimporteerd']);
        $this->assertCount(1, $result['fouten']);
        $this->assertStringContainsString('Fout Judoka', $result['fouten'][0]);
    }

    // =========================================================================
    // CLEAR CACHE
    // =========================================================================

    #[Test]
    public function clear_cache_resets_internal_state(): void
    {
        $service = app(ImportService::class);

        // After clearCache, re-importing should work cleanly
        $service->clearCache();

        // No assertion needed beyond no exception - just ensure it's callable
        $this->assertTrue(true);
    }
}
