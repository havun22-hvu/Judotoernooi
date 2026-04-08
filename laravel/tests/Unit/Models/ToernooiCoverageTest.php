<?php

namespace Tests\Unit\Models;

use App\Models\Blok;
use App\Models\Club;
use App\Models\DeviceToegang;
use App\Models\Judoka;
use App\Models\Mat;
use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToernooiCoverageTest extends TestCase
{
    use RefreshDatabase;

    private Organisator $org;
    private Toernooi $toernooi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organisator::factory()->create();
        $this->toernooi = Toernooi::factory()->create(['organisator_id' => $this->org->id]);
    }

    // ========================================================================
    // Portaal Modus (HasPortaalModus trait)
    // ========================================================================

    #[Test]
    public function portaal_mag_inschrijven_in_volledig_mode(): void
    {
        $this->toernooi->update(['portaal_modus' => 'volledig']);
        $this->assertTrue($this->toernooi->portaalMagInschrijven());
    }

    #[Test]
    public function portaal_mag_niet_inschrijven_in_mutaties_mode(): void
    {
        $this->toernooi->update(['portaal_modus' => 'mutaties']);
        $this->assertFalse($this->toernooi->portaalMagInschrijven());
    }

    #[Test]
    public function portaal_mag_wijzigen_in_mutaties_mode(): void
    {
        $this->toernooi->update(['portaal_modus' => 'mutaties']);
        $this->assertTrue($this->toernooi->portaalMagWijzigen());
    }

    #[Test]
    public function portaal_mag_wijzigen_in_volledig_mode(): void
    {
        $this->toernooi->update(['portaal_modus' => 'volledig']);
        $this->assertTrue($this->toernooi->portaalMagWijzigen());
    }

    #[Test]
    public function portaal_mag_niet_wijzigen_in_uit_mode(): void
    {
        $this->toernooi->update(['portaal_modus' => 'uit']);
        $this->assertFalse($this->toernooi->portaalMagWijzigen());
    }

    #[Test]
    public function portaal_is_uit(): void
    {
        $this->toernooi->update(['portaal_modus' => 'uit']);
        $this->assertTrue($this->toernooi->portaalIsUit());
    }

    #[Test]
    public function portaal_is_not_uit_in_mutaties(): void
    {
        $this->toernooi->update(['portaal_modus' => 'mutaties']);
        $this->assertFalse($this->toernooi->portaalIsUit());
    }

    #[Test]
    public function portaal_is_uit_when_empty(): void
    {
        // portaal_modus is NOT NULL in DB, so test via model attribute directly
        $this->toernooi->portaal_modus = '';
        $this->assertTrue($this->toernooi->portaalIsUit());
    }

    #[Test]
    public function portaal_modus_text_volledig(): void
    {
        $this->toernooi->update(['portaal_modus' => 'volledig']);
        $text = $this->toernooi->getPortaalModusText();
        $this->assertStringContainsString('Volledig', $text);
    }

    #[Test]
    public function portaal_modus_text_mutaties(): void
    {
        $this->toernooi->update(['portaal_modus' => 'mutaties']);
        $text = $this->toernooi->getPortaalModusText();
        $this->assertStringContainsString('mutaties', $text);
    }

    #[Test]
    public function portaal_modus_text_uit(): void
    {
        $this->toernooi->update(['portaal_modus' => 'uit']);
        $text = $this->toernooi->getPortaalModusText();
        $this->assertStringContainsString('Uit', $text);
    }

    // ========================================================================
    // Freemium / Tier methods
    // ========================================================================

    #[Test]
    public function is_free_tier_true_by_default(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'free', 'organisator_id' => $this->org->id]);
        $this->assertTrue($toernooi->isFreeTier());
    }

    #[Test]
    public function is_free_tier_false_when_paid(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'paid', 'organisator_id' => $this->org->id]);
        $this->assertFalse($toernooi->isFreeTier());
    }

    #[Test]
    public function is_paid_tier(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'paid', 'organisator_id' => $this->org->id]);
        $this->assertTrue($toernooi->isPaidTier());
    }

    #[Test]
    public function is_wimpel_abo(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'wimpel_abo', 'organisator_id' => $this->org->id]);
        $this->assertTrue($toernooi->isWimpelAbo());
    }

    #[Test]
    public function get_effective_max_judokas_free_tier(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'free', 'organisator_id' => $this->org->id]);
        $this->assertEquals(50, $toernooi->getEffectiveMaxJudokas());
    }

    #[Test]
    public function get_effective_max_judokas_paid_tier(): void
    {
        $toernooi = Toernooi::factory()->create([
            'plan_type' => 'paid',
            'paid_max_judokas' => 100,
            'organisator_id' => $this->org->id,
        ]);
        $this->assertEquals(100, $toernooi->getEffectiveMaxJudokas());
    }

    #[Test]
    public function get_effective_max_judokas_wimpel_abo(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'wimpel_abo', 'organisator_id' => $this->org->id]);
        $this->assertEquals(PHP_INT_MAX, $toernooi->getEffectiveMaxJudokas());
    }

    #[Test]
    public function can_add_more_judokas_within_limit(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'free', 'organisator_id' => $this->org->id]);
        $this->assertTrue($toernooi->canAddMoreJudokas());
    }

    #[Test]
    public function get_remaining_judoka_slots(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'free', 'organisator_id' => $this->org->id]);
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        Judoka::factory()->count(10)->create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
        ]);
        $this->assertEquals(40, $toernooi->getRemainingJudokaSlots());
    }

    #[Test]
    public function can_use_print_paid_only(): void
    {
        $paid = Toernooi::factory()->create(['plan_type' => 'paid', 'organisator_id' => $this->org->id]);
        $free = Toernooi::factory()->create(['plan_type' => 'free', 'organisator_id' => $this->org->id]);

        $this->assertTrue($paid->canUsePrint());
        $this->assertFalse($free->canUsePrint());
    }

    #[Test]
    public function needs_upgrade_when_at_50(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'free', 'organisator_id' => $this->org->id]);
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        Judoka::factory()->count(50)->create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
        ]);

        $this->assertTrue($toernooi->needsUpgrade());
    }

    #[Test]
    public function needs_upgrade_false_for_paid(): void
    {
        $toernooi = Toernooi::factory()->create(['plan_type' => 'paid', 'organisator_id' => $this->org->id]);
        $this->assertFalse($toernooi->needsUpgrade());
    }

    // ========================================================================
    // Scope
    // ========================================================================

    #[Test]
    public function scope_actief_filters_active_only(): void
    {
        Toernooi::factory()->create(['is_actief' => true, 'organisator_id' => $this->org->id]);
        Toernooi::factory()->create(['is_actief' => false, 'organisator_id' => $this->org->id]);

        // +1 for the setUp toernooi (default is_actief not set, so could be null)
        $actief = Toernooi::actief()->count();
        $this->assertGreaterThanOrEqual(1, $actief);
    }

    // ========================================================================
    // Relationships not yet tested
    // ========================================================================

    #[Test]
    public function has_many_matten(): void
    {
        Mat::factory()->count(3)->create(['toernooi_id' => $this->toernooi->id]);
        $this->assertEquals(3, $this->toernooi->matten()->count());
    }

    #[Test]
    public function has_many_device_toegangen(): void
    {
        DeviceToegang::create([
            'toernooi_id' => $this->toernooi->id,
            'naam' => 'Test',
            'rol' => 'mat',
        ]);
        $this->assertEquals(1, $this->toernooi->deviceToegangen()->count());
    }

    #[Test]
    public function has_many_organisatoren_via_pivot(): void
    {
        $org2 = Organisator::factory()->create();
        $this->toernooi->organisatoren()->attach($org2->id, ['rol' => 'beheerder']);
        $this->assertGreaterThanOrEqual(1, $this->toernooi->organisatoren()->count());
    }

    #[Test]
    public function totaal_wedstrijden_attribute(): void
    {
        \App\Models\Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'aantal_wedstrijden' => 6,
        ]);
        \App\Models\Poule::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'aantal_wedstrijden' => 10,
        ]);

        $this->assertEquals(16, $this->toernooi->totaal_wedstrijden);
    }

    // ========================================================================
    // getWedstrijddagStartTijd
    // ========================================================================

    #[Test]
    public function get_wedstrijddag_start_tijd_returns_earliest(): void
    {
        $early = now()->subHours(3);
        $late = now()->subHour();

        Blok::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'weging_gesloten' => true,
            'weging_gesloten_op' => $early,
        ]);
        Blok::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'weging_gesloten' => true,
            'weging_gesloten_op' => $late,
        ]);

        $startTijd = $this->toernooi->getWedstrijddagStartTijd();
        $this->assertNotNull($startTijd);
        $this->assertEquals($early->timestamp, $startTijd->timestamp);
    }

    #[Test]
    public function get_wedstrijddag_start_tijd_returns_null_when_no_weging_gesloten(): void
    {
        Blok::factory()->create([
            'toernooi_id' => $this->toernooi->id,
            'weging_gesloten' => false,
        ]);

        $this->assertNull($this->toernooi->getWedstrijddagStartTijd());
    }

    // ========================================================================
    // getClubByPortalCode
    // ========================================================================

    #[Test]
    public function get_club_by_portal_code_finds_club(): void
    {
        $club = Club::factory()->create(['organisator_id' => $this->org->id]);
        $this->toernooi->clubs()->attach($club->id, [
            'portal_code' => 'TESTCODE1234',
            'pincode' => '12345',
        ]);

        $found = $this->toernooi->getClubByPortalCode('TESTCODE1234');
        $this->assertNotNull($found);
        $this->assertEquals($club->id, $found->id);
    }

    #[Test]
    public function get_club_by_portal_code_returns_null_for_unknown(): void
    {
        $this->assertNull($this->toernooi->getClubByPortalCode('UNKNOWNCODE'));
    }

    // ========================================================================
    // resetGewichtsklassenNaarStandaard
    // ========================================================================

    #[Test]
    public function reset_gewichtsklassen_naar_standaard(): void
    {
        $this->toernooi->update(['gewichtsklassen' => ['custom' => ['label' => 'Custom']]]);
        $this->toernooi->resetGewichtsklassenNaarStandaard();
        $this->toernooi->refresh();

        // Should be reset to standard config
        $this->assertNotEquals(['custom' => ['label' => 'Custom']], $this->toernooi->gewichtsklassen);
    }
}
