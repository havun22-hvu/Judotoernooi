<?php

namespace Tests\Unit\DTOs;

use App\DTOs\PaymentResult;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentResultTest extends TestCase
{
    // ========================================================================
    // Status Methods
    // ========================================================================

    #[Test]
    public function is_paid_returns_true_for_paid_status(): void
    {
        $result = new PaymentResult(id: 'tr_123', status: 'paid');

        $this->assertTrue($result->isPaid());
        $this->assertFalse($result->isOpen());
        $this->assertFalse($result->isFailed());
    }

    #[Test]
    public function is_open_returns_true_for_open_and_pending(): void
    {
        $open = new PaymentResult(id: 'tr_123', status: 'open');
        $pending = new PaymentResult(id: 'tr_456', status: 'pending');

        $this->assertTrue($open->isOpen());
        $this->assertTrue($pending->isOpen());
        $this->assertFalse($open->isPaid());
    }

    #[Test]
    public function is_failed_returns_true_for_failed_statuses(): void
    {
        $failed = new PaymentResult(id: 'tr_1', status: 'failed');
        $expired = new PaymentResult(id: 'tr_2', status: 'expired');
        $canceled = new PaymentResult(id: 'tr_3', status: 'canceled');

        $this->assertTrue($failed->isFailed());
        $this->assertTrue($expired->isFailed());
        $this->assertTrue($canceled->isFailed());
    }

    // ========================================================================
    // Constructor & Properties
    // ========================================================================

    #[Test]
    public function constructor_sets_all_properties(): void
    {
        $result = new PaymentResult(
            id: 'tr_test',
            status: 'paid',
            checkoutUrl: 'https://checkout.example.com',
            amount: '25.00',
            currency: 'EUR',
            description: 'Toernooi inschrijving',
            metadata: ['toernooi_id' => 1],
        );

        $this->assertEquals('tr_test', $result->id);
        $this->assertEquals('paid', $result->status);
        $this->assertEquals('https://checkout.example.com', $result->checkoutUrl);
        $this->assertEquals('25.00', $result->amount);
        $this->assertEquals('EUR', $result->currency);
        $this->assertEquals('Toernooi inschrijving', $result->description);
        $this->assertEquals(['toernooi_id' => 1], $result->metadata);
    }

    #[Test]
    public function optional_fields_default_to_null(): void
    {
        $result = new PaymentResult(id: 'tr_minimal', status: 'open');

        $this->assertNull($result->checkoutUrl);
        $this->assertNull($result->amount);
        $this->assertNull($result->currency);
        $this->assertNull($result->description);
        $this->assertNull($result->metadata);
    }

    // ========================================================================
    // fromMollie
    // ========================================================================

    #[Test]
    public function from_mollie_maps_correctly(): void
    {
        $molliePayment = (object) [
            'id' => 'tr_mollie123',
            'status' => 'paid',
            'amount' => (object) ['value' => '15.00', 'currency' => 'EUR'],
            'description' => 'Test betaling',
            '_links' => (object) [
                'checkout' => (object) ['href' => 'https://mollie.com/pay/123'],
            ],
            'metadata' => (object) ['club_id' => 5],
        ];

        $result = PaymentResult::fromMollie($molliePayment);

        $this->assertEquals('tr_mollie123', $result->id);
        $this->assertEquals('paid', $result->status);
        $this->assertEquals('15.00', $result->amount);
        $this->assertEquals('EUR', $result->currency);
        $this->assertEquals('https://mollie.com/pay/123', $result->checkoutUrl);
        $this->assertTrue($result->isPaid());
    }

    #[Test]
    public function from_mollie_handles_missing_checkout_link(): void
    {
        $molliePayment = (object) [
            'id' => 'tr_no_checkout',
            'status' => 'open',
            'amount' => (object) ['value' => '10.00', 'currency' => 'EUR'],
            '_links' => (object) [],
        ];

        $result = PaymentResult::fromMollie($molliePayment);

        $this->assertNull($result->checkoutUrl);
    }

    // ========================================================================
    // PaymentProviderFactory (basic logic test)
    // ========================================================================

    #[Test]
    public function unknown_status_is_not_paid_open_or_failed(): void
    {
        $result = new PaymentResult(id: 'tr_weird', status: 'refunded');

        $this->assertFalse($result->isPaid());
        $this->assertFalse($result->isOpen());
        $this->assertFalse($result->isFailed());
    }
}
