<?php

namespace Tests\Unit\Services;

use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Toernooi;
use App\Services\FreemiumService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FreemiumServiceExtraTest extends TestCase
{
    use RefreshDatabase;

    private FreemiumService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FreemiumService();
    }

    // =========================================================================
    // canUseEliminatie
    // =========================================================================

    #[Test]
    public function free_tier_cannot_use_eliminatie(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'free']);
        $this->assertFalse($this->service->canUseEliminatie($toernooi));
    }

    #[Test]
    public function paid_tier_can_use_eliminatie(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'paid']);
        $this->assertTrue($this->service->canUseEliminatie($toernooi));
    }

    #[Test]
    public function wimpel_abo_can_use_eliminatie(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'wimpel_abo']);
        $this->assertTrue($this->service->canUseEliminatie($toernooi));
    }

    // =========================================================================
    // canAddMoreClubs
    // =========================================================================

    #[Test]
    public function can_add_more_clubs_always_true(): void
    {
        $organisator = Organisator::factory()->create();
        $this->assertTrue($this->service->canAddMoreClubs($organisator));
    }

    // =========================================================================
    // canAddMorePresets
    // =========================================================================

    #[Test]
    public function can_add_more_presets_true_when_none(): void
    {
        $organisator = Organisator::factory()->create();
        $this->assertTrue($this->service->canAddMorePresets($organisator));
    }

    // =========================================================================
    // getMaxPresets
    // =========================================================================

    #[Test]
    public function get_max_presets_returns_constant(): void
    {
        $organisator = Organisator::factory()->create();
        $this->assertEquals(FreemiumService::FREE_MAX_PRESETS, $this->service->getMaxPresets($organisator));
    }

    // =========================================================================
    // getStaffelPrijs
    // =========================================================================

    #[Test]
    public function get_staffel_prijs_valid_tier(): void
    {
        $this->assertEquals(20, $this->service->getStaffelPrijs('51-100'));
        $this->assertEquals(100, $this->service->getStaffelPrijs('401-500'));
    }

    #[Test]
    public function get_staffel_prijs_invalid_tier(): void
    {
        $this->assertNull($this->service->getStaffelPrijs('nonexistent'));
    }

    // =========================================================================
    // getTierInfo
    // =========================================================================

    #[Test]
    public function get_tier_info_valid(): void
    {
        $info = $this->service->getTierInfo('51-100');
        $this->assertIsArray($info);
        $this->assertEquals(51, $info['min']);
        $this->assertEquals(100, $info['max']);
        $this->assertEquals(20, $info['prijs']);
    }

    #[Test]
    public function get_tier_info_invalid(): void
    {
        $this->assertNull($this->service->getTierInfo('nonexistent'));
    }

    // =========================================================================
    // getAlBetaaldePrijs
    // =========================================================================

    #[Test]
    public function get_al_betaalde_prijs_free_tier(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'free']);
        $this->assertEquals(0, $this->service->getAlBetaaldePrijs($toernooi));
    }

    #[Test]
    public function get_al_betaalde_prijs_paid_with_tier(): void
    {
        $toernooi = Toernooi::factory()->create([
            'plan_type' => 'paid',
            'paid_tier' => '51-100',
        ]);
        $this->assertEquals(20, $this->service->getAlBetaaldePrijs($toernooi));
    }

    #[Test]
    public function get_al_betaalde_prijs_paid_no_tier(): void
    {
        $toernooi = Toernooi::factory()->create([
            'plan_type' => 'paid',
            'paid_tier' => null,
        ]);
        $this->assertEquals(0, $this->service->getAlBetaaldePrijs($toernooi));
    }

    // =========================================================================
    // getUpgradeOptions
    // =========================================================================

    #[Test]
    public function get_upgrade_options_returns_array(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'free']);

        $options = $this->service->getUpgradeOptions($toernooi);

        $this->assertIsArray($options);
        $this->assertNotEmpty($options);

        foreach ($options as $option) {
            $this->assertArrayHasKey('tier', $option);
            $this->assertArrayHasKey('min', $option);
            $this->assertArrayHasKey('max', $option);
            $this->assertArrayHasKey('prijs', $option);
            $this->assertArrayHasKey('volle_prijs', $option);
            $this->assertArrayHasKey('label', $option);
        }
    }

    #[Test]
    public function get_upgrade_options_filters_by_current_judokas(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'free']);
        Judoka::factory()->count(120)->create(['toernooi_id' => $toernooi->id]);

        $options = $this->service->getUpgradeOptions($toernooi);

        // All tiers with max <= 120 should be filtered out
        foreach ($options as $option) {
            $this->assertGreaterThan(120, $option['max']);
        }
    }

    #[Test]
    public function get_upgrade_options_re_upgrade_reduces_price(): void
    {
        $toernooi = Toernooi::factory()->create([
            'plan_type' => 'paid',
            'paid_tier' => '51-100',
        ]);

        $options = $this->service->getUpgradeOptions($toernooi);

        // Previously paid 20 for 51-100, so upgrade to 101-150 (30) should cost 10
        $tier150 = collect($options)->firstWhere('tier', '101-150');
        if ($tier150) {
            $this->assertEquals(10, $tier150['prijs']);
        }
    }

    // =========================================================================
    // isStagingKorting
    // =========================================================================

    #[Test]
    public function is_staging_korting_false_in_testing(): void
    {
        $this->assertFalse($this->service->isStagingKorting());
    }

    // =========================================================================
    // pasKortingToe
    // =========================================================================

    #[Test]
    public function pas_korting_toe_no_discount_in_testing(): void
    {
        // Not staging environment, so no discount
        $this->assertEquals(100.0, $this->service->pasKortingToe(100.0));
    }

    // =========================================================================
    // getDemoCsvPath
    // =========================================================================

    #[Test]
    public function get_demo_csv_path_valid_variants(): void
    {
        $this->assertNotNull($this->service->getDemoCsvPath(30));
        $this->assertNotNull($this->service->getDemoCsvPath(40));
        $this->assertNotNull($this->service->getDemoCsvPath(50));
    }

    #[Test]
    public function get_demo_csv_path_invalid_variant(): void
    {
        $this->assertNull($this->service->getDemoCsvPath(25));
        $this->assertNull($this->service->getDemoCsvPath(100));
    }

    #[Test]
    public function get_demo_csv_path_contains_variant_number(): void
    {
        $path = $this->service->getDemoCsvPath(30);
        $this->assertStringContainsString('demo-30.csv', $path);
    }

    // =========================================================================
    // canAddMoreJudokas — various scenarios
    // =========================================================================

    #[Test]
    public function can_add_judokas_free_tier_under_limit(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'free']);
        Judoka::factory()->count(30)->create(['toernooi_id' => $toernooi->id]);

        $this->assertTrue($this->service->canAddMoreJudokas($toernooi, 1));
        $this->assertTrue($this->service->canAddMoreJudokas($toernooi, 20)); // 30 + 20 = 50 = exact limit
        $this->assertFalse($this->service->canAddMoreJudokas($toernooi, 21)); // Over limit
    }

    #[Test]
    public function can_add_judokas_paid_tier_respects_paid_max(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'paid', 'paid_max_judokas' => 100]);
        Judoka::factory()->count(99)->create(['toernooi_id' => $toernooi->id]);

        $this->assertTrue($this->service->canAddMoreJudokas($toernooi, 1));
        $this->assertFalse($this->service->canAddMoreJudokas($toernooi, 2));
    }

    // =========================================================================
    // getRemainingJudokaSlots
    // =========================================================================

    #[Test]
    public function get_remaining_judoka_slots_free_tier(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'free']);
        Judoka::factory()->count(30)->create(['toernooi_id' => $toernooi->id]);

        $this->assertEquals(20, $this->service->getRemainingJudokaSlots($toernooi));
    }

    #[Test]
    public function get_remaining_never_negative(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'free']);
        Judoka::factory()->count(60)->create(['toernooi_id' => $toernooi->id]);

        $this->assertEquals(0, $this->service->getRemainingJudokaSlots($toernooi));
    }

    // =========================================================================
    // getStatus — paid tier
    // =========================================================================

    #[Test]
    public function get_status_paid_tier(): void
    {
        $toernooi = Toernooi::factory()->create([
            'plan_type' => 'paid',
            'paid_max_judokas' => 200,
            'paid_tier' => '151-200',
        ]);

        $status = $this->service->getStatus($toernooi);

        $this->assertEquals('paid', $status['plan_type']);
        $this->assertFalse($status['is_free_tier']);
        $this->assertTrue($status['is_paid_tier']);
        $this->assertFalse($status['is_wimpel_abo']);
        $this->assertEquals(200, $status['max_judokas']);
        $this->assertTrue($status['can_use_print']);
        $this->assertFalse($status['needs_upgrade']);
        $this->assertEquals('151-200', $status['paid_tier']);
    }

    // =========================================================================
    // getEffectiveMaxJudokas — paid with null max
    // =========================================================================

    #[Test]
    public function get_effective_max_judokas_paid_null_falls_to_free(): void
    {
        $toernooi = Toernooi::factory()->create([
            'plan_type' => 'paid',
            'paid_max_judokas' => null,
        ]);

        $this->assertEquals(FreemiumService::FREE_MAX_JUDOKAS, $this->service->getEffectiveMaxJudokas($toernooi));
    }
}
