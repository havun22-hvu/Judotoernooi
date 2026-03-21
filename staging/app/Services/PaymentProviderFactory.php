<?php

namespace App\Services;

use App\Contracts\PaymentProviderInterface;
use App\Models\Toernooi;
use App\Services\Payments\MolliePaymentProvider;
use App\Services\Payments\StripePaymentProvider;

class PaymentProviderFactory
{
    /**
     * Get the payment provider for a tournament.
     */
    public static function forToernooi(Toernooi $toernooi): PaymentProviderInterface
    {
        return match ($toernooi->payment_provider) {
            'stripe' => app(StripePaymentProvider::class),
            default => app(MolliePaymentProvider::class),
        };
    }

    /**
     * Get a specific provider by name.
     */
    public static function make(string $provider): PaymentProviderInterface
    {
        return match ($provider) {
            'stripe' => app(StripePaymentProvider::class),
            default => app(MolliePaymentProvider::class),
        };
    }
}
