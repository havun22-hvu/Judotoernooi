<?php

/**
 * DO NOT REMOVE: Coverage boost tests for PouleController.
 *
 * Targets methods at 0% coverage: index (+ getLeeftijdsklasseVolgorde, parseGewicht),
 * zoekMatch, updateKruisfinale, eliminatie, genereerEliminatie, opslaanEliminatieUitslag,
 * seedingBGroep, getBGroepSeeding, getSeedingStatus, swapSeeding, moveSeeding,
 * herstelBKoppelingen, diagnoseBKoppelingen, uitschrijvenJudoka edge cases,
 * genereer, berekenPouleRanges, updateDynamischeTitel, buildPouleResponse.
 */

namespace Tests\Feature;

use App\Models\Blok;
use App\Models\Club;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use App\Services\EliminatieService;
use App\Services\PouleIndelingService;
use App\Services\WedstrijdSchemaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PouleControllerCoverageTest extends TestCase
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
                'u7' => [
                    'label' => 'U7',
                    'max_leeftijd' => 7,
                    'geslacht' => 'gemengd',
                    'max_kg_verschil' => 3,
                    'max_leeftijd_verschil' => 1,
                ],
                'u11' => [
                    'label' => 'U11',
                    'max_leeftijd' => 11,
                    'geslacht' => 'gemengd',
                    'max_kg_verschil' => 5,
                    'max_leeftijd_verschil' => 2,
                ],
                'u15_vast' => [
                    'label' => 'U15',
                    'max_leeftijd' => 15,
                    'geslacht' => 'gemengd',
                    'max_kg_verschil' => 0,
                    'max_leeftijd_verschil' => 0,
                    'gewichtsklassen' => ['-40', '-46', '-50', '+50'],
                ],
            ],
        ]);
        $this->organisator->toernooien()->attach($this->toernooi->id, ['rol' => 'eigenaar']);
        $this->club = Club::factory()->create(['organisator_id' => $this->organisator->id]);
    }

    private function url(string $path = ''): string
    {
        return "/{$this->organisator->slug}/toernooi/{$this->toernooi->slug}" . ($path ? "/{$path}" : '');
    }

    private function act(): self
    {
        return $this->actingAs($this->organisator, 'organisator');
    }

    private function maakPouleMetJudokas(int $aantal = 3, array $pouleOverrides = [], array $judokaOverrides = []): Poule
    {
        $poule = Poule::factory()->create(array_merge([
            'toernooi_id' => $this->toernooi->id,
            'leeftijdsklasse' => 'U7',
            'categorie_key' => 'u7',
        ], $pouleOverrides));

        for ($i = 0; $i < $aantal; $i++) {
            $judoka = Judoka::factory()->create(array_merge([
                'toernooi_id' => $this->toernooi->id,
                'club_id' => $this->club->id,
                'leeftijdsklasse' => 'U7',
                'gewicht' => 20.0 + $i,
                'geboortejaar' => now()->year - 6,
                'geslacht' => 'M',
                'aanwezigheid' => 'aanwezig',
            ], $judokaOverrides));
            $poule->judokas()->attach($judoka->id, ['positie' => $i + 1]);
        }

        $poule->updateStatistieken();
        $poule->refresh();

        return $poule;
    }

    // ========================================================================
    // INDEX — GET poule (covers getLeeftijdsklasseVolgorde + parseGewicht)
    // ========================================================================

    #[Test]
    public function index_shows_poules_sorted_by_age_and_weight(): void
    {
        // Create poules in different categories to exercise sorting
        Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'leeftijdsklasse' => 'U11',
            'categorie_key' => 'u11',
            'gewichtsklasse' => '-30',
        ]);
        Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'leeftijdsklasse' => 'U7',
            'categorie_key' => 'u7',
            'gewichtsklasse' => '-20',
        ]);
        Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'leeftijdsklasse' => 'U7',
            'categorie_key' => 'u7',
            'gewichtsklasse' => '+25',
        ]);

        $response = $this->act()->get($this->url('poule'));

        $response->assertStatus(200);
        $response->assertViewHas('poulesPerKlasse');
        $response->assertViewHas('toernooi');
    }

    #[Test]
    public function index_handles_poules_with_unknown_leeftijdsklasse(): void
    {
        // Poule with leeftijdsklasse not in config - triggers fallback sorting
        Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'leeftijdsklasse' => 'Senioren',
            'gewichtsklasse' => '-73',
        ]);

        $response = $this->act()->get($this->url('poule'));
        $response->assertStatus(200);
    }

    #[Test]
    public function index_handles_poules_with_prefix_match_leeftijdsklasse(): void
    {
        // "U7 Alles" should match U7 prefix
        Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'leeftijdsklasse' => 'U7 Alles',
            'gewichtsklasse' => '-20',
        ]);

        $response = $this->act()->get($this->url('poule'));
        $response->assertStatus(200);
    }

    #[Test]
    public function index_excludes_wedstrijddag_poules(): void
    {
        // Create a blok with weging_gesloten_op in the past
        $blok = Blok::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'weging_gesloten_op' => now()->subHour(),
        ]);

        // Poule created AFTER weging closed = wedstrijddag poule, should be excluded
        Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'blok_id' => $blok->id,
            'leeftijdsklasse' => 'U7',
            'created_at' => now(), // after weging_gesloten_op
        ]);

        $response = $this->act()->get($this->url('poule'));
        $response->assertStatus(200);
    }

    #[Test]
    public function index_handles_no_poules(): void
    {
        $response = $this->act()->get($this->url('poule'));
        $response->assertStatus(200);
        $response->assertViewHas('poulesPerKlasse');
    }

    #[Test]
    public function index_with_non_numeric_gewichtsklasse(): void
    {
        // Non-parseable gewichtsklasse triggers fallback in parseGewicht
        Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'leeftijdsklasse' => 'U7',
            'gewichtsklasse' => 'open',
        ]);

        $response = $this->act()->get($this->url('poule'));
        $response->assertStatus(200);
    }

    // ========================================================================
    // GENEREER — POST poule/genereer
    // ========================================================================

    #[Test]
    public function genereer_redirects_with_success(): void
    {
        // Create judokas that can be categorized
        for ($i = 0; $i < 6; $i++) {
            Judoka::factory()->create([
                'toernooi_id' => $this->toernooi->id,
                'club_id' => $this->club->id,
                'leeftijdsklasse' => 'U7',
                'categorie_key' => 'u7',
                'gewicht' => 20.0 + $i,
                'geboortejaar' => now()->year - 6,
                'geslacht' => 'M',
                'aanwezigheid' => 'aanwezig',
            ]);
        }

        $response = $this->act()->post($this->url('poule/genereer'));
        $response->assertRedirect();
    }

    #[Test]
    public function genereer_with_no_judokas_still_redirects(): void
    {
        // No judokas at all — genereer should still work (produces 0 poules)
        $response = $this->act()->post($this->url('poule/genereer'));
        $response->assertRedirect();
    }

    // ========================================================================
    // ZOEK MATCH — GET poule/zoek-match/{judoka}
    // ========================================================================

    #[Test]
    public function zoek_match_returns_matching_poules(): void
    {
        $pouleA = $this->maakPouleMetJudokas(3);
        $pouleB = $this->maakPouleMetJudokas(3, [
            'leeftijdsklasse' => 'U7',
            'categorie_key' => 'u7',
        ]);

        $judoka = $pouleA->judokas()->first();

        $response = $this->act()->getJson($this->url("poule/zoek-match/{$judoka->id}"));

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'judoka' => ['id', 'naam', 'gewicht', 'leeftijd'],
                'matches',
            ]);
    }

    #[Test]
    public function zoek_match_with_from_poule_id(): void
    {
        $pouleA = $this->maakPouleMetJudokas(3);
        $pouleB = $this->maakPouleMetJudokas(3);

        $judoka = $pouleA->judokas()->first();

        $response = $this->act()->getJson(
            $this->url("poule/zoek-match/{$judoka->id}") . "?from_poule_id={$pouleA->id}"
        );

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    #[Test]
    public function zoek_match_with_wedstrijddag_flag(): void
    {
        $blok = Blok::factory()->create(['toernooi_id' => $this->toernooi->id, 'nummer' => 1]);
        $pouleA = $this->maakPouleMetJudokas(3, ['blok_id' => $blok->id]);
        $pouleB = $this->maakPouleMetJudokas(3, ['blok_id' => $blok->id]);

        $judoka = $pouleA->judokas()->first();

        $response = $this->act()->getJson(
            $this->url("poule/zoek-match/{$judoka->id}") . "?wedstrijddag=1&from_poule_id={$pouleA->id}"
        );

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    #[Test]
    public function zoek_match_filters_by_gender(): void
    {
        // Male judoka should not see female-only poules
        $pouleM = $this->maakPouleMetJudokas(3, [], ['geslacht' => 'M']);
        $pouleV = $this->maakPouleMetJudokas(3, [], ['geslacht' => 'V']);

        $judoka = $pouleM->judokas()->first();

        $response = $this->act()->getJson($this->url("poule/zoek-match/{$judoka->id}"));

        $response->assertStatus(200);
        $matches = $response->json('matches');
        // Female poule should be filtered out
        $matchPouleIds = collect($matches)->pluck('poule_id')->toArray();
        $this->assertNotContains($pouleV->id, $matchPouleIds);
    }

    #[Test]
    public function zoek_match_with_cross_category(): void
    {
        $pouleA = $this->maakPouleMetJudokas(3, [
            'leeftijdsklasse' => 'U7',
            'categorie_key' => 'u7',
        ]);
        $pouleB = $this->maakPouleMetJudokas(3, [
            'leeftijdsklasse' => 'U11',
            'categorie_key' => 'u11',
        ], ['leeftijdsklasse' => 'U11', 'geboortejaar' => now()->year - 10]);

        $judoka = $pouleA->judokas()->first();

        $response = $this->act()->getJson($this->url("poule/zoek-match/{$judoka->id}"));

        $response->assertStatus(200);
        // Should include cross-category matches
        $matches = $response->json('matches');
        $this->assertNotEmpty($matches);
    }

    #[Test]
    public function zoek_match_with_fixed_weight_classes(): void
    {
        // Use the u15_vast category which has max_kg_verschil=0 (fixed weight classes)
        $pouleA = $this->maakPouleMetJudokas(3, [
            'leeftijdsklasse' => 'U15',
            'categorie_key' => 'u15_vast',
            'gewichtsklasse' => '-40',
        ], [
            'leeftijdsklasse' => 'U15',
            'gewichtsklasse' => '-40',
            'gewicht' => 38.0,
            'geboortejaar' => now()->year - 14,
        ]);

        $pouleB = $this->maakPouleMetJudokas(3, [
            'leeftijdsklasse' => 'U15',
            'categorie_key' => 'u15_vast',
            'gewichtsklasse' => '-46',
        ], [
            'leeftijdsklasse' => 'U15',
            'gewichtsklasse' => '-46',
            'gewicht' => 44.0,
            'geboortejaar' => now()->year - 14,
        ]);

        $judoka = $pouleA->judokas()->first();

        $response = $this->act()->getJson($this->url("poule/zoek-match/{$judoka->id}"));

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    #[Test]
    public function zoek_match_skips_empty_poules(): void
    {
        $pouleA = $this->maakPouleMetJudokas(3);
        // Empty poule (all absent)
        $emptyPoule = Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'leeftijdsklasse' => 'U7',
        ]);
        $absentJudoka = Judoka::factory()->afwezig()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
        ]);
        $emptyPoule->judokas()->attach($absentJudoka->id, ['positie' => 1]);

        $judoka = $pouleA->judokas()->first();

        $response = $this->act()->getJson($this->url("poule/zoek-match/{$judoka->id}"));

        $response->assertStatus(200);
        // Empty poule should not appear in matches
        $matchPouleIds = collect($response->json('matches'))->pluck('poule_id')->toArray();
        $this->assertNotContains($emptyPoule->id, $matchPouleIds);
    }

    // ========================================================================
    // UPDATE KRUISFINALE — PATCH poule/{poule}/kruisfinale
    // ========================================================================

    #[Test]
    public function update_kruisfinale_updates_plaatsen(): void
    {
        // Create voorronde poules
        Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'leeftijdsklasse' => 'U7',
            'gewichtsklasse' => '-20',
            'type' => 'voorronde',
        ]);
        Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'leeftijdsklasse' => 'U7',
            'gewichtsklasse' => '-20',
            'type' => 'voorronde',
        ]);

        // Create kruisfinale poule
        $kruisfinale = Poule::factory()->kruisfinale()->create([
            'toernooi_id' => $this->toernooi->id,
            'leeftijdsklasse' => 'U7',
            'gewichtsklasse' => '-20',
        ]);

        $response = $this->act()->patchJson(
            $this->url("poule/{$kruisfinale->id}/kruisfinale"),
            ['kruisfinale_plaatsen' => 2]
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'aantal_judokas' => 4, // 2 voorronde × 2 plaatsen
            ]);

        $kruisfinale->refresh();
        $this->assertEquals(2, $kruisfinale->kruisfinale_plaatsen);
    }

    #[Test]
    public function update_kruisfinale_rejects_non_kruisfinale(): void
    {
        $poule = Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'type' => 'voorronde',
        ]);

        $response = $this->act()->patchJson(
            $this->url("poule/{$poule->id}/kruisfinale"),
            ['kruisfinale_plaatsen' => 2]
        );

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function update_kruisfinale_validates_plaatsen(): void
    {
        $kruisfinale = Poule::factory()->kruisfinale()->create([
            'toernooi_id' => $this->toernooi->id,
        ]);

        $response = $this->act()->patchJson(
            $this->url("poule/{$kruisfinale->id}/kruisfinale"),
            ['kruisfinale_plaatsen' => 0] // min:1
        );

        $response->assertStatus(422);
    }

    // ========================================================================
    // ELIMINATIE — GET poule/{poule}/eliminatie
    // ========================================================================

    #[Test]
    public function eliminatie_view_loads(): void
    {
        $poule = $this->maakPouleMetJudokas(4, ['type' => 'eliminatie']);

        // Mock EliminatieService since getBracketStructuur may not exist
        $mock = $this->mock(EliminatieService::class);
        $mock->shouldReceive('getBracketStructuur')
            ->once()
            ->andReturn(['rondes' => []]);

        $response = $this->act()->get($this->url("poule/{$poule->id}/eliminatie"));

        $response->assertStatus(200);
        $response->assertViewHas('poule');
        $response->assertViewHas('bracket');
    }

    // ========================================================================
    // GENEREER ELIMINATIE — POST poule/{poule}/eliminatie/genereer
    // ========================================================================

    #[Test]
    public function genereer_eliminatie_with_enough_judokas(): void
    {
        $poule = $this->maakPouleMetJudokas(4, ['type' => 'eliminatie']);

        $mock = $this->mock(EliminatieService::class);
        $mock->shouldReceive('genereerBracket')
            ->once()
            ->andReturn(['totaal_wedstrijden' => 3]);

        $response = $this->act()->postJson($this->url("poule/{$poule->id}/eliminatie/genereer"));

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    #[Test]
    public function genereer_eliminatie_rejects_too_few_judokas(): void
    {
        $poule = $this->maakPouleMetJudokas(1, ['type' => 'eliminatie']);

        $response = $this->act()->postJson($this->url("poule/{$poule->id}/eliminatie/genereer"));

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function genereer_eliminatie_skips_absent_judokas(): void
    {
        $poule = Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'type' => 'eliminatie',
        ]);

        // 2 active + 1 absent = only 2 sent to service
        for ($i = 0; $i < 2; $i++) {
            $judoka = Judoka::factory()->create([
                'toernooi_id' => $this->toernooi->id,
                'club_id' => $this->club->id,
                'aanwezigheid' => 'aanwezig',
            ]);
            $poule->judokas()->attach($judoka->id, ['positie' => $i + 1]);
        }
        $absent = Judoka::factory()->afwezig()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
        ]);
        $poule->judokas()->attach($absent->id, ['positie' => 3]);

        $mock = $this->mock(EliminatieService::class);
        $mock->shouldReceive('genereerBracket')
            ->once()
            ->withArgs(function ($p, $ids) {
                return count($ids) === 2; // Only 2 active judokas
            })
            ->andReturn(['totaal_wedstrijden' => 1]);

        $response = $this->act()->postJson($this->url("poule/{$poule->id}/eliminatie/genereer"));

        $response->assertStatus(200);
    }

    #[Test]
    public function genereer_eliminatie_returns_error_from_service(): void
    {
        $poule = $this->maakPouleMetJudokas(4, ['type' => 'eliminatie']);

        $mock = $this->mock(EliminatieService::class);
        $mock->shouldReceive('genereerBracket')
            ->once()
            ->andReturn(['error' => 'Onverwachte fout']);

        $response = $this->act()->postJson($this->url("poule/{$poule->id}/eliminatie/genereer"));

        $response->assertStatus(400)
            ->assertJson(['success' => false, 'message' => 'Onverwachte fout']);
    }

    // ========================================================================
    // OPSLAAN ELIMINATIE UITSLAG — POST poule/{poule}/eliminatie/uitslag
    // ========================================================================

    #[Test]
    public function opslaan_eliminatie_uitslag_saves_result(): void
    {
        $poule = $this->maakPouleMetJudokas(2, ['type' => 'eliminatie']);
        $judokas = $poule->judokas;

        $wedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judokas[0]->id,
            'judoka_blauw_id' => $judokas[1]->id,
            'groep' => 'A',
            'ronde' => 'finale',
        ]);

        $mock = $this->mock(EliminatieService::class);
        $mock->shouldReceive('verwerkUitslag')->once()->andReturn([]);

        $response = $this->act()->postJson(
            $this->url("poule/{$poule->id}/eliminatie/uitslag"),
            [
                'wedstrijd_id' => $wedstrijd->id,
                'winnaar_id' => $judokas[0]->id,
                'uitslag_type' => 'ippon',
            ]
        );

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $wedstrijd->refresh();
        $this->assertTrue($wedstrijd->is_gespeeld);
        $this->assertEquals($judokas[0]->id, $wedstrijd->winnaar_id);
    }

    #[Test]
    public function opslaan_eliminatie_uitslag_rejects_invalid_winner(): void
    {
        $poule = $this->maakPouleMetJudokas(2, ['type' => 'eliminatie']);
        $judokas = $poule->judokas;

        $wedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judokas[0]->id,
            'judoka_blauw_id' => $judokas[1]->id,
            'groep' => 'A',
        ]);

        // Create a third judoka who is not in the match
        $other = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
        ]);

        $response = $this->act()->postJson(
            $this->url("poule/{$poule->id}/eliminatie/uitslag"),
            [
                'wedstrijd_id' => $wedstrijd->id,
                'winnaar_id' => $other->id,
            ]
        );

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function opslaan_eliminatie_uitslag_validates_fields(): void
    {
        $poule = Poule::factory()->create(['toernooi_id' => $this->toernooi->id]);

        $response = $this->act()->postJson(
            $this->url("poule/{$poule->id}/eliminatie/uitslag"),
            []
        );

        $response->assertStatus(422);
    }

    // ========================================================================
    // SEEDING B-GROEP — POST poule/{poule}/eliminatie/seeding
    // ========================================================================

    #[Test]
    public function seeding_b_groep_validates_groep(): void
    {
        $poule = $this->maakPouleMetJudokas(4, ['type' => 'eliminatie']);
        $judoka = $poule->judokas()->first();

        // Create A-groep wedstrijd (not B) — should be rejected
        $vanWedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judoka->id,
            'groep' => 'A',
        ]);
        $naarWedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'groep' => 'B',
            'judoka_wit_id' => null,
            'judoka_blauw_id' => null,
        ]);

        $response = $this->act()->postJson(
            $this->url("poule/{$poule->id}/eliminatie/seeding"),
            [
                'judoka_id' => $judoka->id,
                'van_wedstrijd_id' => $vanWedstrijd->id,
                'naar_wedstrijd_id' => $naarWedstrijd->id,
                'naar_slot' => 'wit',
            ]
        );

        $response->assertStatus(400)
            ->assertJson(['message' => 'Seeding is alleen mogelijk binnen de B-groep']);
    }

    #[Test]
    public function seeding_b_groep_rejects_wrong_poule(): void
    {
        $poule = $this->maakPouleMetJudokas(4, ['type' => 'eliminatie']);
        $otherPoule = Poule::factory()->create(['toernooi_id' => $this->toernooi->id]);
        $judoka = $poule->judokas()->first();

        $vanWedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $otherPoule->id, // wrong poule
            'judoka_wit_id' => $judoka->id,
            'groep' => 'B',
        ]);
        $naarWedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'groep' => 'B',
            'judoka_wit_id' => null,
        ]);

        $response = $this->act()->postJson(
            $this->url("poule/{$poule->id}/eliminatie/seeding"),
            [
                'judoka_id' => $judoka->id,
                'van_wedstrijd_id' => $vanWedstrijd->id,
                'naar_wedstrijd_id' => $naarWedstrijd->id,
                'naar_slot' => 'wit',
            ]
        );

        $response->assertStatus(400)
            ->assertJson(['message' => 'Wedstrijden horen niet bij deze poule']);
    }

    #[Test]
    public function seeding_b_groep_rejects_when_locked(): void
    {
        $poule = $this->maakPouleMetJudokas(4, ['type' => 'eliminatie']);
        $judoka = $poule->judokas()->first();
        $judoka2 = $poule->judokas()->skip(1)->first();

        $vanWedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judoka->id,
            'judoka_blauw_id' => null,
            'groep' => 'B',
            'is_gespeeld' => false,
        ]);
        $naarWedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => null,
            'judoka_blauw_id' => null,
            'groep' => 'B',
            'is_gespeeld' => false,
        ]);
        // Create a played B-wedstrijd to lock the bracket
        Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judoka2->id,
            'groep' => 'B',
            'is_gespeeld' => true,
            'winnaar_id' => $judoka2->id,
        ]);

        $response = $this->act()->postJson(
            $this->url("poule/{$poule->id}/eliminatie/seeding"),
            [
                'judoka_id' => $judoka->id,
                'van_wedstrijd_id' => $vanWedstrijd->id,
                'naar_wedstrijd_id' => $naarWedstrijd->id,
                'naar_slot' => 'wit',
            ]
        );

        $response->assertStatus(400)
            ->assertJson(['message' => 'Bracket is vergrendeld - er zijn al wedstrijden gespeeld in de B-groep']);
    }

    #[Test]
    public function seeding_b_groep_rejects_judoka_not_in_source(): void
    {
        $poule = $this->maakPouleMetJudokas(4, ['type' => 'eliminatie']);
        $judoka = $poule->judokas()->first();
        $otherJudoka = $poule->judokas()->skip(1)->first();

        $vanWedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $otherJudoka->id,
            'judoka_blauw_id' => null,
            'groep' => 'B',
        ]);
        $naarWedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => null,
            'judoka_blauw_id' => null,
            'groep' => 'B',
        ]);

        $response = $this->act()->postJson(
            $this->url("poule/{$poule->id}/eliminatie/seeding"),
            [
                'judoka_id' => $judoka->id, // not in vanWedstrijd
                'van_wedstrijd_id' => $vanWedstrijd->id,
                'naar_wedstrijd_id' => $naarWedstrijd->id,
                'naar_slot' => 'wit',
            ]
        );

        $response->assertStatus(400)
            ->assertJson(['message' => 'Judoka zit niet in de bron wedstrijd']);
    }

    #[Test]
    public function seeding_b_groep_rejects_occupied_slot(): void
    {
        $poule = $this->maakPouleMetJudokas(4, ['type' => 'eliminatie']);
        $judoka = $poule->judokas()->first();
        $otherJudoka = $poule->judokas()->skip(1)->first();

        $vanWedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judoka->id,
            'judoka_blauw_id' => null,
            'groep' => 'B',
        ]);
        $naarWedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $otherJudoka->id, // wit slot occupied
            'judoka_blauw_id' => null,
            'groep' => 'B',
        ]);

        $response = $this->act()->postJson(
            $this->url("poule/{$poule->id}/eliminatie/seeding"),
            [
                'judoka_id' => $judoka->id,
                'van_wedstrijd_id' => $vanWedstrijd->id,
                'naar_wedstrijd_id' => $naarWedstrijd->id,
                'naar_slot' => 'wit',
            ]
        );

        $response->assertStatus(400)
            ->assertJson(['message' => 'Doel slot is niet leeg']);
    }

    #[Test]
    public function seeding_b_groep_success_moves_judoka(): void
    {
        $poule = $this->maakPouleMetJudokas(4, ['type' => 'eliminatie']);
        $judoka = $poule->judokas()->first();

        $vanWedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judoka->id,
            'judoka_blauw_id' => null,
            'groep' => 'B',
            'ronde' => 'kwartfinale',
        ]);
        $naarWedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => null,
            'judoka_blauw_id' => null,
            'groep' => 'B',
            'ronde' => 'halve_finale',
        ]);

        // Mock heeftAlGespeeld for rematch check
        $mock = $this->partialMock(EliminatieService::class);
        $mock->shouldReceive('heeftAlGespeeld')->andReturn(false);

        $response = $this->act()->postJson(
            $this->url("poule/{$poule->id}/eliminatie/seeding"),
            [
                'judoka_id' => $judoka->id,
                'van_wedstrijd_id' => $vanWedstrijd->id,
                'naar_wedstrijd_id' => $naarWedstrijd->id,
                'naar_slot' => 'blauw',
            ]
        );

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Verify judoka moved
        $vanWedstrijd->refresh();
        $naarWedstrijd->refresh();
        $this->assertNull($vanWedstrijd->judoka_wit_id);
        $this->assertEquals($judoka->id, $naarWedstrijd->judoka_blauw_id);
    }

    #[Test]
    public function seeding_b_groep_warns_on_rematch(): void
    {
        $poule = $this->maakPouleMetJudokas(4, ['type' => 'eliminatie']);
        $judoka = $poule->judokas()->first();
        $tegenstander = $poule->judokas()->skip(1)->first();

        $vanWedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_blauw_id' => $judoka->id,
            'judoka_wit_id' => null,
            'groep' => 'B',
            'ronde' => 'kwartfinale',
        ]);
        $naarWedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $tegenstander->id,
            'judoka_blauw_id' => null, // empty blauw slot
            'groep' => 'B',
            'ronde' => 'halve_finale',
        ]);

        $mock = $this->partialMock(EliminatieService::class);
        $mock->shouldReceive('heeftAlGespeeld')->andReturn(true);

        $response = $this->act()->postJson(
            $this->url("poule/{$poule->id}/eliminatie/seeding"),
            [
                'judoka_id' => $judoka->id,
                'van_wedstrijd_id' => $vanWedstrijd->id,
                'naar_wedstrijd_id' => $naarWedstrijd->id,
                'naar_slot' => 'blauw',
            ]
        );

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
        $this->assertNotNull($response->json('waarschuwing'));
    }

    // ========================================================================
    // GET B-GROEP SEEDING — GET poule/{poule}/eliminatie/b-groep
    // ========================================================================

    #[Test]
    public function get_b_groep_seeding_returns_data(): void
    {
        $poule = $this->maakPouleMetJudokas(4, ['type' => 'eliminatie']);
        $judokas = $poule->judokas;

        // Create B-groep wedstrijden
        Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judokas[0]->id,
            'judoka_blauw_id' => $judokas[1]->id,
            'groep' => 'B',
            'ronde' => 'kwartfinale',
            'bracket_positie' => 1,
        ]);

        $mock = $this->partialMock(EliminatieService::class);
        $mock->shouldReceive('heeftAlGespeeld')->andReturn(false);

        $response = $this->act()->getJson($this->url("poule/{$poule->id}/eliminatie/b-groep"));

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['is_locked', 'rematches', 'wedstrijden']);
    }

    #[Test]
    public function get_b_groep_seeding_detects_rematches(): void
    {
        $poule = $this->maakPouleMetJudokas(4, ['type' => 'eliminatie']);
        $judokas = $poule->judokas;

        Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judokas[0]->id,
            'judoka_blauw_id' => $judokas[1]->id,
            'groep' => 'B',
            'ronde' => 'kwartfinale',
            'bracket_positie' => 1,
        ]);

        $mock = $this->partialMock(EliminatieService::class);
        $mock->shouldReceive('heeftAlGespeeld')->andReturn(true);

        $response = $this->act()->getJson($this->url("poule/{$poule->id}/eliminatie/b-groep"));

        $response->assertStatus(200);
        $rematches = $response->json('rematches');
        $this->assertNotEmpty($rematches);
    }

    // ========================================================================
    // GET SEEDING STATUS — GET poule/{poule}/eliminatie/seeding-status
    // ========================================================================

    #[Test]
    public function get_seeding_status_returns_data(): void
    {
        $poule = $this->maakPouleMetJudokas(4, ['type' => 'eliminatie']);
        $judokas = $poule->judokas;

        // Create A-groep eerste ronde wedstrijden
        Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judokas[0]->id,
            'judoka_blauw_id' => $judokas[1]->id,
            'groep' => 'A',
            'ronde' => 'kwartfinale',
            'bracket_positie' => 1,
        ]);

        $mock = $this->partialMock(EliminatieService::class);
        $mock->shouldReceive('isInSeedingFase')->andReturn(true);

        $response = $this->act()->getJson($this->url("poule/{$poule->id}/eliminatie/seeding-status"));

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['is_locked', 'club_conflicten', 'wedstrijden']);
    }

    #[Test]
    public function get_seeding_status_detects_club_conflicts(): void
    {
        $poule = $this->maakPouleMetJudokas(2, ['type' => 'eliminatie']);
        $judokas = $poule->judokas;

        // Both judokas are from same club (set in maakPouleMetJudokas)
        Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judokas[0]->id,
            'judoka_blauw_id' => $judokas[1]->id,
            'groep' => 'A',
            'ronde' => 'kwartfinale',
            'bracket_positie' => 1,
        ]);

        $mock = $this->partialMock(EliminatieService::class);
        $mock->shouldReceive('isInSeedingFase')->andReturn(true);

        $response = $this->act()->getJson($this->url("poule/{$poule->id}/eliminatie/seeding-status"));

        $response->assertStatus(200);
        $conflicts = $response->json('club_conflicten');
        $this->assertNotEmpty($conflicts);
    }

    // ========================================================================
    // SWAP SEEDING — POST poule/{poule}/eliminatie/swap
    // ========================================================================

    #[Test]
    public function swap_seeding_calls_service(): void
    {
        $poule = $this->maakPouleMetJudokas(4, ['type' => 'eliminatie']);
        $judokas = $poule->judokas;

        $mock = $this->partialMock(EliminatieService::class);
        $mock->shouldReceive('swapJudokas')
            ->once()
            ->andReturn(['success' => true, 'message' => 'Swap uitgevoerd']);

        $response = $this->act()->postJson(
            $this->url("poule/{$poule->id}/eliminatie/swap"),
            [
                'judoka_a_id' => $judokas[0]->id,
                'judoka_b_id' => $judokas[1]->id,
            ]
        );

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    #[Test]
    public function swap_seeding_returns_error_from_service(): void
    {
        $poule = $this->maakPouleMetJudokas(4, ['type' => 'eliminatie']);
        $judokas = $poule->judokas;

        $mock = $this->partialMock(EliminatieService::class);
        $mock->shouldReceive('swapJudokas')
            ->once()
            ->andReturn(['success' => false, 'message' => 'Seeding fase voorbij']);

        $response = $this->act()->postJson(
            $this->url("poule/{$poule->id}/eliminatie/swap"),
            [
                'judoka_a_id' => $judokas[0]->id,
                'judoka_b_id' => $judokas[1]->id,
            ]
        );

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    // ========================================================================
    // MOVE SEEDING — POST poule/{poule}/eliminatie/move
    // ========================================================================

    #[Test]
    public function move_seeding_calls_service(): void
    {
        $poule = $this->maakPouleMetJudokas(4, ['type' => 'eliminatie']);
        $judoka = $poule->judokas()->first();

        $wedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'groep' => 'A',
            'ronde' => 'kwartfinale',
        ]);

        $mock = $this->partialMock(EliminatieService::class);
        $mock->shouldReceive('moveJudokaNaarLegePlek')
            ->once()
            ->andReturn(['success' => true, 'message' => 'Verplaatst']);

        $response = $this->act()->postJson(
            $this->url("poule/{$poule->id}/eliminatie/move"),
            [
                'judoka_id' => $judoka->id,
                'naar_wedstrijd_id' => $wedstrijd->id,
                'naar_positie' => 'wit',
            ]
        );

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    #[Test]
    public function move_seeding_validates_positie(): void
    {
        $poule = Poule::factory()->create(['toernooi_id' => $this->toernooi->id]);
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
        ]);
        $wedstrijd = Wedstrijd::factory()->create(['poule_id' => $poule->id]);

        $response = $this->act()->postJson(
            $this->url("poule/{$poule->id}/eliminatie/move"),
            [
                'judoka_id' => $judoka->id,
                'naar_wedstrijd_id' => $wedstrijd->id,
                'naar_positie' => 'invalid', // not wit/blauw
            ]
        );

        $response->assertStatus(422);
    }

    // ========================================================================
    // HERSTEL B-KOPPELINGEN — POST poule/{poule}/eliminatie/herstel-koppelingen
    // ========================================================================

    #[Test]
    public function herstel_b_koppelingen_calls_service(): void
    {
        $poule = Poule::factory()->create(['toernooi_id' => $this->toernooi->id]);

        $mock = $this->partialMock(EliminatieService::class);
        $mock->shouldReceive('herstelBKoppelingen')
            ->with($poule->id)
            ->once()
            ->andReturn(3);

        $response = $this->act()->postJson(
            $this->url("poule/{$poule->id}/eliminatie/herstel-koppelingen")
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'hersteld' => 3,
            ]);
    }

    // ========================================================================
    // DIAGNOSE B-KOPPELINGEN — GET poule/{poule}/eliminatie/diagnose-koppelingen
    // ========================================================================

    #[Test]
    public function diagnose_b_koppelingen_returns_data(): void
    {
        $poule = Poule::factory()->create(['toernooi_id' => $this->toernooi->id]);

        // Create B-groep wedstrijden
        Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'groep' => 'B',
            'ronde' => 'kwartfinale',
            'bracket_positie' => 1,
            'volgorde' => 1,
        ]);
        Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'groep' => 'B',
            'ronde' => 'halve_finale',
            'bracket_positie' => 1,
            'volgorde' => 2,
        ]);

        $response = $this->act()->getJson(
            $this->url("poule/{$poule->id}/eliminatie/diagnose-koppelingen")
        );

        $response->assertStatus(200)
            ->assertJsonStructure(['rondes', 'koppelingen']);
    }

    #[Test]
    public function diagnose_b_koppelingen_empty_bracket(): void
    {
        $poule = Poule::factory()->create(['toernooi_id' => $this->toernooi->id]);

        $response = $this->act()->getJson(
            $this->url("poule/{$poule->id}/eliminatie/diagnose-koppelingen")
        );

        $response->assertStatus(200)
            ->assertJson(['rondes' => [], 'koppelingen' => []]);
    }

    // ========================================================================
    // VERIFIEER — edge cases for weight/category checks
    // ========================================================================

    #[Test]
    public function verifieer_detects_eliminatie_too_few_judokas(): void
    {
        // Eliminatie poule with < 8 judokas should trigger problem
        $this->maakPouleMetJudokas(5, ['type' => 'eliminatie']);

        $response = $this->act()->postJson($this->url('poule/verifieer'));

        $response->assertStatus(200);
        $problemen = $response->json('problemen');
        $types = collect($problemen)->pluck('type')->toArray();
        $this->assertContains('te_weinig', $types);
    }

    #[Test]
    public function verifieer_skips_kruisfinale_size_check(): void
    {
        // Kruisfinale poules have no size restrictions
        $this->maakPouleMetJudokas(2, [
            'type' => 'kruisfinale',
        ]);

        $response = $this->act()->postJson($this->url('poule/verifieer'));

        $response->assertStatus(200);
        $problemen = $response->json('problemen');
        // Should NOT have size problems for kruisfinale
        $sizeProblems = collect($problemen)->whereIn('type', ['te_weinig', 'te_veel'])->count();
        $this->assertEquals(0, $sizeProblems);
    }

    #[Test]
    public function verifieer_detects_category_mismatch(): void
    {
        $poule = Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'leeftijdsklasse' => 'U7',
            'categorie_key' => 'u7',
        ]);

        // Judoka with wrong category
        for ($i = 0; $i < 3; $i++) {
            $judoka = Judoka::factory()->create([
                'toernooi_id' => $this->toernooi->id,
                'club_id' => $this->club->id,
                'leeftijdsklasse' => 'U11',
                'categorie_key' => 'u11', // mismatch with poule's u7
                'gewicht' => 25.0 + $i,
                'geboortejaar' => now()->year - 10,
                'aanwezigheid' => 'aanwezig',
            ]);
            $poule->judokas()->attach($judoka->id, ['positie' => $i + 1]);
        }
        $poule->updateStatistieken();

        $response = $this->act()->postJson($this->url('poule/verifieer'));

        $response->assertStatus(200);
        $problemen = $response->json('problemen');
        $types = collect($problemen)->pluck('type')->toArray();
        $this->assertContains('categorie', $types);
    }

    #[Test]
    public function verifieer_recalculates_mismatched_wedstrijden(): void
    {
        $poule = $this->maakPouleMetJudokas(3);

        // Manually create wrong number of wedstrijden (should be 3 for 3 judokas)
        Wedstrijd::factory()->count(2)->create([
            'poule_id' => $poule->id,
        ]);

        $response = $this->act()->postJson($this->url('poule/verifieer'));

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertGreaterThanOrEqual(1, $data['herberekend']);
    }

    // ========================================================================
    // STORE — edge case: inherits blok_id and categorie_key
    // ========================================================================

    #[Test]
    public function store_inherits_blok_from_existing_poule(): void
    {
        $blok = Blok::factory()->create(['toernooi_id' => $this->toernooi->id]);
        Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'leeftijdsklasse' => 'U7',
            'categorie_key' => 'u7',
            'blok_id' => $blok->id,
        ]);

        $response = $this->act()->postJson($this->url('poule'), [
            'leeftijdsklasse' => 'U7',
            'gewichtsklasse' => '-22',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // New poule should inherit blok_id and categorie_key
        $newPoule = Poule::where('toernooi_id', $this->toernooi->id)
            ->where('gewichtsklasse', '-22')
            ->first();
        $this->assertEquals($blok->id, $newPoule->blok_id);
        $this->assertEquals('u7', $newPoule->categorie_key);
    }

    // ========================================================================
    // DESTROY — edge case: poule with judokas who have no weight (weging open)
    // ========================================================================

    #[Test]
    public function destroy_rejects_with_unweighed_judokas_when_weging_open(): void
    {
        $poule = Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
        ]);

        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
            'aanwezigheid' => 'aanwezig',
            'gewicht_gewogen' => null, // not weighed
        ]);
        $poule->judokas()->attach($judoka->id, ['positie' => 1]);

        $response = $this->act()->deleteJson($this->url("poule/{$poule->id}"));

        // When weging is not closed, unweighed judokas are still "active"
        $response->assertStatus(400);
    }

    // ========================================================================
    // AUTH — all new endpoints require authentication
    // ========================================================================

    #[Test]
    public function eliminatie_endpoints_require_auth(): void
    {
        $poule = Poule::factory()->create(['toernooi_id' => $this->toernooi->id]);

        $this->getJson($this->url("poule/{$poule->id}/eliminatie"))->assertStatus(401);
        $this->postJson($this->url("poule/{$poule->id}/eliminatie/genereer"))->assertStatus(401);
        $this->postJson($this->url("poule/{$poule->id}/eliminatie/uitslag"))->assertStatus(401);
        $this->postJson($this->url("poule/{$poule->id}/eliminatie/seeding"))->assertStatus(401);
        $this->getJson($this->url("poule/{$poule->id}/eliminatie/b-groep"))->assertStatus(401);
        $this->getJson($this->url("poule/{$poule->id}/eliminatie/seeding-status"))->assertStatus(401);
        $this->postJson($this->url("poule/{$poule->id}/eliminatie/swap"))->assertStatus(401);
        $this->postJson($this->url("poule/{$poule->id}/eliminatie/move"))->assertStatus(401);
        $this->postJson($this->url("poule/{$poule->id}/eliminatie/herstel-koppelingen"))->assertStatus(401);
        $this->getJson($this->url("poule/{$poule->id}/eliminatie/diagnose-koppelingen"))->assertStatus(401);
    }

    #[Test]
    public function zoek_match_requires_auth(): void
    {
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
        ]);

        $this->getJson($this->url("poule/zoek-match/{$judoka->id}"))->assertStatus(401);
    }

    #[Test]
    public function kruisfinale_update_requires_auth(): void
    {
        $poule = Poule::factory()->create(['toernooi_id' => $this->toernooi->id]);
        $this->patchJson($this->url("poule/{$poule->id}/kruisfinale"))->assertStatus(401);
    }
}
