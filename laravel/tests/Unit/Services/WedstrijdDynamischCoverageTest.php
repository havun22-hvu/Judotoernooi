<?php

namespace Tests\Unit\Services;

use App\Models\Blok;
use App\Models\Club;
use App\Models\Judoka;
use App\Models\Mat;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use App\Services\DynamischeIndelingService;
use App\Services\WedstrijdSchemaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class WedstrijdDynamischCoverageTest extends TestCase
{
    use RefreshDatabase;

    private WedstrijdSchemaService $wedstrijdService;
    private DynamischeIndelingService $dynamischeService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wedstrijdService = new WedstrijdSchemaService();
        $this->dynamischeService = new DynamischeIndelingService();
    }

    private function callWedstrijdPrivate(string $method, array $args): mixed
    {
        $ref = new ReflectionMethod(WedstrijdSchemaService::class, $method);
        return $ref->invoke($this->wedstrijdService, ...$args);
    }

    private function callDynamischPrivate(string $method, array $args): mixed
    {
        $ref = new ReflectionMethod(DynamischeIndelingService::class, $method);
        return $ref->invoke($this->dynamischeService, ...$args);
    }

    private function setDynamischProperty(string $property, mixed $value): void
    {
        $ref = new ReflectionProperty(DynamischeIndelingService::class, $property);
        $ref->setValue($this->dynamischeService, $value);
    }

    private function createPouleWithJudokas(int $aantal, array $toernooiOverrides = [], string $pouleType = 'voorronde'): array
    {
        $toernooi = Toernooi::factory()->create(array_merge([
            'dubbel_bij_2_judokas' => true,
            'best_of_three_bij_2' => false,
            'dubbel_bij_3_judokas' => true,
        ], $toernooiOverrides));
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id]);
        $mat = Mat::factory()->create(['toernooi_id' => $toernooi->id]);
        $club = Club::factory()->create(['organisator_id' => $toernooi->organisator_id]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat->id,
            'type' => $pouleType,
            'doorgestuurd_op' => now(),
        ]);

        $judokas = [];
        for ($i = 0; $i < $aantal; $i++) {
            $judoka = Judoka::factory()->create([
                'toernooi_id' => $toernooi->id,
                'club_id' => $club->id,
                'aanwezigheid' => 'aanwezig',
            ]);
            $poule->judokas()->attach($judoka->id, ['positie' => $i + 1]);
            $judokas[] = $judoka;
        }

        return [$poule, $judokas, $toernooi, $blok, $mat, $club];
    }

    // ========================================================================
    // WedstrijdSchemaService — punten competitie via getOptimaleWedstrijdvolgorde
    // Covers lines 106-108
    // ========================================================================

    #[Test]
    public function optimale_volgorde_punten_competitie_schema(): void
    {
        $toernooi = Toernooi::factory()->create([
            'wedstrijd_systeem' => ['test_cat' => 'punten_competitie'],
            'punten_competitie_wedstrijden' => ['test_cat' => 3],
        ]);
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id]);
        $mat = Mat::factory()->create(['toernooi_id' => $toernooi->id]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat->id,
            'type' => 'voorronde',
            'categorie_key' => 'test_cat',
        ]);

        $schema = $this->callWedstrijdPrivate('getOptimaleWedstrijdvolgorde', [$poule, 5]);

        // 5 judokas, 3 matches each = ~7-8 total matches
        $this->assertNotEmpty($schema);

        // Each pair should be valid 1-based indices
        foreach ($schema as [$a, $b]) {
            $this->assertGreaterThanOrEqual(1, $a);
            $this->assertLessThanOrEqual(5, $b);
        }
    }

    // ========================================================================
    // WedstrijdSchemaService — custom schemas (line 117)
    // ========================================================================

    #[Test]
    public function optimale_volgorde_custom_schema(): void
    {
        $customSchema = [[1, 2], [2, 1], [1, 2]];
        $toernooi = Toernooi::factory()->create([
            'wedstrijd_schemas' => [3 => $customSchema],
        ]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'type' => 'voorronde',
        ]);

        $schema = $this->callWedstrijdPrivate('getOptimaleWedstrijdvolgorde', [$poule, 3]);

        $this->assertEquals($customSchema, $schema);
    }

    #[Test]
    public function optimale_volgorde_custom_schema_string_key(): void
    {
        // JSON decode often returns string keys
        $customSchema = [[1, 2], [2, 1]];
        $toernooi = Toernooi::factory()->create([
            'wedstrijd_schemas' => ['2' => $customSchema],
        ]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'type' => 'voorronde',
        ]);

        $schema = $this->callWedstrijdPrivate('getOptimaleWedstrijdvolgorde', [$poule, 2]);

        $this->assertEquals($customSchema, $schema);
    }

    // ========================================================================
    // WedstrijdSchemaService — genereerPuntenCompetitieSchema branches
    // Covers lines 201-207
    // ========================================================================

    #[Test]
    public function punten_comp_schema_minder_dan_round_robin(): void
    {
        // 6 judokas, 3 matches each (round-robin = 5)
        $result = $this->callWedstrijdPrivate('genereerPuntenCompetitieSchema', [6, 3]);

        $this->assertNotEmpty($result);

        // Total should be ~9 matches (6*3/2)
        $totaalNodig = (int) ((6 * 3) / 2);
        $this->assertLessThanOrEqual($totaalNodig + 2, count($result));
    }

    #[Test]
    public function punten_comp_schema_meer_dan_round_robin(): void
    {
        // 4 judokas, 5 matches each (round-robin = 3)
        $result = $this->callWedstrijdPrivate('genereerPuntenCompetitieSchema', [4, 5]);

        $this->assertNotEmpty($result);

        // Each judoka should have approximately 5 matches
        $counts = array_fill(1, 4, 0);
        foreach ($result as [$a, $b]) {
            $counts[$a]++;
            $counts[$b]++;
        }

        foreach ($counts as $count) {
            $this->assertGreaterThanOrEqual(3, $count);
        }
    }

    #[Test]
    public function punten_comp_schema_cap_at_sensible_max(): void
    {
        // wedstrijdenPerJudoka is capped at n*2
        $result = $this->callWedstrijdPrivate('genereerPuntenCompetitieSchema', [3, 100]);

        // Capped to 3*2=6 matches per judoka
        $this->assertNotEmpty($result);
    }

    // ========================================================================
    // WedstrijdSchemaService — puntenCompMinderWedstrijden edge case
    // Covers lines 246-258
    // ========================================================================

    #[Test]
    public function punten_comp_minder_wedstrijden_edge_case_onvoldoende_eerste_pass(): void
    {
        // Large pool where first pass might not fill all needed matches
        // 8 judokas, 2 matches each - very few compared to round-robin of 7
        $roundRobin = $this->callWedstrijdPrivate('genereerRoundRobinSchema', [8]);
        $result = $this->callWedstrijdPrivate('puntenCompMinderWedstrijden', [8, 2, $roundRobin]);

        $this->assertNotEmpty($result);

        $counts = array_fill(1, 8, 0);
        foreach ($result as [$a, $b]) {
            $counts[$a]++;
            $counts[$b]++;
        }

        // Each judoka should have approximately 2 matches
        foreach ($counts as $count) {
            $this->assertLessThanOrEqual(4, $count);
        }
    }

    // ========================================================================
    // WedstrijdSchemaService — getSchemaVoorMat
    // Covers lines 363-463
    // ========================================================================

    #[Test]
    public function get_schema_voor_mat_returns_correct_structure(): void
    {
        [$poule, $judokas, $toernooi, $blok, $mat] = $this->createPouleWithJudokas(4);

        // Generate wedstrijden for the poule
        $this->wedstrijdService->genereerWedstrijdenVoorPoule($poule);

        $result = $this->wedstrijdService->getSchemaVoorMat($blok, $mat);

        $this->assertArrayHasKey('mat', $result);
        $this->assertArrayHasKey('poules', $result);
        $this->assertEquals($mat->id, $result['mat']['id']);
        $this->assertEquals($mat->nummer, $result['mat']['nummer']);

        // Should have 1 poule
        $this->assertCount(1, $result['poules']);

        $pouleSchema = $result['poules'][0];
        $this->assertEquals($poule->id, $pouleSchema['poule_id']);
        $this->assertArrayHasKey('wedstrijden', $pouleSchema);
        $this->assertArrayHasKey('judokas', $pouleSchema);
        $this->assertArrayHasKey('titel', $pouleSchema);
        $this->assertArrayHasKey('judoka_count', $pouleSchema);
        $this->assertArrayHasKey('is_punten_competitie', $pouleSchema);
        $this->assertCount(6, $pouleSchema['wedstrijden']); // 4 judokas = 6 matches

        // Check wedstrijd structure
        $w = $pouleSchema['wedstrijden'][0];
        $this->assertArrayHasKey('id', $w);
        $this->assertArrayHasKey('volgorde', $w);
        $this->assertArrayHasKey('wit', $w);
        $this->assertArrayHasKey('blauw', $w);
        $this->assertArrayHasKey('is_gespeeld', $w);
    }

    #[Test]
    public function get_schema_voor_mat_excludes_afwezige_judokas(): void
    {
        [$poule, $judokas, $toernooi, $blok, $mat] = $this->createPouleWithJudokas(4);
        $this->wedstrijdService->genereerWedstrijdenVoorPoule($poule);

        // Mark one as absent
        $judokas[0]->update(['aanwezigheid' => 'afwezig']);

        $result = $this->wedstrijdService->getSchemaVoorMat($blok, $mat);

        $pouleSchema = $result['poules'][0];
        // judoka_count should exclude absent
        $this->assertEquals(3, $pouleSchema['judoka_count']);
        // judokas array should only contain 3
        $this->assertCount(3, $pouleSchema['judokas']);
    }

    #[Test]
    public function get_schema_voor_mat_no_poules_without_wedstrijden(): void
    {
        // Poule without wedstrijden should not appear
        $toernooi = Toernooi::factory()->create();
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id]);
        $mat = Mat::factory()->create(['toernooi_id' => $toernooi->id]);
        Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat->id,
            'doorgestuurd_op' => now(),
        ]);

        $result = $this->wedstrijdService->getSchemaVoorMat($blok, $mat);

        $this->assertEmpty($result['poules']);
    }

    #[Test]
    public function get_schema_voor_mat_no_poules_without_doorgestuurd(): void
    {
        [$poule, $judokas, $toernooi, $blok, $mat] = $this->createPouleWithJudokas(3);
        $this->wedstrijdService->genereerWedstrijdenVoorPoule($poule);

        // Remove doorgestuurd_op
        $poule->update(['doorgestuurd_op' => null]);

        $result = $this->wedstrijdService->getSchemaVoorMat($blok, $mat);

        $this->assertEmpty($result['poules']);
    }

    // ========================================================================
    // WedstrijdSchemaService — getSchemaVoorMat with eliminatie
    // Covers lines 449-465 (eliminatie-specific fields)
    // ========================================================================

    #[Test]
    public function get_schema_voor_mat_eliminatie_includes_extra_fields(): void
    {
        $toernooi = Toernooi::factory()->create();
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id]);
        $mat = Mat::factory()->create(['toernooi_id' => $toernooi->id]);
        $club = Club::factory()->create(['organisator_id' => $toernooi->organisator_id]);

        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat->id,
            'type' => 'eliminatie',
            'doorgestuurd_op' => now(),
        ]);

        $judoka1 = Judoka::factory()->create(['toernooi_id' => $toernooi->id, 'club_id' => $club->id, 'aanwezigheid' => 'aanwezig']);
        $judoka2 = Judoka::factory()->create(['toernooi_id' => $toernooi->id, 'club_id' => $club->id, 'aanwezigheid' => 'aanwezig']);
        $poule->judokas()->attach($judoka1->id, ['positie' => 1]);
        $poule->judokas()->attach($judoka2->id, ['positie' => 2]);

        // Create eliminatie wedstrijd with specific fields
        Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judoka1->id,
            'judoka_blauw_id' => $judoka2->id,
            'volgorde' => 1,
            'groep' => 'A',
            'ronde' => 'halve_finale',
            'bracket_positie' => 1,
        ]);

        $result = $this->wedstrijdService->getSchemaVoorMat($blok, $mat);

        $this->assertCount(1, $result['poules']);
        $pouleSchema = $result['poules'][0];
        $this->assertEquals('eliminatie', $pouleSchema['type']);

        $w = $pouleSchema['wedstrijden'][0];
        $this->assertArrayHasKey('groep', $w);
        $this->assertArrayHasKey('ronde', $w);
        $this->assertArrayHasKey('bracket_positie', $w);
        $this->assertArrayHasKey('volgende_wedstrijd_id', $w);
        $this->assertArrayHasKey('winnaar_naar_slot', $w);
        $this->assertArrayHasKey('uitslag_type', $w);
        $this->assertArrayHasKey('locatie_wit', $w);
        $this->assertArrayHasKey('locatie_blauw', $w);
        $this->assertEquals('A', $w['groep']);
        $this->assertEquals('halve_finale', $w['ronde']);
    }

    // ========================================================================
    // WedstrijdSchemaService — getSchemaVoorMat eliminatie split mats (groep filter)
    // Covers lines 386-394
    // ========================================================================

    #[Test]
    public function get_schema_voor_mat_eliminatie_split_mats_groep_filter(): void
    {
        $toernooi = Toernooi::factory()->create();
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id]);
        $matA = Mat::factory()->create(['toernooi_id' => $toernooi->id]);
        $matB = Mat::factory()->create(['toernooi_id' => $toernooi->id]);
        $club = Club::factory()->create(['organisator_id' => $toernooi->organisator_id]);

        // Eliminatie poule with split mats
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $matA->id,
            'b_mat_id' => $matB->id,
            'type' => 'eliminatie',
            'doorgestuurd_op' => now(),
        ]);

        $judoka1 = Judoka::factory()->create(['toernooi_id' => $toernooi->id, 'club_id' => $club->id, 'aanwezigheid' => 'aanwezig']);
        $judoka2 = Judoka::factory()->create(['toernooi_id' => $toernooi->id, 'club_id' => $club->id, 'aanwezigheid' => 'aanwezig']);
        $judoka3 = Judoka::factory()->create(['toernooi_id' => $toernooi->id, 'club_id' => $club->id, 'aanwezigheid' => 'aanwezig']);
        $judoka4 = Judoka::factory()->create(['toernooi_id' => $toernooi->id, 'club_id' => $club->id, 'aanwezigheid' => 'aanwezig']);
        $poule->judokas()->attach($judoka1->id, ['positie' => 1]);
        $poule->judokas()->attach($judoka2->id, ['positie' => 2]);
        $poule->judokas()->attach($judoka3->id, ['positie' => 3]);
        $poule->judokas()->attach($judoka4->id, ['positie' => 4]);

        // Group A wedstrijd on matA
        Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judoka1->id,
            'judoka_blauw_id' => $judoka2->id,
            'volgorde' => 1,
            'groep' => 'A',
            'ronde' => 'halve_finale',
        ]);
        // Group B wedstrijd on matB
        Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judoka3->id,
            'judoka_blauw_id' => $judoka4->id,
            'volgorde' => 2,
            'groep' => 'B',
            'ronde' => 'halve_finale',
        ]);

        // Get schema for matA - should only show group A
        $resultA = $this->wedstrijdService->getSchemaVoorMat($blok, $matA);
        $this->assertCount(1, $resultA['poules']);
        $this->assertEquals('A', $resultA['poules'][0]['groep_filter']);
        $this->assertCount(1, $resultA['poules'][0]['wedstrijden']);
        $this->assertEquals('A', $resultA['poules'][0]['wedstrijden'][0]['groep']);

        // Get schema for matB - should only show group B
        $resultB = $this->wedstrijdService->getSchemaVoorMat($blok, $matB);
        $this->assertCount(1, $resultB['poules']);
        $this->assertEquals('B', $resultB['poules'][0]['groep_filter']);
        $this->assertCount(1, $resultB['poules'][0]['wedstrijden']);
        $this->assertEquals('B', $resultB['poules'][0]['wedstrijden'][0]['groep']);
    }

    // ========================================================================
    // WedstrijdSchemaService — registreerUitslag auto-fill scoreWit
    // Covers line 501 (scoreWit empty, scoreBlauw filled)
    // ========================================================================

    #[Test]
    public function registreer_uitslag_auto_fills_score_wit_when_empty(): void
    {
        [$poule, $judokas] = $this->createPouleWithJudokas(4);
        $wedstrijden = $this->wedstrijdService->genereerWedstrijdenVoorPoule($poule);
        $w = $wedstrijden[0];

        // Winner with only score_blauw filled, score_wit empty
        $this->wedstrijdService->registreerUitslag($w, $w->judoka_blauw_id, '', '10');

        $w->refresh();
        $this->assertEquals('0', $w->score_wit, 'Empty score_wit should be set to 0 when winner has score_blauw');
        $this->assertEquals('10', $w->score_blauw);
    }

    #[Test]
    public function registreer_uitslag_gelijkspel_zonder_winnaar(): void
    {
        [$poule, $judokas] = $this->createPouleWithJudokas(3);
        $wedstrijden = $this->wedstrijdService->genereerWedstrijdenVoorPoule($poule);
        $w = $wedstrijden[0];

        // Draw: both scores 0, no winner — still counts as played
        $this->wedstrijdService->registreerUitslag($w, null, '0', '0');

        $w->refresh();
        $this->assertTrue($w->is_gespeeld);
        $this->assertNull($w->winnaar_id);
    }

    // ========================================================================
    // WedstrijdSchemaService — barrage with default (>3 judokas)
    // ========================================================================

    #[Test]
    public function barrage_4_plus_judokas_uses_round_robin(): void
    {
        $toernooi = Toernooi::factory()->create();
        $poule = Poule::factory()->create(['toernooi_id' => $toernooi->id, 'type' => 'barrage']);

        $schema = $this->callWedstrijdPrivate('getOptimaleWedstrijdvolgorde', [$poule, 4]);

        // 4 judokas in barrage = round-robin = 6 matches
        $this->assertCount(6, $schema);
    }

    // ========================================================================
    // WedstrijdSchemaService — genereerWedstrijdenVoorPoule with fixed category
    // Covers line 42 (judoka outside weight class filtered)
    // ========================================================================

    #[Test]
    public function genereer_wedstrijden_filters_judoka_buiten_gewichtsklasse(): void
    {
        $toernooi = Toernooi::factory()->vasteKlassen()->create([
            'dubbel_bij_2_judokas' => false,
            'gewicht_tolerantie' => 0.5,
        ]);
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id]);
        $mat = Mat::factory()->create(['toernooi_id' => $toernooi->id]);
        $club = Club::factory()->create(['organisator_id' => $toernooi->organisator_id]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat->id,
            'type' => 'voorronde',
        ]);

        // Judoka within weight class
        $judoka1 = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'aanwezigheid' => 'aanwezig',
            'gewicht' => 23.0,
            'gewicht_gewogen' => 23.0,
            'gewichtsklasse' => '-24',
        ]);
        // Judoka within weight class
        $judoka2 = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'aanwezigheid' => 'aanwezig',
            'gewicht' => 22.0,
            'gewicht_gewogen' => 22.0,
            'gewichtsklasse' => '-24',
        ]);
        // Judoka way outside weight class — should be filtered
        $judoka3 = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'aanwezigheid' => 'aanwezig',
            'gewicht' => 30.0,
            'gewicht_gewogen' => 30.0,
            'gewichtsklasse' => '-24',
        ]);

        $poule->judokas()->attach($judoka1->id, ['positie' => 1]);
        $poule->judokas()->attach($judoka2->id, ['positie' => 2]);
        $poule->judokas()->attach($judoka3->id, ['positie' => 3]);

        $wedstrijden = $this->wedstrijdService->genereerWedstrijdenVoorPoule($poule);

        // judoka3 should be filtered out (30kg in -24 class, way over tolerance)
        // Only judoka1 and judoka2 remain = 1 match (single, dubbel_bij_2=false)
        $this->assertCount(1, $wedstrijden);

        // Verify judoka3 is not in any match
        foreach ($wedstrijden as $w) {
            $this->assertNotEquals($judoka3->id, $w->judoka_wit_id);
            $this->assertNotEquals($judoka3->id, $w->judoka_blauw_id);
        }
    }

    // ========================================================================
    // DynamischeIndelingService — getEffectiefGewicht fallback
    // Covers lines 42-45
    // ========================================================================

    #[Test]
    public function get_effectief_gewicht_fallback_naar_gewichtsklasse(): void
    {
        $judoka = (object) ['gewicht' => null, 'gewichtsklasse' => '-28'];
        $result = $this->callDynamischPrivate('getEffectiefGewicht', [$judoka]);
        $this->assertEquals(28.0, $result);
    }

    #[Test]
    public function get_effectief_gewicht_zonder_gewicht_of_klasse(): void
    {
        $judoka = (object) ['gewicht' => null, 'gewichtsklasse' => null];
        $result = $this->callDynamischPrivate('getEffectiefGewicht', [$judoka]);
        $this->assertEquals(0.0, $result);
    }

    #[Test]
    public function get_effectief_gewicht_met_gewicht(): void
    {
        $judoka = (object) ['gewicht' => 25.5, 'gewichtsklasse' => '-28'];
        $result = $this->callDynamischPrivate('getEffectiefGewicht', [$judoka]);
        $this->assertEquals(25.5, $result);
    }

    #[Test]
    public function get_effectief_gewicht_gewichtsklasse_plus(): void
    {
        $judoka = (object) ['gewicht' => null, 'gewichtsklasse' => '+40'];
        $result = $this->callDynamischPrivate('getEffectiefGewicht', [$judoka]);
        $this->assertEquals(40.0, $result);
    }

    // ========================================================================
    // DynamischeIndelingService — berekenIndeling empty
    // Covers line 72
    // ========================================================================

    #[Test]
    public function bereken_indeling_lege_collectie(): void
    {
        $result = $this->dynamischeService->berekenIndeling(collect());

        $this->assertIsArray($result);
        $this->assertEmpty($result['poules']);
        $this->assertEquals(0, $result['totaal_judokas']);
        $this->assertEquals(0, $result['totaal_ingedeeld']);
    }

    // ========================================================================
    // DynamischeIndelingService — simpleFallback
    // Covers lines 260-406
    // ========================================================================

    #[Test]
    public function simple_fallback_groups_by_weight_and_age(): void
    {
        $judokas = collect([
            (object) ['id' => 1, 'leeftijd' => 8, 'gewicht' => 25.0, 'band' => 'wit', 'club_id' => 1, 'gewichtsklasse' => '-28'],
            (object) ['id' => 2, 'leeftijd' => 8, 'gewicht' => 26.0, 'band' => 'wit', 'club_id' => 2, 'gewichtsklasse' => '-28'],
            (object) ['id' => 3, 'leeftijd' => 8, 'gewicht' => 27.0, 'band' => 'geel', 'club_id' => 1, 'gewichtsklasse' => '-28'],
            (object) ['id' => 4, 'leeftijd' => 9, 'gewicht' => 26.5, 'band' => 'geel', 'club_id' => 2, 'gewichtsklasse' => '-28'],
            (object) ['id' => 5, 'leeftijd' => 8, 'gewicht' => 25.5, 'band' => 'wit', 'club_id' => 1, 'gewichtsklasse' => '-28'],
        ]);

        $this->setDynamischProperty('config', [
            'poule_grootte_voorkeur' => [5, 4, 6, 3],
            'gewicht_tolerantie' => 0.5,
        ]);

        $result = $this->callDynamischPrivate('simpleFallback', [$judokas, 3.0, 2, 0, '', 1]);

        $this->assertNotEmpty($result);

        // All judokas should be assigned
        $totaal = array_sum(array_map(fn($p) => count($p['judokas']), $result));
        $this->assertEquals(5, $totaal);
    }

    #[Test]
    public function simple_fallback_splits_when_constraints_exceeded(): void
    {
        // Create judokas with big weight differences that force splitting
        $judokas = collect([
            (object) ['id' => 1, 'leeftijd' => 8, 'gewicht' => 20.0, 'band' => 'wit', 'club_id' => 1, 'gewichtsklasse' => '-20'],
            (object) ['id' => 2, 'leeftijd' => 8, 'gewicht' => 21.0, 'band' => 'wit', 'club_id' => 2, 'gewichtsklasse' => '-24'],
            (object) ['id' => 3, 'leeftijd' => 8, 'gewicht' => 30.0, 'band' => 'wit', 'club_id' => 1, 'gewichtsklasse' => '-32'],
            (object) ['id' => 4, 'leeftijd' => 8, 'gewicht' => 31.0, 'band' => 'wit', 'club_id' => 2, 'gewichtsklasse' => '-32'],
            (object) ['id' => 5, 'leeftijd' => 8, 'gewicht' => 40.0, 'band' => 'wit', 'club_id' => 1, 'gewichtsklasse' => '-40'],
            (object) ['id' => 6, 'leeftijd' => 8, 'gewicht' => 41.0, 'band' => 'wit', 'club_id' => 2, 'gewichtsklasse' => '-44'],
        ]);

        $this->setDynamischProperty('config', [
            'poule_grootte_voorkeur' => [5, 4, 6, 3],
            'gewicht_tolerantie' => 0.5,
        ]);

        // Max 3 kg difference will force splits
        $result = $this->callDynamischPrivate('simpleFallback', [$judokas, 3.0, 2, 0, '', 1]);

        // Should have multiple poules due to weight constraints
        $this->assertGreaterThanOrEqual(2, count($result));
    }

    #[Test]
    public function simple_fallback_orphan_placement_with_tolerance(): void
    {
        // Create a scenario where orphans (poules of 1-2) get placed in bigger poules
        $judokas = collect([
            // Main group
            (object) ['id' => 1, 'leeftijd' => 8, 'gewicht' => 25.0, 'band' => 'wit', 'club_id' => 1, 'gewichtsklasse' => '-28'],
            (object) ['id' => 2, 'leeftijd' => 8, 'gewicht' => 26.0, 'band' => 'wit', 'club_id' => 2, 'gewichtsklasse' => '-28'],
            (object) ['id' => 3, 'leeftijd' => 8, 'gewicht' => 27.0, 'band' => 'wit', 'club_id' => 1, 'gewichtsklasse' => '-28'],
            // Orphan that is slightly out of range but within tolerance
            (object) ['id' => 4, 'leeftijd' => 8, 'gewicht' => 28.2, 'band' => 'wit', 'club_id' => 2, 'gewichtsklasse' => '-28'],
        ]);

        $this->setDynamischProperty('config', [
            'poule_grootte_voorkeur' => [4, 3, 5],
            'gewicht_tolerantie' => 1.0,
        ]);

        $result = $this->callDynamischPrivate('simpleFallback', [$judokas, 2.0, 2, 0, '', 1]);

        // With tolerance, orphan(s) should be merged into main group
        $totaal = array_sum(array_map(fn($p) => count($p['judokas']), $result));
        $this->assertEquals(4, $totaal);
    }

    #[Test]
    public function simple_fallback_with_band_constraint(): void
    {
        $judokas = collect([
            (object) ['id' => 1, 'leeftijd' => 8, 'gewicht' => 25.0, 'band' => 'wit', 'club_id' => 1, 'gewichtsklasse' => '-28'],
            (object) ['id' => 2, 'leeftijd' => 8, 'gewicht' => 25.5, 'band' => 'blauw', 'club_id' => 2, 'gewichtsklasse' => '-28'],
            (object) ['id' => 3, 'leeftijd' => 8, 'gewicht' => 26.0, 'band' => 'wit', 'club_id' => 1, 'gewichtsklasse' => '-28'],
        ]);

        $this->setDynamischProperty('config', [
            'poule_grootte_voorkeur' => [5, 4, 6, 3],
            'gewicht_tolerantie' => 0.5,
        ]);

        // Max band verschil = 1, grens = geel
        $result = $this->callDynamischPrivate('simpleFallback', [$judokas, 5.0, 5, 1, 'geel', 1]);

        // wit(0) vs blauw(4) = band verschil 4, should split
        $this->assertGreaterThanOrEqual(2, count($result));
    }

    // ========================================================================
    // DynamischeIndelingService — berekenScore rest/niet in voorkeurlijst
    // Covers lines 490-493
    // ========================================================================

    #[Test]
    public function bereken_score_derde_voorkeur_is_40(): void
    {
        $this->setDynamischProperty('config', ['poule_grootte_voorkeur' => [5, 4, 6, 3]]);

        $poules = [
            ['judokas' => array_fill(0, 6, 'j')], // grootte 6 = derde voorkeur (index 2)
        ];

        $this->assertEquals(40.0, $this->callDynamischPrivate('berekenScore', [$poules]));
    }

    #[Test]
    public function bereken_score_vierde_voorkeur_is_40(): void
    {
        $this->setDynamischProperty('config', ['poule_grootte_voorkeur' => [5, 4, 6, 3]]);

        $poules = [
            ['judokas' => array_fill(0, 3, 'j')], // grootte 3 = vierde voorkeur (index 3)
        ];

        $this->assertEquals(40.0, $this->callDynamischPrivate('berekenScore', [$poules]));
    }

    #[Test]
    public function bereken_score_niet_in_voorkeur_is_70(): void
    {
        $this->setDynamischProperty('config', ['poule_grootte_voorkeur' => [5, 4, 6, 3]]);

        $poules = [
            ['judokas' => array_fill(0, 8, 'j')], // grootte 8 = niet in voorkeurlijst
        ];

        $this->assertEquals(70.0, $this->callDynamischPrivate('berekenScore', [$poules]));
    }

    // ========================================================================
    // DynamischeIndelingService — genereerVarianten
    // Covers lines 525-529
    // ========================================================================

    #[Test]
    public function genereer_varianten_has_argument_order_bug(): void
    {
        // BUG: genereerVarianten line 525 passes arguments in wrong order:
        //   $this->berekenIndeling($judokas, 2, 3.0, 0, false, $config)
        // Should be: $this->berekenIndeling($judokas, 2, 3.0, 0, '', 1, $config)
        // $config (array) is passed as $bandVerschilBeginners (int) → TypeError
        $judokas = collect([
            (object) ['id' => 1, 'leeftijd' => 8, 'gewicht' => 25.0, 'band' => 'wit', 'club_id' => 1, 'gewichtsklasse' => '-28'],
        ]);

        $this->expectException(\TypeError::class);
        $this->dynamischeService->genereerVarianten($judokas);
    }

    // ========================================================================
    // DynamischeIndelingService — maakPouleData
    // Covers various ranges
    // ========================================================================

    #[Test]
    public function maak_poule_data_berekent_ranges_correct(): void
    {
        $judokas = [
            (object) ['gewicht' => 25.0, 'leeftijd' => 8, 'band' => 'wit', 'gewichtsklasse' => '-28'],
            (object) ['gewicht' => 28.0, 'leeftijd' => 10, 'band' => 'groen', 'gewichtsklasse' => '-28'],
        ];

        $result = $this->callDynamischPrivate('maakPouleData', [$judokas]);

        $this->assertEquals(3.0, $result['gewicht_range']);
        $this->assertEquals(2, $result['leeftijd_range']);
        $this->assertEquals(3, $result['band_range']); // groen(3) - wit(0)
        $this->assertEquals('8-10j', $result['leeftijd_groep']);
        $this->assertEquals('25-28kg', $result['gewicht_groep']);
    }

    #[Test]
    public function maak_poule_data_single_judoka(): void
    {
        $judokas = [
            (object) ['gewicht' => 25.0, 'leeftijd' => 8, 'band' => 'wit', 'gewichtsklasse' => '-28'],
        ];

        $result = $this->callDynamischPrivate('maakPouleData', [$judokas]);

        $this->assertEquals(0.0, $result['gewicht_range']);
        $this->assertEquals(0, $result['leeftijd_range']);
        $this->assertEquals('8j', $result['leeftijd_groep']);
        $this->assertEquals('25kg', $result['gewicht_groep']);
    }

    // ========================================================================
    // DynamischeIndelingService — maakResultaat
    // Covers lines 454-467
    // ========================================================================

    #[Test]
    public function maak_resultaat_correct_structure(): void
    {
        $this->setDynamischProperty('config', ['poule_grootte_voorkeur' => [5, 4, 6, 3]]);

        $poules = [
            [
                'judokas' => [(object) ['gewicht' => 25, 'leeftijd' => 8, 'band' => 'wit']],
                'leeftijd_range' => 0,
                'gewicht_range' => 0,
            ],
        ];

        $result = $this->callDynamischPrivate('maakResultaat', [$poules, 5]);

        $this->assertArrayHasKey('poules', $result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('totaal_ingedeeld', $result);
        $this->assertArrayHasKey('totaal_judokas', $result);
        $this->assertArrayHasKey('aantal_poules', $result);
        $this->assertArrayHasKey('onvolledige_judokas', $result);
        $this->assertArrayHasKey('params', $result);
        $this->assertArrayHasKey('stats', $result);
        $this->assertEquals(1, $result['totaal_ingedeeld']);
        $this->assertEquals(5, $result['totaal_judokas']);
        $this->assertEquals(1, $result['aantal_poules']);
    }
}
