<?php

namespace Tests\Unit\Services;

use App\Models\Judoka;
use App\Models\Toernooi;
use App\Services\CategorieClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Mutation-killer tests for CategorieClassifier. The baseline Infection run
 * (MSI 47%) showed these behaviours were executed but never asserted: the
 * returned sortCategorie index, the isDynamisch/getMaxKgVerschil boundaries,
 * gender auto-detection from key/label, weight-class boundaries, and overlap
 * detection. Each test below pins an exact value/branch a surviving mutant flips.
 * See docs/3-DEVELOPMENT/MUTATION-TESTING.md.
 */
class CategorieClassifierMutationTest extends TestCase
{
    use RefreshDatabase;

    /** Fixed tournament year so age = YEAR - geboortejaar is deterministic. */
    private const YEAR = 2025;

    private function config(): array
    {
        return [
            'minis' => [
                'label' => "Mini's", 'max_leeftijd' => 7, 'geslacht' => 'gemengd',
                'max_kg_verschil' => 3, 'gewichten' => [],
            ],
            'pupillen' => [
                'label' => 'Pupillen', 'max_leeftijd' => 11, 'geslacht' => 'gemengd',
                'max_kg_verschil' => 4, 'gewichten' => [],
            ],
            'cadetten_h' => [
                'label' => 'Cadetten Heren', 'max_leeftijd' => 15, 'geslacht' => 'M',
                'max_kg_verschil' => 0, 'gewichten' => ['-46', '-50', '-55', '-60', '+60'],
            ],
            'cadetten_d' => [
                'label' => 'Cadetten Dames', 'max_leeftijd' => 15, 'geslacht' => 'V',
                'max_kg_verschil' => 0, 'gewichten' => ['-40', '-44', '-48', '-52', '+52'],
            ],
        ];
    }

    /** Create a judoka; `leeftijd` is translated to geboortejaar (no such column). */
    private function judoka(array $attrs): Judoka
    {
        $toernooi = Toernooi::factory()->create();
        if (isset($attrs['leeftijd'])) {
            $attrs['geboortejaar'] = self::YEAR - $attrs['leeftijd'];
            unset($attrs['leeftijd']);
        }

        return Judoka::factory()->create(array_merge(['toernooi_id' => $toernooi->id], $attrs));
    }

    // --- sortCategorie index (kills the categorieSortIndex++ mutants, lines 72/78/85) ---

    #[Test]
    public function sort_categorie_is_the_index_after_skipped_categories(): void
    {
        $classifier = new CategorieClassifier($this->config());

        // A 14yo boy matches cadetten_h, which is index 2 (after minis=0, pupillen=1).
        $boy = $this->judoka(['leeftijd' => 14, 'geslacht' => 'M', 'gewicht' => 48, 'band' => 'wit']);
        $this->assertSame(2, $classifier->classificeer($boy, self::YEAR)['sortCategorie']);

        // A 14yo girl skips cadetten_h too → cadetten_d, index 3.
        $girl = $this->judoka(['leeftijd' => 14, 'geslacht' => 'V', 'gewicht' => 42, 'band' => 'wit']);
        $this->assertSame(3, $classifier->classificeer($girl, self::YEAR)['sortCategorie']);
    }

    #[Test]
    public function uncategorised_returns_sentinel_sort_99(): void
    {
        $classifier = new CategorieClassifier($this->config());
        // Too old for every category → not categorised.
        $old = $this->judoka(['leeftijd' => 40, 'geslacht' => 'M', 'gewicht' => 80, 'band' => 'zwart']);

        $result = $classifier->classificeer($old, self::YEAR);
        $this->assertNull($result['key']);
        $this->assertSame(99, $result['sortCategorie']);
    }

    // --- isDynamisch / getMaxKgVerschil boundaries (kills lines 132/146) ---

    #[Test]
    public function is_dynamisch_only_when_max_kg_verschil_positive(): void
    {
        $classifier = new CategorieClassifier($this->config());
        $this->assertTrue($classifier->isDynamisch('minis'));       // max_kg_verschil 3
        $this->assertFalse($classifier->isDynamisch('cadetten_h')); // max_kg_verschil 0
        $this->assertFalse($classifier->isDynamisch('bestaat_niet'));
    }

