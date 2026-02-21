<?php

namespace Tests\Unit\Models;

use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToernooiFreemiumTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // PLAN TYPE CHECKS
    // =========================================================================

    #[Test]
    public function is_free_tier_for_default_toernooi(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'free']);

        $this->assertTrue($toernooi->isFreeTier());
        $this->assertFalse($toernooi->isPaidTier());
        $this->assertFalse($toernooi->isWimpelAbo());
    }

    #[Test]
    public function is_paid_tier_detected(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'paid']);

        $this->assertTrue($toernooi->isPaidTier());
        $this->assertFalse($toernooi->isFreeTier());
        $this->assertFalse($toernooi->isWimpelAbo());
    }

    #[Test]
    public function is_wimpel_abo_detected(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'wimpel_abo']);

        $this->assertTrue($toernooi->isWimpelAbo());
        $this->assertFalse($toernooi->isFreeTier());
        $this->assertFalse($toernooi->isPaidTier());
    }

    // =========================================================================
    // EFFECTIVE MAX JUDOKAS
    // =========================================================================

    #[Test]
    public function free_tier_max_50_judokas(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'free']);

        $this->assertEquals(50, $toernooi->getEffectiveMaxJudokas());
    }

    #[Test]
    public function paid_tier_uses_paid_max(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'paid', 'paid_max_judokas' => 200]);

        $this->assertEquals(200, $toernooi->getEffectiveMaxJudokas());
    }

    #[Test]
    public function wimpel_abo_unlimited_judokas(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'wimpel_abo']);

        $this->assertEquals(PHP_INT_MAX, $toernooi->getEffectiveMaxJudokas());
    }

    // =========================================================================
    // CAN USE PRINT
    // =========================================================================

    #[Test]
    public function free_tier_cannot_print(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'free']);

        $this->assertFalse($toernooi->canUsePrint());
    }

    #[Test]
    public function paid_tier_can_print(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'paid']);

        $this->assertTrue($toernooi->canUsePrint());
    }

    #[Test]
    public function wimpel_abo_can_print(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'wimpel_abo']);

        $this->assertTrue($toernooi->canUsePrint());
    }

    // =========================================================================
    // NEEDS UPGRADE
    // =========================================================================

    #[Test]
    public function free_tier_needs_upgrade_at_limit(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'free']);
        Judoka::factory()->count(50)->create(['toernooi_id' => $toernooi->id]);

        $this->assertTrue($toernooi->needsUpgrade());
    }

    #[Test]
    public function free_tier_no_upgrade_under_limit(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'free']);
        Judoka::factory()->count(10)->create(['toernooi_id' => $toernooi->id]);

        $this->assertFalse($toernooi->needsUpgrade());
    }

    #[Test]
    public function paid_tier_never_needs_upgrade(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'paid', 'paid_max_judokas' => 100]);

        $this->assertFalse($toernooi->needsUpgrade());
    }

    #[Test]
    public function wimpel_abo_never_needs_upgrade(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'wimpel_abo']);

        $this->assertFalse($toernooi->needsUpgrade());
    }

    // =========================================================================
    // CAN ADD MORE JUDOKAS
    // =========================================================================

    #[Test]
    public function can_add_judokas_respects_limit(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'free']);
        Judoka::factory()->count(49)->create(['toernooi_id' => $toernooi->id]);

        $this->assertTrue($toernooi->canAddMoreJudokas(1));
        $this->assertFalse($toernooi->canAddMoreJudokas(2));
        $this->assertEquals(1, $toernooi->getRemainingJudokaSlots());
    }
}
