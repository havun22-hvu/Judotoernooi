<?php

namespace Tests\Feature;

use App\Models\Betaling;
use App\Models\Club;
use App\Models\Organisator;
use App\Models\Toernooi;
use App\Models\ToernooiBetaling;
use App\Services\MollieService;
use App\Services\Payments\StripePaymentProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentControllersCoverageTest extends TestCase
{
    use RefreshDatabase;

    private Organisator $organisator;
    private Toernooi $toernooi;
    private Club $club;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisator = Organisator::factory()->kycCompleet()->create();
        $this->toernooi = Toernooi::factory()->create([
            'organisator_id' => $this->organisator->id,
        ]);
        $this->club = Club::factory()->create();
    }

    /**
     * Helper to create a Betaling with all required fields.
     */
    private function createBetaling(array $attrs = []): Betaling
    {
        return Betaling::create(array_merge([
            'toernooi_id' => $this->toernooi->id,
            'club_id' => $this->club->id,
            'mollie_payment_id' => 'tr_' . uniqid(),
            'bedrag' => 25.00,
            'status' => Betaling::STATUS_OPEN,
            'aantal_judokas' => 5,
        ], $attrs));
    }

    /**
     * Helper to create a fake Stripe Event object for webhook tests.
     */
    private function createStripeEvent(string $type, string $sessionId): \Stripe\Event
    {
        $event = \Stripe\Event::constructFrom([
            'id' => 'evt_test_' . uniqid(),
            'object' => 'event',
            'type' => $type,
            'data' => [
                'object' => [
                    'id' => $sessionId,
                    'object' => 'checkout.session',
                ],
            ],
        ]);
        return $event;
    }

    /**
     * Helper to create a ToernooiBetaling with all required fields.
     */
    private function createToernooiBetaling(array $attrs = []): ToernooiBetaling
    {
        return ToernooiBetaling::create(array_merge([
            'toernooi_id' => $this->toernooi->id,
            'organisator_id' => $this->organisator->id,
            'mollie_payment_id' => 'tr_' . uniqid(),
            'bedrag' => 49.00,
            'tier' => 'basis',
            'max_judokas' => 100,
            'status' => ToernooiBetaling::STATUS_OPEN,
        ], $attrs));
    }

    /*
    |--------------------------------------------------------------------------
    | MollieController - Webhook Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function mollie_webhook_returns_400_without_payment_id(): void
    {
        $response = $this->postJson('mollie/webhook', []);

        $response->assertStatus(400);
    }

    #[Test]
    public function mollie_webhook_returns_200_for_unknown_payment(): void
    {
        $response = $this->postJson('mollie/webhook', ['id' => 'tr_unknown123']);

        $response->assertStatus(200);
    }

    #[Test]
    public function mollie_webhook_updates_betaling_status_to_paid(): void
    {
        $betaling = $this->createBetaling(['mollie_payment_id' => 'tr_test123']);

        // Mock the MollieService to return a paid payment
        $mockService = $this->mock(MollieService::class);
        $mockService->shouldReceive('ensureValidToken')->once();
        $mockService->shouldReceive('getPayment')->once()
            ->andReturn((object) ['status' => 'paid']);

        $response = $this->postJson('mollie/webhook', ['id' => 'tr_test123']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('betalingen', [
            'id' => $betaling->id,
            'status' => Betaling::STATUS_PAID,
        ]);
    }

    #[Test]
    public function mollie_webhook_handles_failed_status(): void
    {
        $betaling = $this->createBetaling(['mollie_payment_id' => 'tr_fail456']);

        $mockService = $this->mock(MollieService::class);
        $mockService->shouldReceive('ensureValidToken')->once();
        $mockService->shouldReceive('getPayment')->once()
            ->andReturn((object) ['status' => 'failed']);

        $response = $this->postJson('mollie/webhook', ['id' => 'tr_fail456']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('betalingen', [
            'id' => $betaling->id,
            'status' => Betaling::STATUS_FAILED,
        ]);
    }

    #[Test]
    public function mollie_webhook_handles_exception_gracefully(): void
    {
        $this->createBetaling(['mollie_payment_id' => 'tr_error789']);

        $mockService = $this->mock(MollieService::class);
        $mockService->shouldReceive('ensureValidToken')->once()
            ->andThrow(new \Exception('Connection timeout'));

        $response = $this->postJson('mollie/webhook', ['id' => 'tr_error789']);

        $response->assertStatus(500);
    }

    #[Test]
    public function mollie_toernooi_webhook_returns_400_without_payment_id(): void
    {
        $response = $this->postJson('mollie/webhook/toernooi', []);

        $response->assertStatus(400);
    }

    #[Test]
    public function mollie_toernooi_webhook_returns_200_for_unknown_payment(): void
    {
        $response = $this->postJson('mollie/webhook/toernooi', ['id' => 'tr_unknown456']);

        $response->assertStatus(200);
    }

    /*
    |--------------------------------------------------------------------------
    | MollieController - OAuth Flow Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function mollie_authorize_redirects_to_oauth_url(): void
    {
        $mockService = $this->mock(MollieService::class);
        $mockService->shouldReceive('getOAuthAuthorizeUrl')
            ->once()
            ->andReturn('https://www.mollie.com/oauth2/authorize?test=1');

        $response = $this->actingAs($this->organisator, 'organisator')
            ->get(route('toernooi.mollie.authorize', $this->toernooi->routeParams()));

        $response->assertRedirect('https://www.mollie.com/oauth2/authorize?test=1');
    }

    #[Test]
    public function mollie_callback_with_invalid_state_redirects_to_login(): void
    {
        $mockService = $this->mock(MollieService::class);
        $mockService->shouldReceive('validateOAuthState')
            ->once()
            ->andReturn(null);

        $response = $this->get(route('mollie.callback', ['state' => 'invalid', 'code' => 'abc']));

        $response->assertRedirect(route('organisator.login'));
    }

    #[Test]
    public function mollie_callback_with_error_redirects_with_error_message(): void
    {
        $mockService = $this->mock(MollieService::class);
        $mockService->shouldReceive('validateOAuthState')
            ->once()
            ->andReturn($this->toernooi->id);

        $response = $this->get(route('mollie.callback', [
            'state' => 'valid',
            'error' => 'access_denied',
            'error_description' => 'User cancelled',
        ]));

        $response->assertRedirect(route('toernooi.edit', $this->toernooi->routeParams()));
        $response->assertSessionHas('error');
    }

    #[Test]
    public function mollie_callback_success_saves_tokens(): void
    {
        $mockService = $this->mock(MollieService::class);
        $mockService->shouldReceive('validateOAuthState')
            ->once()
            ->andReturn($this->toernooi->id);
        $mockService->shouldReceive('exchangeCodeForTokens')
            ->once()
            ->andReturn(['access_token' => 'at_123', 'refresh_token' => 'rt_123']);
        $mockService->shouldReceive('saveTokensToToernooi')->once();

        $response = $this->get(route('mollie.callback', [
            'state' => 'valid',
            'code' => 'auth_code_123',
        ]));

        $response->assertRedirect(route('toernooi.edit', $this->toernooi->routeParams()));
        $response->assertSessionHas('success');
    }

    #[Test]
    public function mollie_callback_handles_mollie_exception(): void
    {
        $mockService = $this->mock(MollieService::class);
        $mockService->shouldReceive('validateOAuthState')
            ->once()
            ->andReturn($this->toernooi->id);
        $mockService->shouldReceive('exchangeCodeForTokens')
            ->once()
            ->andThrow(new \App\Exceptions\MollieException('Token exchange failed'));

        $response = $this->get(route('mollie.callback', [
            'state' => 'valid',
            'code' => 'bad_code',
        ]));

        $response->assertRedirect(route('toernooi.edit', $this->toernooi->routeParams()));
        $response->assertSessionHas('error');
    }

    #[Test]
    public function mollie_callback_handles_generic_exception(): void
    {
        $mockService = $this->mock(MollieService::class);
        $mockService->shouldReceive('validateOAuthState')
            ->once()
            ->andReturn($this->toernooi->id);
        $mockService->shouldReceive('exchangeCodeForTokens')
            ->once()
            ->andThrow(new \RuntimeException('Unexpected error'));

        $response = $this->get(route('mollie.callback', [
            'state' => 'valid',
            'code' => 'bad_code',
        ]));

        $response->assertRedirect(route('toernooi.edit', $this->toernooi->routeParams()));
        $response->assertSessionHas('error');
    }

    #[Test]
    public function mollie_disconnect_removes_connection(): void
    {
        $mockService = $this->mock(MollieService::class);
        $mockService->shouldReceive('disconnectFromToernooi')->once();

        $response = $this->actingAs($this->organisator, 'organisator')
            ->post(route('toernooi.mollie.disconnect', $this->toernooi->routeParams()));

        $response->assertRedirect(route('toernooi.edit', $this->toernooi->routeParams()));
        $response->assertSessionHas('success');
    }

    /*
    |--------------------------------------------------------------------------
    | MollieController - Simulation Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function mollie_simulate_page_loads(): void
    {
        $betaling = $this->createBetaling(['mollie_payment_id' => 'tr_sim123']);

        $response = $this->get(route('betaling.simulate', ['payment_id' => 'tr_sim123']));

        $response->assertStatus(200);
        $response->assertViewIs('pages.betaling.simulate');
    }

    #[Test]
    public function mollie_simulate_complete_updates_betaling_status(): void
    {
        $betaling = $this->createBetaling(['mollie_payment_id' => 'tr_simcomplete']);

        $response = $this->post(route('betaling.simulate.complete'), [
            'payment_id' => 'tr_simcomplete',
            'status' => 'paid',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('betalingen', [
            'id' => $betaling->id,
            'status' => Betaling::STATUS_PAID,
        ]);
    }

    #[Test]
    public function mollie_simulate_complete_for_toernooi_betaling(): void
    {
        $betaling = $this->createToernooiBetaling(['mollie_payment_id' => 'tr_toerisim']);

        $response = $this->post(route('betaling.simulate.complete'), [
            'payment_id' => 'tr_toerisim',
            'status' => 'paid',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('toernooi_betalingen', [
            'id' => $betaling->id,
            'status' => ToernooiBetaling::STATUS_PAID,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | StripeController - Webhook Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function stripe_webhook_returns_400_without_signature(): void
    {
        $response = $this->postJson('stripe/webhook', ['test' => true]);

        $response->assertStatus(400);
    }

    #[Test]
    public function stripe_webhook_returns_400_for_invalid_signature(): void
    {
        $mockProvider = $this->mock(StripePaymentProvider::class);
        $mockProvider->shouldReceive('verifyWebhookSignature')
            ->once()
            ->andThrow(new \Exception('Invalid signature'));

        $response = $this->postJson('stripe/webhook', ['test' => true], [
            'Stripe-Signature' => 'invalid_sig',
        ]);

        $response->assertStatus(400);
    }

    #[Test]
    public function stripe_webhook_processes_checkout_completed(): void
    {
        $betaling = $this->createBetaling(['stripe_payment_id' => 'cs_test_123']);

        $event = $this->createStripeEvent('checkout.session.completed', 'cs_test_123');

        $mockProvider = $this->mock(StripePaymentProvider::class);
        $mockProvider->shouldReceive('verifyWebhookSignature')
            ->once()
            ->andReturn($event);

        $response = $this->postJson('stripe/webhook', [], [
            'Stripe-Signature' => 'valid_sig',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('betalingen', [
            'id' => $betaling->id,
            'status' => Betaling::STATUS_PAID,
        ]);
    }

    #[Test]
    public function stripe_webhook_processes_checkout_expired(): void
    {
        $betaling = $this->createBetaling(['stripe_payment_id' => 'cs_expired_123']);

        $event = $this->createStripeEvent('checkout.session.expired', 'cs_expired_123');

        $mockProvider = $this->mock(StripePaymentProvider::class);
        $mockProvider->shouldReceive('verifyWebhookSignature')
            ->once()
            ->andReturn($event);

        $response = $this->postJson('stripe/webhook', [], [
            'Stripe-Signature' => 'valid_sig',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('betalingen', [
            'id' => $betaling->id,
            'status' => Betaling::STATUS_EXPIRED,
        ]);
    }

    #[Test]
    public function stripe_toernooi_webhook_returns_400_without_signature(): void
    {
        $response = $this->postJson('stripe/webhook/toernooi', ['test' => true]);

        $response->assertStatus(400);
    }

    #[Test]
    public function stripe_toernooi_webhook_processes_checkout_completed(): void
    {
        $betaling = $this->createToernooiBetaling(['stripe_payment_id' => 'cs_toernooi_123']);

        $event = $this->createStripeEvent('checkout.session.completed', 'cs_toernooi_123');

        $mockProvider = $this->mock(StripePaymentProvider::class);
        $mockProvider->shouldReceive('verifyWebhookSignature')
            ->once()
            ->andReturn($event);

        $response = $this->postJson('stripe/webhook/toernooi', [], [
            'Stripe-Signature' => 'valid_sig',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('toernooi_betalingen', [
            'id' => $betaling->id,
            'status' => ToernooiBetaling::STATUS_PAID,
        ]);
    }

    #[Test]
    public function stripe_toernooi_webhook_processes_checkout_expired(): void
    {
        $betaling = $this->createToernooiBetaling(['stripe_payment_id' => 'cs_toernooi_exp']);

        $event = $this->createStripeEvent('checkout.session.expired', 'cs_toernooi_exp');

        $mockProvider = $this->mock(StripePaymentProvider::class);
        $mockProvider->shouldReceive('verifyWebhookSignature')
            ->once()
            ->andReturn($event);

        $response = $this->postJson('stripe/webhook/toernooi', [], [
            'Stripe-Signature' => 'valid_sig',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('toernooi_betalingen', [
            'id' => $betaling->id,
            'status' => ToernooiBetaling::STATUS_EXPIRED,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | StripeController - Connect Flow Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function stripe_authorize_redirects_to_onboarding_url(): void
    {
        $mockProvider = $this->mock(StripePaymentProvider::class);
        $mockProvider->shouldReceive('getOAuthAuthorizeUrl')
            ->once()
            ->andReturn('https://connect.stripe.com/setup/test');

        $response = $this->actingAs($this->organisator, 'organisator')
            ->get(route('toernooi.stripe.authorize', $this->toernooi->routeParams()));

        $response->assertRedirect('https://connect.stripe.com/setup/test');
    }

    #[Test]
    public function stripe_authorize_handles_exception(): void
    {
        $mockProvider = $this->mock(StripePaymentProvider::class);
        $mockProvider->shouldReceive('getOAuthAuthorizeUrl')
            ->once()
            ->andThrow(new \RuntimeException('Stripe API error'));

        $response = $this->actingAs($this->organisator, 'organisator')
            ->get(route('toernooi.stripe.authorize', $this->toernooi->routeParams()));

        $response->assertRedirect(route('toernooi.edit', $this->toernooi->routeParams()));
        $response->assertSessionHas('error');
    }

    #[Test]
    public function stripe_callback_with_invalid_hash_redirects_to_login(): void
    {
        $mockProvider = $this->mock(StripePaymentProvider::class);
        $mockProvider->shouldReceive('validateCallbackHash')
            ->once()
            ->andReturn(false);

        $response = $this->get(route('stripe.callback', [
            'toernooi_id' => $this->toernooi->id,
            'hash' => 'invalid',
        ]));

        $response->assertRedirect(route('organisator.login'));
    }

    #[Test]
    public function stripe_callback_with_fully_onboarded_account_succeeds(): void
    {
        $this->toernooi->update(['stripe_account_id' => 'acct_test123']);

        $account = \Stripe\Account::constructFrom([
            'id' => 'acct_test123',
            'charges_enabled' => true,
            'payouts_enabled' => true,
        ]);

        $mockProvider = $this->mock(StripePaymentProvider::class);
        $mockProvider->shouldReceive('validateCallbackHash')
            ->once()
            ->andReturn(true);
        $mockProvider->shouldReceive('getAccount')
            ->once()
            ->andReturn($account);

        $response = $this->get(route('stripe.callback', [
            'toernooi_id' => $this->toernooi->id,
            'hash' => 'valid',
        ]));

        $response->assertRedirect(route('toernooi.edit', $this->toernooi->routeParams()));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('toernooien', [
            'id' => $this->toernooi->id,
            'mollie_mode' => 'connect',
        ]);
    }

    #[Test]
    public function stripe_callback_with_incomplete_onboarding_shows_warning(): void
    {
        $this->toernooi->update(['stripe_account_id' => 'acct_incomplete']);

        $account = \Stripe\Account::constructFrom([
            'id' => 'acct_incomplete',
            'charges_enabled' => false,
            'payouts_enabled' => false,
        ]);

        $mockProvider = $this->mock(StripePaymentProvider::class);
        $mockProvider->shouldReceive('validateCallbackHash')
            ->once()
            ->andReturn(true);
        $mockProvider->shouldReceive('getAccount')
            ->once()
            ->andReturn($account);

        $response = $this->get(route('stripe.callback', [
            'toernooi_id' => $this->toernooi->id,
            'hash' => 'valid',
        ]));

        $response->assertRedirect(route('toernooi.edit', $this->toernooi->routeParams()));
        $response->assertSessionHas('warning');
    }

    #[Test]
    public function stripe_callback_handles_exception(): void
    {
        $this->toernooi->update(['stripe_account_id' => 'acct_error']);

        $mockProvider = $this->mock(StripePaymentProvider::class);
        $mockProvider->shouldReceive('validateCallbackHash')
            ->once()
            ->andReturn(true);
        $mockProvider->shouldReceive('getAccount')
            ->once()
            ->andThrow(new \RuntimeException('Stripe API error'));

        $response = $this->get(route('stripe.callback', [
            'toernooi_id' => $this->toernooi->id,
            'hash' => 'valid',
        ]));

        $response->assertRedirect(route('toernooi.edit', $this->toernooi->routeParams()));
        $response->assertSessionHas('error');
    }

    #[Test]
    public function stripe_disconnect_removes_connection(): void
    {
        $mockProvider = $this->mock(StripePaymentProvider::class);
        $mockProvider->shouldReceive('disconnect')->once();

        $response = $this->actingAs($this->organisator, 'organisator')
            ->post(route('toernooi.stripe.disconnect', $this->toernooi->routeParams()));

        $response->assertRedirect(route('toernooi.edit', $this->toernooi->routeParams()));
        $response->assertSessionHas('success');
    }

    /*
    |--------------------------------------------------------------------------
    | ToernooiBetalingController Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function upgrade_page_loads_for_authenticated_organisator(): void
    {
        $response = $this->actingAs($this->organisator, 'organisator')
            ->get(route('toernooi.upgrade', $this->toernooi->routeParams()));

        $response->assertStatus(200);
        $response->assertViewIs('pages.toernooi.upgrade');
    }

    #[Test]
    public function save_kyc_validates_and_stores_data(): void
    {
        $kycData = [
            'organisatie_naam' => 'Test Judoclub',
            'kvk_nummer' => '12345678',
            'btw_nummer' => 'NL123456789B01',
            'straat' => 'Teststraat 1',
            'postcode' => '1234AB',
            'plaats' => 'Amsterdam',
            'land' => 'Nederland',
            'contactpersoon' => 'Jan Janssen',
            'telefoon' => '06-12345678',
            'factuur_email' => 'factuur@test.nl',
        ];

        $response = $this->actingAs($this->organisator, 'organisator')
            ->post(route('toernooi.upgrade.kyc', $this->toernooi->routeParams()), $kycData);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('organisators', [
            'id' => $this->organisator->id,
            'organisatie_naam' => 'Test Judoclub',
            'kyc_compleet' => true,
        ]);
    }

    #[Test]
    public function save_kyc_fails_with_invalid_data(): void
    {
        $response = $this->actingAs($this->organisator, 'organisator')
            ->post(route('toernooi.upgrade.kyc', $this->toernooi->routeParams()), [
                'organisatie_naam' => '', // required
                'straat' => '',
                'postcode' => '',
                'plaats' => '',
                'land' => '',
                'contactpersoon' => '',
                'factuur_email' => 'not-an-email',
            ]);

        $response->assertSessionHasErrors([
            'organisatie_naam',
            'straat',
            'postcode',
            'plaats',
            'land',
            'contactpersoon',
            'factuur_email',
        ]);
    }

    #[Test]
    public function start_payment_requires_kyc(): void
    {
        $orgWithoutKyc = Organisator::factory()->create(['kyc_compleet' => false]);
        $toernooi = Toernooi::factory()->create(['organisator_id' => $orgWithoutKyc->id]);

        $response = $this->actingAs($orgWithoutKyc, 'organisator')
            ->post(route('toernooi.upgrade.start', $toernooi->routeParams()), [
                'tier' => 'basis',
            ]);

        $response->assertRedirect(route('toernooi.upgrade', $toernooi->routeParams()));
        $response->assertSessionHas('error');
    }

    #[Test]
    public function upgrade_success_page_loads(): void
    {
        $betaling = $this->createToernooiBetaling([
            'mollie_payment_id' => 'tr_success_123',
            'status' => ToernooiBetaling::STATUS_PAID,
            'betaald_op' => now(),
        ]);

        $response = $this->actingAs($this->organisator, 'organisator')
            ->get(route('toernooi.upgrade.succes', $this->toernooi->routeParamsWith(['betaling' => $betaling->id])));

        $response->assertStatus(200);
        $response->assertViewIs('pages.toernooi.upgrade-succes');
    }

    #[Test]
    public function upgrade_success_returns_403_for_wrong_toernooi(): void
    {
        $otherToernooi = Toernooi::factory()->create(['organisator_id' => $this->organisator->id]);
        $betaling = $this->createToernooiBetaling([
            'toernooi_id' => $otherToernooi->id,
            'mollie_payment_id' => 'tr_wrong_123',
            'status' => ToernooiBetaling::STATUS_PAID,
        ]);

        $response = $this->actingAs($this->organisator, 'organisator')
            ->get(route('toernooi.upgrade.succes', $this->toernooi->routeParamsWith(['betaling' => $betaling->id])));

        $response->assertStatus(403);
    }

    #[Test]
    public function upgrade_cancelled_redirects_with_warning(): void
    {
        $response = $this->actingAs($this->organisator, 'organisator')
            ->get(route('toernooi.upgrade.geannuleerd', $this->toernooi->routeParams()));

        $response->assertRedirect(route('toernooi.upgrade', $this->toernooi->routeParams()));
        $response->assertSessionHas('warning');
    }

    /*
    |--------------------------------------------------------------------------
    | OfflineController Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function offline_controller_index_returns_data(): void
    {
        // OfflineController routes are only registered when OFFLINE_MODE=true at boot.
        // Test the controller directly instead.
        $toernooi = Toernooi::factory()->create();
        Config::set('app.offline_toernooi_id', $toernooi->id);

        $controller = app(\App\Http\Controllers\OfflineController::class);
        $result = $controller->uploadResultaten(new \Illuminate\Http\Request());

        $this->assertEquals(200, $result->getStatusCode());
        $data = json_decode($result->getContent(), true);
        $this->assertArrayHasKey('resultaten', $data);
        $this->assertArrayHasKey('toernooi_id', $data);
        $this->assertEquals($toernooi->id, $data['toernooi_id']);
    }

    #[Test]
    public function offline_controller_returns_error_without_toernooi(): void
    {
        Config::set('app.offline_toernooi_id', null);
        // Clear any existing toernooien
        Toernooi::query()->delete();

        $controller = app(\App\Http\Controllers\OfflineController::class);
        $result = $controller->uploadResultaten(new \Illuminate\Http\Request());

        $this->assertEquals(404, $result->getStatusCode());
    }

    /*
    |--------------------------------------------------------------------------
    | LocalSyncController Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function local_sync_status_returns_json(): void
    {
        Config::set('local-server.role', 'primary');
        Config::set('local-server.ip', '192.168.1.1');
        Config::set('local-server.device_name', 'Test Server');
        Config::set('local-server.configured_at', now()->toDateTimeString());

        $response = $this->get(route('local.status'), [
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['role', 'ip', 'device_name', 'timestamp']);
    }

    #[Test]
    public function local_sync_heartbeat_returns_ok(): void
    {
        $response = $this->getJson(route('local.heartbeat'));

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
    }

    #[Test]
    public function local_sync_health_returns_healthy(): void
    {
        Config::set('local-server.role', 'primary');

        $response = $this->getJson(route('local.health'));

        $response->assertStatus(200);
        $response->assertJson(['status' => 'healthy']);
    }

    #[Test]
    public function local_sync_health_reports_no_role(): void
    {
        Config::set('local-server.role', null);

        $response = $this->getJson(route('local.health'));

        $response->assertStatus(200);
        $response->assertJson(['status' => 'unhealthy']);
    }

    #[Test]
    public function local_sync_receive_sync_rejects_non_standby(): void
    {
        Config::set('local-server.role', 'primary');

        $response = $this->postJson(route('local.receive-sync'), ['data' => 'test']);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'This server is not in standby mode']);
    }

    #[Test]
    public function local_sync_receive_sync_accepts_on_standby(): void
    {
        Config::set('local-server.role', 'standby');

        $response = $this->postJson(route('local.receive-sync'), [
            'toernooien' => [['id' => 1, 'naam' => 'Test']],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
    }

    #[Test]
    public function local_sync_standby_status_returns_json(): void
    {
        $response = $this->getJson(route('local.standby-status'));

        $response->assertStatus(200);
        $response->assertJsonStructure(['role', 'last_sync', 'last_heartbeat', 'is_synced']);
    }

    #[Test]
    public function local_sync_data_returns_toernooien(): void
    {
        $toernooi = Toernooi::factory()->wedstrijddag()->create();

        $response = $this->getJson(route('local.sync'));

        $response->assertStatus(200);
        $response->assertJsonStructure(['timestamp', 'toernooien']);
    }

    #[Test]
    public function local_sync_sync_status_returns_json(): void
    {
        $response = $this->getJson(route('local.sync-status'));

        $response->assertStatus(200);
        $response->assertJsonStructure(['last_cloud_sync', 'last_standby_sync', 'role', 'cloud_available']);
    }

    #[Test]
    public function local_sync_queue_status_returns_zero_without_toernooi(): void
    {
        $response = $this->getJson(route('local.queue-status'));

        $response->assertStatus(200);
        $response->assertJson(['pending' => 0, 'failed' => 0]);
    }

    #[Test]
    public function local_sync_auto_sync_redirects_without_role(): void
    {
        Config::set('local-server.role', null);

        $response = $this->get(route('local.auto-sync'));

        $response->assertRedirect(route('local.setup'));
    }

    #[Test]
    public function local_sync_internet_status_returns_json(): void
    {
        $mockMonitor = $this->mock(\App\Services\InternetMonitorService::class);
        $mockMonitor->shouldReceive('getFullStatus')->once()->andReturn([
            'status' => 'online',
            'latency' => 15,
            'checked_at' => now()->toIso8601String(),
        ]);

        $response = $this->getJson(route('local.internet-status'));

        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'latency', 'checked_at', 'queue_count']);
    }
}
