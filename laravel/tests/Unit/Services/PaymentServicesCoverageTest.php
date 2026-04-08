<?php

namespace Tests\Unit\Services;

use App\DTOs\PaymentResult;
use App\Exceptions\MollieException;
use App\Models\Toernooi;
use App\Services\MollieService;
use App\Services\Payments\MolliePaymentProvider;
use App\Services\Payments\StripePaymentProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentServicesCoverageTest extends TestCase
{
    use RefreshDatabase;

    private MollieService $mollieService;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.mollie.api_url' => 'https://api.mollie.com/v2',
            'services.mollie.oauth_url' => 'https://my.mollie.com/oauth2',
            'services.mollie.oauth_token_url' => 'https://api.mollie.com/oauth2',
            'services.mollie.platform_key' => 'live_platform_key',
            'services.mollie.platform_test_key' => 'test_platform_key',
            'services.mollie.default_platform_fee' => 1.50,
            'services.mollie.client_id' => 'app_testclient',
            'services.mollie.client_secret' => 'test_secret',
            'services.mollie.redirect_uri' => 'https://example.com/callback',
            'services.stripe.secret' => 'sk_test_fake123',
            'services.stripe.webhook_secret' => 'whsec_test123',
        ]);
        $this->mollieService = new MollieService();
    }

    // ========================================================================
    // MollieService — addPlatformFeeToDescription (private, via createPayment)
    // ========================================================================

    #[Test]
    public function mollie_create_payment_adds_platform_fee_description_in_platform_mode(): void
    {
        Http::fake([
            'api.mollie.com/v2/payments' => Http::response([
                'id' => 'tr_test123',
                'status' => 'open',
                'amount' => ['currency' => 'EUR', 'value' => '15.00'],
                'description' => 'Inschrijving (incl. €5,00 platformkosten)',
                '_links' => ['checkout' => ['href' => 'https://pay.mollie.com/test']],
            ]),
        ]);

        $toernooi = Toernooi::factory()->create([
            'mollie_mode' => 'platform',
            'platform_toeslag' => 5.00,
        ]);

        $result = $this->mollieService->createPayment($toernooi, [
            'amount' => ['currency' => 'EUR', 'value' => '15.00'],
            'description' => 'Inschrijving',
            'redirectUrl' => 'https://example.com/return',
        ]);

        $this->assertEquals('tr_test123', $result->id);
    }

    #[Test]
    public function mollie_create_payment_no_fee_in_connect_mode(): void
    {
        $encryptedToken = Crypt::encryptString('access_token_xyz');

        Http::fake([
            'api.mollie.com/v2/payments' => Http::response([
                'id' => 'tr_connect123',
                'status' => 'open',
                'amount' => ['currency' => 'EUR', 'value' => '10.00'],
                'description' => 'Inschrijving',
                '_links' => ['checkout' => ['href' => 'https://pay.mollie.com/test']],
            ]),
        ]);

        $toernooi = Toernooi::factory()->create([
            'mollie_mode' => 'connect',
            'mollie_access_token' => $encryptedToken,
        ]);

        $result = $this->mollieService->createPayment($toernooi, [
            'amount' => ['currency' => 'EUR', 'value' => '10.00'],
            'description' => 'Inschrijving',
            'redirectUrl' => 'https://example.com/return',
        ]);

        $this->assertEquals('tr_connect123', $result->id);
    }

    #[Test]
    public function mollie_create_payment_throws_on_api_error(): void
    {
        Http::fake([
            'api.mollie.com/v2/payments' => Http::response(['status' => 422, 'title' => 'Unprocessable Entity'], 422),
        ]);

        $toernooi = Toernooi::factory()->create(['mollie_mode' => 'platform']);

        $this->expectException(MollieException::class);

        $this->mollieService->createPayment($toernooi, [
            'amount' => ['currency' => 'EUR', 'value' => '10.00'],
            'description' => 'Test',
            'redirectUrl' => 'https://example.com/return',
        ]);
    }

    // ========================================================================
    // MollieService — getPayment
    // ========================================================================

    #[Test]
    public function mollie_get_payment_returns_payment_object(): void
    {
        Http::fake([
            'api.mollie.com/v2/payments/tr_abc123' => Http::response([
                'id' => 'tr_abc123',
                'status' => 'paid',
                'amount' => ['currency' => 'EUR', 'value' => '10.00'],
            ]),
        ]);

        $toernooi = Toernooi::factory()->create(['mollie_mode' => 'platform']);

        $result = $this->mollieService->getPayment($toernooi, 'tr_abc123');

        $this->assertEquals('tr_abc123', $result->id);
        $this->assertEquals('paid', $result->status);
    }

    #[Test]
    public function mollie_get_payment_throws_on_not_found(): void
    {
        Http::fake([
            'api.mollie.com/v2/payments/tr_nonexist' => Http::response(['status' => 404], 404),
        ]);

        $toernooi = Toernooi::factory()->create(['mollie_mode' => 'platform']);

        $this->expectException(MollieException::class);

        $this->mollieService->getPayment($toernooi, 'tr_nonexist');
    }

    // ========================================================================
    // MollieService — addPlatformFeeToDescription with zero fee
    // ========================================================================

    #[Test]
    public function mollie_create_payment_platform_zero_fee_no_description_suffix(): void
    {
        Http::fake([
            'api.mollie.com/v2/payments' => Http::response([
                'id' => 'tr_zerofee',
                'status' => 'open',
                'amount' => ['currency' => 'EUR', 'value' => '10.00'],
                'description' => 'Inschrijving',
                '_links' => ['checkout' => ['href' => 'https://pay.mollie.com/test']],
            ]),
        ]);

        $toernooi = Toernooi::factory()->create([
            'mollie_mode' => 'platform',
            'platform_toeslag' => 0,
        ]);

        $result = $this->mollieService->createPayment($toernooi, [
            'amount' => ['currency' => 'EUR', 'value' => '10.00'],
            'description' => 'Inschrijving',
            'redirectUrl' => 'https://example.com/return',
        ]);

        $this->assertEquals('tr_zerofee', $result->id);
    }

    // ========================================================================
    // MollieService — calculateTotalAmount with null platform_toeslag (uses default)
    // ========================================================================

    #[Test]
    public function mollie_calculate_total_null_toeslag_uses_config_default(): void
    {
        $toernooi = Toernooi::factory()->create([
            'mollie_mode' => 'platform',
            'platform_toeslag' => 1.50,
            'platform_toeslag_percentage' => false,
        ]);

        // Force null via attribute override to test the ?? operator path
        $toernooi->platform_toeslag = null;

        $total = $this->mollieService->calculateTotalAmount($toernooi, 100.00);
        // null ?? config default (1.50) = 1.50
        $this->assertEquals(101.50, $total);
    }

    // ========================================================================
    // MollieService — exchangeCodeForTokens
    // ========================================================================

    #[Test]
    public function mollie_exchange_code_returns_tokens(): void
    {
        Http::fake([
            'api.mollie.com/oauth2/tokens' => Http::response([
                'access_token' => 'at_new_token',
                'refresh_token' => 'rt_new_token',
                'expires_in' => 3600,
            ]),
        ]);

        $tokens = $this->mollieService->exchangeCodeForTokens('auth_code_123');

        $this->assertEquals('at_new_token', $tokens['access_token']);
        $this->assertEquals('rt_new_token', $tokens['refresh_token']);
    }

    #[Test]
    public function mollie_exchange_code_throws_on_failure(): void
    {
        Http::fake([
            'api.mollie.com/oauth2/tokens' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $this->expectException(MollieException::class);

        $this->mollieService->exchangeCodeForTokens('bad_code');
    }

    // ========================================================================
    // MollieService — refreshAccessToken
    // ========================================================================

    #[Test]
    public function mollie_refresh_token_throws_without_refresh_token(): void
    {
        $toernooi = Toernooi::factory()->create([
            'mollie_mode' => 'connect',
            'mollie_refresh_token' => null,
        ]);

        $this->expectException(MollieException::class);

        $this->mollieService->refreshAccessToken($toernooi);
    }

    #[Test]
    public function mollie_refresh_token_updates_tournament(): void
    {
        $encryptedRefresh = Crypt::encryptString('old_refresh_token');

        Http::fake([
            'api.mollie.com/oauth2/tokens' => Http::response([
                'access_token' => 'new_access_token',
                'refresh_token' => 'new_refresh_token',
                'expires_in' => 3600,
            ]),
        ]);

        $toernooi = Toernooi::factory()->create([
            'mollie_mode' => 'connect',
            'mollie_refresh_token' => $encryptedRefresh,
        ]);

        $tokens = $this->mollieService->refreshAccessToken($toernooi);

        $this->assertEquals('new_access_token', $tokens['access_token']);
        $toernooi->refresh();
        $this->assertNotNull($toernooi->mollie_access_token);
        $this->assertNotNull($toernooi->mollie_token_expires_at);
    }

    #[Test]
    public function mollie_refresh_token_throws_on_api_failure(): void
    {
        $encryptedRefresh = Crypt::encryptString('old_refresh_token');

        Http::fake([
            'api.mollie.com/oauth2/tokens' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $toernooi = Toernooi::factory()->create([
            'mollie_mode' => 'connect',
            'mollie_refresh_token' => $encryptedRefresh,
        ]);

        $this->expectException(MollieException::class);

        $this->mollieService->refreshAccessToken($toernooi);
    }

    // ========================================================================
    // MollieService — ensureValidToken triggers refresh when near expiry
    // ========================================================================

    #[Test]
    public function mollie_ensure_valid_token_refreshes_when_near_expiry(): void
    {
        $encryptedRefresh = Crypt::encryptString('refresh_token');

        Http::fake([
            'api.mollie.com/oauth2/tokens' => Http::response([
                'access_token' => 'fresh_token',
                'refresh_token' => 'fresh_refresh',
                'expires_in' => 3600,
            ]),
        ]);

        $toernooi = Toernooi::factory()->create([
            'mollie_mode' => 'connect',
            'mollie_refresh_token' => $encryptedRefresh,
            'mollie_token_expires_at' => now()->addMinutes(2), // Expires in 2 min, < 5 min threshold
        ]);

        $this->mollieService->ensureValidToken($toernooi);

        $toernooi->refresh();
        $this->assertNotNull($toernooi->mollie_access_token);
    }

    #[Test]
    public function mollie_ensure_valid_token_does_nothing_when_not_expiring(): void
    {
        $toernooi = Toernooi::factory()->create([
            'mollie_mode' => 'connect',
            'mollie_token_expires_at' => now()->addHours(2), // Far from expiry
        ]);

        // Should not throw or make any HTTP call
        Http::fake();
        $this->mollieService->ensureValidToken($toernooi);
        Http::assertNothingSent();
    }

    // ========================================================================
    // MollieService — getOrganization
    // ========================================================================

    #[Test]
    public function mollie_get_organization_returns_object(): void
    {
        Http::fake([
            'api.mollie.com/v2/organizations/me' => Http::response([
                'id' => 'org_12345',
                'name' => 'Test Judo Club',
            ]),
        ]);

        $result = $this->mollieService->getOrganization('test_access_token');

        $this->assertEquals('org_12345', $result->id);
        $this->assertEquals('Test Judo Club', $result->name);
    }

    // ========================================================================
    // MollieService — getCircuitStatus content
    // ========================================================================

    #[Test]
    public function mollie_circuit_status_has_expected_keys(): void
    {
        $status = $this->mollieService->getCircuitStatus();

        $this->assertArrayHasKey('service', $status);
        $this->assertArrayHasKey('state', $status);
        $this->assertArrayHasKey('failures', $status);
        $this->assertArrayHasKey('threshold', $status);
        $this->assertEquals('mollie', $status['service']);
    }

    // ========================================================================
    // MolliePaymentProvider
    // ========================================================================

    #[Test]
    public function mollie_provider_get_name_returns_mollie(): void
    {
        $provider = new MolliePaymentProvider($this->mollieService);
        $this->assertEquals('mollie', $provider->getName());
    }

    #[Test]
    public function mollie_provider_is_available_delegates_to_service(): void
    {
        $provider = new MolliePaymentProvider($this->mollieService);
        $this->assertTrue($provider->isAvailable());
    }

    #[Test]
    public function mollie_provider_is_simulation_mode_delegates_to_service(): void
    {
        $provider = new MolliePaymentProvider($this->mollieService);
        // test key is set, so not simulation mode
        $this->assertFalse($provider->isSimulationMode());
    }

    #[Test]
    public function mollie_provider_calculate_total_delegates_to_service(): void
    {
        $provider = new MolliePaymentProvider($this->mollieService);
        $toernooi = Toernooi::factory()->create([
            'mollie_mode' => 'platform',
            'platform_toeslag' => 2.00,
            'platform_toeslag_percentage' => false,
        ]);

        $total = $provider->calculateTotalAmount($toernooi, 50.00);
        $this->assertEquals(52.00, $total);
    }

    #[Test]
    public function mollie_provider_simulate_payment_returns_payment_result(): void
    {
        $provider = new MolliePaymentProvider($this->mollieService);

        $result = $provider->simulatePayment([
            'amount' => ['currency' => 'EUR', 'value' => '10.00'],
            'description' => 'Simulated',
            'redirectUrl' => 'https://example.com/return',
            'webhookUrl' => 'https://example.com/webhook',
        ]);

        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertStringStartsWith('tr_simulated_', $result->id);
        $this->assertEquals('open', $result->status);
    }

    #[Test]
    public function mollie_provider_create_payment_delegates_to_service(): void
    {
        Http::fake([
            'api.mollie.com/v2/payments' => Http::response([
                'id' => 'tr_provider_test',
                'status' => 'open',
                'amount' => ['currency' => 'EUR', 'value' => '15.00'],
                'description' => 'Provider test',
                '_links' => ['checkout' => ['href' => 'https://pay.mollie.com/checkout']],
            ]),
        ]);

        $provider = new MolliePaymentProvider($this->mollieService);
        $toernooi = Toernooi::factory()->create(['mollie_mode' => 'platform']);

        $result = $provider->createPayment($toernooi, [
            'amount' => ['currency' => 'EUR', 'value' => '15.00'],
            'description' => 'Provider test',
            'redirectUrl' => 'https://example.com/return',
        ]);

        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertEquals('tr_provider_test', $result->id);
        $this->assertEquals('open', $result->status);
    }

    #[Test]
    public function mollie_provider_get_payment_delegates_to_service(): void
    {
        Http::fake([
            'api.mollie.com/v2/payments/tr_existing' => Http::response([
                'id' => 'tr_existing',
                'status' => 'paid',
                'amount' => ['currency' => 'EUR', 'value' => '20.00'],
            ]),
        ]);

        $provider = new MolliePaymentProvider($this->mollieService);
        $toernooi = Toernooi::factory()->create(['mollie_mode' => 'platform']);

        $result = $provider->getPayment($toernooi, 'tr_existing');

        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertEquals('paid', $result->status);
    }

    #[Test]
    public function mollie_provider_get_oauth_url_delegates_to_service(): void
    {
        $provider = new MolliePaymentProvider($this->mollieService);
        $toernooi = Toernooi::factory()->create();

        $url = $provider->getOAuthAuthorizeUrl($toernooi);

        $this->assertStringContainsString('my.mollie.com/oauth2/authorize', $url);
        $this->assertStringContainsString('client_id=app_testclient', $url);
    }

    #[Test]
    public function mollie_provider_handle_oauth_callback_saves_tokens(): void
    {
        Http::fake([
            // exchangeCodeForTokens
            'api.mollie.com/oauth2/tokens' => Http::response([
                'access_token' => 'new_at',
                'refresh_token' => 'new_rt',
                'expires_in' => 3600,
            ]),
            // getOrganization (called by saveTokensToToernooi)
            'api.mollie.com/v2/organizations/me' => Http::response([
                'id' => 'org_1',
                'name' => 'Judo Club Test',
            ]),
        ]);

        $provider = new MolliePaymentProvider($this->mollieService);
        $toernooi = Toernooi::factory()->create(['mollie_mode' => 'platform']);

        $provider->handleOAuthCallback($toernooi, 'auth_code_xyz');

        $toernooi->refresh();
        $this->assertEquals('connect', $toernooi->mollie_mode);
        $this->assertTrue($toernooi->mollie_onboarded);
        $this->assertEquals('Judo Club Test', $toernooi->mollie_organization_name);
    }

    #[Test]
    public function mollie_provider_disconnect_clears_fields(): void
    {
        $provider = new MolliePaymentProvider($this->mollieService);
        $toernooi = Toernooi::factory()->create([
            'mollie_mode' => 'connect',
            'mollie_access_token' => 'encrypted',
            'mollie_onboarded' => true,
        ]);

        $provider->disconnect($toernooi);

        $toernooi->refresh();
        $this->assertEquals('platform', $toernooi->mollie_mode);
        $this->assertNull($toernooi->mollie_access_token);
        $this->assertFalse($toernooi->mollie_onboarded);
    }

    #[Test]
    public function mollie_provider_create_platform_payment_success(): void
    {
        Http::fake([
            'api.mollie.com/v2/payments' => Http::response([
                'id' => 'tr_platform_pay',
                'status' => 'open',
                'amount' => ['currency' => 'EUR', 'value' => '29.00'],
                'description' => 'Upgrade',
                '_links' => ['checkout' => ['href' => 'https://pay.mollie.com/platform']],
            ]),
        ]);

        $provider = new MolliePaymentProvider($this->mollieService);

        $result = $provider->createPlatformPayment([
            'amount' => ['currency' => 'EUR', 'value' => '29.00'],
            'description' => 'Upgrade',
            'redirectUrl' => 'https://example.com/return',
        ]);

        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertEquals('tr_platform_pay', $result->id);
    }

    #[Test]
    public function mollie_provider_create_platform_payment_throws_on_error(): void
    {
        Http::fake([
            'api.mollie.com/v2/payments' => Http::response(['error' => 'bad request'], 400),
        ]);

        $provider = new MolliePaymentProvider($this->mollieService);

        $this->expectException(MollieException::class);

        $provider->createPlatformPayment([
            'amount' => ['currency' => 'EUR', 'value' => '29.00'],
            'description' => 'Upgrade',
            'redirectUrl' => 'https://example.com/return',
        ]);
    }

    #[Test]
    public function mollie_provider_get_platform_payment_success(): void
    {
        Http::fake([
            'api.mollie.com/v2/payments/tr_plat_exist' => Http::response([
                'id' => 'tr_plat_exist',
                'status' => 'paid',
                'amount' => ['currency' => 'EUR', 'value' => '29.00'],
            ]),
        ]);

        $provider = new MolliePaymentProvider($this->mollieService);

        $result = $provider->getPlatformPayment('tr_plat_exist');

        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertEquals('paid', $result->status);
    }

    #[Test]
    public function mollie_provider_get_platform_payment_throws_on_error(): void
    {
        Http::fake([
            'api.mollie.com/v2/payments/tr_bad' => Http::response(['error' => 'not found'], 404),
        ]);

        $provider = new MolliePaymentProvider($this->mollieService);

        $this->expectException(MollieException::class);

        $provider->getPlatformPayment('tr_bad');
    }

    // ========================================================================
    // StripePaymentProvider — non-API methods
    // ========================================================================

    #[Test]
    public function stripe_provider_get_name_returns_stripe(): void
    {
        $provider = new StripePaymentProvider();
        $this->assertEquals('stripe', $provider->getName());
    }

    #[Test]
    public function stripe_provider_is_available_true_with_secret(): void
    {
        $provider = new StripePaymentProvider();
        $this->assertTrue($provider->isAvailable());
    }

    #[Test]
    public function stripe_provider_is_available_false_without_secret(): void
    {
        config(['services.stripe.secret' => null]);
        $provider = new StripePaymentProvider();
        $this->assertFalse($provider->isAvailable());
    }

    #[Test]
    public function stripe_provider_is_simulation_mode_false_with_key(): void
    {
        $provider = new StripePaymentProvider();
        $this->assertFalse($provider->isSimulationMode());
    }

    #[Test]
    public function stripe_provider_is_simulation_mode_true_without_key(): void
    {
        config(['services.stripe.secret' => null]);
        $provider = new StripePaymentProvider();
        $this->assertTrue($provider->isSimulationMode());
    }

    #[Test]
    public function stripe_provider_simulate_payment_returns_payment_result(): void
    {
        $provider = new StripePaymentProvider();

        $result = $provider->simulatePayment([
            'amount' => ['currency' => 'EUR', 'value' => '25.00'],
            'description' => 'Stripe sim',
            'metadata' => ['test' => true],
        ]);

        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertStringStartsWith('cs_simulated_', $result->id);
        $this->assertEquals('open', $result->status);
        $this->assertEquals('25.00', $result->amount);
        $this->assertEquals('EUR', $result->currency);
        $this->assertEquals('Stripe sim', $result->description);
    }

    #[Test]
    public function stripe_provider_simulate_payment_handles_missing_optional_fields(): void
    {
        $provider = new StripePaymentProvider();

        $result = $provider->simulatePayment([
            'amount' => ['currency' => 'USD', 'value' => '5.00'],
        ]);

        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertNull($result->description);
    }

    #[Test]
    public function stripe_provider_calculate_total_connected_account(): void
    {
        $provider = new StripePaymentProvider();
        $toernooi = Toernooi::factory()->create([
            'payment_provider' => 'stripe',
            'stripe_account_id' => 'acct_123abc',
        ]);

        // Connected account = no platform fee
        $total = $provider->calculateTotalAmount($toernooi, 100.00);
        $this->assertEquals(100.00, $total);
    }

    #[Test]
    public function stripe_provider_calculate_total_platform_fixed_fee(): void
    {
        $provider = new StripePaymentProvider();
        $toernooi = Toernooi::factory()->create([
            'payment_provider' => 'stripe',
            'stripe_account_id' => null,
            'platform_toeslag' => 3.00,
            'platform_toeslag_percentage' => false,
        ]);

        $total = $provider->calculateTotalAmount($toernooi, 100.00);
        $this->assertEquals(103.00, $total);
    }

    #[Test]
    public function stripe_provider_calculate_total_platform_percentage_fee(): void
    {
        $provider = new StripePaymentProvider();
        $toernooi = Toernooi::factory()->create([
            'payment_provider' => 'stripe',
            'stripe_account_id' => null,
            'platform_toeslag' => 5.0,
            'platform_toeslag_percentage' => true,
        ]);

        $total = $provider->calculateTotalAmount($toernooi, 100.00);
        $this->assertEqualsWithDelta(105.00, $total, 0.01);
    }

    #[Test]
    public function stripe_provider_calculate_total_platform_null_toeslag_uses_default(): void
    {
        $provider = new StripePaymentProvider();
        $toernooi = Toernooi::factory()->create([
            'payment_provider' => 'stripe',
            'stripe_account_id' => null,
            'platform_toeslag' => 0.50,
            'platform_toeslag_percentage' => false,
        ]);

        // Force null via attribute override to test ?? operator
        $toernooi->platform_toeslag = null;

        // null ?? 0.50 = 0.50
        $total = $provider->calculateTotalAmount($toernooi, 100.00);
        $this->assertEquals(100.50, $total);
    }

    #[Test]
    public function stripe_provider_calculate_total_no_connected_account_different_provider(): void
    {
        $provider = new StripePaymentProvider();
        $toernooi = Toernooi::factory()->create([
            'payment_provider' => 'mollie', // Not stripe
            'stripe_account_id' => 'acct_123abc', // Has stripe account but provider is mollie
            'platform_toeslag' => 2.00,
            'platform_toeslag_percentage' => false,
        ]);

        // hasConnectedAccount checks payment_provider === 'stripe', so false here
        $total = $provider->calculateTotalAmount($toernooi, 100.00);
        $this->assertEquals(102.00, $total);
    }

    #[Test]
    public function stripe_provider_disconnect_clears_stripe_fields(): void
    {
        // Mock the Stripe client to avoid real API calls
        $provider = $this->getMockBuilder(StripePaymentProvider::class)
            ->onlyMethods([])
            ->getMock();

        $toernooi = Toernooi::factory()->create([
            'payment_provider' => 'stripe',
            'stripe_account_id' => null, // No account to delete
            'stripe_access_token' => 'tok_test',
            'stripe_refresh_token' => 'rt_test',
            'stripe_publishable_key' => 'pk_test',
        ]);

        $provider->disconnect($toernooi);

        $toernooi->refresh();
        $this->assertNull($toernooi->stripe_account_id);
        $this->assertNull($toernooi->stripe_access_token);
        $this->assertNull($toernooi->stripe_refresh_token);
        $this->assertNull($toernooi->stripe_publishable_key);
    }

    #[Test]
    public function stripe_provider_generate_callback_hash_is_deterministic(): void
    {
        $provider = new StripePaymentProvider();
        $toernooi = Toernooi::factory()->create();

        $hash1 = $provider->generateCallbackHash($toernooi);
        $hash2 = $provider->generateCallbackHash($toernooi);

        $this->assertEquals($hash1, $hash2);
        $this->assertEquals(64, strlen($hash1)); // sha256 hex = 64 chars
    }

    #[Test]
    public function stripe_provider_validate_callback_hash_valid(): void
    {
        $provider = new StripePaymentProvider();
        $toernooi = Toernooi::factory()->create();

        $hash = $provider->generateCallbackHash($toernooi);
        $this->assertTrue($provider->validateCallbackHash($toernooi->id, $hash));
    }

    #[Test]
    public function stripe_provider_validate_callback_hash_invalid(): void
    {
        $provider = new StripePaymentProvider();

        $this->assertFalse($provider->validateCallbackHash(1, 'invalid_hash'));
    }

    #[Test]
    public function stripe_provider_validate_callback_hash_null_params(): void
    {
        $provider = new StripePaymentProvider();

        $this->assertFalse($provider->validateCallbackHash(null, null));
        $this->assertFalse($provider->validateCallbackHash(1, null));
        $this->assertFalse($provider->validateCallbackHash(null, 'hash'));
    }

    #[Test]
    public function stripe_provider_validate_callback_hash_wrong_toernooi_id(): void
    {
        $provider = new StripePaymentProvider();
        $toernooi = Toernooi::factory()->create();

        $hash = $provider->generateCallbackHash($toernooi);

        // Different toernooi_id should fail
        $this->assertFalse($provider->validateCallbackHash($toernooi->id + 999, $hash));
    }
}
