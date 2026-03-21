<?php

namespace App\DTOs;

class PaymentResult
{
    public function __construct(
        public readonly string $id,
        public readonly string $status,
        public readonly ?string $checkoutUrl = null,
        public readonly ?string $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $description = null,
        public readonly ?array $metadata = null,
    ) {}

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'pending']);
    }

    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'expired', 'canceled']);
    }

    /**
     * Create from Mollie API response object.
     */
    public static function fromMollie(object $molliePayment): self
    {
        return new self(
            id: $molliePayment->id,
            status: $molliePayment->status,
            checkoutUrl: $molliePayment->_links->checkout->href ?? null,
            amount: $molliePayment->amount->value ?? null,
            currency: $molliePayment->amount->currency ?? null,
            description: $molliePayment->description ?? null,
            metadata: isset($molliePayment->metadata) ? (array) $molliePayment->metadata : null,
        );
    }

    /**
     * Create from Stripe Checkout Session.
     */
    public static function fromStripe(\Stripe\Checkout\Session $session): self
    {
        $statusMap = [
            'complete' => 'paid',
            'open' => 'open',
            'expired' => 'expired',
        ];

        return new self(
            id: $session->id,
            status: $statusMap[$session->status] ?? $session->status,
            checkoutUrl: $session->url,
            amount: $session->amount_total ? number_format($session->amount_total / 100, 2, '.', '') : null,
            currency: strtoupper($session->currency ?? 'EUR'),
            description: $session->metadata['description'] ?? null,
            metadata: $session->metadata ? $session->metadata->toArray() : null,
        );
    }
}
