<?php

namespace Tests\Unit\Services;

use App\Models\Toernooi;
use App\Services\PaymentProviderFactory;
use App\Services\Payments\MolliePaymentProvider;
use App\Services\Payments\StripePaymentProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage voor de payment-provider factory: routes per tournament naar
 * de juiste implementation. Lege Unit-test toegevoegd 2026-04-20 om
 * de gap richting 80 % Unit-coverage te dichten.
 */
class PaymentProviderFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_toernooi_returns_stripe_when_provider_is_stripe(): void
    {
        $toernooi = Toernooi::factory()->create(['payment_provider' => 'stripe']);

        $this->assertInstanceOf(StripePaymentProvider::class, PaymentProviderFactory::forToernooi($toernooi));
    }

    public function test_for_toernooi_defaults_to_mollie_when_provider_is_default_string(): void
    {
        // payment_provider is NOT NULL in schema; default = 'mollie' string
        $toernooi = Toernooi::factory()->create(['payment_provider' => 'mollie']);

        $this->assertInstanceOf(MolliePaymentProvider::class, PaymentProviderFactory::forToernooi($toernooi));
    }

    public function test_for_toernooi_defaults_to_mollie_for_unknown_provider(): void
    {
        $toernooi = Toernooi::factory()->create(['payment_provider' => 'paypal']);

        $this->assertInstanceOf(MolliePaymentProvider::class, PaymentProviderFactory::forToernooi($toernooi));
    }

    public function test_make_returns_stripe_for_stripe_string(): void
    {
        $this->assertInstanceOf(StripePaymentProvider::class, PaymentProviderFactory::make('stripe'));
    }

    public function test_make_defaults_to_mollie_for_unknown_string(): void
    {
        $this->assertInstanceOf(MolliePaymentProvider::class, PaymentProviderFactory::make('paypal'));
    }
}
