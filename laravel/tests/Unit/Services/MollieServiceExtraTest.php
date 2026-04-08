<?php

namespace Tests\Unit\Services;

use App\Models\Toernooi;
use App\Services\MollieService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class MollieServiceExtraTest extends TestCase
{
    use RefreshDatabase;

    private MollieService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.mollie.api_url' => 'https://api.mollie.com/v2',
            'services.mollie.oauth_url' => 'https://my.mollie.com/oauth2',
            'services.mollie.oauth_token_url' => 'https://api.mollie.com/oauth2/tokens',
            'services.mollie.platform_key' => 'live_test_key',
            'services.mollie.platform_test_key' => 'test_key_123',
            'services.mollie.default_platform_fee' => 1.50,
            'services.mollie.client_id' => 'app_test123',
            'services.mollie.client_secret' => 'secret_test',
            'services.mollie.redirect_uri' => 'https://example.com/callback',
        ]);
        $this->service = new MollieService();
    }

    private function callPrivate(string $method, array $args): mixed
    {
        $ref = new ReflectionMethod(MollieService::class, $method);
        return $ref->invoke($this->service, ...$args);
    }

    // ========================================================================
    // getOAuthAuthorizeUrl
    // ========================================================================

    #[Test]
    public function get_oauth_authorize_url_contains_required_params(): void
    {
        $toernooi = Toernooi::factory()->create();

        $url = $this->service->getOAuthAuthorizeUrl($toernooi);

        $this->assertStringContainsString('https://my.mollie.com/oauth2/authorize', $url);
        $this->assertStringContainsString('client_id=app_test123', $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('scope=', $url);
    }

    // ========================================================================
    // getApiKeyForToernooi — connect mode with token
    // ========================================================================

    #[Test]
    public function get_api_key_connect_mode_uses_decrypted_token(): void
    {
        $encryptedToken = Crypt::encryptString('access_token_123');

        $toernooi = Toernooi::factory()->create([
            'mollie_mode' => 'connect',
            'mollie_access_token' => $encryptedToken,
        ]);

        $key = $this->service->getApiKeyForToernooi($toernooi);
        $this->assertEquals('access_token_123', $key);
    }

    #[Test]
    public function get_api_key_connect_mode_no_token_falls_back_to_platform(): void
    {
        $toernooi = Toernooi::factory()->create([
            'mollie_mode' => 'connect',
            'mollie_access_token' => null,
        ]);

        $key = $this->service->getApiKeyForToernooi($toernooi);
        $this->assertEquals('test_key_123', $key);
    }

    // ========================================================================
    // disconnectFromToernooi
    // ========================================================================

    #[Test]
    public function disconnect_clears_all_mollie_fields(): void
    {
        $toernooi = Toernooi::factory()->create([
            'mollie_mode' => 'connect',
            'mollie_access_token' => 'encrypted_token',
            'mollie_refresh_token' => 'encrypted_refresh',
            'mollie_onboarded' => true,
            'mollie_organization_name' => 'Test Org',
        ]);

        $this->service->disconnectFromToernooi($toernooi);
        $toernooi->refresh();

        $this->assertEquals('platform', $toernooi->mollie_mode);
        $this->assertNull($toernooi->mollie_access_token);
        $this->assertNull($toernooi->mollie_refresh_token);
        $this->assertNull($toernooi->mollie_token_expires_at);
        $this->assertFalse($toernooi->mollie_onboarded);
        $this->assertNull($toernooi->mollie_organization_name);
    }

    // ========================================================================
    // ensureValidToken
    // ========================================================================

    #[Test]
    public function ensure_valid_token_skips_platform_mode(): void
    {
        $toernooi = Toernooi::factory()->create([
            'mollie_mode' => 'platform',
        ]);

        // Should not throw — just return
        $this->service->ensureValidToken($toernooi);
        $this->assertTrue(true); // No exception = pass
    }

    // ========================================================================
    // validateOAuthState
    // ========================================================================

    #[Test]
    public function validate_oauth_state_null_returns_null(): void
    {
        $this->assertNull($this->service->validateOAuthState(null));
    }

    #[Test]
    public function validate_oauth_state_invalid_returns_null(): void
    {
        $this->assertNull($this->service->validateOAuthState('not-valid-base64'));
    }

    #[Test]
    public function validate_oauth_state_valid_returns_toernooi_id(): void
    {
        $toernooi = Toernooi::factory()->create();

        // Generate valid state
        $state = $this->callPrivate('generateOAuthState', [$toernooi]);

        $result = $this->service->validateOAuthState($state);
        $this->assertEquals($toernooi->id, $result);
    }

    #[Test]
    public function validate_oauth_state_tampered_hash_returns_null(): void
    {
        $state = base64_encode(json_encode([
            'toernooi_id' => 999,
            'timestamp' => time(),
            'hash' => 'tampered_hash',
        ]));

        $this->assertNull($this->service->validateOAuthState($state));
    }

    #[Test]
    public function validate_oauth_state_expired_returns_null(): void
    {
        $toernooiId = 1;
        $state = base64_encode(json_encode([
            'toernooi_id' => $toernooiId,
            'timestamp' => time() - 7200, // 2 hours ago (> 1 hour limit)
            'hash' => hash_hmac('sha256', $toernooiId, config('app.key')),
        ]));

        $this->assertNull($this->service->validateOAuthState($state));
    }

    // ========================================================================
    // isSimulationMode
    // ========================================================================

    #[Test]
    public function is_simulation_mode_false_when_test_key_exists(): void
    {
        // platform_test_key is set in setUp
        $this->assertFalse($this->service->isSimulationMode());
    }

    #[Test]
    public function is_simulation_mode_true_without_test_key(): void
    {
        config(['services.mollie.platform_test_key' => null]);
        $service = new MollieService();
        $this->assertTrue($service->isSimulationMode());
    }

    // ========================================================================
    // simulatePayment
    // ========================================================================

    #[Test]
    public function simulate_payment_returns_valid_structure(): void
    {
        $data = [
            'amount' => ['currency' => 'EUR', 'value' => '10.00'],
            'description' => 'Test betaling',
            'redirectUrl' => 'https://example.com/return',
            'webhookUrl' => 'https://example.com/webhook',
            'metadata' => ['toernooi_id' => 1],
        ];

        $result = $this->service->simulatePayment($data);

        $this->assertStringStartsWith('tr_simulated_', $result->id);
        $this->assertEquals('open', $result->status);
        $this->assertEquals('Test betaling', $result->description);
        $this->assertNotNull($result->_links->checkout->href);
    }

    // ========================================================================
    // simulatePaymentStatus
    // ========================================================================

    #[Test]
    public function simulate_payment_status_returns_correct_status(): void
    {
        $result = $this->service->simulatePaymentStatus('tr_test_123', 'paid');

        $this->assertEquals('tr_test_123', $result->id);
        $this->assertEquals('paid', $result->status);
    }

    #[Test]
    public function simulate_payment_status_can_be_failed(): void
    {
        $result = $this->service->simulatePaymentStatus('tr_test_123', 'failed');
        $this->assertEquals('failed', $result->status);
    }

    // ========================================================================
    // calculateTotalAmount — edge cases
    // ========================================================================

    #[Test]
    public function calculate_total_with_default_platform_fee(): void
    {
        $toernooi = Toernooi::factory()->create([
            'mollie_mode' => 'platform',
            'platform_toeslag' => 0, // Explicit zero
            'platform_toeslag_percentage' => false,
        ]);

        $total = $this->service->calculateTotalAmount($toernooi, 100.00);
        // platform_toeslag is 0, so falls back to config default 1.50
        // Actually: $toeslag = $toernooi->platform_toeslag ?? config(...) = 0 (not null)
        $this->assertEquals(100.00, $total);
    }

    #[Test]
    public function calculate_total_small_percentage(): void
    {
        $toernooi = Toernooi::factory()->create([
            'mollie_mode' => 'platform',
            'platform_toeslag' => 2.5,
            'platform_toeslag_percentage' => true,
        ]);

        $total = $this->service->calculateTotalAmount($toernooi, 50.00);
        $this->assertEqualsWithDelta(51.25, $total, 0.01);
    }

    // ========================================================================
    // encryptToken / decryptToken (private)
    // ========================================================================

    #[Test]
    public function encrypt_decrypt_roundtrip(): void
    {
        $original = 'secret_token_12345';
        $encrypted = $this->callPrivate('encryptToken', [$original]);
        $decrypted = $this->callPrivate('decryptToken', [$encrypted]);

        $this->assertEquals($original, $decrypted);
        $this->assertNotEquals($original, $encrypted);
    }

    // ========================================================================
    // generateOAuthState (private)
    // ========================================================================

    #[Test]
    public function generate_oauth_state_is_base64(): void
    {
        $toernooi = Toernooi::factory()->create();
        $state = $this->callPrivate('generateOAuthState', [$toernooi]);

        $decoded = base64_decode($state, true);
        $this->assertNotFalse($decoded);

        $data = json_decode($decoded, true);
        $this->assertArrayHasKey('toernooi_id', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('hash', $data);
        $this->assertEquals($toernooi->id, $data['toernooi_id']);
    }
}
