<?php

/**
 * DO NOT REMOVE: These tests protect Poule::checkPouleRegels() which is CRITICAL
 * for showing weight/age warnings on the poule indeling page.
 *
 * This has been re-implemented 5+ times because it kept getting accidentally removed.
 * If ANY of these tests fail, the poule warnings UI is broken.
 *
 * @see Poule::checkPouleRegels()
 * @see PouleController::buildPouleResponse() — must include 'problemen' key
 * @see resources/views/pages/poule/index.blade.php — updatePouleStats() JS function
 */

namespace Tests\Unit\Models;

use App\Models\Club;
use App\Models\Judoka;
use App\Models\Poule;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PouleCheckRegelsTest extends TestCase
{
    use RefreshDatabase;

    private function maakToernooiMetConfig(array $categorieConfig): Toernooi
    {
        return Toernooi::factory()->create([
            'gewichtsklassen' => [
                'test_cat' => array_merge([
                    'label' => 'Test',
                    'max_leeftijd' => 12,
                    'geslacht' => 'gemengd',
                ], $categorieConfig),
            ],
        ]);
    }

    private function maakJudokaInPoule(Poule $poule, float $gewicht, int $geboortejaar): Judoka
    {
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $poule->toernooi_id,
            'club_id' => Club::factory()->create(['organisator_id' => $poule->toernooi->organisator_id])->id,
            'gewicht' => $gewicht,
            'geboortejaar' => $geboortejaar,
            'leeftijdsklasse' => 'Test',
        ]);

        $poule->judokas()->attach($judoka->id, ['positie' => $poule->judokas()->count() + 1]);
        $poule->load('judokas'); // Refresh relation

        return $judoka;
    }

    // ========================================================================
    // checkPouleRegels() EXISTS and WORKS
    // ========================================================================

    #[Test]
    public function checkPouleRegels_method_exists_on_poule_model(): void
    {
        $this->assertTrue(
            method_exists(Poule::class, 'checkPouleRegels'),
            'CRITICAL: Poule::checkPouleRegels() method is missing! This breaks poule warning UI.'
        );
    }

    #[Test]
    public function checkPouleRegels_returns_array(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 3, 'max_leeftijd_verschil' => 1]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'leeftijdsklasse' => 'Test',
            'categorie_key' => 'test_cat',
        ]);

        $result = $poule->checkPouleRegels();
        $this->assertIsArray($result);
    }

    // ========================================================================
    // WEIGHT checks (max_kg_verschil)
    // ========================================================================

    #[Test]
    public function weight_ok_returns_no_problems(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 3, 'max_leeftijd_verschil' => 0]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'leeftijdsklasse' => 'Test',
            'categorie_key' => 'test_cat',
        ]);

        $this->maakJudokaInPoule($poule, 25.0, 2018);
        $this->maakJudokaInPoule($poule, 27.5, 2018);

        $problemen = $poule->checkPouleRegels();
        $this->assertEmpty($problemen, 'Weight difference 2.5kg should be OK with max 3kg');
    }

    #[Test]
    public function weight_exceeded_returns_gewicht_problem(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 3, 'max_leeftijd_verschil' => 0]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'leeftijdsklasse' => 'Test',
            'categorie_key' => 'test_cat',
        ]);

        $this->maakJudokaInPoule($poule, 25.0, 2018);
        $this->maakJudokaInPoule($poule, 29.0, 2018);

        $problemen = $poule->checkPouleRegels();
        $this->assertNotEmpty($problemen, 'Weight difference 4kg should exceed max 3kg');
        $this->assertEquals('gewicht', $problemen[0]['type']);
        $this->assertEquals(4.0, $problemen[0]['verschil']);
        $this->assertEquals(3, $problemen[0]['max']);
    }

    #[Test]
    public function weight_problem_resolves_after_removing_heavy_judoka(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 3, 'max_leeftijd_verschil' => 0]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'leeftijdsklasse' => 'Test',
            'categorie_key' => 'test_cat',
        ]);

        $this->maakJudokaInPoule($poule, 25.0, 2018);
        $this->maakJudokaInPoule($poule, 26.0, 2018);
        $heavy = $this->maakJudokaInPoule($poule, 30.0, 2018);

        // Before: should have weight problem (5kg diff)
        $this->assertNotEmpty($poule->checkPouleRegels());

        // Remove the heavy judoka
        $poule->judokas()->detach($heavy->id);
        $poule->load('judokas');

        // After: should be clean (1kg diff)
        $problemen = $poule->checkPouleRegels();
        $this->assertEmpty($problemen, 'After removing heavy judoka, weight problem should resolve');
    }

    // ========================================================================
    // AGE checks (max_leeftijd_verschil)
    // ========================================================================

    #[Test]
    public function age_ok_returns_no_problems(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0, 'max_leeftijd_verschil' => 1]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'leeftijdsklasse' => 'Test',
            'categorie_key' => 'test_cat',
        ]);

        $jaar = now()->year;
        $this->maakJudokaInPoule($poule, 25.0, $jaar - 8); // 8 jaar
        $this->maakJudokaInPoule($poule, 26.0, $jaar - 9); // 9 jaar

        $problemen = $poule->checkPouleRegels();
        $this->assertEmpty($problemen, 'Age difference 1 year should be OK with max 1');
    }

    #[Test]
    public function age_exceeded_returns_leeftijd_problem(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0, 'max_leeftijd_verschil' => 1]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'leeftijdsklasse' => 'Test',
            'categorie_key' => 'test_cat',
        ]);

        $jaar = now()->year;
        $this->maakJudokaInPoule($poule, 25.0, $jaar - 7); // 7 jaar
        $this->maakJudokaInPoule($poule, 26.0, $jaar - 10); // 10 jaar

        $problemen = $poule->checkPouleRegels();
        $this->assertNotEmpty($problemen, 'Age difference 3 years should exceed max 1');
        $this->assertEquals('leeftijd', $problemen[0]['type']);
        $this->assertEquals(3, $problemen[0]['verschil']);
        $this->assertEquals(1, $problemen[0]['max']);
    }

    #[Test]
    public function age_problem_resolves_after_removing_judoka(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0, 'max_leeftijd_verschil' => 1]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'leeftijdsklasse' => 'Test',
            'categorie_key' => 'test_cat',
        ]);

        $jaar = now()->year;
        $this->maakJudokaInPoule($poule, 25.0, $jaar - 8);
        $this->maakJudokaInPoule($poule, 26.0, $jaar - 9);
        $old = $this->maakJudokaInPoule($poule, 27.0, $jaar - 12);

        // Before: age problem (4 year diff)
        $this->assertNotEmpty($poule->checkPouleRegels());

        // Remove the oldest judoka
        $poule->judokas()->detach($old->id);
        $poule->load('judokas');

        // After: should be clean (1 year diff)
        $problemen = $poule->checkPouleRegels();
        $this->assertEmpty($problemen, 'After removing oldest judoka, age problem should resolve');
    }

    // ========================================================================
    // COMBINED checks
    // ========================================================================

    #[Test]
    public function both_weight_and_age_problems_returned(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 3, 'max_leeftijd_verschil' => 1]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'leeftijdsklasse' => 'Test',
            'categorie_key' => 'test_cat',
        ]);

        $jaar = now()->year;
        $this->maakJudokaInPoule($poule, 20.0, $jaar - 7);
        $this->maakJudokaInPoule($poule, 28.0, $jaar - 10);

        $problemen = $poule->checkPouleRegels();
        $this->assertCount(2, $problemen, 'Should have both weight AND age problems');

        $types = collect($problemen)->pluck('type')->toArray();
        $this->assertContains('gewicht', $types);
        $this->assertContains('leeftijd', $types);
    }

    #[Test]
    public function single_judoka_has_no_problems(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 3, 'max_leeftijd_verschil' => 1]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'leeftijdsklasse' => 'Test',
            'categorie_key' => 'test_cat',
        ]);

        $this->maakJudokaInPoule($poule, 25.0, 2018);

        $problemen = $poule->checkPouleRegels();
        $this->assertEmpty($problemen, 'Single judoka should never have problems');
    }

    #[Test]
    public function empty_poule_has_no_problems(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 3, 'max_leeftijd_verschil' => 1]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'leeftijdsklasse' => 'Test',
            'categorie_key' => 'test_cat',
        ]);

        $problemen = $poule->checkPouleRegels();
        $this->assertEmpty($problemen, 'Empty poule should have no problems');
    }

    // ========================================================================
    // GUARD: buildPouleResponse must include 'problemen'
    // ========================================================================

    #[Test]
    public function buildPouleResponse_includes_problemen_key(): void
    {
        // Use reflection to test private method
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 3, 'max_leeftijd_verschil' => 1]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'leeftijdsklasse' => 'Test',
            'categorie_key' => 'test_cat',
        ]);

        $controller = app(\App\Http\Controllers\PouleJudokaController::class);
        $method = new \ReflectionMethod($controller, 'buildPouleResponse');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $poule);

        $this->assertArrayHasKey('problemen', $result,
            'CRITICAL: buildPouleResponse() must include "problemen" key. '
            . 'Without it, the JS updatePouleStats() cannot update warning UI after mutations.'
        );
        $this->assertIsArray($result['problemen']);
    }

    // ========================================================================
    // GUARD: JS constants exist in blade (compile-time check not possible,
    // but we can verify the view file contains the critical patterns)
    // ========================================================================

    #[Test]
    public function poule_index_blade_contains_critical_js_patterns(): void
    {
        $viewPath = resource_path('views/pages/poule/index.blade.php');
        $this->assertFileExists($viewPath);

        $content = file_get_contents($viewPath);

        // The JS function that updates poule UI after mutations must exist
        $this->assertStringContainsString(
            'function updatePouleStats(pouleData)',
            $content,
            'CRITICAL: updatePouleStats() JS function is missing from poule index view'
        );

        // It must read problemen from the server response
        $this->assertStringContainsString(
            'pouleData.problemen',
            $content,
            'CRITICAL: updatePouleStats() must read problemen from server response'
        );

        // The problematic poules section must use checkPouleRegels
        $this->assertStringContainsString(
            'checkPouleRegels()',
            $content,
            'CRITICAL: Initial render must use Poule::checkPouleRegels() for problem detection'
        );

        // Weight warning icon update must exist
        $this->assertStringContainsString(
            'text-orange-600',
            $content,
            'CRITICAL: Warning icon (orange) must be present in poule view'
        );
    }
}