    #[Test]
    public function get_max_kg_verschil_returns_exact_float(): void
    {
        $classifier = new CategorieClassifier($this->config());
        $this->assertSame(3.0, $classifier->getMaxKgVerschil('minis'));
        $this->assertSame(0.0, $classifier->getMaxKgVerschil('cadetten_h'));
        $this->assertSame(0.0, $classifier->getMaxKgVerschil('bestaat_niet'));
    }

    // --- gender auto-detect from key/label when geslacht not explicitly set (lines 169-171) ---

    #[Test]
    public function gender_is_auto_detected_from_key_suffix_when_not_explicit(): void
    {
        // No 'geslacht' key → auto-detect from label 'Heren' / key '_h'.
        $config = [
            'jeugd_h' => [
                'label' => 'Jeugd Heren', 'max_leeftijd' => 11,
                'max_kg_verschil' => 0, 'gewichten' => ['-30', '+30'],
            ],
        ];
        $classifier = new CategorieClassifier($config);

        $boy = $this->judoka(['leeftijd' => 10, 'geslacht' => 'M', 'gewicht' => 28, 'band' => 'wit']);
        $this->assertSame('jeugd_h', $classifier->classificeer($boy, self::YEAR)['key']);

        // A girl does NOT match the heren-category → uncategorised.
        $girl = $this->judoka(['leeftijd' => 10, 'geslacht' => 'V', 'gewicht' => 28, 'band' => 'wit']);
        $this->assertNull($classifier->classificeer($girl, self::YEAR)['key']);
    }

    // --- weight class boundaries (kills the bepaalGewichtsklasse comparison, line 236) ---

    #[Test]
    public function weight_class_picks_the_first_fitting_minus_class(): void
    {
        $classifier = new CategorieClassifier($this->config());

        // 45kg fits -46; 47kg falls through to -50; 100kg hits the +60 catch-all.
        $a = $this->judoka(['leeftijd' => 14, 'geslacht' => 'M', 'gewicht' => 45, 'band' => 'wit']);
        $this->assertSame('-46', $classifier->classificeer($a, self::YEAR)['gewichtsklasse']);

        $b = $this->judoka(['leeftijd' => 14, 'geslacht' => 'M', 'gewicht' => 47, 'band' => 'wit']);
        $this->assertSame('-50', $classifier->classificeer($b, self::YEAR)['gewichtsklasse']);

        $c = $this->judoka(['leeftijd' => 14, 'geslacht' => 'M', 'gewicht' => 100, 'band' => 'wit']);
        $this->assertSame('+60', $classifier->classificeer($c, self::YEAR)['gewichtsklasse']);
    }

    #[Test]
    public function dynamic_category_has_no_fixed_weight_class(): void
    {
        $classifier = new CategorieClassifier($this->config());
        $mini = $this->judoka(['leeftijd' => 6, 'geslacht' => 'M', 'gewicht' => 20, 'band' => 'wit']);

        $result = $classifier->classificeer($mini, self::YEAR);
        $this->assertSame('minis', $result['key']);
        $this->assertTrue($result['isDynamisch']);
        $this->assertNull($result['gewichtsklasse']);
    }

    // --- overlap detection (kills the detectOverlap loop/branch mutants, lines 276-311) ---

    #[Test]
    public function detect_overlap_flags_two_mixed_same_age_categories(): void
    {
        $config = [
            'a' => ['label' => 'A', 'max_leeftijd' => 11, 'geslacht' => 'gemengd', 'max_kg_verschil' => 4, 'gewichten' => []],
            'b' => ['label' => 'B', 'max_leeftijd' => 11, 'geslacht' => 'gemengd', 'max_kg_verschil' => 4, 'gewichten' => []],
        ];
        $overlaps = (new CategorieClassifier($config))->detectOverlap();

        $this->assertCount(1, $overlaps);
        $this->assertSame('A', $overlaps[0]['cat1']);
        $this->assertSame('B', $overlaps[0]['cat2']);
    }

    #[Test]
    public function detect_overlap_is_empty_when_gender_or_age_differs(): void
    {
        // config(): cadetten_h (M) vs cadetten_d (V) same age but different gender;
        // minis vs pupillen different age → no overlaps at all.
        $this->assertSame([], (new CategorieClassifier($this->config()))->detectOverlap());
    }
}
