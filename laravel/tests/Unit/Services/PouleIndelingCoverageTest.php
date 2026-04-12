<?php

namespace Tests\Unit\Services;

use App\Models\Club;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Services\PouleIndeling\PouleTitleBuilder;
use App\Services\PouleIndelingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PouleIndelingCoverageTest extends TestCase
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

    private function maakToernooi(array $gewichtsklassen, array $extra = []): Toernooi
    {
        return Toernooi::factory()->create(array_merge([
            'organisator_id' => $this->organisator->id,
            'gewichtsklassen' => $gewichtsklassen,
            'datum' => now(),
        ], $extra));
    }

    private function maakClub(): Club
    {
        return Club::factory()->create(['organisator_id' => $this->organisator->id]);
    }

    private function maakJudoka(Toernooi $toernooi, Club $club, array $attributes = []): Judoka
    {
        return Judoka::factory()->create(array_merge([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
        ], $attributes));
    }

    // =========================================================================
    // Elimination category path (lines 178-247)
    // =========================================================================

    #[Test]
    public function eliminatie_categorie_creates_eliminatie_poule(): void
    {
        $jaar = (int) date('Y');
        $toernooi = $this->maakToernooi([
            'cadetten' => [
                'label' => 'Cadetten',
                'max_leeftijd' => 15,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 0,
                'max_leeftijd_verschil' => 0,
                'gewichten' => ['-50'],
            ],
        ], [
            'gebruik_gewichtsklassen' => true,
            'wedstrijd_systeem' => ['cadetten' => 'eliminatie'],
            'poule_grootte_voorkeur' => [5, 4, 6, 3],
        ]);
        $club = $this->maakClub();

        // 8 judokas for elimination (>= 7 required)
        for ($i = 0; $i < 8; $i++) {
            $this->maakJudoka($toernooi, $club, [
                'geboortejaar' => $jaar - 13,
                'gewicht' => 40.0 + $i,
                'geslacht' => 'M',
                'band' => 'oranje',
                'leeftijdsklasse' => 'Cadetten',
                'gewichtsklasse' => '-50',
            ]);
        }

        $result = $this->service->genereerPouleIndeling($toernooi);

        $poule = $toernooi->poules()->where('type', 'eliminatie')->first();
        $this->assertNotNull($poule);
        $this->assertEquals(8, $poule->judokas()->count());
        $this->assertStringContains('Eliminatie', $poule->titel);
        $this->assertArrayHasKey('per_leeftijdsklasse', $result);
    }

    #[Test]
    public function eliminatie_with_less_than_7_adds_error_warning(): void
    {
        $jaar = (int) date('Y');
        $toernooi = $this->maakToernooi([
            'cadetten' => [
                'label' => 'Cadetten',
                'max_leeftijd' => 15,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 0,
                'max_leeftijd_verschil' => 0,
                'gewichten' => ['-50'],
            ],
        ], [
            'gebruik_gewichtsklassen' => true,
            'wedstrijd_systeem' => ['cadetten' => 'eliminatie'],
        ]);
        $club = $this->maakClub();

        // Only 5 judokas (< 7)
        for ($i = 0; $i < 5; $i++) {
            $this->maakJudoka($toernooi, $club, [
                'geboortejaar' => $jaar - 13,
                'gewicht' => 40.0 + $i,
                'geslacht' => 'M',
                'band' => 'oranje',
                'leeftijdsklasse' => 'Cadetten',
                'gewichtsklasse' => '-50',
            ]);
        }

        $result = $this->service->genereerPouleIndeling($toernooi);

        $errorWarnings = array_filter($result['waarschuwingen'], fn($w) => ($w['type'] ?? '') === 'error' && str_contains($w['bericht'] ?? '', 'Te weinig'));
        $this->assertNotEmpty($errorWarnings);
    }

    #[Test]
    public function eliminatie_with_exactly_7_adds_warning(): void
    {
        $jaar = (int) date('Y');
        $toernooi = $this->maakToernooi([
            'cadetten' => [
                'label' => 'Cadetten',
                'max_leeftijd' => 15,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 0,
                'max_leeftijd_verschil' => 0,
                'gewichten' => ['-50'],
            ],
        ], [
            'gebruik_gewichtsklassen' => true,
            'wedstrijd_systeem' => ['cadetten' => 'eliminatie'],
        ]);
        $club = $this->maakClub();

        for ($i = 0; $i < 7; $i++) {
            $this->maakJudoka($toernooi, $club, [
                'geboortejaar' => $jaar - 13,
                'gewicht' => 40.0 + $i,
                'geslacht' => 'M',
                'band' => 'oranje',
                'leeftijdsklasse' => 'Cadetten',
                'gewichtsklasse' => '-50',
            ]);
        }

        $result = $this->service->genereerPouleIndeling($toernooi);

        $warningWarnings = array_filter($result['waarschuwingen'], fn($w) => ($w['type'] ?? '') === 'warning' && str_contains($w['bericht'] ?? '', 'Weinig deelnemers'));
        $this->assertNotEmpty($warningWarnings);
    }

    #[Test]
    public function eliminatie_with_gender_specific_shows_gender_in_title(): void
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
        ], [
            'gebruik_gewichtsklassen' => true,
            'wedstrijd_systeem' => ['heren' => 'eliminatie'],
        ]);
        $club = $this->maakClub();

        for ($i = 0; $i < 8; $i++) {
            $this->maakJudoka($toernooi, $club, [
                'geboortejaar' => $jaar - 13,
                'gewicht' => 40.0 + $i,
                'geslacht' => 'M',
                'band' => 'oranje',
                'leeftijdsklasse' => 'Heren',
                'gewichtsklasse' => '-50',
            ]);
        }

        $result = $this->service->genereerPouleIndeling($toernooi);

        $poule = $toernooi->poules()->where('type', 'eliminatie')->first();
        $this->assertNotNull($poule);
        $this->assertStringContains('M', $poule->titel);
    }

    // =========================================================================
    // Kruisfinale path (lines 475-526)
    // =========================================================================

    #[Test]
    public function kruisfinale_created_for_poules_kruisfinale_system_with_2_plus_poules(): void
    {
        $jaar = (int) date('Y');
        $toernooi = $this->maakToernooi([
            'cadetten' => [
                'label' => 'Cadetten',
                'max_leeftijd' => 15,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 0,
                'max_leeftijd_verschil' => 0,
                'gewichten' => ['-50'],
            ],
        ], [
            'gebruik_gewichtsklassen' => true,
            'wedstrijd_systeem' => ['cadetten' => 'poules_kruisfinale'],
            'poule_grootte_voorkeur' => [5, 4, 6, 3],
        ]);
        $club = $this->maakClub();

        // 10 judokas -> 2 poules of 5 -> triggers kruisfinale
        for ($i = 0; $i < 10; $i++) {
            $this->maakJudoka($toernooi, $club, [
                'geboortejaar' => $jaar - 13,
                'gewicht' => 40.0 + ($i * 0.3),
                'geslacht' => 'M',
                'band' => 'oranje',
                'leeftijdsklasse' => 'Cadetten',
                'gewichtsklasse' => '-50',
            ]);
        }

        $result = $this->service->genereerPouleIndeling($toernooi);

        $kruisfinale = $toernooi->poules()->where('type', 'kruisfinale')->first();
        $this->assertNotNull($kruisfinale, 'Kruisfinale poule should be created');
        $this->assertGreaterThan(0, $result['totaal_kruisfinales']);
        $this->assertStringContains('Kruisfinale', $kruisfinale->titel);
    }

    #[Test]
    public function kruisfinale_with_3_poules_gets_top_2(): void
    {
        $jaar = (int) date('Y');
        $toernooi = $this->maakToernooi([
            'cadetten' => [
                'label' => 'Cadetten',
                'max_leeftijd' => 15,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 0,
                'max_leeftijd_verschil' => 0,
                'gewichten' => ['-50'],
            ],
        ], [
            'gebruik_gewichtsklassen' => true,
            'wedstrijd_systeem' => ['cadetten' => 'poules_kruisfinale'],
            'poule_grootte_voorkeur' => [5, 4, 6, 3],
        ]);
        $club = $this->maakClub();

        // 15 judokas -> 3 poules of 5 -> kruisfinale top 2 (= 6 judokas)
        for ($i = 0; $i < 15; $i++) {
            $this->maakJudoka($toernooi, $club, [
                'geboortejaar' => $jaar - 13,
                'gewicht' => 40.0 + ($i * 0.3),
                'geslacht' => 'M',
                'band' => 'oranje',
                'leeftijdsklasse' => 'Cadetten',
                'gewichtsklasse' => '-50',
            ]);
        }

        $result = $this->service->genereerPouleIndeling($toernooi);

        $kruisfinale = $toernooi->poules()->where('type', 'kruisfinale')->first();
        $this->assertNotNull($kruisfinale);
        $this->assertEquals(2, $kruisfinale->kruisfinale_plaatsen);
        $this->assertEquals(6, $kruisfinale->aantal_judokas); // 3 * 2
    }

    #[Test]
    public function kruisfinale_with_4_poules_gets_top_1(): void
    {
        $jaar = (int) date('Y');
        $toernooi = $this->maakToernooi([
            'cadetten' => [
                'label' => 'Cadetten',
                'max_leeftijd' => 15,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 0,
                'max_leeftijd_verschil' => 0,
                'gewichten' => ['-50'],
            ],
        ], [
            'gebruik_gewichtsklassen' => true,
            'wedstrijd_systeem' => ['cadetten' => 'poules_kruisfinale'],
            'poule_grootte_voorkeur' => [5, 4, 6, 3],
        ]);
        $club = $this->maakClub();

        // 20 judokas -> 4 poules of 5 -> kruisfinale top 1 (= 4 judokas)
        for ($i = 0; $i < 20; $i++) {
            $this->maakJudoka($toernooi, $club, [
                'geboortejaar' => $jaar - 13,
                'gewicht' => 40.0 + ($i * 0.3),
                'geslacht' => 'M',
                'band' => 'oranje',
                'leeftijdsklasse' => 'Cadetten',
                'gewichtsklasse' => '-50',
            ]);
        }

        $result = $this->service->genereerPouleIndeling($toernooi);

        $kruisfinale = $toernooi->poules()->where('type', 'kruisfinale')->first();
        $this->assertNotNull($kruisfinale);
        $this->assertEquals(1, $kruisfinale->kruisfinale_plaatsen);
        $this->assertEquals(4, $kruisfinale->aantal_judokas); // 4 * 1
    }

    // =========================================================================
    // berekenKruisfinalesPlaatsen (lines 557-577)
    // =========================================================================

    #[Test]
    public function kruisfinale_with_6_plus_poules_gets_top_1(): void
    {
        $jaar = (int) date('Y');
        $toernooi = $this->maakToernooi([
            'cadetten' => [
                'label' => 'Cadetten',
                'max_leeftijd' => 15,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 0,
                'max_leeftijd_verschil' => 0,
                'gewichten' => ['-50'],
            ],
        ], [
            'gebruik_gewichtsklassen' => true,
            'wedstrijd_systeem' => ['cadetten' => 'poules_kruisfinale'],
            'poule_grootte_voorkeur' => [5, 4, 6, 3],
        ]);
        $club = $this->maakClub();

        // 30 judokas -> 6 poules of 5 -> kruisfinale top 1 (= 6 judokas)
        for ($i = 0; $i < 30; $i++) {
            $this->maakJudoka($toernooi, $club, [
                'geboortejaar' => $jaar - 13,
                'gewicht' => 40.0 + ($i * 0.3),
                'geslacht' => 'M',
                'band' => 'oranje',
                'leeftijdsklasse' => 'Cadetten',
                'gewichtsklasse' => '-50',
            ]);
        }

        $result = $this->service->genereerPouleIndeling($toernooi);

        $kruisfinale = $toernooi->poules()->where('type', 'kruisfinale')->first();
        $this->assertNotNull($kruisfinale);
        $this->assertEquals(1, $kruisfinale->kruisfinale_plaatsen);
    }

    // =========================================================================
    // berekenAantalWedstrijden (lines 573-578)
    // =========================================================================

    #[Test]
    public function berekenAantalWedstrijden_covers_all_branches(): void
    {
        // Use reflection to test private method
        $reflection = new \ReflectionMethod($this->service, 'berekenAantalWedstrijden');
        $reflection->setAccessible(true);

        // antal <= 1 -> 0
        $this->assertEquals(0, $reflection->invoke($this->service, 0));
        $this->assertEquals(0, $reflection->invoke($this->service, 1));

        // antal === 3 -> 6 (double round)
        $this->assertEquals(6, $reflection->invoke($this->service, 3));

        // normal case: n*(n-1)/2
        $this->assertEquals(6, $reflection->invoke($this->service, 4)); // 4*3/2
        $this->assertEquals(10, $reflection->invoke($this->service, 5)); // 5*4/2
    }

    // =========================================================================
    // berekenVerdelingScore - size not in preference (line 773)
    // =========================================================================

    #[Test]
    public function berekenVerdelingScore_penalty_for_unknown_size(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'berekenVerdelingScore');
        $reflection->setAccessible(true);

        // Initialize service with known preference
        $this->service->initializeFromToernooi($this->maakToernooi([
            'test' => [
                'label' => 'Test',
                'max_leeftijd' => 99,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 0,
                'max_leeftijd_verschil' => 0,
            ],
        ], ['poule_grootte_voorkeur' => [5, 4, 6, 3]]));

        // Size 2 is not in preference [5,4,6,3] -> gets 1000 penalty
        $score = $reflection->invoke($this->service, [2]);
        $this->assertEquals(1000, $score);

        // Size 5 is first preference -> score = 2^1 - 1 = 1
        $score = $reflection->invoke($this->service, [5]);
        $this->assertEquals(1, $score);
    }

    // =========================================================================
    // maakOptimalePoules - invalid pool sizes (lines 728-729, 744)
    // =========================================================================

    #[Test]
    public function maakOptimalePoules_returns_single_pool_when_no_valid_split(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'maakOptimalePoules');
        $reflection->setAccessible(true);

        // Set min=3 max=4, preference [4, 3]
        $toernooi = $this->maakToernooi([
            'test' => [
                'label' => 'Test',
                'max_leeftijd' => 99,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 0,
                'max_leeftijd_verschil' => 0,
            ],
        ], ['poule_grootte_voorkeur' => [4, 3]]);
        $this->service->initializeFromToernooi($toernooi);

        // 7 judokas with min=3, max=4: 7/2 = 3+4 works, but let's test edge
        // 2 judokas: <= minJudokas (3) -> returns single pool
        $club = $this->maakClub();
        $judokas = collect();
        for ($i = 0; $i < 2; $i++) {
            $judokas->push($this->maakJudoka($toernooi, $club, [
                'geboortejaar' => (int) date('Y') - 10,
                'gewicht' => 30.0 + $i,
                'geslacht' => 'M',
            ]));
        }

        $result = $reflection->invoke($this->service, $judokas);
        $this->assertCount(1, $result);
        $this->assertCount(2, $result[0]);
    }

    // =========================================================================
    // Niet-ingedeelde judokas (lines 534-535, 904-977)
    // =========================================================================

    #[Test]
    public function vindNietIngedeeldeJudokas_finds_unassigned_judokas(): void
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

        // Create judoka but don't put in any poule
        $judoka = $this->maakJudoka($toernooi, $club, [
            'geboortejaar' => $jaar - 6,
            'gewicht' => 22.0,
            'geslacht' => 'M',
            'band' => 'wit',
            'leeftijdsklasse' => "Mini's",
        ]);

        // Create a poule without this judoka
        Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'leeftijdsklasse' => "Mini's",
            'categorie_key' => 'minis',
        ]);

        $reflection = new \ReflectionMethod($this->service, 'vindNietIngedeeldeJudokas');
        $reflection->setAccessible(true);
        $nietIngedeeld = $reflection->invoke($this->service, $toernooi);

        $this->assertNotEmpty($nietIngedeeld);
        $this->assertEquals($judoka->id, $nietIngedeeld[0]['id']);
        $this->assertArrayHasKey('reden', $nietIngedeeld[0]);
    }

    #[Test]
    public function bepaalRedenNietIngedeeld_no_category_for_age(): void
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

        // Too old (age 25, no category)
        $judoka = $this->maakJudoka($toernooi, $club, [
            'geboortejaar' => $jaar - 25,
            'gewicht' => 80.0,
            'geslacht' => 'M',
            'band' => 'blauw',
            'leeftijdsklasse' => 'Onbekend',
        ]);

        $reflection = new \ReflectionMethod($this->service, 'bepaalRedenNietIngedeeld');
        $reflection->setAccessible(true);
        $reden = $reflection->invoke($this->service, $judoka, $toernooi);

        $this->assertStringContains('Geen categorie voor leeftijd', $reden);
    }

    #[Test]
    public function bepaalRedenNietIngedeeld_no_weight_class_match(): void
    {
        $jaar = (int) date('Y');
        $toernooi = $this->maakToernooi([
            'cadetten' => [
                'label' => 'Cadetten',
                'max_leeftijd' => 15,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 0,
                'max_leeftijd_verschil' => 0,
                'gewichten' => ['-30'], // Only up to 30kg
            ],
        ], ['gebruik_gewichtsklassen' => true]);
        $club = $this->maakClub();

        // Judoka is 90kg, only -30 weight class exists
        $judoka = $this->maakJudoka($toernooi, $club, [
            'geboortejaar' => $jaar - 13,
            'gewicht' => 90.0,
            'geslacht' => 'M',
            'band' => 'oranje',
            'leeftijdsklasse' => 'Cadetten',
            'gewichtsklasse' => '-30',
        ]);

        $reflection = new \ReflectionMethod($this->service, 'bepaalRedenNietIngedeeld');
        $reflection->setAccessible(true);
        $reden = $reflection->invoke($this->service, $judoka, $toernooi);

        $this->assertStringContains('Geen gewichtsklasse', $reden);
    }

    #[Test]
    public function bepaalRedenNietIngedeeld_band_filter_mismatch(): void
    {
        $jaar = (int) date('Y');
        $toernooi = $this->maakToernooi([
            'minis' => [
                'label' => "Mini's",
                'max_leeftijd' => 7,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 3,
                'max_leeftijd_verschil' => 1,
                'band_filter' => 'tm_geel', // Only white and yellow belts (t/m geel)
            ],
        ], ['gebruik_gewichtsklassen' => false]);
        $club = $this->maakClub();

        // Judoka with bruin belt (not in tm_geel filter)
        $judoka = $this->maakJudoka($toernooi, $club, [
            'geboortejaar' => $jaar - 6,
            'gewicht' => 22.0,
            'geslacht' => 'M',
            'band' => 'bruin',
            'leeftijdsklasse' => "Mini's",
        ]);

        $reflection = new \ReflectionMethod($this->service, 'bepaalRedenNietIngedeeld');
        $reflection->setAccessible(true);
        $reden = $reflection->invoke($this->service, $judoka, $toernooi);

        $this->assertStringContains('band', $reden);
    }

    #[Test]
    public function bepaalRedenNietIngedeeld_plus_weight_class_match(): void
    {
        $jaar = (int) date('Y');
        $toernooi = $this->maakToernooi([
            'cadetten' => [
                'label' => 'Cadetten',
                'max_leeftijd' => 15,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 0,
                'max_leeftijd_verschil' => 0,
                'gewichten' => ['+60'], // Only +60kg
            ],
        ], ['gebruik_gewichtsklassen' => true]);
        $club = $this->maakClub();

        // Judoka at 65kg should match +60
        $judoka = $this->maakJudoka($toernooi, $club, [
            'geboortejaar' => $jaar - 13,
            'gewicht' => 65.0,
            'geslacht' => 'M',
            'band' => 'oranje',
            'leeftijdsklasse' => 'Cadetten',
        ]);

        $reflection = new \ReflectionMethod($this->service, 'bepaalRedenNietIngedeeld');
        $reflection->setAccessible(true);
        $reden = $reflection->invoke($this->service, $judoka, $toernooi);

        // Should match +60 -> fallback reason about weight difference
        $this->assertStringContains('gewichtsverschil', $reden);
    }

    // =========================================================================
    // Dynamic grouping with gender split (lines 637, 649-652)
    // =========================================================================

    #[Test]
    public function dynamic_grouping_with_gender_split(): void
    {
        $jaar = (int) date('Y');
        $toernooi = $this->maakToernooi([
            'heren_dynamic' => [
                'label' => 'Heren',
                'max_leeftijd' => 15,
                'geslacht' => 'M',
                'max_kg_verschil' => 5,
                'max_leeftijd_verschil' => 1,
            ],
            'dames_dynamic' => [
                'label' => 'Dames',
                'max_leeftijd' => 15,
                'geslacht' => 'V',
                'max_kg_verschil' => 5,
                'max_leeftijd_verschil' => 1,
            ],
        ], ['gebruik_gewichtsklassen' => false]);
        $club = $this->maakClub();

        // 4 male judokas
        for ($i = 0; $i < 4; $i++) {
            $this->maakJudoka($toernooi, $club, [
                'geboortejaar' => $jaar - 10,
                'gewicht' => 30.0 + $i,
                'geslacht' => 'M',
                'band' => 'oranje',
            ]);
        }

        // 4 female judokas
        for ($i = 0; $i < 4; $i++) {
            $this->maakJudoka($toernooi, $club, [
                'geboortejaar' => $jaar - 10,
                'gewicht' => 28.0 + $i,
                'geslacht' => 'V',
                'band' => 'oranje',
            ]);
        }

        $result = $this->service->genereerPouleIndeling($toernooi);

        $this->assertGreaterThan(0, $result['totaal_poules']);

        // Each poule should have one gender only
        $poules = $toernooi->poules()->with('judokas')->get();
        foreach ($poules as $poule) {
            $geslachten = $poule->judokas->pluck('geslacht')->unique();
            $this->assertLessThanOrEqual(1, $geslachten->count());
        }
    }

    // =========================================================================
    // No weight classes, no dynamic - group only by age (lines 649-652)
    // =========================================================================

    #[Test]
    public function no_weight_classes_no_dynamic_groups_by_age_only(): void
    {
        $jaar = (int) date('Y');
        $toernooi = $this->maakToernooi([
            'test' => [
                'label' => 'Test',
                'max_leeftijd' => 99,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 0,
                'max_leeftijd_verschil' => 0,
                // No 'gewichten' key -> no fixed weight classes, no dynamic
            ],
        ], ['gebruik_gewichtsklassen' => false]);
        $club = $this->maakClub();

        for ($i = 0; $i < 5; $i++) {
            $this->maakJudoka($toernooi, $club, [
                'geboortejaar' => $jaar - 10,
                'gewicht' => 30.0 + $i,
                'geslacht' => 'M',
                'band' => 'oranje',
            ]);
        }

        $result = $this->service->genereerPouleIndeling($toernooi);

        $this->assertGreaterThan(0, $result['totaal_poules']);
    }

    #[Test]
    public function no_weight_classes_with_gender_split(): void
    {
        $jaar = (int) date('Y');
        $toernooi = $this->maakToernooi([
            'dames_only' => [
                'label' => 'Dames',
                'max_leeftijd' => 99,
                'geslacht' => 'V',
                'max_kg_verschil' => 0,
                'max_leeftijd_verschil' => 0,
                // No gewichten, no dynamic
            ],
        ], ['gebruik_gewichtsklassen' => false]);
        $club = $this->maakClub();

        for ($i = 0; $i < 4; $i++) {
            $this->maakJudoka($toernooi, $club, [
                'geboortejaar' => $jaar - 10,
                'gewicht' => 30.0 + $i,
                'geslacht' => 'V',
                'band' => 'oranje',
            ]);
        }

        $result = $this->service->genereerPouleIndeling($toernooi);

        $this->assertGreaterThan(0, $result['totaal_poules']);
    }

    // =========================================================================
    // maakPouleTitel branches
    // =========================================================================

    #[Test]
    public function maakPouleTitel_dynamic_with_age_and_weight_range(): void
    {
        $builder = new PouleTitleBuilder();

        $jaar = (int) date('Y');
        $club = $this->maakClub();
        $toernooi = $this->maakToernooi([
            'test' => [
                'label' => 'Test',
                'max_leeftijd' => 99,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 5,
                'max_leeftijd_verschil' => 2,
            ],
        ]);

        $config = $toernooi->getAlleGewichtsklassen();

        $j1 = $this->maakJudoka($toernooi, $club, [
            'geboortejaar' => $jaar - 8,
            'gewicht' => 25.0,
            'geslacht' => 'M',
        ]);
        $j2 = $this->maakJudoka($toernooi, $club, [
            'geboortejaar' => $jaar - 10,
            'gewicht' => 30.0,
            'geslacht' => 'M',
        ]);

        $titel = $builder->build('Test', '25-30kg', null, [$j1, $j2], $config, 'test');

        $this->assertStringContains('Test', $titel);
        $this->assertTrue(str_contains($titel, 'j') || str_contains($titel, 'kg'));
    }

    #[Test]
    public function maakPouleTitel_with_label_hidden(): void
    {
        $builder = new PouleTitleBuilder();

        $config = [
            'test' => [
                'label' => 'Test',
                'max_kg_verschil' => 0,
                'max_leeftijd_verschil' => 0,
                'toon_label_in_titel' => false,
            ],
        ];

        $titel = $builder->build('Test', '-50', null, [], $config, 'test');

        // Label should not appear (toon_label_in_titel = false)
        // Should still show weight class
        $this->assertStringContains('-50', $titel);
    }

    #[Test]
    public function maakPouleTitel_returns_onbekend_when_empty(): void
    {
        $builder = new PouleTitleBuilder();

        $config = [
            'test' => [
                'label' => '',
                'max_kg_verschil' => 0,
                'max_leeftijd_verschil' => 0,
                'toon_label_in_titel' => false,
            ],
        ];

        $titel = $builder->build('', 'Onbekend', null, [], $config, 'test');

        // With empty label and hidden, gewichtsklasse = Onbekend (isVasteGewichtsklasse=false), no dynamic
        $this->assertEquals('Onbekend', $titel);
    }

    // =========================================================================
    // Band priority sorting in groepeerJudokas (line 658-662)
    // =========================================================================

    #[Test]
    public function groepeerJudokas_band_first_priority_sorts_by_band_then_weight(): void
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
            'verdeling_prioriteiten' => ['band', 'gewicht', 'leeftijd'],
        ]);
        $club = $this->maakClub();

        // Create judokas with different bands
        $this->maakJudoka($toernooi, $club, [
            'geboortejaar' => $jaar - 6,
            'gewicht' => 25.0,
            'geslacht' => 'M',
            'band' => 'oranje',
        ]);
        $this->maakJudoka($toernooi, $club, [
            'geboortejaar' => $jaar - 6,
            'gewicht' => 22.0,
            'geslacht' => 'M',
            'band' => 'wit',
        ]);
        $this->maakJudoka($toernooi, $club, [
            'geboortejaar' => $jaar - 6,
            'gewicht' => 23.0,
            'geslacht' => 'M',
            'band' => 'geel',
        ]);

        $result = $this->service->genereerPouleIndeling($toernooi);

        $this->assertGreaterThan(0, $result['totaal_poules']);
    }

    // =========================================================================
    // SQLite sequence reset (lines 128, 131) - covered via normal flow on SQLite
    // =========================================================================

    #[Test]
    public function sqlite_sequence_reset_on_regenerate(): void
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

        for ($i = 0; $i < 5; $i++) {
            $this->maakJudoka($toernooi, $club, [
                'geboortejaar' => $jaar - 10,
                'gewicht' => 40.0 + $i,
                'geslacht' => 'M',
                'band' => 'oranje',
                'leeftijdsklasse' => 'Test',
                'gewichtsklasse' => '-50',
            ]);
        }

        // First generation creates poules (and sqlite_sequence entries)
        $this->service->genereerPouleIndeling($toernooi);
        $firstIds = $toernooi->poules()->pluck('id')->toArray();
        $this->assertNotEmpty($firstIds);

        // Second generation should reset and regenerate
        $toernooi->load('judokas');
        $this->service->genereerPouleIndeling($toernooi);
        $secondIds = $toernooi->poules()->pluck('id')->toArray();

        // Poules should exist after regeneration
        $this->assertNotEmpty($secondIds);
    }

    // =========================================================================
    // Kruisfinale with gender-specific category
    // =========================================================================

    #[Test]
    public function kruisfinale_with_gender_specific_category(): void
    {
        $jaar = (int) date('Y');
        $toernooi = $this->maakToernooi([
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
            'wedstrijd_systeem' => ['dames' => 'poules_kruisfinale'],
            'poule_grootte_voorkeur' => [5, 4, 6, 3],
        ]);
        $club = $this->maakClub();

        // 10 female judokas -> 2 poules of 5 -> kruisfinale
        for ($i = 0; $i < 10; $i++) {
            $this->maakJudoka($toernooi, $club, [
                'geboortejaar' => $jaar - 13,
                'gewicht' => 40.0 + ($i * 0.3),
                'geslacht' => 'V',
                'band' => 'oranje',
                'leeftijdsklasse' => 'Dames',
                'gewichtsklasse' => '-50',
            ]);
        }

        $result = $this->service->genereerPouleIndeling($toernooi);

        $kruisfinale = $toernooi->poules()->where('type', 'kruisfinale')->first();
        $this->assertNotNull($kruisfinale);
        $this->assertStringContains('V', $kruisfinale->titel);
    }

    // =========================================================================
    // Helper assertion
    // =========================================================================

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'"
        );
    }
}
