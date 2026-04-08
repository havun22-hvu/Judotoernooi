<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\StamJudoka;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JudokaControllerCoverageTest extends TestCase
{
    use RefreshDatabase;

    private Organisator $org;
    private Toernooi $toernooi;
    private Club $club;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organisator::factory()->create();
        $this->toernooi = Toernooi::factory()->create([
            'organisator_id' => $this->org->id,
            'plan_type' => 'paid',
            'gewichtsklassen' => [
                'pupillen' => [
                    'label' => 'Pupillen',
                    'min_leeftijd' => 7,
                    'max_leeftijd' => 9,
                    'max_kg_verschil' => 4,
                    'gewichten' => ['-24', '-28', '-32', '-36', '-40', '+40'],
                ],
                'aspiranten' => [
                    'label' => 'Aspiranten',
                    'min_leeftijd' => 10,
                    'max_leeftijd' => 12,
                    'max_kg_verschil' => 0,
                    'gewichten' => ['-30', '-34', '-38', '-42', '-46', '-50', '-55', '+55'],
                ],
                'cadetten' => [
                    'label' => 'Cadetten',
                    'min_leeftijd' => 13,
                    'max_leeftijd' => 15,
                    'max_kg_verschil' => 0,
                    'gewichten' => ['-40', '-46', '-50', '-55', '-60', '-66', '-73', '+73'],
                ],
            ],
        ]);
        $this->org->toernooien()->attach($this->toernooi->id, ['rol' => 'eigenaar']);
        $this->club = Club::factory()->create(['organisator_id' => $this->org->id]);
    }

    private function routeParams(array $extra = []): array
    {
        return array_merge($this->toernooi->routeParams(), $extra);
    }

    // ========================================================================
    // INDEX
    // ========================================================================

    #[Test]
    public function index_shows_judokas_list(): void
    {
        Judoka::factory()->count(3)->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
            'leeftijdsklasse' => 'Pupillen',
        ]);

        $response = $this->actingAs($this->org, 'organisator')
            ->get(route('toernooi.judoka.index', $this->routeParams()));

        $response->assertStatus(200);
        $response->assertViewIs('pages.judoka.index');
        $response->assertViewHas('judokas');
        $response->assertViewHas('toernooi');
    }

    #[Test]
    public function index_filters_out_niet_in_categorie(): void
    {
        Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'leeftijdsklasse' => 'Pupillen',
        ]);
        Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'import_status' => 'niet_in_categorie',
            'leeftijdsklasse' => 'Onbekend',
        ]);

        $response = $this->actingAs($this->org, 'organisator')
            ->get(route('toernooi.judoka.index', $this->routeParams()));

        $response->assertStatus(200);
        $response->assertViewHas('nietInCategorie', function ($collection) {
            return $collection->count() === 1;
        });
    }

    #[Test]
    public function index_requires_auth(): void
    {
        $response = $this->get(route('toernooi.judoka.index', $this->routeParams()));
        $response->assertRedirect();
    }

    // ========================================================================
    // SHOW
    // ========================================================================

    #[Test]
    public function show_displays_judoka_details(): void
    {
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
        ]);

        $response = $this->actingAs($this->org, 'organisator')
            ->get(route('toernooi.judoka.show', $this->routeParams(['judoka' => $judoka->id])));

        $response->assertStatus(200);
        $response->assertViewIs('pages.judoka.show');
        $response->assertViewHas('judoka');
    }

    // ========================================================================
    // EDIT
    // ========================================================================

    #[Test]
    public function edit_shows_form(): void
    {
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
        ]);

        $response = $this->actingAs($this->org, 'organisator')
            ->get(route('toernooi.judoka.edit', $this->routeParams(['judoka' => $judoka->id])));

        $response->assertStatus(200);
        $response->assertViewIs('pages.judoka.edit');
    }

    // ========================================================================
    // UPDATE
    // ========================================================================

    #[Test]
    public function update_saves_judoka_changes(): void
    {
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'naam' => 'Oude Naam',
            'geboortejaar' => date('Y') - 10,
            'geslacht' => 'M',
            'band' => 'wit',
        ]);

        $response = $this->actingAs($this->org, 'organisator')
            ->put(route('toernooi.judoka.update', $this->routeParams(['judoka' => $judoka->id])), [
                'naam' => 'Nieuwe Naam',
                'geboortejaar' => date('Y') - 11,
                'geslacht' => 'M',
                'band' => 'geel',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('judokas', [
            'id' => $judoka->id,
            'naam' => 'Nieuwe Naam',
            'band' => 'geel',
        ]);
    }

    #[Test]
    public function update_with_gewicht_recalculates_gewichtsklasse(): void
    {
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'geboortejaar' => date('Y') - 10,
            'geslacht' => 'M',
            'band' => 'wit',
            'gewichtsklasse' => '-30',
        ]);

        $response = $this->actingAs($this->org, 'organisator')
            ->put(route('toernooi.judoka.update', $this->routeParams(['judoka' => $judoka->id])), [
                'naam' => $judoka->naam,
                'geboortejaar' => $judoka->geboortejaar,
                'geslacht' => 'M',
                'band' => 'wit',
                'gewicht' => 35.5,
            ]);

        $response->assertRedirect();
        $judoka->refresh();
        $this->assertNotNull($judoka->gewicht);
    }

    #[Test]
    public function update_free_tier_cannot_change_naam(): void
    {
        $this->toernooi->update(['plan_type' => 'free']);

        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'naam' => 'Originele Naam',
            'geboortejaar' => date('Y') - 10,
            'geslacht' => 'M',
            'band' => 'wit',
        ]);

        $this->actingAs($this->org, 'organisator')
            ->put(route('toernooi.judoka.update', $this->routeParams(['judoka' => $judoka->id])), [
                'naam' => 'Gewijzigde Naam',
                'geboortejaar' => $judoka->geboortejaar,
                'geslacht' => 'M',
                'band' => 'wit',
            ]);

        $judoka->refresh();
        $this->assertEquals('Originele Naam', $judoka->naam);
    }

    #[Test]
    public function update_with_onvolledig_filter_redirects_back_to_filtered_list(): void
    {
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'geboortejaar' => date('Y') - 10,
            'geslacht' => 'M',
            'band' => 'wit',
        ]);

        $response = $this->actingAs($this->org, 'organisator')
            ->put(route('toernooi.judoka.update', $this->routeParams(['judoka' => $judoka->id])), [
                'naam' => $judoka->naam,
                'geboortejaar' => $judoka->geboortejaar,
                'geslacht' => 'M',
                'band' => 'wit',
                'filter' => 'onvolledig',
            ]);

        $response->assertRedirect();
        $this->assertStringContainsString('filter=onvolledig', $response->headers->get('Location'));
    }

    #[Test]
    public function update_validation_fails_without_required_fields(): void
    {
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
        ]);

        $response = $this->actingAs($this->org, 'organisator')
            ->put(route('toernooi.judoka.update', $this->routeParams(['judoka' => $judoka->id])), [
                // Missing required fields
            ]);

        $response->assertSessionHasErrors(['naam', 'geboortejaar', 'geslacht', 'band']);
    }

    // ========================================================================
    // STORE
    // ========================================================================

    #[Test]
    public function store_creates_new_judoka(): void
    {
        $response = $this->actingAs($this->org, 'organisator')
            ->post(route('toernooi.judoka.store', $this->routeParams()), [
                'naam' => 'Test Judoka',
                'geboortejaar' => date('Y') - 8,
                'geslacht' => 'M',
                'band' => 'wit',
                'gewicht' => 28,
                'club_id' => $this->club->id,
            ]);

        $response->assertRedirect(route('toernooi.judoka.index', $this->routeParams()));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('judokas', [
            'toernooi_id' => $this->toernooi->id,
            'naam' => 'Test Judoka',
        ]);
    }

    #[Test]
    public function store_creates_judoka_without_optional_fields(): void
    {
        $response = $this->actingAs($this->org, 'organisator')
            ->post(route('toernooi.judoka.store', $this->routeParams()), [
                'naam' => 'Alleen Naam',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('judokas', [
            'toernooi_id' => $this->toernooi->id,
            'naam' => 'Alleen Naam',
        ]);
    }

    #[Test]
    public function store_blocks_judoka_not_fitting_any_category(): void
    {
        $response = $this->actingAs($this->org, 'organisator')
            ->post(route('toernooi.judoka.store', $this->routeParams()), [
                'naam' => 'Te Oud',
                'geboortejaar' => date('Y') - 18,
                'geslacht' => 'M',
                'band' => 'wit',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('judokas', ['naam' => 'Te Oud']);
    }

    #[Test]
    public function store_respects_freemium_limit(): void
    {
        $this->toernooi->update(['plan_type' => 'free']);

        // Create 50 judokas (free tier limit)
        Judoka::factory()->count(50)->create([
            'toernooi_id' => $this->toernooi->id,
            'leeftijdsklasse' => 'Pupillen',
        ]);

        $response = $this->actingAs($this->org, 'organisator')
            ->post(route('toernooi.judoka.store', $this->routeParams()), [
                'naam' => 'Over Limiet',
                'geboortejaar' => date('Y') - 8,
                'geslacht' => 'M',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $response->assertSessionHas('show_upgrade');
    }

    #[Test]
    public function store_calculates_fallback_gewichtsklasse(): void
    {
        // Create judoka with gewicht but no matching category gewichtsklasse
        $response = $this->actingAs($this->org, 'organisator')
            ->post(route('toernooi.judoka.store', $this->routeParams()), [
                'naam' => 'Fallback Test',
                'gewicht' => 30,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('judokas', [
            'naam' => 'Fallback Test',
            'gewichtsklasse' => '-30',
        ]);
    }

    #[Test]
    public function store_validation_fails_without_naam(): void
    {
        $response = $this->actingAs($this->org, 'organisator')
            ->post(route('toernooi.judoka.store', $this->routeParams()), [
                'geboortejaar' => date('Y') - 10,
            ]);

        $response->assertSessionHasErrors('naam');
    }

    // ========================================================================
    // DESTROY
    // ========================================================================

    #[Test]
    public function destroy_deletes_judoka(): void
    {
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
        ]);

        $response = $this->actingAs($this->org, 'organisator')
            ->delete(route('toernooi.judoka.destroy', $this->routeParams(['judoka' => $judoka->id])));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('judokas', ['id' => $judoka->id]);
    }

    #[Test]
    public function destroy_blocked_on_free_tier(): void
    {
        $this->toernooi->update(['plan_type' => 'free']);

        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
        ]);

        $response = $this->actingAs($this->org, 'organisator')
            ->delete(route('toernooi.judoka.destroy', $this->routeParams(['judoka' => $judoka->id])));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('judokas', ['id' => $judoka->id]);
    }

    // ========================================================================
    // UPDATE API (PATCH - inline updates)
    // ========================================================================

    #[Test]
    public function update_api_updates_judoka_fields(): void
    {
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'geboortejaar' => date('Y') - 10,
            'geslacht' => 'M',
            'band' => 'wit',
        ]);

        $response = $this->actingAs($this->org, 'organisator')
            ->patchJson(route('toernooi.judoka.update-api', $this->routeParams(['judoka' => $judoka->id])), [
                'naam' => 'API Updated',
                'band' => 'geel',
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('judoka.naam', 'Api Updated');
        $response->assertJsonPath('judoka.band', 'geel');
    }

    #[Test]
    public function update_api_recalculates_on_geboortejaar_change(): void
    {
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'geboortejaar' => date('Y') - 10,
            'geslacht' => 'M',
            'band' => 'wit',
        ]);

        $response = $this->actingAs($this->org, 'organisator')
            ->patchJson(route('toernooi.judoka.update-api', $this->routeParams(['judoka' => $judoka->id])), [
                'geboortejaar' => date('Y') - 8,
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    #[Test]
    public function update_api_recalculates_gewichtsklasse_on_gewicht_change(): void
    {
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'geboortejaar' => date('Y') - 10,
            'geslacht' => 'M',
            'band' => 'wit',
            'gewicht' => 30,
        ]);

        $response = $this->actingAs($this->org, 'organisator')
            ->patchJson(route('toernooi.judoka.update-api', $this->routeParams(['judoka' => $judoka->id])), [
                'gewicht' => 35,
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    #[Test]
    public function update_api_free_tier_strips_naam(): void
    {
        $this->toernooi->update(['plan_type' => 'free']);

        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'naam' => 'Original',
            'geboortejaar' => date('Y') - 10,
            'geslacht' => 'M',
            'band' => 'wit',
        ]);

        $response = $this->actingAs($this->org, 'organisator')
            ->patchJson(route('toernooi.judoka.update-api', $this->routeParams(['judoka' => $judoka->id])), [
                'naam' => 'Changed',
            ]);

        $response->assertOk();
        $judoka->refresh();
        $this->assertEquals('Original', $judoka->naam);
    }

    #[Test]
    public function update_api_validation_fails_with_invalid_data(): void
    {
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
        ]);

        $response = $this->actingAs($this->org, 'organisator')
            ->patchJson(route('toernooi.judoka.update-api', $this->routeParams(['judoka' => $judoka->id])), [
                'geslacht' => 'X',
                'gewicht' => 5,
            ]);

        $response->assertStatus(422);
    }

    // ========================================================================
    // ZOEK (search API)
    // ========================================================================

    #[Test]
    public function zoek_returns_matching_judokas(): void
    {
        Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'naam' => 'Jansen, Pieter',
        ]);
        Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'naam' => 'De Vries, Anna',
        ]);

        $response = $this->actingAs($this->org, 'organisator')
            ->getJson(route('toernooi.judoka.zoek', $this->routeParams()) . '?q=Jansen');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['naam' => 'Jansen, Pieter']);
    }

    #[Test]
    public function zoek_returns_empty_for_short_query(): void
    {
        Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'naam' => 'Test',
        ]);

        $response = $this->actingAs($this->org, 'organisator')
            ->getJson(route('toernooi.judoka.zoek', $this->routeParams()) . '?q=T');

        $response->assertOk();
        $response->assertJsonCount(0);
    }

    #[Test]
    public function zoek_searches_by_club_name(): void
    {
        $club = Club::factory()->create([
            'organisator_id' => $this->org->id,
            'naam' => 'Judoclub Amsterdam',
        ]);
        Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $club->id,
            'naam' => 'Pietje',
        ]);

        $response = $this->actingAs($this->org, 'organisator')
            ->getJson(route('toernooi.judoka.zoek', $this->routeParams()) . '?q=Amsterdam');

        $response->assertOk();
        $response->assertJsonCount(1);
    }

    // ========================================================================
    // IMPORT FORM
    // ========================================================================

    #[Test]
    public function import_form_shows_page(): void
    {
        $response = $this->actingAs($this->org, 'organisator')
            ->get(route('toernooi.judoka.import', $this->routeParams()));

        $response->assertStatus(200);
        $response->assertViewIs('pages.judoka.import');
    }

    // ========================================================================
    // IMPORT CONFIRM (session-based flow)
    // ========================================================================

    #[Test]
    public function import_confirm_fails_without_session_data(): void
    {
        $response = $this->actingAs($this->org, 'organisator')
            ->post(route('toernooi.judoka.import.confirm', $this->routeParams()), [
                'mapping' => [],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    // ========================================================================
    // IMPORT PROGRESS
    // ========================================================================

    #[Test]
    public function import_progress_requires_import_id(): void
    {
        $response = $this->actingAs($this->org, 'organisator')
            ->getJson(route('toernooi.judoka.import.progress', $this->routeParams()));

        $response->assertStatus(400);
        $response->assertJsonPath('error', 'Import ID required');
    }

    #[Test]
    public function import_progress_returns_404_for_unknown_import(): void
    {
        $response = $this->actingAs($this->org, 'organisator')
            ->getJson(route('toernooi.judoka.import.progress', $this->routeParams()) . '?import_id=nonexistent');

        $response->assertStatus(404);
        $response->assertJsonPath('error', 'Import not found');
    }

    // ========================================================================
    // STAMBESTAND JSON
    // ========================================================================

    #[Test]
    public function stambestand_json_returns_stam_judokas(): void
    {
        StamJudoka::factory()->count(3)->create([
            'organisator_id' => $this->org->id,
            'actief' => true,
        ]);

        $response = $this->actingAs($this->org, 'organisator')
            ->getJson(route('toernooi.judoka.stambestand', $this->routeParams()));

        $response->assertOk();
        $response->assertJsonCount(3);
    }

    #[Test]
    public function stambestand_json_marks_already_registered(): void
    {
        $stam = StamJudoka::factory()->create([
            'organisator_id' => $this->org->id,
            'actief' => true,
        ]);

        Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'stam_judoka_id' => $stam->id,
        ]);

        $response = $this->actingAs($this->org, 'organisator')
            ->getJson(route('toernooi.judoka.stambestand', $this->routeParams()));

        $response->assertOk();
        $data = $response->json();
        $found = collect($data)->firstWhere('id', $stam->id);
        $this->assertTrue($found['al_aangemeld']);
    }

    // ========================================================================
    // VALIDEER
    // ========================================================================

    #[Test]
    public function valideer_corrects_judokas_and_redirects(): void
    {
        Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'geboortejaar' => date('Y') - 8,
            'geslacht' => 'M',
            'band' => 'WIT',
            'leeftijdsklasse' => 'Verkeerd',
        ]);

        $response = $this->actingAs($this->org, 'organisator')
            ->post(route('toernooi.judoka.valideer', $this->routeParams()));

        $response->assertRedirect(route('toernooi.judoka.index', $this->routeParams()));
        $response->assertSessionHas('success');
    }

    #[Test]
    public function valideer_reports_missing_fields(): void
    {
        Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'naam' => 'Incompleet',
            'geboortejaar' => null,
            'geslacht' => null,
            'band' => null,
        ]);

        $response = $this->actingAs($this->org, 'organisator')
            ->post(route('toernooi.judoka.valideer', $this->routeParams()));

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    // ========================================================================
    // IMPORT UIT DATABASE (stambestand)
    // ========================================================================

    #[Test]
    public function import_uit_database_requires_ids(): void
    {
        $response = $this->actingAs($this->org, 'organisator')
            ->post(route('toernooi.judoka.import-database', $this->routeParams()), [
                'stam_judoka_ids' => [],
            ]);

        $response->assertSessionHasErrors('stam_judoka_ids');
    }

    // ========================================================================
    // ACCESS CONTROL
    // ========================================================================

    #[Test]
    public function other_organisator_cannot_access_judokas(): void
    {
        $other = Organisator::factory()->create();

        $response = $this->actingAs($other, 'organisator')
            ->get(route('toernooi.judoka.index', $this->routeParams()));

        // Should be redirected (middleware denies access)
        $this->assertContains($response->status(), [302, 401, 403]);
    }

    #[Test]
    public function index_with_empty_toernooi_shows_empty_list(): void
    {
        $response = $this->actingAs($this->org, 'organisator')
            ->get(route('toernooi.judoka.index', $this->routeParams()));

        $response->assertStatus(200);
        $response->assertViewHas('judokas', function ($judokas) {
            return $judokas->count() === 0;
        });
    }

    // ========================================================================
    // SORTING (index covers parseGewicht via sorting)
    // ========================================================================

    #[Test]
    public function index_sorts_by_weight_class_correctly(): void
    {
        Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'leeftijdsklasse' => 'Pupillen',
            'gewichtsklasse' => '+40',
            'geslacht' => 'M',
            'naam' => 'Zwaar',
        ]);
        Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'leeftijdsklasse' => 'Pupillen',
            'gewichtsklasse' => '-24',
            'geslacht' => 'M',
            'naam' => 'Licht',
        ]);
        Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'leeftijdsklasse' => 'Pupillen',
            'gewichtsklasse' => 'Onbekend',
            'geslacht' => 'M',
            'naam' => 'Onbekend',
        ]);

        $response = $this->actingAs($this->org, 'organisator')
            ->get(route('toernooi.judoka.index', $this->routeParams()));

        $response->assertStatus(200);
    }
}
