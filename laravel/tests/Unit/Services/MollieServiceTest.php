<?php

namespace Tests\Unit\Services;

use App\Models\Toernooi;
use App\Services\MollieService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MollieServiceTest extends TestCase
{
    use RefreshDatabase;

    private MollieService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Set test config values so MollieService can instantiate
        config([
            'services.mollie.api_url' => 'https://api.mollie.com/v2',
            'services.mollie.oauth_url' => 'https://my.mollie.com/oauth2',
            'services.mollie.oauth_token_url' => 'https://api.mollie.com/oauth2/tokens',
            'services.mollie.platform_key' => 'live_test_key',
            'services.mollie.platform_test_key' => 'test_key_123',
            'services.mollie.default_platform_fee' => 1.50,
        ]);
        $this->service = new MollieService();
    }

    // ========================================================================
    // calculateTotalAmount
    // ========================================================================

    #[Test]
    public function calculate_total_no_platform_mode(): void
    {
        $toernooi = Toernooi::factory()->create(['mollie_mode' => 'connect']);
        $total = $this->service->calculateTotalAmount($toernooi, 100.00);
        $this->assertEquals(100.00, $total);
    }

    #[Test]
    public function calculate_total_platform_fixed_fee(): void
    {
        $toernooi = Toernooi::factory()->create([
            'mollie_mode' => 'platform',
            'platform_toeslag' => 5.00,
            'platform_toeslag_percentage' => false,
        ]);

        $total = $this->service->calculateTotalAmount($toernooi, 100.00);
        $this->assertEquals(105.00, $total);
    }

    #[Test]
    public function calculate_total_platform_percentage_fee(): void
    {
        $toernooi = Toernooi::factory()->create([
            'mollie_mode' => 'platform',
            'platform_toeslag' => 10.00,
            'platform_toeslag_percentage' => true,
        ]);

        $total = $this->service->calculateTotalAmount($toernooi, 100.00);
        $this->assertEqualsWithDelta(110.00, $total, 0.01);
    }

    #[Test]
    public function calculate_total_platform_zero_toeslag(): void
    {
        $toernooi = Toernooi::factory()->create([
            'mollie_mode' => 'platform',
            'platform_toeslag' => 0,
            'platform_toeslag_percentage' => false,
        ]);

        $total = $this->service->calculateTotalAmount($toernooi, 100.00);
        $this->assertEquals(100.00, $total);
    }

    // ========================================================================
    // isAvailable / getCircuitStatus
    // ========================================================================

    #[Test]
    public function is_available_returns_bool(): void
    {
        $this->assertIsBool($this->service->isAvailable());
    }

    #[Test]
    public function get_circuit_status_returns_array(): void
    {
        $status = $this->service->getCircuitStatus();
        $this->assertIsArray($status);
    }

    // ========================================================================
    // getPlatformApiKey
    // ========================================================================

    #[Test]
    public function get_platform_api_key_returns_test_key_in_testing(): void
    {
        $key = $this->service->getPlatformApiKey();
        $this->assertEquals('test_key_123', $key);
    }

    // ========================================================================
    // getApiKeyForToernooi
    // ========================================================================

    #[Test]
    public function get_api_key_platform_mode_returns_platform_key(): void
    {
        $toernooi = Toernooi::factory()->create([
            'mollie_mode' => 'platform',
            'mollie_access_token' => null,
        ]);

        $key = $this->service->getApiKeyForToernooi($toernooi);
        $this->assertEquals('test_key_123', $key);
    }
}
