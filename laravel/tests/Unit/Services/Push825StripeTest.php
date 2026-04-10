<?php

namespace Tests\Unit\Services;

use App\Models\Organisator;
use App\Models\Toernooi;
use App\Services\Payments\StripePaymentProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for simple pure-PHP methods on StripePaymentProvider.
 * Pushes coverage on methods that don't require the Stripe SDK.
 */
class Push825StripeTest extends TestCase
{
    use RefreshDatabase;

    private StripePaymentProvider $provider;
    private Organisator $org;
    private Toernooi $toernooi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new StripePaymentProvider();
        $this->org = Organisator::factory()->create();
        $this->toernooi = Toernooi::factory()->create([
            'organisator_id' => $this->org->id,
            'platform_toeslag' => 0.50,
            'platform_toeslag_percentage' => false,
        ]);
    }

    #[Test]
    public function is_available_checks_config(): void
    {
        config(['services.stripe.secret' => null]);
        $this->assertFalse($this->provider->isAvailable());

        config(['services.stripe.secret' => 'sk_test_123']);
        $this->assertTrue($this->provider->isAvailable());
    }

    #[Test]
    public function is_simulation_mode_true_when_not_production_and_no_secret(): void
    {
        config(['app.env' => 'testing']);
        config(['services.stripe.secret' => null]);

        $this->assertTrue($this->provider->isSimulationMode());
    }

    #[Test]
    public function is_simulation_mode_false_in_production(): void
    {
        config(['app.env' => 'production']);
        config(['services.stripe.secret' => null]);

        $this->assertFalse($this->provider->isSimulationMode());
    }

    #[Test]
    public function is_simulation_mode_false_when_secret_set(): void
    {
        config(['app.env' => 'testing']);
        config(['services.stripe.secret' => 'sk_test_123']);

        $this->assertFalse($this->provider->isSimulationMode());
    }

    #[Test]
    public function simulate_payment_returns_payment_result(): void
    {
        $result = $this->provider->simulatePayment([
            'amount' => ['value' => '25.00', 'currency' => 'EUR'],
            'description' => 'Test payment',
            'metadata' => ['key' => 'value'],
        ]);

        $this->assertStringStartsWith('cs_simulated_', $result->id);
        $this->assertEquals('open', $result->status);
        $this->assertNotNull($result->checkoutUrl);
        $this->assertEquals('25.00', $result->amount);
        $this->assertEquals('EUR', $result->currency);
        $this->assertEquals('Test payment', $result->description);
        $this->assertEquals(['key' => 'value'], $result->metadata);
    }

    #[Test]
    public function simulate_payment_with_minimal_data(): void
    {
        $result = $this->provider->simulatePayment([]);

        $this->assertStringStartsWith('cs_simulated_', $result->id);
        $this->assertEquals('EUR', $result->currency);
    }

    #[Test]
    public function calculate_total_amount_adds_fixed_toeslag(): void
    {
        $this->toernooi->update([
            'platform_toeslag' => 0.50,
            'platform_toeslag_percentage' => false,
        ]);

        $total = $this->provider->calculateTotalAmount($this->toernooi, 10.00);
        $this->assertEquals(10.50, $total);
    }

    #[Test]
    public function calculate_total_amount_adds_percentage_toeslag(): void
    {
        $this->toernooi->update([
            'platform_toeslag' => 5.0, // 5%
            'platform_toeslag_percentage' => true,
        ]);

        $total = $this->provider->calculateTotalAmount($this->toernooi, 100.00);
        $this->assertEquals(105.00, $total);
    }

    #[Test]
    public function calculate_total_amount_uses_toeslag_value(): void
    {
        $this->toernooi->update([
            'platform_toeslag' => 0.75,
            'platform_toeslag_percentage' => false,
        ]);

        $total = $this->provider->calculateTotalAmount($this->toernooi, 10.00);
        $this->assertEquals(10.75, $total);
    }

    #[Test]
    public function get_name_returns_stripe(): void
    {
        $this->assertEquals('stripe', $this->provider->getName());
    }

    #[Test]
    public function generate_and_validate_callback_hash(): void
    {
        $hash = $this->provider->generateCallbackHash($this->toernooi);

        $this->assertIsString($hash);
        $this->assertNotEmpty($hash);
        $this->assertTrue($this->provider->validateCallbackHash($this->toernooi->id, $hash));
    }

    #[Test]
    public function validate_callback_hash_rejects_null_inputs(): void
    {
        $this->assertFalse($this->provider->validateCallbackHash(null, 'somehash'));
        $this->assertFalse($this->provider->validateCallbackHash($this->toernooi->id, null));
        $this->assertFalse($this->provider->validateCallbackHash(null, null));
    }

    #[Test]
    public function validate_callback_hash_rejects_wrong_hash(): void
    {
        $this->assertFalse($this->provider->validateCallbackHash($this->toernooi->id, 'invalid_hash'));
    }

    #[Test]
    public function validate_callback_hash_rejects_different_toernooi(): void
    {
        $otherToernooi = Toernooi::factory()->create(['organisator_id' => $this->org->id]);
        $hash = $this->provider->generateCallbackHash($this->toernooi);

        $this->assertFalse($this->provider->validateCallbackHash($otherToernooi->id, $hash));
    }
}
