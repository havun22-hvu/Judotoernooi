<?php

namespace Tests\Unit\Models;

use App\DTOs\PaymentResult;
use App\Enums\Leeftijdsklasse;
use App\Helpers\BandHelper;
use App\Models\Blok;
use App\Models\Club;
use App\Models\ClubUitnodiging;
use App\Models\Coach;
use App\Models\Judoka;
use App\Models\Mat;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Support\Result;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PartialCoverageBoostTest extends TestCase
{
    use RefreshDatabase;

    // ========================================================================
    // Blok - herberekenPouleStatistieken, getJudokas, matten, problematischePoules
    // ========================================================================

    #[Test]
    public function blok_herbereken_poule_statistieken_updates_poules(): void
    {
        $toernooi = Toernooi::factory()->create();
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'type' => 'voorronde',
        ]);

        // Attach some judokas to verify statistieken update
        $judoka1 = Judoka::factory()->create(['toernooi_id' => $toernooi->id]);
        $judoka2 = Judoka::factory()->create(['toernooi_id' => $toernooi->id]);
        $poule->judokas()->attach([$judoka1->id, $judoka2->id]);

        $blok->herberekenPouleStatistieken();

        $poule->refresh();
        $this->assertEquals(2, $poule->aantal_judokas);
    }

    #[Test]
    public function blok_herbereken_eliminatie_removes_afwezige_judokas(): void
    {
        $toernooi = Toernooi::factory()->create();
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'type' => 'eliminatie',
        ]);

        $judokaAanwezig = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'aanwezigheid' => 'aanwezig',
        ]);
        $judokaAfwezig = Judoka::factory()->create([
            'toernooi_id' => $toernooi->id,
            'aanwezigheid' => 'afwezig',
        ]);
        $poule->judokas()->attach([$judokaAanwezig->id, $judokaAfwezig->id]);

        $blok->herberekenPouleStatistieken();

        // Afwezige judoka should be detached from eliminatie poule
        $this->assertCount(1, $poule->fresh()->judokas);
        $this->assertTrue($poule->fresh()->judokas->contains($judokaAanwezig));
    }

    #[Test]
    public function blok_get_judokas_returns_query_builder(): void
    {
        $toernooi = Toernooi::factory()->create();
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
        ]);

        $judoka = Judoka::factory()->create(['toernooi_id' => $toernooi->id]);
        $poule->judokas()->attach($judoka->id);

        $result = $blok->getJudokas()->get();

        $this->assertCount(1, $result);
        $this->assertEquals($judoka->id, $result->first()->id);
    }

    #[Test]
    public function blok_get_matten_attribute_returns_mats(): void
    {
        $toernooi = Toernooi::factory()->create();
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id]);
        $mat = Mat::factory()->create(['toernooi_id' => $toernooi->id]);
        Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat->id,
        ]);

        $matten = $blok->matten;

        $this->assertCount(1, $matten);
        $this->assertEquals($mat->id, $matten->first()->id);
    }

    #[Test]
    public function blok_get_matten_attribute_returns_empty_without_mats(): void
    {
        $toernooi = Toernooi::factory()->create();
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id]);
        Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => null,
        ]);

        $this->assertCount(0, $blok->matten);
    }

    #[Test]
    public function blok_get_problematische_poules_returns_empty_when_weging_open(): void
    {
        $toernooi = Toernooi::factory()->create();
        $blok = Blok::factory()->create([
            'toernooi_id' => $toernooi->id,
            'weging_gesloten' => false,
        ]);

        $result = $blok->getProblematischePoules();

        $this->assertTrue($result->isEmpty());
    }

    #[Test]
    public function blok_get_problematische_poules_when_weging_gesloten(): void
    {
        $toernooi = Toernooi::factory()->create();
        $blok = Blok::factory()->create([
            'toernooi_id' => $toernooi->id,
            'weging_gesloten' => true,
        ]);
        // Create a standard (non-dynamic) poule - should not be problematic
        Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
        ]);

        $result = $blok->getProblematischePoules();

        // Non-dynamic poules return null from isProblematischNaWeging, so filtered out
        $this->assertTrue($result->isEmpty());
    }

    // ========================================================================
    // Coach - toernooi relation, updateLaatstIngelogd
    // ========================================================================

    #[Test]
    public function coach_belongs_to_toernooi(): void
    {
        $toernooi = Toernooi::factory()->create();
        $club = Club::factory()->create(['organisator_id' => $toernooi->organisator_id]);

        $coach = Coach::create([
            'club_id' => $club->id,
            'toernooi_id' => $toernooi->id,
            'naam' => 'Test Coach',
            'email' => 'coach@test.nl',
        ]);

        $this->assertInstanceOf(Toernooi::class, $coach->toernooi);
        $this->assertEquals($toernooi->id, $coach->toernooi->id);
    }

    #[Test]
    public function coach_update_laatst_ingelogd(): void
    {
        $toernooi = Toernooi::factory()->create();
        $club = Club::factory()->create(['organisator_id' => $toernooi->organisator_id]);

        $coach = Coach::create([
            'club_id' => $club->id,
            'toernooi_id' => $toernooi->id,
            'naam' => 'Test Coach',
            'email' => 'coach@test.nl',
        ]);

        $this->assertNull($coach->laatst_ingelogd_op);

        $coach->updateLaatstIngelogd();

        $coach->refresh();
        $this->assertNotNull($coach->laatst_ingelogd_op);
    }

    // ========================================================================
    // ClubUitnodiging - booted token, isGeregistreerd, wachtwoord, login
    // ========================================================================

    #[Test]
    public function club_uitnodiging_auto_generates_token(): void
    {
        $toernooi = Toernooi::factory()->create();
        $club = Club::factory()->create(['organisator_id' => $toernooi->organisator_id]);

        $uitnodiging = ClubUitnodiging::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'uitgenodigd_op' => now(),
        ]);

        $this->assertNotNull($uitnodiging->token);
        $this->assertEquals(64, strlen($uitnodiging->token));
    }

    #[Test]
    public function club_uitnodiging_does_not_overwrite_existing_token(): void
    {
        $toernooi = Toernooi::factory()->create();
        $club = Club::factory()->create(['organisator_id' => $toernooi->organisator_id]);

        $uitnodiging = ClubUitnodiging::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'token' => 'my-custom-token',
            'uitgenodigd_op' => now(),
        ]);

        $this->assertEquals('my-custom-token', $uitnodiging->token);
    }

    #[Test]
    public function club_uitnodiging_is_geregistreerd(): void
    {
        $toernooi = Toernooi::factory()->create();
        $club = Club::factory()->create(['organisator_id' => $toernooi->organisator_id]);

        $uitnodiging = ClubUitnodiging::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'uitgenodigd_op' => now(),
        ]);

        $this->assertFalse($uitnodiging->isGeregistreerd());

        $uitnodiging->geregistreerd_op = now();
        $this->assertTrue($uitnodiging->isGeregistreerd());
    }

    #[Test]
    public function club_uitnodiging_set_and_check_wachtwoord(): void
    {
        $toernooi = Toernooi::factory()->create();
        $club = Club::factory()->create(['organisator_id' => $toernooi->organisator_id]);

        $uitnodiging = ClubUitnodiging::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'uitgenodigd_op' => now(),
        ]);

        $uitnodiging->setWachtwoord('geheim123');

        $uitnodiging->refresh();
        $this->assertTrue($uitnodiging->checkWachtwoord('geheim123'));
        $this->assertFalse($uitnodiging->checkWachtwoord('fout'));
        $this->assertNotNull($uitnodiging->geregistreerd_op);
    }

    #[Test]
    public function club_uitnodiging_update_laatst_ingelogd(): void
    {
        $toernooi = Toernooi::factory()->create();
        $club = Club::factory()->create(['organisator_id' => $toernooi->organisator_id]);

        $uitnodiging = ClubUitnodiging::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'uitgenodigd_op' => now(),
        ]);

        $this->assertNull($uitnodiging->laatst_ingelogd_op);

        $uitnodiging->updateLaatstIngelogd();

        $uitnodiging->refresh();
        $this->assertNotNull($uitnodiging->laatst_ingelogd_op);
    }

    #[Test]
    public function club_uitnodiging_belongs_to_toernooi(): void
    {
        $toernooi = Toernooi::factory()->create();
        $club = Club::factory()->create(['organisator_id' => $toernooi->organisator_id]);

        $uitnodiging = ClubUitnodiging::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'uitgenodigd_op' => now(),
        ]);

        $this->assertInstanceOf(Toernooi::class, $uitnodiging->toernooi);
        $this->assertInstanceOf(Club::class, $uitnodiging->club);
    }

    // ========================================================================
    // Leeftijdsklasse enum - label, code, maxLeeftijd, configKey, gewichtsklassen, fromLeeftijdEnGeslacht
    // ========================================================================

    #[Test]
    public function leeftijdsklasse_label_for_all_cases(): void
    {
        $this->assertEquals("Mini's", Leeftijdsklasse::MINIS->label());
        $this->assertEquals('A-pupillen', Leeftijdsklasse::A_PUPILLEN->label());
        $this->assertEquals('B-pupillen', Leeftijdsklasse::B_PUPILLEN->label());
        $this->assertEquals('Dames -15', Leeftijdsklasse::DAMES_15->label());
        $this->assertEquals('Heren -15', Leeftijdsklasse::HEREN_15->label());
        $this->assertEquals('Dames -18', Leeftijdsklasse::DAMES_18->label());
        $this->assertEquals('Heren -18', Leeftijdsklasse::HEREN_18->label());
        $this->assertEquals('Dames', Leeftijdsklasse::DAMES->label());
        $this->assertEquals('Heren', Leeftijdsklasse::HEREN->label());
    }

    #[Test]
    public function leeftijdsklasse_code_for_all_cases(): void
    {
        $this->assertEquals('08', Leeftijdsklasse::MINIS->code());
        $this->assertEquals('10', Leeftijdsklasse::A_PUPILLEN->code());
        $this->assertEquals('12', Leeftijdsklasse::B_PUPILLEN->code());
        $this->assertEquals('15', Leeftijdsklasse::DAMES_15->code());
        $this->assertEquals('15', Leeftijdsklasse::HEREN_15->code());
        $this->assertEquals('18', Leeftijdsklasse::DAMES_18->code());
        $this->assertEquals('18', Leeftijdsklasse::HEREN_18->code());
        $this->assertEquals('21', Leeftijdsklasse::DAMES->code());
        $this->assertEquals('21', Leeftijdsklasse::HEREN->code());
    }

    #[Test]
    public function leeftijdsklasse_max_leeftijd_for_all_cases(): void
    {
        $this->assertEquals(8, Leeftijdsklasse::MINIS->maxLeeftijd());
        $this->assertEquals(10, Leeftijdsklasse::A_PUPILLEN->maxLeeftijd());
        $this->assertEquals(12, Leeftijdsklasse::B_PUPILLEN->maxLeeftijd());
        $this->assertEquals(15, Leeftijdsklasse::DAMES_15->maxLeeftijd());
        $this->assertEquals(15, Leeftijdsklasse::HEREN_15->maxLeeftijd());
        $this->assertEquals(18, Leeftijdsklasse::DAMES_18->maxLeeftijd());
        $this->assertEquals(18, Leeftijdsklasse::HEREN_18->maxLeeftijd());
        $this->assertEquals(99, Leeftijdsklasse::DAMES->maxLeeftijd());
        $this->assertEquals(99, Leeftijdsklasse::HEREN->maxLeeftijd());
    }

    #[Test]
    public function leeftijdsklasse_config_key_for_all_cases(): void
    {
        $this->assertEquals('minis', Leeftijdsklasse::MINIS->configKey());
        $this->assertEquals('a_pupillen', Leeftijdsklasse::A_PUPILLEN->configKey());
        $this->assertEquals('b_pupillen', Leeftijdsklasse::B_PUPILLEN->configKey());
        $this->assertEquals('dames_15', Leeftijdsklasse::DAMES_15->configKey());
        $this->assertEquals('heren_15', Leeftijdsklasse::HEREN_15->configKey());
        $this->assertEquals('dames_18', Leeftijdsklasse::DAMES_18->configKey());
        $this->assertEquals('heren_18', Leeftijdsklasse::HEREN_18->configKey());
        $this->assertEquals('dames', Leeftijdsklasse::DAMES->configKey());
        $this->assertEquals('heren', Leeftijdsklasse::HEREN->configKey());
    }

    #[Test]
    public function leeftijdsklasse_gewichtsklassen_for_all_cases(): void
    {
        $this->assertEquals([-20, -23, -26, -29, 29], Leeftijdsklasse::MINIS->gewichtsklassen());
        $this->assertEquals([-24, -27, -30, -34, -38, 38], Leeftijdsklasse::A_PUPILLEN->gewichtsklassen());
        $this->assertEquals([-27, -30, -34, -38, -42, -46, -50, 50], Leeftijdsklasse::B_PUPILLEN->gewichtsklassen());
        $this->assertEquals([-36, -40, -44, -48, -52, -57, -63, 63], Leeftijdsklasse::DAMES_15->gewichtsklassen());
        $this->assertEquals([-34, -38, -42, -46, -50, -55, -60, -66, 66], Leeftijdsklasse::HEREN_15->gewichtsklassen());
        $this->assertEquals([-40, -44, -48, -52, -57, -63, -70, 70], Leeftijdsklasse::DAMES_18->gewichtsklassen());
        $this->assertEquals([-46, -50, -55, -60, -66, -73, -81, -90, 90], Leeftijdsklasse::HEREN_18->gewichtsklassen());
        $this->assertEquals([-48, -52, -57, -63, -70, -78, 78], Leeftijdsklasse::DAMES->gewichtsklassen());
        $this->assertEquals([-60, -66, -73, -81, -90, -100, 100], Leeftijdsklasse::HEREN->gewichtsklassen());
    }

    #[Test]
    public function leeftijdsklasse_from_leeftijd_en_geslacht(): void
    {
        // Minis (< 8)
        $this->assertEquals(Leeftijdsklasse::MINIS, Leeftijdsklasse::fromLeeftijdEnGeslacht(6, 'M'));
        $this->assertEquals(Leeftijdsklasse::MINIS, Leeftijdsklasse::fromLeeftijdEnGeslacht(7, 'V'));

        // A-pupillen (8-9)
        $this->assertEquals(Leeftijdsklasse::A_PUPILLEN, Leeftijdsklasse::fromLeeftijdEnGeslacht(8, 'M'));
        $this->assertEquals(Leeftijdsklasse::A_PUPILLEN, Leeftijdsklasse::fromLeeftijdEnGeslacht(9, 'V'));

        // B-pupillen (10-11)
        $this->assertEquals(Leeftijdsklasse::B_PUPILLEN, Leeftijdsklasse::fromLeeftijdEnGeslacht(10, 'M'));
        $this->assertEquals(Leeftijdsklasse::B_PUPILLEN, Leeftijdsklasse::fromLeeftijdEnGeslacht(11, 'V'));

        // 12-14: gender split
        $this->assertEquals(Leeftijdsklasse::HEREN_15, Leeftijdsklasse::fromLeeftijdEnGeslacht(13, 'M'));
        $this->assertEquals(Leeftijdsklasse::DAMES_15, Leeftijdsklasse::fromLeeftijdEnGeslacht(13, 'V'));

        // 15-17: gender split
        $this->assertEquals(Leeftijdsklasse::HEREN_18, Leeftijdsklasse::fromLeeftijdEnGeslacht(16, 'M'));
        $this->assertEquals(Leeftijdsklasse::DAMES_18, Leeftijdsklasse::fromLeeftijdEnGeslacht(16, 'V'));

        // 18+: adults
        $this->assertEquals(Leeftijdsklasse::HEREN, Leeftijdsklasse::fromLeeftijdEnGeslacht(25, 'M'));
        $this->assertEquals(Leeftijdsklasse::DAMES, Leeftijdsklasse::fromLeeftijdEnGeslacht(25, 'V'));
    }

    // ========================================================================
    // PaymentResult - fromMollie (already tested), fromStripe
    // ========================================================================

    #[Test]
    public function payment_result_from_stripe_complete_session(): void
    {
        $metadata = new \Stripe\StripeObject();
        $metadata['description'] = 'Toernooi betaling';
        $metadata['club_id'] = '5';

        $session = \Stripe\Checkout\Session::constructFrom([
            'id' => 'cs_test_123',
            'object' => 'checkout.session',
            'status' => 'complete',
            'url' => 'https://checkout.stripe.com/pay/cs_test_123',
            'amount_total' => 2500,
            'currency' => 'eur',
            'metadata' => ['description' => 'Toernooi betaling', 'club_id' => '5'],
        ]);

        $result = PaymentResult::fromStripe($session);

        $this->assertEquals('cs_test_123', $result->id);
        $this->assertEquals('paid', $result->status); // 'complete' maps to 'paid'
        $this->assertEquals('https://checkout.stripe.com/pay/cs_test_123', $result->checkoutUrl);
        $this->assertEquals('25.00', $result->amount);
        $this->assertEquals('EUR', $result->currency);
        $this->assertTrue($result->isPaid());
    }

    #[Test]
    public function payment_result_from_stripe_open_session(): void
    {
        $session = \Stripe\Checkout\Session::constructFrom([
            'id' => 'cs_open_456',
            'object' => 'checkout.session',
            'status' => 'open',
            'url' => 'https://checkout.stripe.com/pay/cs_open_456',
            'amount_total' => null,
            'currency' => null,
            'metadata' => [],
        ]);

        $result = PaymentResult::fromStripe($session);

        $this->assertEquals('cs_open_456', $result->id);
        $this->assertEquals('open', $result->status);
        $this->assertNull($result->amount);
        $this->assertEquals('EUR', $result->currency); // default
        $this->assertTrue($result->isOpen());
    }

    #[Test]
    public function payment_result_from_stripe_expired_session(): void
    {
        $session = \Stripe\Checkout\Session::constructFrom([
            'id' => 'cs_expired_789',
            'object' => 'checkout.session',
            'status' => 'expired',
            'url' => null,
            'amount_total' => 1000,
            'currency' => 'eur',
            'metadata' => [],
        ]);

        $result = PaymentResult::fromStripe($session);

        $this->assertEquals('expired', $result->status);
        $this->assertEquals('10.00', $result->amount);
        $this->assertTrue($result->isFailed());
    }

    // ========================================================================
    // Result - onSuccess, onFailure, try (not yet covered)
    // ========================================================================

    #[Test]
    public function result_on_success_calls_callback(): void
    {
        $called = false;
        $receivedValue = null;

        Result::success('hello')
            ->onSuccess(function ($value) use (&$called, &$receivedValue) {
                $called = true;
                $receivedValue = $value;
            });

        $this->assertTrue($called);
        $this->assertEquals('hello', $receivedValue);
    }

    #[Test]
    public function result_on_success_does_not_call_on_failure(): void
    {
        $called = false;

        Result::success('data')
            ->onFailure(function () use (&$called) {
                $called = true;
            });

        $this->assertFalse($called);
    }

    #[Test]
    public function result_on_failure_calls_callback(): void
    {
        $called = false;
        $receivedError = null;
        $receivedContext = null;

        Result::failure('oops', ['key' => 'val'])
            ->onFailure(function ($error, $context) use (&$called, &$receivedError, &$receivedContext) {
                $called = true;
                $receivedError = $error;
                $receivedContext = $context;
            });

        $this->assertTrue($called);
        $this->assertEquals('oops', $receivedError);
        $this->assertEquals(['key' => 'val'], $receivedContext);
    }

    #[Test]
    public function result_on_failure_does_not_call_on_success(): void
    {
        $called = false;

        Result::failure('error')
            ->onSuccess(function () use (&$called) {
                $called = true;
            });

        $this->assertFalse($called);
    }

    #[Test]
    public function result_flat_map_on_failure_returns_self(): void
    {
        $called = false;

        $result = Result::failure('error')
            ->flatMap(function ($value) use (&$called) {
                $called = true;
                return Result::success($value);
            });

        $this->assertFalse($called);
        $this->assertTrue($result->isFailure());
        $this->assertEquals('error', $result->getError());
    }

    #[Test]
    public function result_try_catches_exception(): void
    {
        $result = Result::try(function () {
            throw new \RuntimeException('something broke');
        });

        $this->assertTrue($result->isFailure());
        $this->assertEquals('something broke', $result->getError());
        $this->assertEquals('RuntimeException', $result->getContext()['exception']);
        $this->assertNotEmpty($result->getContext()['trace']);
    }

    #[Test]
    public function result_try_with_prefix(): void
    {
        $result = Result::try(function () {
            throw new \InvalidArgumentException('bad input');
        }, 'Validation');

        $this->assertTrue($result->isFailure());
        $this->assertEquals('Validation: bad input', $result->getError());
    }

    #[Test]
    public function result_try_returns_success_on_no_exception(): void
    {
        $result = Result::try(function () {
            return 42;
        });

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(42, $result->getValue());
    }

    // ========================================================================
    // BandHelper - getNiveau, pastInFilter, getSortNiveau
    // ========================================================================

    #[Test]
    public function band_helper_get_niveau_known_band(): void
    {
        // getNiveau returns Band enum value: WIT=6, ZWART=0, GROEN=3
        $this->assertEquals(6, BandHelper::getNiveau('wit'));
        $this->assertEquals(0, BandHelper::getNiveau('zwart'));
        $this->assertEquals(3, BandHelper::getNiveau('groen'));
    }

    #[Test]
    public function band_helper_get_niveau_unknown_band(): void
    {
        $this->assertEquals(6, BandHelper::getNiveau('onbekend'));
    }

    #[Test]
    public function band_helper_past_in_filter(): void
    {
        $this->assertTrue(BandHelper::pastInFilter('wit', 'tm_groen'));
        $this->assertFalse(BandHelper::pastInFilter('zwart', 'tm_groen'));
        $this->assertTrue(BandHelper::pastInFilter('zwart', 'vanaf_blauw'));
        $this->assertFalse(BandHelper::pastInFilter('wit', 'vanaf_blauw'));
        // null filter = allow all
        $this->assertTrue(BandHelper::pastInFilter('wit', null));
        $this->assertTrue(BandHelper::pastInFilter(null, 'tm_groen'));
    }

    #[Test]
    public function band_helper_get_sort_niveau(): void
    {
        $this->assertEquals(1, BandHelper::getSortNiveau('wit'));
        $this->assertEquals(7, BandHelper::getSortNiveau('zwart'));
        $this->assertEquals(7, BandHelper::getSortNiveau('onbekend'));
    }
}
