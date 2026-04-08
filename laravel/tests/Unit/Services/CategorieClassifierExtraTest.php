<?php

namespace Tests\Unit\Services;

use App\Models\Judoka;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Services\CategorieClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class CategorieClassifierExtraTest extends TestCase
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

    private function callPrivate(string $method, object $instance, array $args): mixed
    {
        $ref = new ReflectionMethod(CategorieClassifier::class, $method);
        return $ref->invoke($instance, ...$args);
    }

    // =========================================================================
    // getConfigVoorPoule
    // =========================================================================

    #[Test]
    public function get_config_voor_poule_valid_key(): void
    {
        $classifier = new CategorieClassifier($this->createConfig());
        $toernooi = Toernooi::factory()->create();
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'categorie_key' => 'minis',
        ]);

        $config = $classifier->getConfigVoorPoule($poule);
        $this->assertNotNull($config);
        $this->assertEquals("Mini's", $config['label']);
    }

    #[Test]
    public function get_config_voor_poule_invalid_key(): void
    {
        $classifier = new CategorieClassifier($this->createConfig());
        $toernooi = Toernooi::factory()->create();
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'categorie_key' => 'nonexistent',
        ]);

        $this->assertNull($classifier->getConfigVoorPoule($poule));
    }

    #[Test]
    public function get_config_voor_poule_null_key(): void
    {
        $classifier = new CategorieClassifier($this->createConfig());
        $toernooi = Toernooi::factory()->create();
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'categorie_key' => null,
        ]);

        $this->assertNull($classifier->getConfigVoorPoule($poule));
    }

    // =========================================================================
    // bepaalGewichtsklasse (private)
    // =========================================================================

    #[Test]
    public function bepaal_gewichtsklasse_dynamic_returns_null(): void
    {
        $classifier = new CategorieClassifier($this->createConfig());
        $config = ['max_kg_verschil' => 3, 'gewichten' => []];

        $result = $this->callPrivate('bepaalGewichtsklasse', $classifier, [25.0, $config]);
        $this->assertNull($result);
    }

    #[Test]
    public function bepaal_gewichtsklasse_no_gewichten_returns_null(): void
    {
        $classifier = new CategorieClassifier($this->createConfig());
        $config = ['max_kg_verschil' => 0, 'gewichten' => []];

        $result = $this->callPrivate('bepaalGewichtsklasse', $classifier, [25.0, $config]);
        $this->assertNull($result);
    }

    #[Test]
    public function bepaal_gewichtsklasse_finds_correct_class(): void
    {
        $classifier = new CategorieClassifier($this->createConfig());
        $config = ['max_kg_verschil' => 0, 'gewichten' => ['-46', '-50', '-55', '+55']];

        $this->assertEquals('-46', $this->callPrivate('bepaalGewichtsklasse', $classifier, [44.0, $config]));
        $this->assertEquals('-50', $this->callPrivate('bepaalGewichtsklasse', $classifier, [48.0, $config]));
        $this->assertEquals('+55', $this->callPrivate('bepaalGewichtsklasse', $classifier, [60.0, $config]));
    }

    #[Test]
    public function bepaal_gewichtsklasse_respects_tolerance(): void
    {
        // Default tolerance = 0.5
        $classifier = new CategorieClassifier($this->createConfig(), 0.5);
        $config = ['max_kg_verschil' => 0, 'gewichten' => ['-46', '-50']];

        // 46.4 is within tolerance of -46 (46 + 0.5 = 46.5)
        $this->assertEquals('-46', $this->callPrivate('bepaalGewichtsklasse', $classifier, [46.4, $config]));

        // 46.6 is outside tolerance of -46 → goes to -50
        $this->assertEquals('-50', $this->callPrivate('bepaalGewichtsklasse', $classifier, [46.6, $config]));
    }

    // =========================================================================
    // voldoetAanBandFilter (private)
    // =========================================================================

    #[Test]
    public function voldoet_aan_band_filter_tm_oranje(): void
    {
        $classifier = new CategorieClassifier($this->createConfig());

        // BandHelper::getSortNiveau: wit=1, geel=2, oranje=3, groen=4, blauw=5, bruin=6, zwart=7
        // tm_oranje: bandNiveau <= maxNiveau(oranje=3)
        $this->assertTrue($this->callPrivate('voldoetAanBandFilter', $classifier, [1, 'tm_oranje'])); // wit
        $this->assertTrue($this->callPrivate('voldoetAanBandFilter', $classifier, [3, 'tm_oranje'])); // oranje
        $this->assertFalse($this->callPrivate('voldoetAanBandFilter', $classifier, [4, 'tm_oranje'])); // groen
    }

    #[Test]
    public function voldoet_aan_band_filter_vanaf_groen(): void
    {
        $classifier = new CategorieClassifier($this->createConfig());

        // vanaf_groen: bandNiveau >= minNiveau(groen=4)
        $this->assertFalse($this->callPrivate('voldoetAanBandFilter', $classifier, [3, 'vanaf_groen'])); // oranje
        $this->assertTrue($this->callPrivate('voldoetAanBandFilter', $classifier, [4, 'vanaf_groen'])); // groen
        $this->assertTrue($this->callPrivate('voldoetAanBandFilter', $classifier, [7, 'vanaf_groen'])); // zwart
    }

    #[Test]
    public function voldoet_aan_band_filter_unknown_allows_all(): void
    {
        $classifier = new CategorieClassifier($this->createConfig());
        $this->assertTrue($this->callPrivate('voldoetAanBandFilter', $classifier, [3, 'unknown_filter']));
    }

    // =========================================================================
    // geslachtMatcht (private)
    // =========================================================================

    #[Test]
    public function geslacht_matcht_gemengd_matches_all(): void
    {
        $classifier = new CategorieClassifier($this->createConfig());
        $config = ['geslacht' => 'gemengd', 'label' => ''];

        $this->assertTrue($this->callPrivate('geslachtMatcht', $classifier, ['M', $config, 'test']));
        $this->assertTrue($this->callPrivate('geslachtMatcht', $classifier, ['V', $config, 'test']));
    }

    #[Test]
    public function geslacht_matcht_m_only_matches_m(): void
    {
        $classifier = new CategorieClassifier($this->createConfig());
        $config = ['geslacht' => 'M', 'label' => ''];

        $this->assertTrue($this->callPrivate('geslachtMatcht', $classifier, ['M', $config, 'test']));
        $this->assertFalse($this->callPrivate('geslachtMatcht', $classifier, ['V', $config, 'test']));
    }

    #[Test]
    public function geslacht_matcht_legacy_meisjes(): void
    {
        $classifier = new CategorieClassifier($this->createConfig());
        $config = ['geslacht' => 'meisjes', 'label' => ''];

        $this->assertFalse($this->callPrivate('geslachtMatcht', $classifier, ['M', $config, 'test']));
        $this->assertTrue($this->callPrivate('geslachtMatcht', $classifier, ['V', $config, 'test']));
    }

    #[Test]
    public function geslacht_auto_detect_from_key_suffix(): void
    {
        $classifier = new CategorieClassifier($this->createConfig());
        // Null geslacht (not explicitly set) + key ending in _d → detect as V
        // Note: '' is not null, so ?? 'gemengd' only triggers on null
        $config = ['label' => '']; // geslacht key missing → null coalesced to 'gemengd'

        $this->assertTrue($this->callPrivate('geslachtMatcht', $classifier, ['V', $config, 'cadetten_d']));
        $this->assertFalse($this->callPrivate('geslachtMatcht', $classifier, ['M', $config, 'cadetten_d']));
    }

    #[Test]
    public function geslacht_auto_detect_from_label(): void
    {
        $classifier = new CategorieClassifier($this->createConfig());
        // geslacht key missing → coalesced to 'gemengd', then auto-detect from label
        $config = ['label' => 'Heren U15'];

        $this->assertTrue($this->callPrivate('geslachtMatcht', $classifier, ['M', $config, 'test']));
        $this->assertFalse($this->callPrivate('geslachtMatcht', $classifier, ['V', $config, 'test']));
    }

    // =========================================================================
    // detectOverlap — band filter overlap
    // =========================================================================

    #[Test]
    public function detect_overlap_different_gender_no_overlap(): void
    {
        $config = [
            'heren' => [
                'label' => 'Heren',
                'max_leeftijd' => 12,
                'geslacht' => 'M',
                'gewichten' => [],
            ],
            'dames' => [
                'label' => 'Dames',
                'max_leeftijd' => 12,
                'geslacht' => 'V',
                'gewichten' => [],
            ],
        ];

        $classifier = new CategorieClassifier($config);
        $overlaps = $classifier->detectOverlap();

        $this->assertEmpty($overlaps, 'Different genders should not overlap');
    }

    #[Test]
    public function detect_overlap_same_age_same_gender_overlap(): void
    {
        $config = [
            'groep_a' => [
                'label' => 'Groep A',
                'max_leeftijd' => 12,
                'geslacht' => 'gemengd',
                'gewichten' => [],
            ],
            'groep_b' => [
                'label' => 'Groep B',
                'max_leeftijd' => 12,
                'geslacht' => 'gemengd',
                'gewichten' => [],
            ],
        ];

        $classifier = new CategorieClassifier($config);
        $overlaps = $classifier->detectOverlap();

        $this->assertNotEmpty($overlaps, 'Same age + same gender should overlap');
    }

    #[Test]
    public function detect_overlap_skips_non_array_config(): void
    {
        $config = [
            'minis' => ['label' => 'Minis', 'max_leeftijd' => 7, 'geslacht' => 'gemengd', 'gewichten' => []],
            'poule_grootte_voorkeur' => [4, 3, 5], // non-array entry at top level
        ];

        $classifier = new CategorieClassifier($config);
        $overlaps = $classifier->detectOverlap();

        // Should not crash on non-array entries
        $this->assertIsArray($overlaps);
    }

    // =========================================================================
    // classificeer — tolerantie in weight class
    // =========================================================================

    #[Test]
    public function classificeer_uses_custom_tolerance(): void
    {
        $classifier = new CategorieClassifier($this->createConfig(), 1.0); // 1.0kg tolerance
        $toernooi = Toernooi::factory()->create();
        $judoka = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'geboortejaar' => (int) date('Y') - 14,
            'geslacht' => 'M',
            'gewicht' => 46.9, // With 1.0 tolerance: 46.9 <= 46+1.0 → fits -46
        ]);

        $result = $classifier->classificeer($judoka);
        $this->assertEquals('-46', $result['gewichtsklasse']);
    }

    // =========================================================================
    // getBandRange (private)
    // =========================================================================

    #[Test]
    public function get_band_range_tm(): void
    {
        $classifier = new CategorieClassifier($this->createConfig());
        $range = $this->callPrivate('getBandRange', $classifier, ['tm_oranje']);

        $this->assertArrayHasKey('min', $range);
        $this->assertArrayHasKey('max', $range);
        $this->assertEquals(99, $range['max']); // includes all beginners
    }

    #[Test]
    public function get_band_range_vanaf(): void
    {
        $classifier = new CategorieClassifier($this->createConfig());
        $range = $this->callPrivate('getBandRange', $classifier, ['vanaf_groen']);

        $this->assertEquals(0, $range['min']); // includes zwart
    }

    #[Test]
    public function get_band_range_unknown(): void
    {
        $classifier = new CategorieClassifier($this->createConfig());
        $range = $this->callPrivate('getBandRange', $classifier, ['unknown']);

        $this->assertEquals(0, $range['min']);
        $this->assertEquals(99, $range['max']);
    }

    // =========================================================================
    // nietGecategoriseerd (private)
    // =========================================================================

    #[Test]
    public function niet_gecategoriseerd_structure(): void
    {
        $classifier = new CategorieClassifier($this->createConfig());
        $result = $this->callPrivate('nietGecategoriseerd', $classifier, []);

        $this->assertNull($result['key']);
        $this->assertEquals('Onbekend', $result['label']);
        $this->assertEquals(99, $result['sortCategorie']);
        $this->assertNull($result['gewichtsklasse']);
        $this->assertFalse($result['isDynamisch']);
    }

    // =========================================================================
    // beschrijfOverlap (private)
    // =========================================================================

    #[Test]
    public function beschrijf_overlap_both_no_filter(): void
    {
        $classifier = new CategorieClassifier($this->createConfig());
        $result = $this->callPrivate('beschrijfOverlap', $classifier, [
            ['band_filter' => null],
            ['band_filter' => null],
        ]);

        $this->assertStringContainsString('Beide hebben alle banden', $result);
    }

    #[Test]
    public function beschrijf_overlap_one_filter(): void
    {
        $classifier = new CategorieClassifier($this->createConfig());
        $result = $this->callPrivate('beschrijfOverlap', $classifier, [
            ['band_filter' => null],
            ['band_filter' => 'tm_oranje'],
        ]);

        $this->assertStringContainsString('alle banden', $result);
    }

    #[Test]
    public function beschrijf_overlap_two_filters(): void
    {
        $classifier = new CategorieClassifier($this->createConfig());
        $result = $this->callPrivate('beschrijfOverlap', $classifier, [
            ['band_filter' => 'tm_groen'],
            ['band_filter' => 'vanaf_oranje'],
        ]);

        $this->assertStringContainsString('Bandfilters overlappen', $result);
    }
}
