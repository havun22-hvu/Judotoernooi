<?php

namespace Tests\Unit\Models;

use App\Models\Organisator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrganisatorTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // WIMPEL ABO
    // =========================================================================

    #[Test]
    public function heeft_wimpel_abo_returns_true_when_active_and_future_end(): void
    {
        $org = Organisator::factory()->wimpelAbo()->create();

        $this->assertTrue($org->heeftWimpelAbo());
    }

    #[Test]
    public function heeft_wimpel_abo_returns_false_when_not_active(): void
    {
        $org = Organisator::factory()->create([
            'wimpel_abo_actief' => false,
            'wimpel_abo_einde' => now()->addYear(),
        ]);

        $this->assertFalse($org->heeftWimpelAbo());
    }

    #[Test]
    public function heeft_wimpel_abo_returns_false_when_expired(): void
    {
        $org = Organisator::factory()->create([
            'wimpel_abo_actief' => true,
            'wimpel_abo_start' => now()->subYear()->subDay(),
            'wimpel_abo_einde' => now()->subDay(),
        ]);

        $this->assertFalse($org->heeftWimpelAbo());
    }

    #[Test]
    public function heeft_wimpel_abo_returns_false_when_no_end_date(): void
    {
        $org = Organisator::factory()->create([
            'wimpel_abo_actief' => true,
            'wimpel_abo_einde' => null,
        ]);

        $this->assertFalse($org->heeftWimpelAbo());
    }

    #[Test]
    public function wimpel_abo_bijna_verlopen_true_within_30_days(): void
    {
        $org = Organisator::factory()->wimpelAboBijnaVerlopen()->create();

        $this->assertTrue($org->heeftWimpelAbo());
        $this->assertTrue($org->wimpelAboBijnaVerlopen());
    }

    #[Test]
    public function wimpel_abo_bijna_verlopen_false_when_plenty_of_time(): void
    {
        $org = Organisator::factory()->wimpelAbo()->create();

        $this->assertTrue($org->heeftWimpelAbo());
        $this->assertFalse($org->wimpelAboBijnaVerlopen());
    }

    #[Test]
    public function wimpel_abo_bijna_verlopen_false_when_expired(): void
    {
        $org = Organisator::factory()->create([
            'wimpel_abo_actief' => true,
            'wimpel_abo_einde' => now()->subDay(),
        ]);

        $this->assertFalse($org->wimpelAboBijnaVerlopen());
    }

    // =========================================================================
    // ROLE CHECKS
    // =========================================================================

    #[Test]
    public function is_sitebeheerder_returns_correct_value(): void
    {
        $admin = Organisator::factory()->sitebeheerder()->create();
        $regular = Organisator::factory()->create();

        $this->assertTrue($admin->isSitebeheerder());
        $this->assertFalse($regular->isSitebeheerder());
    }

    #[Test]
    public function is_test_returns_correct_value(): void
    {
        $test = Organisator::factory()->test()->create();
        $regular = Organisator::factory()->create();

        $this->assertTrue($test->isTest());
        $this->assertFalse($regular->isTest());
    }
}
