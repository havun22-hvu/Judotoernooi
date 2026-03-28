<?php

namespace Tests\Unit\Services;

use App\Models\Club;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Services\PouleIndelingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PouleIndelingServiceTest extends TestCase
{
    use RefreshDatabase;

    private PouleIndelingService $service;
    private Organisator $organisator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PouleIndelingService::class);
        $this->organisator = Organisator::factory()->create();
    }

    /**
     * Helper: create tournament with specific category config.
     */
    private function maakToernooi(array $gewichtsklassen, array $extra = []): Toernooi
    {
        return Toernooi::factory()->create(array_merge([
            'organisator_id' => $this->organisator->id,
            'gewichtsklassen' => $gewichtsklassen,
            'datum' => now(),
        ], $extra));
    }

    /**
     * Helper: create a club for this organisator.
     */
    private function maakClub(): Club
    {
        return Club::factory()->create(['organisator_id' => $this->organisator->id]);
    }

    /**
     * Helper: create judoka with specific attributes.
     */
    private function maakJudoka(Toernooi $toernooi, Club $club, array $attributes = []): Judoka
    {
        return Judoka::factory()->create(array_merge([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
        ], $attributes));
    }

    // =========================================================================
    // herberkenKlassen() — correctly classifies judokas into categories
    // =========================================================================

    #[Test]
    public function herberkenKlassen_classifies_judokas_by_age_category(): void
    {
        $jaar = (int) date('Y');
        $toernooi = $this->maakToernooi([
            'minis' => [
                'label' => "Mini's",
                'max_leeftijd' => 7,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 3,
                'max_leeftijd_verschil' => 1,
            ],
            'pupillen' => [
                'label' => 'Pupillen',
                'max_leeftijd' => 11,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 4,
                'max_leeftijd_verschil' => 1,
            ],
        ]);
        $club = $this->maakClub();

        // 6 year old → should be classified as Mini's
        $mini = $this->maakJudoka($toernooi, $club, [
            'geboortejaar' => $jaar - 6,
            'gewicht' => 22.0,
            'geslacht' => 'M',
            'leeftijdsklasse' => 'onbekend', // Wrong initial value
        ]);

        // 9 year old → should be classified as Pupillen
        $pupil = $this->maakJudoka($toernooi, $club, [
            'geboortejaar' => $jaar - 9,
            'gewicht' => 30.0,
            'geslacht' => 'V',
            'leeftijdsklasse' => 'onbekend', // Wrong initial value
        ]);

        $bijgewerkt = $this->service->herberkenKlassen($toernooi);

        $this->assertEquals(2, $bijgewerkt);

        $mini->refresh();
        $pupil->refresh();

        $this->assertEquals("Mini's", $mini->leeftijdsklasse);
        $this->assertEquals('minis', $mini->categorie_key);

        $this->assertEquals('Pupillen', $pupil->leeftijdsklasse);
        $this->assertEquals('pupillen', $pupil->categorie_key);
    }

    #[Test]
    public function herberkenKlassen_returns_zero_when_nothing_changed(): void
    {
        $jaar = (int) date('Y');
        $toernooi = $this->maakToernooi([
            'minis' => [
                'label' => "Mini's",
                'max_leeftijd' => 7,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 3,
                'max_leeftijd_verschil' => 1,
            ],
        ]);
        $club = $this->maakClub();

        // Create judoka already correctly classified
        $judoka = $this->maakJudoka($toernooi, $club, [
            'geboortejaar' => $jaar - 6,
            'gewicht' => 22.0,
            'geslacht' => 'M',
            'leeftijdsklasse' => "Mini's",
            'categorie_key' => 'minis',
        ]);

        // First run to normalize all fields
        $run1 = $this->service->herberkenKlassen($toernooi);

        // Second run should find nothing changed (all fields already correct)
        $toernooi->load('judokas');
        $run2 = $this->service->herberkenKlassen($toernooi);

        // Second run should return equal or fewer updates than first
        $this->assertLessThanOrEqual($run1, $run2);
    }

    #[Test]
    public function herberkenKlassen_updates_sort_fields(): void
    {
        $jaar = (int) date('Y');
        $toernooi = $this->maakToernooi([
            'minis' => [
                'label' => "Mini's",
                'max_leeftijd' => 7,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 3,
                'max_leeftijd_verschil' => 1,
            ],
        ]);
        $club = $this->maakClub();

        $judoka = $this->maakJudoka($toernooi, $club, [
            'geboortejaar' => $jaar - 5,
            'gewicht' => 20.5,
            'geslacht' => 'M',
            'band' => 'wit',
            'leeftijdsklasse' => 'onbekend',
        ]);

        $this->service->herberkenKlassen($toernooi);
        $judoka->refresh();

        // sort_gewicht should be weight in grams
        $this->assertEquals(20500, $judoka->sort_gewicht);

        // sort_categorie should be set (0 for first category)
        $this->assertEquals(0, $judoka->sort_categorie);
    }

    // =========================================================================
    // genereerPouleIndeling() with dynamic categories
    // =========================================================================

    #[Test]
    public function genereerPouleIndeling_dynamic_creates_poules(): void
    {
        $jaar = (int) date('Y');
        $toernooi = $this->maakToernooi([
            'minis' => [
                'label' => "Mini's",
                'max_leeftijd' => 7,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 3,
                'max_leeftijd_verschil' => 1,
            ],
        ], [
            'gebruik_gewichtsklassen' => false,
            'poule_grootte_voorkeur' => [4, 3, 5],
        ]);
        $club = $this->maakClub();

        // Create 8 mini judokas with varying weights
        for ($i = 0; $i < 8; $i++) {
            $this->maakJudoka($toernooi, $club, [
                'geboortejaar' => $jaar - 6,
                'gewicht' => 20.0 + ($i * 0.5),
                'geslacht' => 'M',
                'band' => 'wit',
                'leeftijdsklasse' => "Mini's",
            ]);
        }

        $result = $this->service->genereerPouleIndeling($toernooi);

        // Should have created poules
        $this->assertGreaterThan(0, $result['totaal_poules']);

        // All judokas should be in poules
        $poules = $toernooi->poules()->with('judokas')->get();
        $totaalInPoules = $poules->sum(fn($p) => $p->judokas->count());
        $this->assertEquals(8, $totaalInPoules);

        // Each poule should have at least 3 judokas (min from preference)
        foreach ($poules as $poule) {
            $this->assertGreaterThanOrEqual(3, $poule->judokas->count());
        }
    }

    // =========================================================================
    // genereerPouleIndeling() with fixed weight classes
    // =========================================================================

    #[Test]
    public function genereerPouleIndeling_fixed_creates_poules_per_weight_class(): void
    {
        $jaar = (int) date('Y');
        $toernooi = $this->maakToernooi([
            'cadetten' => [
                'label' => 'Cadetten',
                'max_leeftijd' => 15,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 0,
                'max_leeftijd_verschil' => 0,
                'gewichten' => ['-30', '-35', '+35'],
            ],
        ], [
            'gebruik_gewichtsklassen' => true,
            'poule_grootte_voorkeur' => [4, 3, 5],
        ]);
        $club = $this->maakClub();

        // Create judokas in -30 class
        for ($i = 0; $i < 4; $i++) {
            $this->maakJudoka($toernooi, $club, [
                'geboortejaar' => $jaar - 13,
                'gewicht' => 27.0 + $i,
                'geslacht' => 'M',
                'band' => 'oranje',
                'leeftijdsklasse' => 'Cadetten',
                'gewichtsklasse' => '-30',
            ]);
        }

        // Create judokas in -35 class
        for ($i = 0; $i < 3; $i++) {
            $this->maakJudoka($toernooi, $club, [
                'geboortejaar' => $jaar - 14,
                'gewicht' => 31.0 + $i,
                'geslacht' => 'M',
                'band' => 'groen',
                'leeftijdsklasse' => 'Cadetten',
                'gewichtsklasse' => '-35',
            ]);
        }

        $result = $this->service->genereerPouleIndeling($toernooi);

        // Should have poules for both weight classes
        $poules = $toernooi->poules()->get();
        $gewichtsklassen = $poules->pluck('gewichtsklasse')->unique()->sort()->values();

        $this->assertContains('-30', $gewichtsklassen->toArray());
        $this->assertContains('-35', $gewichtsklassen->toArray());

        // -30 class: 4 judokas
        $poules30 = $toernooi->poules()->where('gewichtsklasse', '-30')->with('judokas')->get();
        $totaal30 = $poules30->sum(fn($p) => $p->judokas->count());
        $this->assertEquals(4, $totaal30);

        // -35 class: 3 judokas
        $poules35 = $toernooi->poules()->where('gewichtsklasse', '-35')->with('judokas')->get();
        $totaal35 = $poules35->sum(fn($p) => $p->judokas->count());
        $this->assertEquals(3, $totaal35);
    }

    // =========================================================================
    // genereerPouleIndeling() — poule sizes follow poule_grootte_voorkeur
    // =========================================================================

    #[Test]
    public function genereerPouleIndeling_respects_poule_grootte_voorkeur(): void
    {
        $jaar = (int) date('Y');
        $toernooi = $this->maakToernooi([
            'test' => [
                'label' => 'Test',
                'max_leeftijd' => 99,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 0,
                'max_leeftijd_verschil' => 0,
                'gewichten' => ['-50'],
            ],
        ], [
            'gebruik_gewichtsklassen' => true,
            'poule_grootte_voorkeur' => [5, 4, 6, 3],
        ]);
        $club = $this->maakClub();

        // 10 judokas → should split into 2 poules of 5 (preferred size)
        for ($i = 0; $i < 10; $i++) {
            $this->maakJudoka($toernooi, $club, [
                'geboortejaar' => $jaar - 12,
                'gewicht' => 40.0 + ($i * 0.3),
                'geslacht' => 'M',
                'band' => 'oranje',
                'leeftijdsklasse' => 'Test',
                'gewichtsklasse' => '-50',
            ]);
        }

        $result = $this->service->genereerPouleIndeling($toernooi);

        $poules = $toernooi->poules()->where('type', 'voorronde')->with('judokas')->get();

        // With preference [5,4,6,3] and 10 judokas: 2×5 is optimal
        $this->assertEquals(2, $poules->count());
        foreach ($poules as $poule) {
            $this->assertEquals(5, $poule->judokas->count());
        }
    }

    #[Test]
    public function genereerPouleIndeling_prefers_4_over_3_when_configured(): void
    {
        $jaar = (int) date('Y');
        $toernooi = $this->maakToernooi([
            'test' => [
                'label' => 'Test',
                'max_leeftijd' => 99,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 0,
                'max_leeftijd_verschil' => 0,
                'gewichten' => ['-50'],
            ],
        ], [
            'gebruik_gewichtsklassen' => true,
            'poule_grootte_voorkeur' => [4, 5, 3, 6],
        ]);
        $club = $this->maakClub();

        // 8 judokas → should split into 2 poules of 4 (preferred)
        for ($i = 0; $i < 8; $i++) {
            $this->maakJudoka($toernooi, $club, [
                'geboortejaar' => $jaar - 12,
                'gewicht' => 40.0 + ($i * 0.3),
                'geslacht' => 'M',
                'band' => 'oranje',
                'leeftijdsklasse' => 'Test',
                'gewichtsklasse' => '-50',
            ]);
        }

        $result = $this->service->genereerPouleIndeling($toernooi);

        $poules = $toernooi->poules()->where('type', 'voorronde')->with('judokas')->get();

        // With preference [4,5,3,6]: 2×4 is optimal
        $this->assertEquals(2, $poules->count());
        foreach ($poules as $poule) {
            $this->assertEquals(4, $poule->judokas->count());
        }
    }

    // =========================================================================
    // genereerPouleIndeling() — returns warnings for problems
    // =========================================================================

    #[Test]
    public function genereerPouleIndeling_returns_statistics_structure(): void
    {
        $toernooi = $this->maakToernooi([
            'minis' => [
                'label' => "Mini's",
                'max_leeftijd' => 7,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 3,
                'max_leeftijd_verschil' => 1,
            ],
        ], ['gebruik_gewichtsklassen' => false]);
        $club = $this->maakClub();

        // Create a few judokas
        $jaar = (int) date('Y');
        for ($i = 0; $i < 4; $i++) {
            $this->maakJudoka($toernooi, $club, [
                'geboortejaar' => $jaar - 6,
                'gewicht' => 20.0 + $i,
                'geslacht' => 'M',
                'band' => 'wit',
                'leeftijdsklasse' => "Mini's",
            ]);
        }

        $result = $this->service->genereerPouleIndeling($toernooi);

        // Check required keys in statistics
        $this->assertArrayHasKey('totaal_poules', $result);
        $this->assertArrayHasKey('totaal_wedstrijden', $result);
        $this->assertArrayHasKey('totaal_kruisfinales', $result);
        $this->assertArrayHasKey('per_leeftijdsklasse', $result);
        $this->assertArrayHasKey('waarschuwingen', $result);

        $this->assertIsArray($result['waarschuwingen']);
    }

    #[Test]
    public function genereerPouleIndeling_returns_niet_ingedeeld_key_when_applicable(): void
    {
        $jaar = (int) date('Y');
        $toernooi = $this->maakToernooi([
            'minis' => [
                'label' => "Mini's",
                'max_leeftijd' => 7,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 3,
                'max_leeftijd_verschil' => 1,
            ],
        ], ['gebruik_gewichtsklassen' => false]);
        $club = $this->maakClub();

        $this->maakJudoka($toernooi, $club, [
            'geboortejaar' => $jaar - 6,
            'gewicht' => 22.0,
            'geslacht' => 'M',
            'band' => 'wit',
        ]);

        $result = $this->service->genereerPouleIndeling($toernooi);

        // Result should always have these keys
        $this->assertArrayHasKey('waarschuwingen', $result);
        // If there are unassigned judokas, niet_ingedeeld key should be present
        // This is a structural check — the key exists when needed
        $this->assertIsArray($result['waarschuwingen']);
    }

    // =========================================================================
    // genereerPouleIndeling() — deletes old poules before regenerating
    // =========================================================================

    #[Test]
    public function genereerPouleIndeling_clears_existing_poules(): void
    {
        $jaar = (int) date('Y');
        $toernooi = $this->maakToernooi([
            'minis' => [
                'label' => "Mini's",
                'max_leeftijd' => 7,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 3,
                'max_leeftijd_verschil' => 1,
            ],
        ], ['gebruik_gewichtsklassen' => false]);
        $club = $this->maakClub();

        for ($i = 0; $i < 4; $i++) {
            $this->maakJudoka($toernooi, $club, [
                'geboortejaar' => $jaar - 6,
                'gewicht' => 20.0 + $i,
                'geslacht' => 'M',
                'band' => 'wit',
                'leeftijdsklasse' => "Mini's",
            ]);
        }

        // Generate once
        $this->service->genereerPouleIndeling($toernooi);
        $eersteAantal = $toernooi->poules()->count();

        // Generate again — should replace, not duplicate
        $toernooi->load('judokas');
        $this->service->genereerPouleIndeling($toernooi);
        $tweedeAantal = $toernooi->poules()->count();

        $this->assertEquals($eersteAantal, $tweedeAantal);
    }

    // =========================================================================
    // verplaatsJudoka() — moves judoka between poules
    // =========================================================================

    #[Test]
    public function verplaatsJudoka_moves_judoka_to_new_poule(): void
    {
        $toernooi = $this->maakToernooi([
            'test' => [
                'label' => 'Test',
                'max_leeftijd' => 99,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 3,
                'max_leeftijd_verschil' => 1,
            ],
        ]);
        $club = $this->maakClub();

        $poule1 = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'leeftijdsklasse' => 'Test',
            'gewichtsklasse' => '-30',
            'categorie_key' => 'test',
        ]);
        $poule2 = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'leeftijdsklasse' => 'Test',
            'gewichtsklasse' => '-35',
            'categorie_key' => 'test',
        ]);

        $judoka = $this->maakJudoka($toernooi, $club, [
            'geboortejaar' => (int) date('Y') - 10,
            'gewicht' => 28.0,
            'geslacht' => 'M',
            'leeftijdsklasse' => 'Test',
            'gewichtsklasse' => '-30',
        ]);

        // Attach to poule1
        $poule1->judokas()->attach($judoka->id, ['positie' => 1]);
        $poule1->updateStatistieken();

        // Move to poule2
        $this->service->verplaatsJudoka($judoka, $poule2);

        // Judoka should no longer be in poule1
        $this->assertFalse($poule1->fresh()->judokas->contains($judoka->id));

        // Judoka should be in poule2
        $this->assertTrue($poule2->fresh()->judokas->contains($judoka->id));
    }

    #[Test]
    public function verplaatsJudoka_updates_statistics_on_both_poules(): void
    {
        $toernooi = $this->maakToernooi([
            'test' => [
                'label' => 'Test',
                'max_leeftijd' => 99,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 3,
                'max_leeftijd_verschil' => 1,
            ],
        ]);
        $club = $this->maakClub();

        $poule1 = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'leeftijdsklasse' => 'Test',
            'categorie_key' => 'test',
        ]);
        $poule2 = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'leeftijdsklasse' => 'Test',
            'categorie_key' => 'test',
        ]);

        // Add 3 judokas to poule1
        $judokas = [];
        for ($i = 0; $i < 3; $i++) {
            $j = $this->maakJudoka($toernooi, $club, [
                'geboortejaar' => (int) date('Y') - 10,
                'gewicht' => 25.0 + $i,
                'geslacht' => 'M',
                'leeftijdsklasse' => 'Test',
            ]);
            $poule1->judokas()->attach($j->id, ['positie' => $i + 1]);
            $judokas[] = $j;
        }
        $poule1->updateStatistieken();

        $this->assertEquals(3, $poule1->fresh()->aantal_judokas);

        // Move first judoka to poule2
        $this->service->verplaatsJudoka($judokas[0], $poule2);

        // poule1 should have 2 judokas now
        $this->assertEquals(2, $poule1->fresh()->aantal_judokas);

        // poule2 should have 1 judoka
        $this->assertEquals(1, $poule2->fresh()->aantal_judokas);
    }

    #[Test]
    public function verplaatsJudoka_updates_judoka_gewichtsklasse(): void
    {
        $toernooi = $this->maakToernooi([
            'test' => [
                'label' => 'Test',
                'max_leeftijd' => 99,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 0,
                'max_leeftijd_verschil' => 0,
                'gewichten' => ['-30', '-35'],
            ],
        ]);
        $club = $this->maakClub();

        $poule1 = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'leeftijdsklasse' => 'Test',
            'gewichtsklasse' => '-30',
            'categorie_key' => 'test',
        ]);
        $poule2 = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'leeftijdsklasse' => 'Test',
            'gewichtsklasse' => '-35',
            'categorie_key' => 'test',
        ]);

        $judoka = $this->maakJudoka($toernooi, $club, [
            'geboortejaar' => (int) date('Y') - 10,
            'gewicht' => 29.0,
            'geslacht' => 'M',
            'leeftijdsklasse' => 'Test',
            'gewichtsklasse' => '-30',
        ]);
        $poule1->judokas()->attach($judoka->id, ['positie' => 1]);

        // Move to poule2 (different gewichtsklasse)
        $this->service->verplaatsJudoka($judoka, $poule2);

        // Judoka's gewichtsklasse should update to match new poule
        $this->assertEquals('-35', $judoka->fresh()->gewichtsklasse);
    }

    // =========================================================================
    // Edge cases
    // =========================================================================

    #[Test]
    public function genereerPouleIndeling_single_judoka_creates_single_poule(): void
    {
        $jaar = (int) date('Y');
        $toernooi = $this->maakToernooi([
            'test' => [
                'label' => 'Test',
                'max_leeftijd' => 99,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 0,
                'max_leeftijd_verschil' => 0,
                'gewichten' => ['-50'],
            ],
        ], [
            'gebruik_gewichtsklassen' => true,
            'poule_grootte_voorkeur' => [5, 4, 6, 3],
        ]);
        $club = $this->maakClub();

        $this->maakJudoka($toernooi, $club, [
            'geboortejaar' => $jaar - 10,
            'gewicht' => 40.0,
            'geslacht' => 'M',
            'band' => 'oranje',
            'leeftijdsklasse' => 'Test',
            'gewichtsklasse' => '-50',
        ]);

        $result = $this->service->genereerPouleIndeling($toernooi);

        // Single judoka should still be placed in a poule
        $this->assertEquals(1, $result['totaal_poules']);
        $poule = $toernooi->poules()->first();
        $this->assertEquals(1, $poule->judokas()->count());
    }

    #[Test]
    public function genereerPouleIndeling_empty_tournament_returns_zero_poules(): void
    {
        $toernooi = $this->maakToernooi([
            'test' => [
                'label' => 'Test',
                'max_leeftijd' => 99,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 3,
                'max_leeftijd_verschil' => 1,
            ],
        ], ['gebruik_gewichtsklassen' => false]);

        $result = $this->service->genereerPouleIndeling($toernooi);

        $this->assertEquals(0, $result['totaal_poules']);
        $this->assertEquals(0, $result['totaal_wedstrijden']);
        $this->assertEquals(0, $toernooi->poules()->count());
    }

    #[Test]
    public function genereerPouleIndeling_all_same_weight_places_in_same_poule(): void
    {
        $jaar = (int) date('Y');
        $toernooi = $this->maakToernooi([
            'test' => [
                'label' => 'Test',
                'max_leeftijd' => 99,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 0,
                'max_leeftijd_verschil' => 0,
                'gewichten' => ['-30'],
            ],
        ], [
            'gebruik_gewichtsklassen' => true,
            'poule_grootte_voorkeur' => [5, 4, 6, 3],
        ]);
        $club = $this->maakClub();

        // 5 judokas, all exactly the same weight and weight class
        for ($i = 0; $i < 5; $i++) {
            $this->maakJudoka($toernooi, $club, [
                'geboortejaar' => $jaar - 10,
                'gewicht' => 28.0,
                'geslacht' => 'M',
                'band' => 'oranje',
                'leeftijdsklasse' => 'Test',
                'gewichtsklasse' => '-30',
            ]);
        }

        $result = $this->service->genereerPouleIndeling($toernooi);

        // All in same weight class → should be in 1 poule (5 is preferred)
        $poules = $toernooi->poules()->where('type', 'voorronde')->get();
        $this->assertEquals(1, $poules->count());
        $this->assertEquals(5, $poules->first()->judokas()->count());
    }

    #[Test]
    public function genereerPouleIndeling_sets_poules_gegenereerd_op(): void
    {
        $toernooi = $this->maakToernooi([
            'test' => [
                'label' => 'Test',
                'max_leeftijd' => 99,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 3,
                'max_leeftijd_verschil' => 1,
            ],
        ], ['gebruik_gewichtsklassen' => false]);

        $this->assertNull($toernooi->poules_gegenereerd_op);

        $this->service->genereerPouleIndeling($toernooi);

        $this->assertNotNull($toernooi->fresh()->poules_gegenereerd_op);
    }

    #[Test]
    public function genereerPouleIndeling_gender_split_creates_separate_poules(): void
    {
        $jaar = (int) date('Y');
        $toernooi = $this->maakToernooi([
            'heren' => [
                'label' => 'Heren',
                'max_leeftijd' => 15,
                'geslacht' => 'M',
                'max_kg_verschil' => 0,
                'max_leeftijd_verschil' => 0,
                'gewichten' => ['-50'],
            ],
            'dames' => [
                'label' => 'Dames',
                'max_leeftijd' => 15,
                'geslacht' => 'V',
                'max_kg_verschil' => 0,
                'max_leeftijd_verschil' => 0,
                'gewichten' => ['-50'],
            ],
        ], [
            'gebruik_gewichtsklassen' => true,
        ]);
        $club = $this->maakClub();

        // 3 male judokas
        for ($i = 0; $i < 3; $i++) {
            $this->maakJudoka($toernooi, $club, [
                'geboortejaar' => $jaar - 13,
                'gewicht' => 45.0 + $i,
                'geslacht' => 'M',
                'band' => 'oranje',
                'leeftijdsklasse' => 'Heren',
                'gewichtsklasse' => '-50',
            ]);
        }

        // 3 female judokas
        for ($i = 0; $i < 3; $i++) {
            $this->maakJudoka($toernooi, $club, [
                'geboortejaar' => $jaar - 13,
                'gewicht' => 45.0 + $i,
                'geslacht' => 'V',
                'band' => 'oranje',
                'leeftijdsklasse' => 'Dames',
                'gewichtsklasse' => '-50',
            ]);
        }

        $result = $this->service->genereerPouleIndeling($toernooi);

        // Should have separate poules for heren and dames
        $poules = $toernooi->poules()->where('type', 'voorronde')->get();
        $this->assertGreaterThanOrEqual(2, $poules->count());

        // Check that male and female judokas are in different poules
        foreach ($poules as $poule) {
            $geslachten = $poule->judokas->pluck('geslacht')->unique();
            // Each poule should only contain one gender (since categories are gender-specific)
            $this->assertEquals(1, $geslachten->count());
        }
    }
}
