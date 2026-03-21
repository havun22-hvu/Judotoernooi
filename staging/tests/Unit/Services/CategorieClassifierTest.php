<?php

namespace Tests\Unit\Services;

use App\Models\Judoka;
use App\Models\Toernooi;
use App\Services\CategorieClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CategorieClassifierTest extends TestCase
{
    use RefreshDatabase;

    private function createConfig(): array
    {
        return [
            'minis' => [
                'label' => "Mini's",
                'max_leeftijd' => 7,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 3,
                'gewichten' => [],
            ],
            'pupillen' => [
                'label' => 'Pupillen',
                'max_leeftijd' => 11,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 4,
                'gewichten' => [],
            ],
            'cadetten_h' => [
                'label' => 'Cadetten Heren',
                'max_leeftijd' => 15,
                'geslacht' => 'M',
                'max_kg_verschil' => 0,
                'gewichten' => ['-46', '-50', '-55', '-60', '+60'],
            ],
            'cadetten_d' => [
                'label' => 'Cadetten Dames',
                'max_leeftijd' => 15,
                'geslacht' => 'V',
                'max_kg_verschil' => 0,
                'gewichten' => ['-40', '-44', '-48', '-52', '+52'],
            ],
        ];
    }

    // =========================================================================
    // BASIC CLASSIFICATION
    // =========================================================================

    #[Test]
    public function classifies_young_judoka_as_mini(): void
    {
        $classifier = new CategorieClassifier($this->createConfig());
        $toernooi = Toernooi::factory()->create();
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'geboortejaar' => (int) date('Y') - 6,
            'geslacht' => 'M',
            'gewicht' => 25,
        ]);

        $result = $classifier->classificeer($judoka);

        $this->assertEquals('minis', $result['key']);
        $this->assertEquals("Mini's", $result['label']);
        $this->assertTrue($result['isDynamisch']);
    }

    #[Test]
    public function classifies_pupil_age_correctly(): void
    {
        $classifier = new CategorieClassifier($this->createConfig());
        $toernooi = Toernooi::factory()->create();
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'geboortejaar' => (int) date('Y') - 10,
            'geslacht' => 'V',
            'gewicht' => 35,
        ]);

        $result = $classifier->classificeer($judoka);

        $this->assertEquals('pupillen', $result['key']);
    }

    #[Test]
    public function classifies_male_cadet_with_weight_class(): void
    {
        $classifier = new CategorieClassifier($this->createConfig());
        $toernooi = Toernooi::factory()->create();
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'geboortejaar' => (int) date('Y') - 14,
            'geslacht' => 'M',
            'gewicht' => 48,
        ]);

        $result = $classifier->classificeer($judoka);

        $this->assertEquals('cadetten_h', $result['key']);
        $this->assertEquals('-50', $result['gewichtsklasse']);
        $this->assertFalse($result['isDynamisch']);
    }

    #[Test]
    public function classifies_female_cadet_separately(): void
    {
        $classifier = new CategorieClassifier($this->createConfig());
        $toernooi = Toernooi::factory()->create();
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'geboortejaar' => (int) date('Y') - 14,
            'geslacht' => 'V',
            'gewicht' => 42,
        ]);

        $result = $classifier->classificeer($judoka);

        $this->assertEquals('cadetten_d', $result['key']);
        $this->assertEquals('-44', $result['gewichtsklasse']);
    }

    // =========================================================================
    // EDGE CASES
    // =========================================================================

    #[Test]
    public function returns_niet_gecategoriseerd_for_too_old(): void
    {
        $classifier = new CategorieClassifier($this->createConfig());
        $toernooi = Toernooi::factory()->create();
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'geboortejaar' => (int) date('Y') - 20,
            'geslacht' => 'M',
        ]);

        $result = $classifier->classificeer($judoka);

        $this->assertNull($result['key']);
        $this->assertEquals('Onbekend', $result['label']);
        $this->assertEquals(99, $result['sortCategorie']);
    }

    #[Test]
    public function young_judoka_does_not_fall_through_to_older_category(): void
    {
        $classifier = new CategorieClassifier($this->createConfig());
        $toernooi = Toernooi::factory()->create();

        // 7-year-old male should be in minis, not cadetten_h
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'geboortejaar' => (int) date('Y') - 7,
            'geslacht' => 'M',
            'gewicht' => 25,
        ]);

        $result = $classifier->classificeer($judoka);

        $this->assertEquals('minis', $result['key']);
        $this->assertNotEquals('cadetten_h', $result['key']);
    }

    #[Test]
    public function heavy_judoka_gets_plus_category(): void
    {
        $classifier = new CategorieClassifier($this->createConfig());
        $toernooi = Toernooi::factory()->create();
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'geboortejaar' => (int) date('Y') - 14,
            'geslacht' => 'M',
            'gewicht' => 75,
        ]);

        $result = $classifier->classificeer($judoka);

        $this->assertEquals('+60', $result['gewichtsklasse']);
    }

    // =========================================================================
    // BAND FILTER
    // =========================================================================

    #[Test]
    public function band_filter_restricts_classification(): void
    {
        $config = [
            'beginners' => [
                'label' => 'Beginners',
                'max_leeftijd' => 12,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 3,
                'band_filter' => 'tm_oranje',
                'gewichten' => [],
            ],
            'gevorderden' => [
                'label' => 'Gevorderden',
                'max_leeftijd' => 12,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 3,
                'band_filter' => 'vanaf_groen',
                'gewichten' => [],
            ],
        ];

        $classifier = new CategorieClassifier($config);
        $toernooi = Toernooi::factory()->create();

        $beginner = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'geboortejaar' => (int) date('Y') - 10,
            'band' => 'geel',
        ]);

        $gevorderde = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'geboortejaar' => (int) date('Y') - 10,
            'band' => 'groen',
        ]);

        $this->assertEquals('beginners', $classifier->classificeer($beginner)['key']);
        $this->assertEquals('gevorderden', $classifier->classificeer($gevorderde)['key']);
    }

    // =========================================================================
    // OVERLAP DETECTION
    // =========================================================================

    #[Test]
    public function detects_overlap_same_age_same_gender(): void
    {
        $config = [
            'cat1' => ['label' => 'Cat 1', 'max_leeftijd' => 12, 'geslacht' => 'gemengd', 'gewichten' => []],
            'cat2' => ['label' => 'Cat 2', 'max_leeftijd' => 12, 'geslacht' => 'gemengd', 'gewichten' => []],
        ];

        $classifier = new CategorieClassifier($config);
        $overlaps = $classifier->detectOverlap();

        $this->assertNotEmpty($overlaps);
    }

    #[Test]
    public function no_overlap_different_genders(): void
    {
        $config = [
            'heren' => ['label' => 'Heren', 'max_leeftijd' => 15, 'geslacht' => 'M', 'gewichten' => []],
            'dames' => ['label' => 'Dames', 'max_leeftijd' => 15, 'geslacht' => 'V', 'gewichten' => []],
        ];

        $classifier = new CategorieClassifier($config);
        $overlaps = $classifier->detectOverlap();

        $this->assertEmpty($overlaps);
    }

    #[Test]
    public function no_overlap_different_ages(): void
    {
        $config = [
            'minis' => ['label' => 'Minis', 'max_leeftijd' => 7, 'geslacht' => 'gemengd', 'gewichten' => []],
            'pupillen' => ['label' => 'Pupillen', 'max_leeftijd' => 11, 'geslacht' => 'gemengd', 'gewichten' => []],
        ];

        $classifier = new CategorieClassifier($config);
        $overlaps = $classifier->detectOverlap();

        $this->assertEmpty($overlaps);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    #[Test]
    public function is_dynamisch_returns_correct_value(): void
    {
        $classifier = new CategorieClassifier($this->createConfig());

        $this->assertTrue($classifier->isDynamisch('minis'));
        $this->assertFalse($classifier->isDynamisch('cadetten_h'));
        $this->assertFalse($classifier->isDynamisch('nonexistent'));
    }

    #[Test]
    public function get_max_kg_verschil_returns_correct_value(): void
    {
        $classifier = new CategorieClassifier($this->createConfig());

        $this->assertEquals(3.0, $classifier->getMaxKgVerschil('minis'));
        $this->assertEquals(0.0, $classifier->getMaxKgVerschil('cadetten_h'));
        $this->assertEquals(0.0, $classifier->getMaxKgVerschil('nonexistent'));
    }
}
