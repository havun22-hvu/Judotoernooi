<?php

/**
 * DO NOT REMOVE: Feature tests for PouleController API endpoints.
 *
 * These tests verify actual HTTP requests to the poule management endpoints,
 * ensuring JSON responses include the 'problemen' key where applicable.
 * This is a CRITICAL guard — without 'problemen', the JS updatePouleStats()
 * function cannot update warning icons after mutations.
 *
 * @see PouleController::buildPouleResponse() — must include 'problemen' key
 * @see PouleController::verplaatsJudokaApi()
 * @see PouleController::uitschrijvenJudoka()
 * @see PouleController::store()
 * @see PouleController::destroy()
 * @see PouleController::verifieer()
 */

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PouleControllerApiTest extends TestCase
{
    use RefreshDatabase;

    private Organisator $organisator;
    private Toernooi $toernooi;
    private Club $club;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisator = Organisator::factory()->create();
        $this->toernooi = Toernooi::factory()->create([
            'organisator_id' => $this->organisator->id,
            'gewichtsklassen' => [
                'test_cat' => [
                    'label' => 'Test',
                    'max_leeftijd' => 12,
                    'geslacht' => 'gemengd',
                    'max_kg_verschil' => 3,
                    'max_leeftijd_verschil' => 1,
                ],
            ],
        ]);

        // Link organisator to toernooi via pivot
        $this->organisator->toernooien()->attach($this->toernooi->id, ['rol' => 'eigenaar']);

        $this->club = Club::factory()->create(['organisator_id' => $this->organisator->id]);
    }

    /**
     * Build the URL prefix for toernooi-scoped routes.
     */
    private function toernooiUrl(string $path = ''): string
    {
        return "/{$this->organisator->slug}/toernooi/{$this->toernooi->slug}" . ($path ? "/{$path}" : '');
    }

    /**
     * Create a poule with judokas attached.
     */
    private function maakPouleMetJudokas(int $aantalJudokas = 3, array $pouleOverrides = []): Poule
    {
        $poule = Poule::factory()->create(array_merge([
            'toernooi_id' => $this->toernooi->id,
            'leeftijdsklasse' => 'Test',
            'categorie_key' => 'test_cat',
        ], $pouleOverrides));

        for ($i = 0; $i < $aantalJudokas; $i++) {
            $judoka = Judoka::factory()->create([
                'toernooi_id' => $this->toernooi->id,
                'club_id' => $this->club->id,
                'leeftijdsklasse' => 'Test',
                'gewicht' => 25.0 + $i,
                'geboortejaar' => now()->year - 10,
            ]);
            $poule->judokas()->attach($judoka->id, ['positie' => $i + 1]);
        }

        $poule->updateStatistieken();
        $poule->refresh();

        return $poule;
    }

    // ========================================================================
    // verplaatsJudokaApi — POST poule/verplaats-judoka
    // ========================================================================

    #[Test]
    public function verplaats_judoka_api_returns_success_with_problemen(): void
    {
        $vanPoule = $this->maakPouleMetJudokas(4);
        $naarPoule = $this->maakPouleMetJudokas(3);

        $judoka = $vanPoule->judokas()->first();

        $response = $this->actingAs($this->organisator, 'organisator')
            ->postJson($this->toernooiUrl('poule/verplaats-judoka'), [
                'judoka_id' => $judoka->id,
                'van_poule_id' => $vanPoule->id,
                'naar_poule_id' => $naarPoule->id,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'message',
                'judoka_id',
                'van_poule' => ['id', 'nummer', 'problemen'],
                'naar_poule' => ['id', 'nummer', 'problemen'],
            ]);

        // CRITICAL guard: both poule responses must contain 'problemen' array
        $data = $response->json();
        $this->assertArrayHasKey('problemen', $data['van_poule'],
            'CRITICAL: van_poule response must include "problemen" key for JS warning UI');
        $this->assertIsArray($data['van_poule']['problemen']);
        $this->assertArrayHasKey('problemen', $data['naar_poule'],
            'CRITICAL: naar_poule response must include "problemen" key for JS warning UI');
        $this->assertIsArray($data['naar_poule']['problemen']);
    }

    #[Test]
    public function verplaats_judoka_api_same_poule_returns_no_change(): void
    {
        $poule = $this->maakPouleMetJudokas(3);
        $judoka = $poule->judokas()->first();

        $response = $this->actingAs($this->organisator, 'organisator')
            ->postJson($this->toernooiUrl('poule/verplaats-judoka'), [
                'judoka_id' => $judoka->id,
                'van_poule_id' => $poule->id,
                'naar_poule_id' => $poule->id,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    #[Test]
    public function verplaats_judoka_api_validates_required_fields(): void
    {
        $response = $this->actingAs($this->organisator, 'organisator')
            ->postJson($this->toernooiUrl('poule/verplaats-judoka'), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['judoka_id', 'van_poule_id', 'naar_poule_id']);
    }

    #[Test]
    public function verplaats_judoka_api_requires_authentication(): void
    {
        $response = $this->postJson($this->toernooiUrl('poule/verplaats-judoka'), [
            'judoka_id' => 1,
            'van_poule_id' => 1,
            'naar_poule_id' => 2,
        ]);

        $response->assertStatus(401);
    }

    // ========================================================================
    // uitschrijvenJudoka — POST poule/uitschrijven/{judoka}
    // ========================================================================

    #[Test]
    public function uitschrijven_judoka_returns_success_with_problemen(): void
    {
        $poule = $this->maakPouleMetJudokas(4);
        $judoka = $poule->judokas()->first();

        $response = $this->actingAs($this->organisator, 'organisator')
            ->postJson($this->toernooiUrl("poule/uitschrijven/{$judoka->id}"));

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'message',
                'updated_poules',
            ]);

        // CRITICAL guard: each updated poule must contain 'problemen'
        $data = $response->json();
        foreach ($data['updated_poules'] as $pouleData) {
            $this->assertArrayHasKey('problemen', $pouleData,
                'CRITICAL: updated_poules entries must include "problemen" key for JS warning UI');
            $this->assertIsArray($pouleData['problemen']);
        }
    }

    #[Test]
    public function uitschrijven_judoka_sets_status_to_afgemeld(): void
    {
        $poule = $this->maakPouleMetJudokas(3);
        $judoka = $poule->judokas()->first();

        $this->actingAs($this->organisator, 'organisator')
            ->postJson($this->toernooiUrl("poule/uitschrijven/{$judoka->id}"));

        $judoka->refresh();
        $this->assertEquals('afgemeld', $judoka->aanwezigheid);
    }

    #[Test]
    public function uitschrijven_judoka_removes_from_poule(): void
    {
        $poule = $this->maakPouleMetJudokas(3);
        $judoka = $poule->judokas()->first();

        $this->actingAs($this->organisator, 'organisator')
            ->postJson($this->toernooiUrl("poule/uitschrijven/{$judoka->id}"));

        $poule->refresh();
        $this->assertFalse($poule->judokas->contains($judoka->id));
    }

    #[Test]
    public function uitschrijven_judoka_from_other_toernooi_returns_403(): void
    {
        $otherToernooi = Toernooi::factory()->create(['organisator_id' => $this->organisator->id]);
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $otherToernooi->id,
            'club_id' => $this->club->id,
        ]);

        $response = $this->actingAs($this->organisator, 'organisator')
            ->postJson($this->toernooiUrl("poule/uitschrijven/{$judoka->id}"));

        $response->assertStatus(403);
    }

    // ========================================================================
    // store — POST poule
    // ========================================================================

    #[Test]
    public function store_creates_new_empty_poule(): void
    {
        $response = $this->actingAs($this->organisator, 'organisator')
            ->postJson($this->toernooiUrl('poule'), [
                'leeftijdsklasse' => 'Test',
                'gewichtsklasse' => '-28',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'message',
                'poule' => ['id', 'nummer', 'leeftijdsklasse'],
            ]);

        // Verify poule exists in database
        $this->assertDatabaseHas('poules', [
            'toernooi_id' => $this->toernooi->id,
            'leeftijdsklasse' => 'Test',
            'gewichtsklasse' => '-28',
            'aantal_judokas' => 0,
        ]);
    }

    #[Test]
    public function store_without_gewichtsklasse_creates_poule(): void
    {
        $response = $this->actingAs($this->organisator, 'organisator')
            ->postJson($this->toernooiUrl('poule'), [
                'leeftijdsklasse' => 'Test',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    #[Test]
    public function store_validates_leeftijdsklasse_required(): void
    {
        $response = $this->actingAs($this->organisator, 'organisator')
            ->postJson($this->toernooiUrl('poule'), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['leeftijdsklasse']);
    }

    #[Test]
    public function store_increments_nummer_correctly(): void
    {
        // Create first poule manually
        Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'nummer' => 5,
        ]);

        $response = $this->actingAs($this->organisator, 'organisator')
            ->postJson($this->toernooiUrl('poule'), [
                'leeftijdsklasse' => 'Test',
            ]);

        $response->assertStatus(200);
        $pouleData = $response->json('poule');
        $this->assertEquals(6, $pouleData['nummer']);
    }

    // ========================================================================
    // destroy — DELETE poule/{poule}
    // ========================================================================

    #[Test]
    public function destroy_deletes_empty_poule(): void
    {
        $poule = Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
        ]);

        $response = $this->actingAs($this->organisator, 'organisator')
            ->deleteJson($this->toernooiUrl("poule/{$poule->id}"));

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('poules', ['id' => $poule->id]);
    }

    #[Test]
    public function destroy_rejects_poule_with_active_judokas(): void
    {
        $poule = $this->maakPouleMetJudokas(3);

        $response = $this->actingAs($this->organisator, 'organisator')
            ->deleteJson($this->toernooiUrl("poule/{$poule->id}"));

        $response->assertStatus(400)
            ->assertJson(['success' => false]);

        // Poule should still exist
        $this->assertDatabaseHas('poules', ['id' => $poule->id]);
    }

    #[Test]
    public function destroy_allows_poule_with_only_absent_judokas(): void
    {
        $poule = Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
        ]);

        $judoka = Judoka::factory()->afwezig()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
        ]);
        $poule->judokas()->attach($judoka->id, ['positie' => 1]);

        $response = $this->actingAs($this->organisator, 'organisator')
            ->deleteJson($this->toernooiUrl("poule/{$poule->id}"));

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('poules', ['id' => $poule->id]);
    }

    // ========================================================================
    // verifieer — POST poule/verifieer
    // ========================================================================

    #[Test]
    public function verifieer_returns_success_with_problemen(): void
    {
        // Create some poules with judokas
        $this->maakPouleMetJudokas(3);
        $this->maakPouleMetJudokas(4);

        $response = $this->actingAs($this->organisator, 'organisator')
            ->postJson($this->toernooiUrl('poule/verifieer'));

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'totaal_poules',
                'totaal_wedstrijden',
                'herberekend',
                'problemen',
            ]);

        // CRITICAL guard: problemen key must be present and be an array
        $data = $response->json();
        $this->assertArrayHasKey('problemen', $data,
            'CRITICAL: verifieer response must include "problemen" key');
        $this->assertIsArray($data['problemen']);
    }

    #[Test]
    public function verifieer_detects_poule_with_too_few_judokas(): void
    {
        // Poule with only 2 judokas (min is 3 for regular poules)
        $this->maakPouleMetJudokas(2);

        $response = $this->actingAs($this->organisator, 'organisator')
            ->postJson($this->toernooiUrl('poule/verifieer'));

        $response->assertStatus(200);
        $problemen = $response->json('problemen');
        $this->assertNotEmpty($problemen, 'Poule with 2 judokas should trigger te_weinig problem');

        $types = collect($problemen)->pluck('type')->toArray();
        $this->assertContains('te_weinig', $types);
    }

    #[Test]
    public function verifieer_detects_poule_with_too_many_judokas(): void
    {
        // Poule with 7 judokas (max is 6 for regular poules)
        $this->maakPouleMetJudokas(7);

        $response = $this->actingAs($this->organisator, 'organisator')
            ->postJson($this->toernooiUrl('poule/verifieer'));

        $response->assertStatus(200);
        $problemen = $response->json('problemen');
        $this->assertNotEmpty($problemen, 'Poule with 7 judokas should trigger te_veel problem');

        $types = collect($problemen)->pluck('type')->toArray();
        $this->assertContains('te_veel', $types);
    }

    #[Test]
    public function verifieer_reports_correct_totals(): void
    {
        $this->maakPouleMetJudokas(3); // 3 wedstrijden
        $this->maakPouleMetJudokas(4); // 6 wedstrijden

        $response = $this->actingAs($this->organisator, 'organisator')
            ->postJson($this->toernooiUrl('poule/verifieer'));

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals(2, $data['totaal_poules']);
        $this->assertEquals(9, $data['totaal_wedstrijden']); // 3 + 6
    }

    #[Test]
    public function verifieer_with_no_poules_returns_empty_problemen(): void
    {
        $response = $this->actingAs($this->organisator, 'organisator')
            ->postJson($this->toernooiUrl('poule/verifieer'));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'totaal_poules' => 0,
                'totaal_wedstrijden' => 0,
                'problemen' => [],
            ]);
    }

    // ========================================================================
    // Authentication guard tests
    // ========================================================================

    #[Test]
    public function all_endpoints_require_authentication(): void
    {
        $poule = Poule::factory()->create(['toernooi_id' => $this->toernooi->id]);

        // Each endpoint should return 401 without auth
        $this->postJson($this->toernooiUrl('poule/verplaats-judoka'), [])->assertStatus(401);
        $this->postJson($this->toernooiUrl('poule/uitschrijven/1'))->assertStatus(401);
        $this->postJson($this->toernooiUrl('poule'), [])->assertStatus(401);
        $this->deleteJson($this->toernooiUrl("poule/{$poule->id}"))->assertStatus(401);
        $this->postJson($this->toernooiUrl('poule/verifieer'))->assertStatus(401);
    }
}
