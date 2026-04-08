<?php

namespace Tests\Unit\Models;

use App\Models\Blok;
use App\Models\Club;
use App\Models\Judoka;
use App\Models\Mat;
use App\Models\Poule;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PouleExtendedTest extends TestCase
{
    use RefreshDatabase;

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
    // Type checks: isKruisfinale, isVoorronde, isBarrage
    // ========================================================================

    #[Test]
    public function isKruisfinale_returns_true_for_kruisfinale(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0]);
        $poule = $this->maakPoule($toernooi, ['type' => 'kruisfinale']);
        $this->assertTrue($poule->isKruisfinale());
    }

    #[Test]
    public function isKruisfinale_returns_false_for_voorronde(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0]);
        $poule = $this->maakPoule($toernooi, ['type' => 'voorronde']);
        $this->assertFalse($poule->isKruisfinale());
    }

    #[Test]
    public function isVoorronde_returns_true_for_voorronde(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0]);
        $poule = $this->maakPoule($toernooi, ['type' => 'voorronde']);
        $this->assertTrue($poule->isVoorronde());
    }

    #[Test]
    public function isVoorronde_returns_true_for_null_type(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0]);
        $poule = $this->maakPoule($toernooi, ['type' => 'voorronde']);
        // Simulate null type by direct update (bypassing NOT NULL for the model check)
        $poule->type = null;
        $this->assertTrue($poule->isVoorronde());
    }

    #[Test]
    public function isVoorronde_returns_false_for_kruisfinale(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0]);
        $poule = $this->maakPoule($toernooi, ['type' => 'kruisfinale']);
        $this->assertFalse($poule->isVoorronde());
    }

    #[Test]
    public function isBarrage_returns_true_for_barrage(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0]);
        $poule = $this->maakPoule($toernooi, ['type' => 'barrage']);
        $this->assertTrue($poule->isBarrage());
    }

    #[Test]
    public function isBarrage_returns_false_for_voorronde(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0]);
        $poule = $this->maakPoule($toernooi, ['type' => 'voorronde']);
        $this->assertFalse($poule->isBarrage());
    }

    // ========================================================================
    // isPuntenCompetitie / getPuntenCompetitieWedstrijden
    // ========================================================================

    #[Test]
    public function isPuntenCompetitie_returns_true_when_configured(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0], [
            'wedstrijd_systeem' => ['test_cat' => 'punten_competitie'],
        ]);
        $poule = $this->maakPoule($toernooi);
        $this->assertTrue($poule->isPuntenCompetitie());
    }

    #[Test]
    public function isPuntenCompetitie_returns_false_for_poules(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0], [
            'wedstrijd_systeem' => ['test_cat' => 'poules'],
        ]);
        $poule = $this->maakPoule($toernooi);
        $this->assertFalse($poule->isPuntenCompetitie());
    }

    #[Test]
    public function isPuntenCompetitie_returns_false_when_no_systeem(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0]);
        $poule = $this->maakPoule($toernooi);
        $this->assertFalse($poule->isPuntenCompetitie());
    }

    #[Test]
    public function getPuntenCompetitieWedstrijden_returns_configured_value(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0], [
            'punten_competitie_wedstrijden' => ['test_cat' => 6],
        ]);
        $poule = $this->maakPoule($toernooi);
        $this->assertEquals(6, $poule->getPuntenCompetitieWedstrijden());
    }

    #[Test]
    public function getPuntenCompetitieWedstrijden_defaults_to_4(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0]);
        $poule = $this->maakPoule($toernooi);
        $this->assertEquals(4, $poule->getPuntenCompetitieWedstrijden());
    }

    // ========================================================================
    // berekenAantalWedstrijden - punten competitie
    // ========================================================================

    #[Test]
    public function berekenAantalWedstrijden_punten_competitie(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0], [
            'wedstrijd_systeem' => ['test_cat' => 'punten_competitie'],
            'punten_competitie_wedstrijden' => ['test_cat' => 4],
        ]);
        $poule = $this->maakPoule($toernooi);

        // 6 judokas × 4 wedstrijden / 2 = 12
        $this->assertEquals(12, $poule->berekenAantalWedstrijden(6));
        // 5 judokas × 4 / 2 = 10
        $this->assertEquals(10, $poule->berekenAantalWedstrijden(5));
    }

    // ========================================================================
    // berekenAWedstrijden / berekenBWedstrijden
    // ========================================================================

    #[Test]
    public function berekenAWedstrijden_returns_n_minus_1(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0]);
        $poule = $this->maakPoule($toernooi);

        $this->assertEquals(0, $poule->berekenAWedstrijden(1));
        $this->assertEquals(3, $poule->berekenAWedstrijden(4));
        $this->assertEquals(7, $poule->berekenAWedstrijden(8));
    }

    #[Test]
    public function berekenAWedstrijden_uses_stored_count_when_no_param(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0]);
        $poule = $this->maakPoule($toernooi, ['aantal_judokas' => 6]);

        $this->assertEquals(5, $poule->berekenAWedstrijden());
    }

    #[Test]
    public function berekenBWedstrijden_with_2_brons(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0], [
            'aantal_brons' => 2,
        ]);
        $poule = $this->maakPoule($toernooi);

        // N - 4 for 2 bronze
        $this->assertEquals(0, $poule->berekenBWedstrijden(4)); // < 5 returns 0
        $this->assertEquals(1, $poule->berekenBWedstrijden(5)); // 5-4=1
        $this->assertEquals(4, $poule->berekenBWedstrijden(8)); // 8-4=4
    }

    #[Test]
    public function berekenBWedstrijden_with_1_brons(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0], [
            'aantal_brons' => 1,
        ]);
        $poule = $this->maakPoule($toernooi);

        // N - 3 for 1 bronze
        $this->assertEquals(0, $poule->berekenBWedstrijden(4)); // < 5 returns 0
        $this->assertEquals(2, $poule->berekenBWedstrijden(5)); // 5-3=2
        $this->assertEquals(5, $poule->berekenBWedstrijden(8)); // 8-3=5
    }

    // ========================================================================
    // Relationships
    // ========================================================================

    #[Test]
    public function belongs_to_toernooi(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0]);
        $poule = $this->maakPoule($toernooi);

        $this->assertNotNull($poule->toernooi);
        $this->assertEquals($toernooi->id, $poule->toernooi->id);
    }

    #[Test]
    public function belongs_to_blok(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0]);
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id]);
        $poule = $this->maakPoule($toernooi, ['blok_id' => $blok->id]);

        $this->assertNotNull($poule->blok);
        $this->assertEquals($blok->id, $poule->blok->id);
    }

    #[Test]
    public function belongs_to_mat(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0]);
        $mat = Mat::factory()->create(['toernooi_id' => $toernooi->id]);
        $poule = $this->maakPoule($toernooi, ['mat_id' => $mat->id]);

        $this->assertNotNull($poule->mat);
        $this->assertEquals($mat->id, $poule->mat->id);
    }

    #[Test]
    public function belongs_to_b_mat(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0]);
        $mat = Mat::factory()->create(['toernooi_id' => $toernooi->id]);
        $poule = $this->maakPoule($toernooi, ['b_mat_id' => $mat->id]);

        $this->assertNotNull($poule->bMat);
        $this->assertEquals($mat->id, $poule->bMat->id);
    }

    #[Test]
    public function has_many_wedstrijden(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0]);
        $poule = $this->maakPoule($toernooi);

        // wedstrijden is a HasMany, should return empty collection
        $this->assertCount(0, $poule->wedstrijden);
    }

    #[Test]
    public function originele_poule_relationship(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0]);
        $origineel = $this->maakPoule($toernooi);
        $barrage = $this->maakPoule($toernooi, [
            'type' => 'barrage',
            'barrage_van_poule_id' => $origineel->id,
        ]);

        $this->assertNotNull($barrage->originelePoule);
        $this->assertEquals($origineel->id, $barrage->originelePoule->id);
    }

    // ========================================================================
    // getVoorrondePoules
    // ========================================================================

    #[Test]
    public function getVoorrondePoules_returns_matching_voorronde_poules(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0]);

        // Create voorronde poules with same leeftijdsklasse
        $vr1 = $this->maakPoule($toernooi, ['type' => 'voorronde', 'leeftijdsklasse' => 'Pupillen']);
        $vr2 = $this->maakPoule($toernooi, ['type' => 'voorronde', 'leeftijdsklasse' => 'Pupillen']);
        // Different leeftijdsklasse - should not be returned
        $vr3 = $this->maakPoule($toernooi, ['type' => 'voorronde', 'leeftijdsklasse' => 'Cadetten']);

        $kruisfinale = $this->maakPoule($toernooi, [
            'type' => 'kruisfinale',
            'leeftijdsklasse' => 'Pupillen',
        ]);

        $voorronden = $kruisfinale->getVoorrondePoules();
        $this->assertCount(2, $voorronden);
    }

    #[Test]
    public function getVoorrondePoules_returns_empty_for_non_kruisfinale(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0]);
        $poule = $this->maakPoule($toernooi, ['type' => 'voorronde']);

        $this->assertTrue($poule->getVoorrondePoules()->isEmpty());
    }

    // ========================================================================
    // genereerWedstrijdSchema
    // ========================================================================

    #[Test]
    public function genereerWedstrijdSchema_returns_empty_for_less_than_2(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0]);
        $poule = $this->maakPoule($toernooi);

        $this->maakJudokaInPoule($poule, 25.0, 2018);

        $this->assertEquals([], $poule->genereerWedstrijdSchema());
    }

    #[Test]
    public function genereerWedstrijdSchema_returns_correct_pairs_for_3_judokas(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0]);
        $poule = $this->maakPoule($toernooi);

        $j1 = $this->maakJudokaInPoule($poule, 25.0, 2018);
        $j2 = $this->maakJudokaInPoule($poule, 26.0, 2018);
        $j3 = $this->maakJudokaInPoule($poule, 27.0, 2018);

        $schema = $poule->genereerWedstrijdSchema();

        // Should have 6 matches (3 judokas with dubbel default schema)
        $this->assertNotEmpty($schema);
        // Each match should have 2 judoka IDs
        foreach ($schema as $match) {
            $this->assertCount(2, $match);
            $this->assertContains($match[0], [$j1->id, $j2->id, $j3->id]);
            $this->assertContains($match[1], [$j1->id, $j2->id, $j3->id]);
        }
    }

    // ========================================================================
    // genereerRoundRobinSchema (via genereerWedstrijdSchema with 8+ judokas)
    // ========================================================================

    #[Test]
    public function genereerWedstrijdSchema_handles_large_poule(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0], [
            'dubbel_bij_2_judokas' => false,
            'dubbel_bij_3_judokas' => false,
        ]);
        $poule = $this->maakPoule($toernooi);

        // Create 8 judokas (triggers genereerRoundRobinSchema for n > 7)
        for ($i = 0; $i < 8; $i++) {
            $this->maakJudokaInPoule($poule, 25.0 + $i, 2018);
        }

        $schema = $poule->genereerWedstrijdSchema();

        // 8 judokas = 28 matches in round-robin
        $this->assertCount(28, $schema);

        // Each match should have 2 distinct judoka IDs
        foreach ($schema as $match) {
            $this->assertCount(2, $match);
            $this->assertNotEquals($match[0], $match[1]);
        }
    }

    // ========================================================================
    // updateTitel (only for dynamic categories)
    // ========================================================================

    #[Test]
    public function updateTitel_skips_for_fixed_categories(): void
    {
        $toernooi = $this->maakToernooiMetConfig(['max_kg_verschil' => 0]);
        $poule = $this->maakPoule($toernooi, ['titel' => 'Original Title']);

        $poule->updateTitel();

        $this->assertEquals('Original Title', $poule->titel);
    }

    #[Test]
    public function updateTitel_updates_for_dynamic_categories(): void
    {
        $jaar = date('Y');
        $toernooi = $this->maakToernooiMetConfig([
            'max_kg_verschil' => 4,
            'max_leeftijd_verschil' => 2,
        ]);
        $poule = $this->maakPoule($toernooi, ['leeftijdsklasse' => "Mini's 4-6j"]);

        $this->maakJudokaInPoule($poule, 22.0, $jaar - 5);
        $this->maakJudokaInPoule($poule, 25.0, $jaar - 6);

        $poule->updateTitel();

        // Should contain weight range
        $this->assertStringContainsString('22', $poule->titel);
        $this->assertStringContainsString('25', $poule->titel);
    }
}
