<?php

namespace Tests\Unit\Models;

use App\Models\Blok;
use App\Models\Club;
use App\Models\Judoka;
use App\Models\Mat;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MatAndConcernsCoverageTest extends TestCase
{
    use RefreshDatabase;

    private function maakSetup(): array
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);
        $mat = Mat::factory()->create(['toernooi_id' => $toernooi->id]);
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'mat_id' => $mat->id,
            'blok_id' => $blok->id,
        ]);

        return compact('org', 'toernooi', 'mat', 'blok', 'poule');
    }

    private function maakWedstrijd(Poule $poule, array $overrides = []): Wedstrijd
    {
        return Wedstrijd::factory()->create(array_merge(
            ['poule_id' => $poule->id],
            $overrides,
        ));
    }

    private function maakGespeeldeWedstrijd(Poule $poule): Wedstrijd
    {
        $club = Club::factory()->create();
        $judokaWit = Judoka::factory()->create([
            'toernooi_id' => $poule->toernooi_id,
            'club_id' => $club->id,
        ]);
        $judokaBlauw = Judoka::factory()->create([
            'toernooi_id' => $poule->toernooi_id,
            'club_id' => $club->id,
        ]);

        return Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $judokaWit->id,
            'judoka_blauw_id' => $judokaBlauw->id,
            'is_gespeeld' => true,
            'winnaar_id' => $judokaWit->id,
            'score_wit' => 2,
            'score_blauw' => 0,
            'uitslag_type' => 'ippon',
        ]);
    }

    // ========================================================================
    // Mat: wedstrijd relations (lines 49-58)
    // ========================================================================

    #[Test]
    public function actieve_wedstrijd_relation_returns_wedstrijd(): void
    {
        $s = $this->maakSetup();
        $wedstrijd = $this->maakWedstrijd($s['poule']);

        $s['mat']->update(['actieve_wedstrijd_id' => $wedstrijd->id]);
        $s['mat']->refresh();

        $this->assertInstanceOf(Wedstrijd::class, $s['mat']->actieveWedstrijd);
        $this->assertEquals($wedstrijd->id, $s['mat']->actieveWedstrijd->id);
    }

    #[Test]
    public function volgende_wedstrijd_relation_returns_wedstrijd(): void
    {
        $s = $this->maakSetup();
        $wedstrijd = $this->maakWedstrijd($s['poule']);

        $s['mat']->update(['volgende_wedstrijd_id' => $wedstrijd->id]);
        $s['mat']->refresh();

        $this->assertInstanceOf(Wedstrijd::class, $s['mat']->volgendeWedstrijd);
        $this->assertEquals($wedstrijd->id, $s['mat']->volgendeWedstrijd->id);
    }

    #[Test]
    public function gereedmaken_wedstrijd_relation_returns_wedstrijd(): void
    {
        $s = $this->maakSetup();
        $wedstrijd = $this->maakWedstrijd($s['poule']);

        $s['mat']->update(['gereedmaken_wedstrijd_id' => $wedstrijd->id]);
        $s['mat']->refresh();

        $this->assertInstanceOf(Wedstrijd::class, $s['mat']->gereedmakenWedstrijd);
        $this->assertEquals($wedstrijd->id, $s['mat']->gereedmakenWedstrijd->id);
    }

    #[Test]
    public function wedstrijd_relations_return_null_when_not_set(): void
    {
        $s = $this->maakSetup();

        $this->assertNull($s['mat']->actieveWedstrijd);
        $this->assertNull($s['mat']->volgendeWedstrijd);
        $this->assertNull($s['mat']->gereedmakenWedstrijd);
    }

    // ========================================================================
    // Mat: resetWedstrijdSelectieVoorPoule (lines 64-111)
    // ========================================================================

    #[Test]
    public function reset_selectie_resets_groen_and_shifts_geel_to_groen(): void
    {
        $s = $this->maakSetup();
        $w1 = $this->maakWedstrijd($s['poule']); // groen - same poule
        $w2 = $this->maakWedstrijd($s['poule']); // geel
        $w3 = $this->maakWedstrijd($s['poule']); // blauw

        $s['mat']->update([
            'actieve_wedstrijd_id' => $w1->id,
            'volgende_wedstrijd_id' => $w2->id,
            'gereedmaken_wedstrijd_id' => $w3->id,
        ]);
        $s['mat']->refresh();

        $s['mat']->resetWedstrijdSelectieVoorPoule($s['poule']->id);
        $s['mat']->refresh();

        // Groen reset: geel -> groen, blauw -> geel, blauw -> null
        $this->assertEquals($w2->id, $s['mat']->actieve_wedstrijd_id);
        $this->assertEquals($w3->id, $s['mat']->volgende_wedstrijd_id);
        $this->assertNull($s['mat']->gereedmaken_wedstrijd_id);
    }

    #[Test]
    public function reset_selectie_resets_geel_and_shifts_blauw_to_geel(): void
    {
        $s = $this->maakSetup();
        // Create a second poule for groen (different poule)
        $otherPoule = Poule::factory()->create([
            'toernooi_id' => $s['toernooi']->id,
            'mat_id' => $s['mat']->id,
        ]);
        $w1 = $this->maakWedstrijd($otherPoule); // groen - different poule
        $w2 = $this->maakWedstrijd($s['poule']);  // geel - target poule
        $w3 = $this->maakWedstrijd($s['poule']);  // blauw

        $s['mat']->update([
            'actieve_wedstrijd_id' => $w1->id,
            'volgende_wedstrijd_id' => $w2->id,
            'gereedmaken_wedstrijd_id' => $w3->id,
        ]);
        $s['mat']->refresh();

        $s['mat']->resetWedstrijdSelectieVoorPoule($s['poule']->id);
        $s['mat']->refresh();

        // Groen stays, geel reset: blauw -> geel, blauw -> null
        $this->assertEquals($w1->id, $s['mat']->actieve_wedstrijd_id);
        $this->assertEquals($w3->id, $s['mat']->volgende_wedstrijd_id);
        $this->assertNull($s['mat']->gereedmaken_wedstrijd_id);
    }

    #[Test]
    public function reset_selectie_resets_only_blauw(): void
    {
        $s = $this->maakSetup();
        $otherPoule = Poule::factory()->create([
            'toernooi_id' => $s['toernooi']->id,
            'mat_id' => $s['mat']->id,
        ]);
        $w1 = $this->maakWedstrijd($otherPoule); // groen - different
        $w2 = $this->maakWedstrijd($otherPoule); // geel - different
        $w3 = $this->maakWedstrijd($s['poule']);  // blauw - target poule

        $s['mat']->update([
            'actieve_wedstrijd_id' => $w1->id,
            'volgende_wedstrijd_id' => $w2->id,
            'gereedmaken_wedstrijd_id' => $w3->id,
        ]);
        $s['mat']->refresh();

        $s['mat']->resetWedstrijdSelectieVoorPoule($s['poule']->id);
        $s['mat']->refresh();

        $this->assertEquals($w1->id, $s['mat']->actieve_wedstrijd_id);
        $this->assertEquals($w2->id, $s['mat']->volgende_wedstrijd_id);
        $this->assertNull($s['mat']->gereedmaken_wedstrijd_id);
    }

    #[Test]
    public function reset_selectie_does_nothing_when_no_match(): void
    {
        $s = $this->maakSetup();
        $otherPoule = Poule::factory()->create([
            'toernooi_id' => $s['toernooi']->id,
            'mat_id' => $s['mat']->id,
        ]);
        $w1 = $this->maakWedstrijd($otherPoule);
        $w2 = $this->maakWedstrijd($otherPoule);

        $s['mat']->update([
            'actieve_wedstrijd_id' => $w1->id,
            'volgende_wedstrijd_id' => $w2->id,
        ]);
        $s['mat']->refresh();

        $s['mat']->resetWedstrijdSelectieVoorPoule($s['poule']->id);
        $s['mat']->refresh();

        $this->assertEquals($w1->id, $s['mat']->actieve_wedstrijd_id);
        $this->assertEquals($w2->id, $s['mat']->volgende_wedstrijd_id);
    }

    // ========================================================================
    // Mat: cleanupGespeeldeSelecties (lines 121-160)
    // ========================================================================

    #[Test]
    public function cleanup_gespeelde_shifts_when_groen_played(): void
    {
        $s = $this->maakSetup();
        $w1 = $this->maakGespeeldeWedstrijd($s['poule']); // groen - played with winner
        $w2 = $this->maakWedstrijd($s['poule']);            // geel
        $w3 = $this->maakWedstrijd($s['poule']);            // blauw

        $s['mat']->update([
            'actieve_wedstrijd_id' => $w1->id,
            'volgende_wedstrijd_id' => $w2->id,
            'gereedmaken_wedstrijd_id' => $w3->id,
        ]);
        $s['mat']->refresh();

        $s['mat']->cleanupGespeeldeSelecties();

        $this->assertEquals($w2->id, $s['mat']->actieve_wedstrijd_id);
        $this->assertEquals($w3->id, $s['mat']->volgende_wedstrijd_id);
        $this->assertNull($s['mat']->gereedmaken_wedstrijd_id);
    }

    #[Test]
    public function cleanup_gespeelde_shifts_when_geel_played_and_groen_not(): void
    {
        $s = $this->maakSetup();
        $w1 = $this->maakWedstrijd($s['poule']);            // groen - not played
        $w2 = $this->maakGespeeldeWedstrijd($s['poule']); // geel - played
        $w3 = $this->maakWedstrijd($s['poule']);            // blauw

        $s['mat']->update([
            'actieve_wedstrijd_id' => $w1->id,
            'volgende_wedstrijd_id' => $w2->id,
            'gereedmaken_wedstrijd_id' => $w3->id,
        ]);
        $s['mat']->refresh();

        $s['mat']->cleanupGespeeldeSelecties();

        // Groen stays, geel replaced by blauw
        $this->assertEquals($w1->id, $s['mat']->actieve_wedstrijd_id);
        $this->assertEquals($w3->id, $s['mat']->volgende_wedstrijd_id);
        $this->assertNull($s['mat']->gereedmaken_wedstrijd_id);
    }

    #[Test]
    public function cleanup_gespeelde_clears_blauw_when_played(): void
    {
        $s = $this->maakSetup();
        $w1 = $this->maakWedstrijd($s['poule']);            // groen - not played
        $w2 = $this->maakWedstrijd($s['poule']);            // geel - not played
        $w3 = $this->maakGespeeldeWedstrijd($s['poule']); // blauw - played

        $s['mat']->update([
            'actieve_wedstrijd_id' => $w1->id,
            'volgende_wedstrijd_id' => $w2->id,
            'gereedmaken_wedstrijd_id' => $w3->id,
        ]);
        $s['mat']->refresh();

        $s['mat']->cleanupGespeeldeSelecties();

        $this->assertEquals($w1->id, $s['mat']->actieve_wedstrijd_id);
        $this->assertEquals($w2->id, $s['mat']->volgende_wedstrijd_id);
        $this->assertNull($s['mat']->gereedmaken_wedstrijd_id);
    }

    #[Test]
    public function cleanup_gespeelde_does_nothing_when_none_played(): void
    {
        $s = $this->maakSetup();
        $w1 = $this->maakWedstrijd($s['poule']);
        $w2 = $this->maakWedstrijd($s['poule']);

        $s['mat']->update([
            'actieve_wedstrijd_id' => $w1->id,
            'volgende_wedstrijd_id' => $w2->id,
        ]);
        $s['mat']->refresh();

        $s['mat']->cleanupGespeeldeSelecties();

        $this->assertEquals($w1->id, $s['mat']->actieve_wedstrijd_id);
        $this->assertEquals($w2->id, $s['mat']->volgende_wedstrijd_id);
    }

    // ========================================================================
    // Mat: cleanupOngeldigeSelecties (lines 166-189)
    // ========================================================================

    #[Test]
    public function cleanup_ongeldige_clears_nonexistent_wedstrijden(): void
    {
        $s = $this->maakSetup();
        // Create real wedstrijden, then delete them to simulate orphaned references
        $w1 = $this->maakWedstrijd($s['poule']);
        $w2 = $this->maakWedstrijd($s['poule']);
        $w3 = $this->maakWedstrijd($s['poule']);

        $s['mat']->update([
            'actieve_wedstrijd_id' => $w1->id,
            'volgende_wedstrijd_id' => $w2->id,
            'gereedmaken_wedstrijd_id' => $w3->id,
        ]);

        // Delete the wedstrijden to make references invalid
        Wedstrijd::whereIn('id', [$w1->id, $w2->id, $w3->id])->delete();
        $s['mat']->refresh();

        $s['mat']->cleanupOngeldigeSelecties();

        $this->assertNull($s['mat']->actieve_wedstrijd_id);
        $this->assertNull($s['mat']->volgende_wedstrijd_id);
        $this->assertNull($s['mat']->gereedmaken_wedstrijd_id);
    }

    #[Test]
    public function cleanup_ongeldige_keeps_valid_wedstrijden(): void
    {
        $s = $this->maakSetup();
        $w1 = $this->maakWedstrijd($s['poule']);
        $w2 = $this->maakWedstrijd($s['poule']);

        $s['mat']->update([
            'actieve_wedstrijd_id' => $w1->id,
            'volgende_wedstrijd_id' => $w2->id,
        ]);

        // Delete only w2 to make volgende invalid
        Wedstrijd::where('id', $w2->id)->delete();
        $s['mat']->refresh();

        $s['mat']->cleanupOngeldigeSelecties();

        $this->assertEquals($w1->id, $s['mat']->actieve_wedstrijd_id);
        $this->assertNull($s['mat']->volgende_wedstrijd_id);
    }

    #[Test]
    public function cleanup_ongeldige_does_nothing_when_all_valid(): void
    {
        $s = $this->maakSetup();
        $w1 = $this->maakWedstrijd($s['poule']);

        $s['mat']->update(['actieve_wedstrijd_id' => $w1->id]);
        $s['mat']->refresh();

        $s['mat']->cleanupOngeldigeSelecties();

        $this->assertEquals($w1->id, $s['mat']->actieve_wedstrijd_id);
    }

    // ========================================================================
    // Mat: getPoulesVoorBlok (line 196-198)
    // ========================================================================

    #[Test]
    public function get_poules_voor_blok_returns_matching_poules(): void
    {
        $s = $this->maakSetup();
        // poule from setup is already linked to mat and blok
        $otherBlok = Blok::factory()->create(['toernooi_id' => $s['toernooi']->id]);
        Poule::factory()->create([
            'toernooi_id' => $s['toernooi']->id,
            'mat_id' => $s['mat']->id,
            'blok_id' => $otherBlok->id,
        ]);

        $result = $s['mat']->getPoulesVoorBlok($s['blok']);

        $this->assertCount(1, $result);
        $this->assertEquals($s['poule']->id, $result->first()->id);
    }

    #[Test]
    public function get_poules_voor_blok_returns_empty_when_none(): void
    {
        $s = $this->maakSetup();
        $otherBlok = Blok::factory()->create(['toernooi_id' => $s['toernooi']->id]);

        $result = $s['mat']->getPoulesVoorBlok($otherBlok);

        $this->assertCount(0, $result);
    }

    // ========================================================================
    // HasMolliePayments: usesMollieConnect (via Toernooi)
    // ========================================================================

    #[Test]
    public function uses_mollie_connect_true_when_connect_and_onboarded(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'mollie_mode' => 'connect',
            'mollie_onboarded' => true,
        ]);

        $this->assertTrue($toernooi->usesMollieConnect());
    }

    #[Test]
    public function uses_mollie_connect_false_when_platform_mode(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'mollie_mode' => 'platform',
            'mollie_onboarded' => true,
        ]);

        $this->assertFalse($toernooi->usesMollieConnect());
    }

    #[Test]
    public function uses_mollie_connect_false_when_not_onboarded(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'mollie_mode' => 'connect',
            'mollie_onboarded' => false,
        ]);

        $this->assertFalse($toernooi->usesMollieConnect());
    }

    // ========================================================================
    // HasMolliePayments: usesPlatformPayments
    // ========================================================================

    #[Test]
    public function uses_platform_payments_true_when_platform_mode(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'mollie_mode' => 'platform',
            'mollie_onboarded' => true,
        ]);

        $this->assertTrue($toernooi->usesPlatformPayments());
    }

    #[Test]
    public function uses_platform_payments_true_when_not_onboarded(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'mollie_mode' => 'connect',
            'mollie_onboarded' => false,
        ]);

        $this->assertTrue($toernooi->usesPlatformPayments());
    }

    #[Test]
    public function uses_platform_payments_false_when_connect_and_onboarded(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'mollie_mode' => 'connect',
            'mollie_onboarded' => true,
        ]);

        $this->assertFalse($toernooi->usesPlatformPayments());
    }

    // ========================================================================
    // HasMolliePayments: hasMollieConfigured
    // ========================================================================

    #[Test]
    public function has_mollie_configured_connect_with_token(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'mollie_mode' => 'connect',
            'mollie_onboarded' => true,
            'mollie_access_token' => 'access_test_123',
        ]);

        $this->assertTrue($toernooi->hasMollieConfigured());
    }

    #[Test]
    public function has_mollie_configured_connect_without_token(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'mollie_mode' => 'connect',
            'mollie_onboarded' => true,
            'mollie_access_token' => null,
        ]);

        $this->assertFalse($toernooi->hasMollieConfigured());
    }

    #[Test]
    public function has_mollie_configured_platform_with_key(): void
    {
        config(['services.mollie.platform_key' => 'test_key_123']);

        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'mollie_mode' => 'platform',
        ]);

        $this->assertTrue($toernooi->hasMollieConfigured());
    }

    #[Test]
    public function has_mollie_configured_platform_without_key(): void
    {
        config(['services.mollie.platform_key' => null]);
        config(['services.mollie.platform_test_key' => null]);

        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'mollie_mode' => 'platform',
        ]);

        $this->assertFalse($toernooi->hasMollieConfigured());
    }

    // ========================================================================
    // HasMolliePayments: getPlatformFee
    // ========================================================================

    #[Test]
    public function get_platform_fee_returns_zero_for_connect_mode(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'mollie_mode' => 'connect',
        ]);

        $this->assertEquals(0, $toernooi->getPlatformFee());
    }

    #[Test]
    public function get_platform_fee_returns_custom_value(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'mollie_mode' => 'platform',
            'platform_toeslag' => 1.25,
        ]);

        $this->assertEquals(1.25, $toernooi->getPlatformFee());
    }

    #[Test]
    public function get_platform_fee_returns_zero_value_when_set(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'mollie_mode' => 'platform',
            'platform_toeslag' => 0.00,
        ]);

        $this->assertEquals(0.00, $toernooi->getPlatformFee());
    }

    // ========================================================================
    // HasMolliePayments: calculatePaymentAmount
    // ========================================================================

    #[Test]
    public function calculate_payment_amount_connect_mode_no_fee(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'mollie_mode' => 'connect',
            'inschrijfgeld' => 15.00,
        ]);

        // 3 judokas * 15 = 45, no platform fee
        $this->assertEquals(45.00, $toernooi->calculatePaymentAmount(3));
    }

    #[Test]
    public function calculate_payment_amount_platform_fixed_fee(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'mollie_mode' => 'platform',
            'inschrijfgeld' => 10.00,
            'platform_toeslag' => 2.00,
            'platform_toeslag_percentage' => false,
        ]);

        // 2 judokas * 10 = 20 + 2.00 fee = 22.00
        $this->assertEquals(22.00, $toernooi->calculatePaymentAmount(2));
    }

    #[Test]
    public function calculate_payment_amount_platform_percentage_fee(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'mollie_mode' => 'platform',
            'inschrijfgeld' => 10.00,
            'platform_toeslag' => 5.00,  // 5%
            'platform_toeslag_percentage' => true,
        ]);

        // 2 judokas * 10 = 20, 20 * (1 + 5/100) = 21.00
        $this->assertEquals(21.00, $toernooi->calculatePaymentAmount(2));
    }

    // ========================================================================
    // HasMolliePayments: getMollieStatusText
    // ========================================================================

    #[Test]
    public function get_mollie_status_text_disabled(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'betaling_actief' => false,
        ]);

        $this->assertEquals('Betalingen uitgeschakeld', $toernooi->getMollieStatusText());
    }

    #[Test]
    public function get_mollie_status_text_connect_onboarded(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'betaling_actief' => true,
            'mollie_mode' => 'connect',
            'mollie_onboarded' => true,
            'mollie_organization_name' => 'Judo Club Test',
        ]);

        $this->assertEquals('Gekoppeld: Judo Club Test', $toernooi->getMollieStatusText());
    }

    #[Test]
    public function get_mollie_status_text_connect_onboarded_no_name(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'betaling_actief' => true,
            'mollie_mode' => 'connect',
            'mollie_onboarded' => true,
            'mollie_organization_name' => null,
        ]);

        $this->assertEquals('Gekoppeld: Eigen Mollie', $toernooi->getMollieStatusText());
    }

    #[Test]
    public function get_mollie_status_text_connect_not_onboarded(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'betaling_actief' => true,
            'mollie_mode' => 'connect',
            'mollie_onboarded' => false,
        ]);

        $this->assertEquals('Niet gekoppeld', $toernooi->getMollieStatusText());
    }

    #[Test]
    public function get_mollie_status_text_platform(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'betaling_actief' => true,
            'mollie_mode' => 'platform',
        ]);

        $this->assertEquals('Via JudoToernooi platform', $toernooi->getMollieStatusText());
    }

    // ========================================================================
    // HasCategorieBepaling: bepaalLeeftijdsklasse
    // ========================================================================

    private function maakToernooiMetConfig(): Toernooi
    {
        $org = Organisator::factory()->create();

        return Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'datum' => '2026-06-01',
            'gewichtsklassen' => [
                'u7' => [
                    'label' => 'U7',
                    'max_leeftijd' => 7,
                    'geslacht' => 'gemengd',
                    'band_filter' => '',
                    'gewichten' => ['-20', '-24', '+24'],
                ],
                'u11_jongens' => [
                    'label' => 'U11 Jongens',
                    'max_leeftijd' => 11,
                    'geslacht' => 'M',
                    'band_filter' => '',
                    'gewichten' => ['-28', '-32', '-36', '+36'],
                ],
                'u11_meisjes' => [
                    'label' => 'U11 Meisjes',
                    'max_leeftijd' => 11,
                    'geslacht' => 'V',
                    'band_filter' => '',
                    'gewichten' => ['-28', '-32', '-36', '+36'],
                ],
                'u15_lage_banden' => [
                    'label' => 'U15 Lage Banden',
                    'max_leeftijd' => 15,
                    'geslacht' => 'gemengd',
                    'band_filter' => 'tm_groen',
                    'gewichten' => ['-40', '-50', '-60', '+60'],
                ],
                'u15_hoge_banden' => [
                    'label' => 'U15 Hoge Banden',
                    'max_leeftijd' => 15,
                    'geslacht' => 'gemengd',
                    'band_filter' => 'vanaf_blauw',
                    'gewichten' => ['-40', '-50', '-60', '+60'],
                ],
            ],
        ]);
    }

    #[Test]
    public function bepaal_leeftijdsklasse_returns_gemengd_for_young(): void
    {
        $toernooi = $this->maakToernooiMetConfig();

        $this->assertEquals('U7', $toernooi->bepaalLeeftijdsklasse(6, 'M'));
        $this->assertEquals('U7', $toernooi->bepaalLeeftijdsklasse(7, 'V'));
    }

    #[Test]
    public function bepaal_leeftijdsklasse_returns_gender_specific(): void
    {
        $toernooi = $this->maakToernooiMetConfig();

        $this->assertEquals('U11 Jongens', $toernooi->bepaalLeeftijdsklasse(9, 'M'));
        $this->assertEquals('U11 Meisjes', $toernooi->bepaalLeeftijdsklasse(10, 'V'));
    }

    #[Test]
    public function bepaal_leeftijdsklasse_respects_band_filter(): void
    {
        $toernooi = $this->maakToernooiMetConfig();

        $this->assertEquals('U15 Lage Banden', $toernooi->bepaalLeeftijdsklasse(13, 'M', 'wit'));
        $this->assertEquals('U15 Hoge Banden', $toernooi->bepaalLeeftijdsklasse(13, 'M', 'blauw'));
    }

    #[Test]
    public function bepaal_leeftijdsklasse_returns_null_for_too_old(): void
    {
        $toernooi = $this->maakToernooiMetConfig();

        $this->assertNull($toernooi->bepaalLeeftijdsklasse(50, 'M'));
    }

    #[Test]
    public function bepaal_leeftijdsklasse_returns_null_for_empty_config(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'gewichtsklassen' => [],
        ]);

        $this->assertNull($toernooi->bepaalLeeftijdsklasse(10, 'M'));
    }

    #[Test]
    public function bepaal_leeftijdsklasse_no_doorval_across_age_groups(): void
    {
        $toernooi = $this->maakToernooiMetConfig();

        // A 6 year old should be in U7, not fall through to U11
        $result = $toernooi->bepaalLeeftijdsklasse(6, 'M');
        $this->assertEquals('U7', $result);
        $this->assertNotEquals('U11 Jongens', $result);
    }

    // ========================================================================
    // HasCategorieBepaling: bepaalGewichtsklasse
    // ========================================================================

    #[Test]
    public function bepaal_gewichtsklasse_returns_minus_class(): void
    {
        $toernooi = $this->maakToernooiMetConfig();

        // 6 year old, 18kg -> U7 -> -20
        $this->assertEquals('-20', $toernooi->bepaalGewichtsklasse(18.0, 6, 'M'));
    }

    #[Test]
    public function bepaal_gewichtsklasse_returns_plus_class(): void
    {
        $toernooi = $this->maakToernooiMetConfig();

        // 6 year old, 30kg -> U7 -> +24
        $this->assertEquals('+24', $toernooi->bepaalGewichtsklasse(30.0, 6, 'M'));
    }

    #[Test]
    public function bepaal_gewichtsklasse_respects_tolerantie(): void
    {
        $toernooi = $this->maakToernooiMetConfig();
        // Default tolerantie is 0.5
        // 6 year old, 20.4kg -> U7 -> -20 (within tolerance)
        $this->assertEquals('-20', $toernooi->bepaalGewichtsklasse(20.4, 6, 'M'));
    }

    #[Test]
    public function bepaal_gewichtsklasse_returns_null_for_too_old(): void
    {
        $toernooi = $this->maakToernooiMetConfig();

        $this->assertNull($toernooi->bepaalGewichtsklasse(70.0, 50, 'M'));
    }

    #[Test]
    public function bepaal_gewichtsklasse_returns_null_for_empty_config(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'gewichtsklassen' => [],
        ]);

        $this->assertNull($toernooi->bepaalGewichtsklasse(30.0, 10, 'M'));
    }

    #[Test]
    public function bepaal_gewichtsklasse_returns_null_for_dynamic_category(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'gewichtsklassen' => [
                'u7' => [
                    'label' => 'U7',
                    'max_leeftijd' => 7,
                    'geslacht' => 'gemengd',
                    'gewichten' => [], // empty = dynamic
                ],
            ],
        ]);

        $this->assertNull($toernooi->bepaalGewichtsklasse(20.0, 6, 'M'));
    }

    // ========================================================================
    // HasCategorieBepaling: getNietGecategoriseerdeJudokas
    // ========================================================================

    #[Test]
    public function get_niet_gecategoriseerde_returns_judokas_without_match(): void
    {
        $toernooi = $this->maakToernooiMetConfig();
        $club = Club::factory()->create();
        $toernooiJaar = 2026;

        // Judoka who fits U7 (age 6)
        Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'geboortejaar' => $toernooiJaar - 6,
            'geslacht' => 'M',
            'band' => 'wit',
        ]);

        // Judoka too old for any category (age 50)
        $tooOld = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'geboortejaar' => $toernooiJaar - 50,
            'geslacht' => 'M',
            'band' => 'zwart',
        ]);

        $result = $toernooi->getNietGecategoriseerdeJudokas();

        $this->assertCount(1, $result);
        $this->assertEquals($tooOld->id, $result->first()->id);
    }

    #[Test]
    public function get_niet_gecategoriseerde_returns_empty_when_all_fit(): void
    {
        $toernooi = $this->maakToernooiMetConfig();
        $club = Club::factory()->create();
        $toernooiJaar = 2026;

        Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'geboortejaar' => $toernooiJaar - 6,
            'geslacht' => 'M',
            'band' => 'wit',
        ]);

        $result = $toernooi->getNietGecategoriseerdeJudokas();

        $this->assertCount(0, $result);
    }

    // ========================================================================
    // HasCategorieBepaling: countNietGecategoriseerd
    // ========================================================================

    #[Test]
    public function count_niet_gecategoriseerd_returns_correct_count(): void
    {
        $toernooi = $this->maakToernooiMetConfig();
        $club = Club::factory()->create();
        $toernooiJaar = 2026;

        // One fitting judoka
        Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'geboortejaar' => $toernooiJaar - 6,
            'geslacht' => 'M',
            'band' => 'wit',
        ]);

        // Two non-fitting judokas
        Judoka::factory()->count(2)->create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'geboortejaar' => $toernooiJaar - 50,
            'geslacht' => 'M',
            'band' => 'zwart',
        ]);

        $this->assertEquals(2, $toernooi->countNietGecategoriseerd());
    }
}
