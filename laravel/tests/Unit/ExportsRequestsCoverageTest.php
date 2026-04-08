<?php

namespace Tests\Unit;

use App\Exports\PouleBlokSheet;
use App\Exports\PouleExport;
use App\Exports\WimpelExport;
use App\Http\Requests\JudokaStoreRequest;
use App\Http\Requests\JudokaUpdateRequest;
use App\Http\Requests\StamJudokaRequest;
use App\Http\Requests\WedstrijdUitslagRequest;
use App\Http\Requests\WegingRequest;
use App\Models\Blok;
use App\Models\Club;
use App\Models\Judoka;
use App\Models\Mat;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\StamJudoka;
use App\Models\Toernooi;
use App\Models\WimpelPuntenLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExportsRequestsCoverageTest extends TestCase
{
    use RefreshDatabase;

    // ========================================================================
    // PouleBlokSheet
    // ========================================================================

    #[Test]
    public function poule_blok_sheet_returns_title_with_blok_nummer(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 3]);

        $sheet = new PouleBlokSheet($blok);

        $this->assertEquals('Blok 3', $sheet->title());
    }

    #[Test]
    public function poule_blok_sheet_array_returns_version_header_when_no_poules(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id]);

        $sheet = new PouleBlokSheet($blok);
        $rows = $sheet->array();

        // First row is version header
        $this->assertStringStartsWith('VERSIE_7_', $rows[0][0]);
        // Second row is empty separator
        $this->assertEquals('', $rows[1][0]);
    }

    #[Test]
    public function poule_blok_sheet_array_includes_poule_and_judoka_data(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id]);
        $mat = Mat::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);
        $club = Club::factory()->create(['organisator_id' => $org->id]);

        $poule = Poule::factory()->metJudokas(2)->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat->id,
            'nummer' => 1,
            'leeftijdsklasse' => 'pupillen',
            'gewichtsklasse' => '-28',
        ]);

        $judoka = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'naam' => 'Test Judoka',
            'band' => 'geel',
            'gewichtsklasse' => '-28',
            'geslacht' => 'M',
            'geboortejaar' => 2018,
        ]);
        $poule->judokas()->attach($judoka->id, ['positie' => 1]);

        $sheet = new PouleBlokSheet($blok->fresh());
        $rows = $sheet->array();

        // Should have: version header, empty row, mat header, poule header, column header, judoka row
        $this->assertGreaterThanOrEqual(6, count($rows));

        // Find mat header
        $matHeaders = array_filter($rows, fn($r) => str_starts_with($r[0] ?? '', 'MAT_HEADER_'));
        $this->assertNotEmpty($matHeaders);

        // Find poule header
        $pouleHeaders = array_filter($rows, fn($r) => str_starts_with($r[0] ?? '', 'POULE_HEADER_'));
        $this->assertNotEmpty($pouleHeaders);

        // Find column header
        $columnHeaders = array_filter($rows, fn($r) => str_starts_with($r[0] ?? '', 'KOLOM_'));
        $this->assertNotEmpty($columnHeaders);

        // Find judoka row
        $judokaRows = array_filter($rows, fn($r) => ($r[0] ?? '') === 'Test Judoka');
        $this->assertNotEmpty($judokaRows);
    }

    #[Test]
    public function poule_blok_sheet_array_handles_poule_without_mat(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id]);

        Poule::factory()->metJudokas(0)->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => null,
            'nummer' => 1,
            'gewichtsklasse' => 'Onbekend',
        ]);

        $sheet = new PouleBlokSheet($blok->fresh());
        $rows = $sheet->array();

        // Poule without mat: currentMat starts as null, matNummer is null,
        // so no MAT_HEADER is generated. But poule header should exist.
        $pouleHeaders = array_filter($rows, fn($r) => str_starts_with($r[0] ?? '', 'POULE_HEADER_'));
        $this->assertNotEmpty($pouleHeaders);

        // Gewichtsklasse 'Onbekend' should not get 'kg' suffix
        $header = reset($pouleHeaders);
        $this->assertStringContainsString('Onbekend', $header[0]);
        $this->assertStringNotContainsString('kg', $header[0]);
    }

    #[Test]
    public function poule_blok_sheet_array_adds_kg_suffix_when_missing(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id]);

        Poule::factory()->metJudokas(2)->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'nummer' => 1,
            'gewichtsklasse' => '-28',
        ]);

        $sheet = new PouleBlokSheet($blok->fresh());
        $rows = $sheet->array();

        $pouleHeaders = array_filter($rows, fn($r) => str_starts_with($r[0] ?? '', 'POULE_HEADER_'));
        $header = reset($pouleHeaders);
        $this->assertStringContainsString('-28 kg', $header[0]);
    }

    #[Test]
    public function poule_blok_sheet_array_does_not_double_kg_suffix(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id]);

        Poule::factory()->metJudokas(2)->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'nummer' => 1,
            'gewichtsklasse' => '-28 kg',
        ]);

        $sheet = new PouleBlokSheet($blok->fresh());
        $rows = $sheet->array();

        $pouleHeaders = array_filter($rows, fn($r) => str_starts_with($r[0] ?? '', 'POULE_HEADER_'));
        $header = reset($pouleHeaders);
        $this->assertStringContainsString('-28 kg', $header[0]);
        $this->assertStringNotContainsString('-28 kg kg', $header[0]);
    }

    #[Test]
    public function poule_blok_sheet_array_adds_spacing_between_mats(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id]);
        $mat1 = Mat::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);
        $mat2 = Mat::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 2]);

        Poule::factory()->metJudokas(0)->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat1->id,
            'nummer' => 1,
        ]);
        Poule::factory()->metJudokas(0)->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat2->id,
            'nummer' => 2,
        ]);

        $sheet = new PouleBlokSheet($blok->fresh());
        $rows = $sheet->array();

        // Should have 2 MAT_HEADER rows
        $matHeaders = array_filter($rows, fn($r) => str_starts_with($r[0] ?? '', 'MAT_HEADER_'));
        $this->assertCount(2, $matHeaders);
    }

    #[Test]
    public function poule_blok_sheet_array_adds_spacing_between_poules_on_same_mat(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id]);
        $mat = Mat::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);

        Poule::factory()->metJudokas(0)->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat->id,
            'nummer' => 1,
        ]);
        Poule::factory()->metJudokas(0)->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat->id,
            'nummer' => 2,
        ]);

        $sheet = new PouleBlokSheet($blok->fresh());
        $rows = $sheet->array();

        // Should have 2 POULE_HEADER rows
        $pouleHeaders = array_filter($rows, fn($r) => str_starts_with($r[0] ?? '', 'POULE_HEADER_'));
        $this->assertCount(2, $pouleHeaders);
    }

    #[Test]
    public function poule_blok_sheet_register_events_returns_after_sheet_handler(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id]);

        $sheet = new PouleBlokSheet($blok);
        $events = $sheet->registerEvents();

        $this->assertArrayHasKey(\Maatwebsite\Excel\Events\AfterSheet::class, $events);
        $this->assertIsCallable($events[\Maatwebsite\Excel\Events\AfterSheet::class]);
    }

    // ========================================================================
    // PouleExport
    // ========================================================================

    #[Test]
    public function poule_export_returns_sheets_per_blok(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 1]);
        Blok::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => 2]);

        $export = new PouleExport($toernooi);
        $sheets = $export->sheets();

        $this->assertCount(2, $sheets);
        $this->assertInstanceOf(PouleBlokSheet::class, $sheets[0]);
        $this->assertInstanceOf(PouleBlokSheet::class, $sheets[1]);
    }

    #[Test]
    public function poule_export_returns_empty_sheets_when_no_blokken(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);

        $export = new PouleExport($toernooi);
        $sheets = $export->sheets();

        $this->assertCount(0, $sheets);
    }

    // ========================================================================
    // WimpelExport
    // ========================================================================

    #[Test]
    public function wimpel_export_returns_no_data_message_when_empty(): void
    {
        $org = Organisator::factory()->create();

        $export = new WimpelExport($org);
        $rows = $export->array();

        $this->assertCount(1, $rows);
        $this->assertEquals("Geen wimpel judoka's gevonden", $rows[0][0]);
    }

    #[Test]
    public function wimpel_export_returns_header_and_judoka_rows(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id, 'datum' => '2026-03-15']);

        $stamJudoka = StamJudoka::factory()->metPunten(10)->create([
            'organisator_id' => $org->id,
            'naam' => 'Jansen, Piet',
            'geboortejaar' => 2015,
        ]);

        WimpelPuntenLog::create([
            'stam_judoka_id' => $stamJudoka->id,
            'toernooi_id' => $toernooi->id,
            'punten' => 10,
            'type' => 'automatisch',
        ]);

        $export = new WimpelExport($org);
        $rows = $export->array();

        // Header row
        $this->assertEquals('Naam', $rows[0][0]);
        $this->assertEquals('Geboortejaar', $rows[0][1]);
        $this->assertEquals('Totaal', $rows[0][2]);
        // Toernooi column in header
        $this->assertStringContainsString('15-03-2026', $rows[0][3]);

        // Data row
        $this->assertEquals('Jansen, Piet', $rows[1][0]);
        $this->assertEquals(2015, $rows[1][1]);
        $this->assertEquals(10, $rows[1][2]);
        $this->assertEquals(10, $rows[1][3]);
    }

    #[Test]
    public function wimpel_export_includes_handmatig_column_when_present(): void
    {
        $org = Organisator::factory()->create();

        $stamJudoka = StamJudoka::factory()->metPunten(5)->create([
            'organisator_id' => $org->id,
        ]);

        // Handmatige punten (toernooi_id = null)
        WimpelPuntenLog::create([
            'stam_judoka_id' => $stamJudoka->id,
            'toernooi_id' => null,
            'punten' => 5,
            'type' => 'handmatig',
        ]);

        $export = new WimpelExport($org);
        $rows = $export->array();

        // Last header column should be 'Handmatig'
        $lastHeaderCol = end($rows[0]);
        $this->assertEquals('Handmatig', $lastHeaderCol);
    }

    #[Test]
    public function wimpel_export_register_events_returns_after_sheet_handler(): void
    {
        $org = Organisator::factory()->create();

        $export = new WimpelExport($org);
        $events = $export->registerEvents();

        $this->assertArrayHasKey(\Maatwebsite\Excel\Events\AfterSheet::class, $events);
        $this->assertIsCallable($events[\Maatwebsite\Excel\Events\AfterSheet::class]);
    }

    // ========================================================================
    // JudokaStoreRequest
    // ========================================================================

    #[Test]
    public function judoka_store_request_authorize_returns_true(): void
    {
        $request = new JudokaStoreRequest();
        $this->assertTrue($request->authorize());
    }

    #[Test]
    public function judoka_store_request_rules_contains_expected_fields(): void
    {
        $request = new JudokaStoreRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey('naam', $rules);
        $this->assertArrayHasKey('club_id', $rules);
        $this->assertArrayHasKey('geboortejaar', $rules);
        $this->assertArrayHasKey('geslacht', $rules);
        $this->assertArrayHasKey('band', $rules);
        $this->assertArrayHasKey('gewicht', $rules);

        $this->assertStringContainsString('required', $rules['naam']);
        $this->assertStringContainsString('nullable', $rules['club_id']);
        $this->assertStringContainsString('nullable', $rules['geslacht']);
    }

    #[Test]
    public function judoka_store_request_messages_contains_custom_messages(): void
    {
        $request = new JudokaStoreRequest();
        $messages = $request->messages();

        $this->assertArrayHasKey('naam.required', $messages);
        $this->assertArrayHasKey('geslacht.in', $messages);
        $this->assertArrayHasKey('gewicht.min', $messages);
    }

    // ========================================================================
    // JudokaUpdateRequest
    // ========================================================================

    #[Test]
    public function judoka_update_request_authorize_returns_true(): void
    {
        $request = new JudokaUpdateRequest();
        $this->assertTrue($request->authorize());
    }

    #[Test]
    public function judoka_update_request_rules_contains_expected_fields(): void
    {
        $request = new JudokaUpdateRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey('naam', $rules);
        $this->assertArrayHasKey('geboortejaar', $rules);
        $this->assertArrayHasKey('geslacht', $rules);
        $this->assertArrayHasKey('band', $rules);
        $this->assertArrayHasKey('jbn_lidnummer', $rules);
        $this->assertArrayHasKey('gewicht', $rules);

        // Update request has required fields unlike store
        $this->assertStringContainsString('required', $rules['geboortejaar']);
        $this->assertStringContainsString('required', $rules['geslacht']);
        $this->assertStringContainsString('required', $rules['band']);
    }

    #[Test]
    public function judoka_update_request_messages_contains_custom_messages(): void
    {
        $request = new JudokaUpdateRequest();
        $messages = $request->messages();

        $this->assertArrayHasKey('naam.required', $messages);
        $this->assertArrayHasKey('geboortejaar.required', $messages);
        $this->assertArrayHasKey('band.required', $messages);
        $this->assertArrayHasKey('geslacht.required', $messages);
    }

    // ========================================================================
    // StamJudokaRequest
    // ========================================================================

    #[Test]
    public function stam_judoka_request_authorize_returns_true(): void
    {
        $request = new StamJudokaRequest();
        $this->assertTrue($request->authorize());
    }

    #[Test]
    public function stam_judoka_request_rules_contains_expected_fields(): void
    {
        $request = new StamJudokaRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey('naam', $rules);
        $this->assertArrayHasKey('geboortejaar', $rules);
        $this->assertArrayHasKey('geslacht', $rules);
        $this->assertArrayHasKey('band', $rules);
        $this->assertArrayHasKey('gewicht', $rules);
        $this->assertArrayHasKey('notities', $rules);

        $this->assertStringContainsString('required', $rules['naam']);
        $this->assertStringContainsString('required', $rules['geboortejaar']);
        $this->assertStringContainsString('nullable', $rules['notities']);
        $this->assertStringContainsString('max:1000', $rules['notities']);
    }

    // ========================================================================
    // WedstrijdUitslagRequest
    // ========================================================================

    #[Test]
    public function wedstrijd_uitslag_request_authorize_returns_true(): void
    {
        $request = new WedstrijdUitslagRequest();
        $this->assertTrue($request->authorize());
    }

    #[Test]
    public function wedstrijd_uitslag_request_rules_contains_expected_fields(): void
    {
        $request = new WedstrijdUitslagRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey('wedstrijd_id', $rules);
        $this->assertArrayHasKey('winnaar_id', $rules);
        $this->assertArrayHasKey('score_wit', $rules);
        $this->assertArrayHasKey('score_blauw', $rules);
        $this->assertArrayHasKey('uitslag_type', $rules);

        $this->assertStringContainsString('required', $rules['wedstrijd_id']);
        $this->assertStringContainsString('exists:wedstrijden,id', $rules['wedstrijd_id']);
        $this->assertStringContainsString('nullable', $rules['winnaar_id']);
    }

    #[Test]
    public function wedstrijd_uitslag_request_messages_contains_custom_messages(): void
    {
        $request = new WedstrijdUitslagRequest();
        $messages = $request->messages();

        $this->assertArrayHasKey('wedstrijd_id.required', $messages);
        $this->assertArrayHasKey('wedstrijd_id.exists', $messages);
        $this->assertArrayHasKey('score_wit.in', $messages);
        $this->assertArrayHasKey('score_blauw.in', $messages);
    }

    // ========================================================================
    // WegingRequest
    // ========================================================================

    #[Test]
    public function weging_request_authorize_returns_true(): void
    {
        $request = new WegingRequest();
        $this->assertTrue($request->authorize());
    }

    #[Test]
    public function weging_request_rules_contains_expected_fields(): void
    {
        $request = new WegingRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey('gewicht', $rules);
        $this->assertArrayHasKey('opmerking', $rules);

        $this->assertStringContainsString('required', $rules['gewicht']);
        $this->assertStringContainsString('numeric', $rules['gewicht']);
        $this->assertStringContainsString('nullable', $rules['opmerking']);
    }

    #[Test]
    public function weging_request_messages_contains_custom_messages(): void
    {
        $request = new WegingRequest();
        $messages = $request->messages();

        $this->assertArrayHasKey('gewicht.required', $messages);
        $this->assertArrayHasKey('gewicht.numeric', $messages);
        $this->assertArrayHasKey('gewicht.min', $messages);
        $this->assertArrayHasKey('gewicht.max', $messages);
        $this->assertArrayHasKey('opmerking.max', $messages);
    }
}
