<?php

namespace Tests\Unit\Models;

use App\Models\Club;
use App\Models\Judoka;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PouleModelTest extends TestCase
{
    use RefreshDatabase;

    // ========================================================================
    // Helpers (same patterns as PouleCheckRegelsTest)
    // ========================================================================

    private function maakToernooiMetConfig(array $categorieConfig, array $extraFields = []): Toernooi
    {
        return Toernooi::factory()->create(array_merge([
            'gewichtsklassen' => [
                'test_cat' => array_merge([
                    'label' => 'Test',
                    'max_leeftijd' => 12,
                    'geslacht' => 'gemengd',
                ], $categorieConfig),
            ],
        ], $extraFields));
    }

    private function maakPoule(Toernooi $toernooi, array $extra = []): Poule
    {
        return Poule::factory()->create(array_merge([
            'toernooi_id' => $toernooi->id,
            'leeftijdsklasse' => 'Test',
            'categorie_key' => 'test_cat',
            'titel' => 'Test Poule',
        ], $extra));
    }

    private function maakJudokaInPoule(Poule $poule, float $gewicht, int $geboortejaar, array $extra = []): Judoka
    {
        $judoka = Judoka::factory()->create(array_merge([
            'toernooi_id' => $poule->toernooi_id,
            'club_id' => Club::factory()->create(['organisator_id' => $poule->toernooi->organisator_id])->id,
            'gewicht' => $gewicht,
            'geboortejaar' => $geboortejaar,
            'leeftijdsklasse' => 'Test',
        ], $extra));

        $poule->judokas()->attach($judoka->id, ['positie' => $poule->judokas()->count() + 1]);
        $poule->load('judokas');

        return $judoka;
    }

    // ========================================================================
    // voegJudokaToe()
    // ========================================================================

    #[Test]
    public function voegJudokaToe_attaches_judoka_to_poule(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0, 'max_leeftijd_verschil' => 0]);
        $poule = $this->maakPoule($toernooi);

        $judoka = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'club_id' => Club::factory()->create(['organisator_id' => $toernooi->organisator_id])->id,
            'gewicht' => 25.0,
            'geboortejaar' => 2018,
        ]);

        $poule->voegJudokaToe($judoka);

        $this->assertCount(1, $poule->fresh()->judokas);
        $this->assertEquals($judoka->id, $poule->fresh()->judokas->first()->id);
    }

    #[Test]
    public function voegJudokaToe_updates_statistieken(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0, 'max_leeftijd_verschil' => 0]);
        $poule = $this->maakPoule($toernooi);

        $club = Club::factory()->create(['organisator_id' => $toernooi->organisator_id]);
        $j1 = Judoka::factory()->create(['toernooi_id' => $toernooi->id, 'club_id' => $club->id, 'gewicht' => 25.0]);
        $j2 = Judoka::factory()->create(['toernooi_id' => $toernooi->id, 'club_id' => $club->id, 'gewicht' => 26.0]);
        $j3 = Judoka::factory()->create(['toernooi_id' => $toernooi->id, 'club_id' => $club->id, 'gewicht' => 27.0]);

        $poule->voegJudokaToe($j1);
        $poule->voegJudokaToe($j2);
        $poule->voegJudokaToe($j3);

        $poule->refresh();
        $this->assertEquals(3, $poule->aantal_judokas);
        // 3*(3-1)/2 = 3, but dubbel_bij_3 is default true → 6
        $this->assertEquals(6, $poule->aantal_wedstrijden);
    }

    #[Test]
    public function voegJudokaToe_assigns_correct_position(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0, 'max_leeftijd_verschil' => 0]);
        $poule = $this->maakPoule($toernooi);

        $club = Club::factory()->create(['organisator_id' => $toernooi->organisator_id]);
        $j1 = Judoka::factory()->create(['toernooi_id' => $toernooi->id, 'club_id' => $club->id]);
        $j2 = Judoka::factory()->create(['toernooi_id' => $toernooi->id, 'club_id' => $club->id]);

        $poule->voegJudokaToe($j1);
        $poule->voegJudokaToe($j2);

        $poule->load('judokas');
        $this->assertEquals(1, $poule->judokas[0]->pivot->positie);
        $this->assertEquals(2, $poule->judokas[1]->pivot->positie);
    }

    #[Test]
    public function voegJudokaToe_with_explicit_position(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0, 'max_leeftijd_verschil' => 0]);
        $poule = $this->maakPoule($toernooi);

        $judoka = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'club_id' => Club::factory()->create(['organisator_id' => $toernooi->organisator_id])->id,
        ]);

        $poule->voegJudokaToe($judoka, 5);

        $poule->load('judokas');
        $this->assertEquals(5, $poule->judokas->first()->pivot->positie);
    }

    // ========================================================================
    // verwijderJudoka()
    // ========================================================================

    #[Test]
    public function verwijderJudoka_detaches_judoka_from_poule(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0, 'max_leeftijd_verschil' => 0]);
        $poule = $this->maakPoule($toernooi);

        $judoka = $this->maakJudokaInPoule($poule, 25.0, 2018);

        $poule->verwijderJudoka($judoka);

        $this->assertCount(0, $poule->fresh()->judokas);
    }

    #[Test]
    public function verwijderJudoka_updates_statistieken(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0, 'max_leeftijd_verschil' => 0]);
        $poule = $this->maakPoule($toernooi);

        $j1 = $this->maakJudokaInPoule($poule, 25.0, 2018);
        $j2 = $this->maakJudokaInPoule($poule, 26.0, 2018);
        $j3 = $this->maakJudokaInPoule($poule, 27.0, 2018);

        // Force update stats for initial state
        $poule->updateStatistieken();
        $poule->refresh();
        $this->assertEquals(3, $poule->aantal_judokas);

        $poule->verwijderJudoka($j3);
        $poule->refresh();

        $this->assertEquals(2, $poule->aantal_judokas);
        // 2*(2-1)/2 = 1, but dubbel_bij_2 is default true → 2
        $this->assertEquals(2, $poule->aantal_wedstrijden);
    }

    // ========================================================================
    // getGewichtsRange()
    // ========================================================================

    #[Test]
    public function getGewichtsRange_returns_null_for_empty_poule(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 3, 'max_leeftijd_verschil' => 0]);
        $poule = $this->maakPoule($toernooi);

        $this->assertNull($poule->getGewichtsRange());
    }

    #[Test]
    public function getGewichtsRange_returns_correct_range(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 5, 'max_leeftijd_verschil' => 0]);
        $poule = $this->maakPoule($toernooi);

        $this->maakJudokaInPoule($poule, 22.0, 2018);
        $this->maakJudokaInPoule($poule, 25.5, 2018);
        $this->maakJudokaInPoule($poule, 28.0, 2018);

        $range = $poule->getGewichtsRange();

        $this->assertNotNull($range);
        $this->assertEquals(22.0, $range['min_kg']);
        $this->assertEquals(28.0, $range['max_kg']);
        $this->assertEquals(6.0, $range['range']);
    }

    #[Test]
    public function getGewichtsRange_excludes_absent_judokas(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 5, 'max_leeftijd_verschil' => 0]);
        $poule = $this->maakPoule($toernooi);

        $this->maakJudokaInPoule($poule, 22.0, 2018);
        $this->maakJudokaInPoule($poule, 25.0, 2018);
        $this->maakJudokaInPoule($poule, 40.0, 2018, ['aanwezigheid' => 'afwezig']);

        $range = $poule->getGewichtsRange();

        $this->assertNotNull($range);
        $this->assertEquals(22.0, $range['min_kg']);
        $this->assertEquals(25.0, $range['max_kg']);
    }

    #[Test]
    public function getGewichtsRange_single_judoka_returns_zero_range(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 3, 'max_leeftijd_verschil' => 0]);
        $poule = $this->maakPoule($toernooi);

        $this->maakJudokaInPoule($poule, 25.0, 2018);

        $range = $poule->getGewichtsRange();

        $this->assertNotNull($range);
        $this->assertEquals(25.0, $range['min_kg']);
        $this->assertEquals(25.0, $range['max_kg']);
        $this->assertEquals(0.0, $range['range']);
    }

    // ========================================================================
    // getLeeftijdsRange()
    // ========================================================================

    #[Test]
    public function getLeeftijdsRange_returns_null_for_empty_poule(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0, 'max_leeftijd_verschil' => 2]);
        $poule = $this->maakPoule($toernooi);

        $this->assertNull($poule->getLeeftijdsRange());
    }

    #[Test]
    public function getLeeftijdsRange_returns_correct_range(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0, 'max_leeftijd_verschil' => 2]);
        $poule = $this->maakPoule($toernooi);

        $jaar = date('Y');
        $this->maakJudokaInPoule($poule, 25.0, $jaar - 7);  // 7 years old
        $this->maakJudokaInPoule($poule, 26.0, $jaar - 9);  // 9 years old
        $this->maakJudokaInPoule($poule, 27.0, $jaar - 10); // 10 years old

        $range = $poule->getLeeftijdsRange();

        $this->assertNotNull($range);
        $this->assertEquals(7, $range['min_jaar']);
        $this->assertEquals(10, $range['max_jaar']);
        $this->assertEquals(3, $range['range']);
    }

    #[Test]
    public function getLeeftijdsRange_excludes_absent_judokas(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0, 'max_leeftijd_verschil' => 2]);
        $poule = $this->maakPoule($toernooi);

        $jaar = date('Y');
        $this->maakJudokaInPoule($poule, 25.0, $jaar - 7);
        $this->maakJudokaInPoule($poule, 26.0, $jaar - 8);
        $this->maakJudokaInPoule($poule, 27.0, $jaar - 15, ['aanwezigheid' => 'afwezig']);

        $range = $poule->getLeeftijdsRange();

        $this->assertNotNull($range);
        $this->assertEquals(7, $range['min_jaar']);
        $this->assertEquals(8, $range['max_jaar']);
    }

    // ========================================================================
    // getDisplayTitel()
    // ========================================================================

    #[Test]
    public function getDisplayTitel_with_fixed_weight_class(): void
    {
        $toernooi = $this->maakToernooiMetConfig([
            'max_kg_verschil' => 0,
            'max_leeftijd_verschil' => 0,
            'toon_label_in_titel' => false,
        ]);
        $poule = $this->maakPoule($toernooi, ['gewichtsklasse' => '-28']);

        $titel = $poule->getDisplayTitel();

        $this->assertEquals('-28kg', $titel);
    }

    #[Test]
    public function getDisplayTitel_with_label_enabled(): void
    {
        $toernooi = $this->maakToernooiMetConfig([
            'label' => "Mini's",
            'max_kg_verschil' => 0,
            'max_leeftijd_verschil' => 0,
            'toon_label_in_titel' => true,
        ]);
        $poule = $this->maakPoule($toernooi, ['gewichtsklasse' => '-28']);

        $titel = $poule->getDisplayTitel();

        $this->assertStringContainsString("Mini's", $titel);
        $this->assertStringContainsString('-28kg', $titel);
    }

    #[Test]
    public function getDisplayTitel_with_dynamic_weight_shows_range(): void
    {
        $toernooi = $this->maakToernooiMetConfig([
            'max_kg_verschil' => 4,
            'max_leeftijd_verschil' => 0,
            'toon_label_in_titel' => false,
        ]);
        $poule = $this->maakPoule($toernooi);

        $this->maakJudokaInPoule($poule, 22.0, 2018);
        $this->maakJudokaInPoule($poule, 25.0, 2018);

        $titel = $poule->getDisplayTitel();

        $this->assertStringContainsString('22', $titel);
        $this->assertStringContainsString('25', $titel);
        $this->assertStringContainsString('kg', $titel);
    }

    #[Test]
    public function getDisplayTitel_with_age_range(): void
    {
        $jaar = date('Y');
        $toernooi = $this->maakToernooiMetConfig([
            'max_kg_verschil' => 0,
            'max_leeftijd_verschil' => 2,
            'toon_label_in_titel' => false,
        ]);
        $poule = $this->maakPoule($toernooi, ['gewichtsklasse' => '-28']);

        $this->maakJudokaInPoule($poule, 25.0, $jaar - 7);
        $this->maakJudokaInPoule($poule, 26.0, $jaar - 9);

        $titel = $poule->getDisplayTitel();

        $this->assertStringContainsString('7-9j', $titel);
        $this->assertStringContainsString('-28kg', $titel);
    }

    #[Test]
    public function getDisplayTitel_fallback_to_titel_field(): void
    {
        $toernooi = $this->maakToernooiMetConfig([
            'max_kg_verschil' => 0,
            'max_leeftijd_verschil' => 0,
            'toon_label_in_titel' => false,
        ]);
        $poule = $this->maakPoule($toernooi, ['gewichtsklasse' => '', 'titel' => 'Mijn Poule']);

        $titel = $poule->getDisplayTitel();

        $this->assertEquals('Mijn Poule', $titel);
    }

    #[Test]
    public function getDisplayTitel_fallback_to_leeftijdsklasse(): void
    {
        $toernooi = $this->maakToernooiMetConfig([
            'max_kg_verschil' => 0,
            'max_leeftijd_verschil' => 0,
            'toon_label_in_titel' => false,
        ]);
        $poule = $this->maakPoule($toernooi, [
            'gewichtsklasse' => '',
            'titel' => '',
            'leeftijdsklasse' => 'Pupillen',
        ]);

        $titel = $poule->getDisplayTitel();

        // When config has no displayable parts, falls back to titel or leeftijdsklasse
        $this->assertNotEmpty($titel);
    }

    // ========================================================================
    // isProblematischNaWeging()
    // ========================================================================

    #[Test]
    public function isProblematischNaWeging_returns_null_for_fixed_category(): void
    {
        $toernooi = $this->maakToernooiMetConfig([
            'max_kg_verschil' => 0,
            'max_leeftijd_verschil' => 0,
        ]);
        $poule = $this->maakPoule($toernooi);

        $this->maakJudokaInPoule($poule, 25.0, 2018, ['gewicht_gewogen' => 25.0]);
        $this->maakJudokaInPoule($poule, 35.0, 2018, ['gewicht_gewogen' => 35.0]);

        $this->assertNull($poule->isProblematischNaWeging());
    }

    #[Test]
    public function isProblematischNaWeging_returns_null_when_range_within_limit(): void
    {
        $toernooi = $this->maakToernooiMetConfig([
            'max_kg_verschil' => 5,
            'max_leeftijd_verschil' => 0,
        ]);
        $poule = $this->maakPoule($toernooi);

        $this->maakJudokaInPoule($poule, 25.0, 2018, ['gewicht_gewogen' => 25.0]);
        $this->maakJudokaInPoule($poule, 28.0, 2018, ['gewicht_gewogen' => 28.0]);

        $this->assertNull($poule->isProblematischNaWeging());
    }

    #[Test]
    public function isProblematischNaWeging_returns_details_when_range_exceeded(): void
    {
        $toernooi = $this->maakToernooiMetConfig([
            'max_kg_verschil' => 3,
            'max_leeftijd_verschil' => 0,
        ]);
        $poule = $this->maakPoule($toernooi);

        $this->maakJudokaInPoule($poule, 22.0, 2018, ['gewicht_gewogen' => 22.0]);
        $this->maakJudokaInPoule($poule, 28.0, 2018, ['gewicht_gewogen' => 28.0]);

        $result = $poule->isProblematischNaWeging();

        $this->assertNotNull($result);
        $this->assertEquals(6.0, $result['range']);
        $this->assertEquals(3.0, $result['max_toegestaan']);
        $this->assertEquals(3.0, $result['overschrijding']);
        $this->assertEquals(22.0, $result['min_kg']);
        $this->assertEquals(28.0, $result['max_kg']);
        $this->assertNotNull($result['lichtste']);
        $this->assertNotNull($result['zwaarste']);
    }

    #[Test]
    public function isProblematischNaWeging_returns_null_for_empty_poule(): void
    {
        $toernooi = $this->maakToernooiMetConfig([
            'max_kg_verschil' => 3,
            'max_leeftijd_verschil' => 0,
        ]);
        $poule = $this->maakPoule($toernooi);

        $this->assertNull($poule->isProblematischNaWeging());
    }

    // ========================================================================
    // isDynamisch()
    // ========================================================================

    #[Test]
    public function isDynamisch_returns_true_for_dynamic_category(): void
    {
        $toernooi = $this->maakToernooiMetConfig([
            'max_kg_verschil' => 4,
            'max_leeftijd_verschil' => 1,
        ]);
        $poule = $this->maakPoule($toernooi);

        $this->assertTrue($poule->isDynamisch());
    }

    #[Test]
    public function isDynamisch_returns_false_for_fixed_category(): void
    {
        $toernooi = $this->maakToernooiMetConfig([
            'max_kg_verschil' => 0,
            'max_leeftijd_verschil' => 0,
        ]);
        $poule = $this->maakPoule($toernooi);

        $this->assertFalse($poule->isDynamisch());
    }

    #[Test]
    public function isDynamisch_returns_false_without_categorie_key(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 4]);
        $poule = $this->maakPoule($toernooi, ['categorie_key' => null]);

        $this->assertFalse($poule->isDynamisch());
    }

    // ========================================================================
    // updateStatistieken()
    // ========================================================================

    #[Test]
    public function updateStatistieken_counts_judokas_and_wedstrijden(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0, 'max_leeftijd_verschil' => 0]);
        $poule = $this->maakPoule($toernooi);

        $this->maakJudokaInPoule($poule, 25.0, 2018);
        $this->maakJudokaInPoule($poule, 26.0, 2018);
        $this->maakJudokaInPoule($poule, 27.0, 2018);
        $this->maakJudokaInPoule($poule, 28.0, 2018);

        $poule->updateStatistieken();
        $poule->refresh();

        $this->assertEquals(4, $poule->aantal_judokas);
        // 4*(4-1)/2 = 6 matches
        $this->assertEquals(6, $poule->aantal_wedstrijden);
    }

    #[Test]
    public function updateStatistieken_kruisfinale_preserves_count_when_no_judokas(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0, 'max_leeftijd_verschil' => 0]);
        $poule = $this->maakPoule($toernooi, [
            'type' => 'kruisfinale',
            'aantal_judokas' => 6,
            'aantal_wedstrijden' => 15,
        ]);

        // No judokas attached, but kruisfinale should keep existing count
        $poule->updateStatistieken();
        $poule->refresh();

        $this->assertEquals(6, $poule->aantal_judokas);
    }

    #[Test]
    public function updateStatistieken_empty_poule(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0, 'max_leeftijd_verschil' => 0]);
        $poule = $this->maakPoule($toernooi);

        $poule->updateStatistieken();
        $poule->refresh();

        $this->assertEquals(0, $poule->aantal_judokas);
        $this->assertEquals(0, $poule->aantal_wedstrijden);
    }

    // ========================================================================
    // getCategorieConfig()
    // ========================================================================

    #[Test]
    public function getCategorieConfig_returns_config_for_known_key(): void
    {
        $toernooi = $this->maakToernooiMetConfig([
            'max_kg_verschil' => 4,
            'max_leeftijd_verschil' => 1,
        ]);
        $poule = $this->maakPoule($toernooi);

        $config = $poule->getCategorieConfig();

        $this->assertEquals(4, $config['max_kg_verschil']);
        $this->assertEquals(1, $config['max_leeftijd_verschil']);
        $this->assertEquals('Test', $config['label']);
    }

    #[Test]
    public function getCategorieConfig_returns_fallback_for_unknown_key(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 3]);
        $poule = $this->maakPoule($toernooi, ['categorie_key' => 'nonexistent_key']);

        $config = $poule->getCategorieConfig();

        $this->assertEquals(0, $config['max_kg_verschil']);
        $this->assertEquals(0, $config['max_leeftijd_verschil']);
    }

    #[Test]
    public function getCategorieConfig_returns_fallback_when_no_categorie_key(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 3]);
        $poule = $this->maakPoule($toernooi, ['categorie_key' => null]);

        $config = $poule->getCategorieConfig();

        $this->assertEquals(0, $config['max_kg_verschil']);
        $this->assertEquals(0, $config['max_leeftijd_verschil']);
    }

    // ========================================================================
    // berekenAantalWedstrijden()
    // ========================================================================

    #[Test]
    public function berekenAantalWedstrijden_round_robin_formula(): void
    {
        // Disable all dubbel settings to test pure round-robin
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0], [
            'dubbel_bij_2_judokas' => false,
            'dubbel_bij_3_judokas' => false,
            'dubbel_bij_4_judokas' => false,
            'best_of_three_bij_2' => false,
        ]);
        $poule = $this->maakPoule($toernooi);

        // n*(n-1)/2
        $this->assertEquals(0, $poule->berekenAantalWedstrijden(0));
        $this->assertEquals(0, $poule->berekenAantalWedstrijden(1));
        $this->assertEquals(1, $poule->berekenAantalWedstrijden(2));
        $this->assertEquals(3, $poule->berekenAantalWedstrijden(3));
        $this->assertEquals(6, $poule->berekenAantalWedstrijden(4));
        $this->assertEquals(10, $poule->berekenAantalWedstrijden(5));
        $this->assertEquals(15, $poule->berekenAantalWedstrijden(6));
    }

    #[Test]
    public function berekenAantalWedstrijden_dubbel_bij_2(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0], [
            'dubbel_bij_2_judokas' => true,
            'best_of_three_bij_2' => false,
        ]);
        $poule = $this->maakPoule($toernooi);

        // 2 judokas with dubbel = 1 * 2 = 2
        $this->assertEquals(2, $poule->berekenAantalWedstrijden(2));
    }

    #[Test]
    public function berekenAantalWedstrijden_best_of_three_bij_2(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0], [
            'best_of_three_bij_2' => true,
            'dubbel_bij_2_judokas' => true, // best_of_three overrides dubbel
        ]);
        $poule = $this->maakPoule($toernooi);

        $this->assertEquals(3, $poule->berekenAantalWedstrijden(2));
    }

    #[Test]
    public function berekenAantalWedstrijden_dubbel_bij_3(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0], [
            'dubbel_bij_3_judokas' => true,
        ]);
        $poule = $this->maakPoule($toernooi);

        // 3 judokas with dubbel: 3 * 2 = 6
        $this->assertEquals(6, $poule->berekenAantalWedstrijden(3));
    }

    #[Test]
    public function berekenAantalWedstrijden_dubbel_bij_4(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0], [
            'dubbel_bij_4_judokas' => true,
        ]);
        $poule = $this->maakPoule($toernooi);

        // 4 judokas with dubbel: 6 * 2 = 12
        $this->assertEquals(12, $poule->berekenAantalWedstrijden(4));
    }

    #[Test]
    public function berekenAantalWedstrijden_eliminatie(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0], [
            'aantal_brons' => 2,
        ]);
        $poule = $this->maakPoule($toernooi, ['type' => 'eliminatie']);

        // Formula with 2 bronze: 2N - 5
        $this->assertEquals(0, $poule->berekenAantalWedstrijden(1));
        $this->assertEquals(0, $poule->berekenAantalWedstrijden(2)); // max(0, 4-5) = 0
        $this->assertEquals(1, $poule->berekenAantalWedstrijden(3)); // 6-5 = 1
        $this->assertEquals(3, $poule->berekenAantalWedstrijden(4)); // 8-5 = 3
        $this->assertEquals(5, $poule->berekenAantalWedstrijden(5)); // 10-5 = 5
    }

    #[Test]
    public function berekenAantalWedstrijden_eliminatie_enkel_brons(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0], [
            'aantal_brons' => 1,
        ]);
        $poule = $this->maakPoule($toernooi, ['type' => 'eliminatie']);

        // Formula with 1 bronze: 2N - 4
        $this->assertEquals(0, $poule->berekenAantalWedstrijden(1));
        $this->assertEquals(0, $poule->berekenAantalWedstrijden(2)); // max(0, 4-4) = 0
        $this->assertEquals(2, $poule->berekenAantalWedstrijden(3)); // 6-4 = 2
        $this->assertEquals(4, $poule->berekenAantalWedstrijden(4)); // 8-4 = 4
    }

    #[Test]
    public function berekenAantalWedstrijden_uses_aantal_judokas_when_no_param(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0]);
        $poule = $this->maakPoule($toernooi, ['aantal_judokas' => 4]);

        // Should use the stored aantal_judokas = 4 → 4*3/2 = 6
        $this->assertEquals(6, $poule->berekenAantalWedstrijden());
    }
}
