<?php

namespace Tests\Unit\Services;

use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Toernooi;
use App\Services\FreemiumService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FreemiumServiceTest extends TestCase
{
    use RefreshDatabase;

    private FreemiumService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FreemiumService();
    }

    // =========================================================================
    // PLAN TYPE DETECTION
    // =========================================================================

    #[Test]
    public function free_tier_is_detected_correctly(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'free']);

        $this->assertTrue($this->service->isFreeTier($toernooi));
        $this->assertFalse($this->service->isPaidTier($toernooi));
        $this->assertFalse($this->service->isWimpelAbo($toernooi));
    }

    #[Test]
    public function paid_tier_is_detected_correctly(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'paid', 'paid_max_judokas' => 150]);

        $this->assertFalse($this->service->isFreeTier($toernooi));
        $this->assertTrue($this->service->isPaidTier($toernooi));
        $this->assertFalse($this->service->isWimpelAbo($toernooi));
    }

    #[Test]
    public function wimpel_abo_is_detected_correctly(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'wimpel_abo']);

        $this->assertFalse($this->service->isFreeTier($toernooi));
        $this->assertFalse($this->service->isPaidTier($toernooi));
        $this->assertTrue($this->service->isWimpelAbo($toernooi));
    }

    // =========================================================================
    // MAX JUDOKAS
    // =========================================================================

    #[Test]
    public function free_tier_has_50_judoka_limit(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'free']);

        $this->assertEquals(50, $this->service->getEffectiveMaxJudokas($toernooi));
    }

    #[Test]
    public function paid_tier_uses_paid_max_judokas(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'paid', 'paid_max_judokas' => 200]);

        $this->assertEquals(200, $this->service->getEffectiveMaxJudokas($toernooi));
    }

    #[Test]
    public function wimpel_abo_has_unlimited_judokas(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'wimpel_abo']);

        $this->assertEquals(PHP_INT_MAX, $this->service->getEffectiveMaxJudokas($toernooi));
    }

    // =========================================================================
    // CAN ADD JUDOKAS
    // =========================================================================

    #[Test]
    public function free_tier_blocks_at_50_judokas(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'free']);
        Judoka::factory()->count(50)->create(['toernooi_id' => $toernooi->id]);

        $this->assertFalse($this->service->canAddMoreJudokas($toernooi));
        $this->assertEquals(0, $this->service->getRemainingJudokaSlots($toernooi));
    }

    #[Test]
    public function wimpel_abo_always_allows_more_judokas(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'wimpel_abo']);
        Judoka::factory()->count(100)->create(['toernooi_id' => $toernooi->id]);

        $this->assertTrue($this->service->canAddMoreJudokas($toernooi));
    }

    // =========================================================================
    // PRINT & UPGRADE
    // =========================================================================

    #[Test]
    public function free_tier_cannot_use_print(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'free']);

        $this->assertFalse($this->service->canUsePrint($toernooi));
    }

    #[Test]
    public function paid_tier_can_use_print(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'paid']);

        $this->assertTrue($this->service->canUsePrint($toernooi));
    }

    #[Test]
    public function wimpel_abo_can_use_print(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'wimpel_abo']);

        $this->assertTrue($this->service->canUsePrint($toernooi));
    }

    #[Test]
    public function free_tier_needs_upgrade_at_50(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'free']);
        Judoka::factory()->count(50)->create(['toernooi_id' => $toernooi->id]);

        $this->assertTrue($this->service->needsUpgrade($toernooi));
    }

    #[Test]
    public function paid_tier_never_needs_upgrade(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'paid', 'paid_max_judokas' => 100]);

        $this->assertFalse($this->service->needsUpgrade($toernooi));
    }

    #[Test]
    public function wimpel_abo_never_needs_upgrade(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'wimpel_abo']);

        $this->assertFalse($this->service->needsUpgrade($toernooi));
    }

    // =========================================================================
    // GET STATUS
    // =========================================================================

    #[Test]
    public function get_status_includes_wimpel_abo_info(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'wimpel_abo']);

        $status = $this->service->getStatus($toernooi);

        $this->assertEquals('wimpel_abo', $status['plan_type']);
        $this->assertTrue($status['is_wimpel_abo']);
        $this->assertFalse($status['is_free_tier']);
        $this->assertFalse($status['is_paid_tier']);
        $this->assertTrue($status['can_use_print']);
        $this->assertFalse($status['needs_upgrade']);
    }
}
