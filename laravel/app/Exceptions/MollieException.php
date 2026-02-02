<?php

namespace App\Exceptions;

/**
 * Exception for Mollie payment errors.
 *
 * Handles payment-specific errors with appropriate user messages
 * that don't expose sensitive payment details.
 */
class MollieException extends JudoToernooiException
{
    public const ERROR_API_FAILURE = 1001;
    public const ERROR_INVALID_WEBHOOK = 1002;
    public const ERROR_PAYMENT_FAILED = 1003;
    public const ERROR_REFUND_FAILED = 1004;
    public const ERROR_CONFIG_MISSING = 1005;

    protected static array $defaultMessages = [
        self::ERROR_API_FAILURE => 'Betalingssysteem is tijdelijk niet beschikbaar.',
        self::ERROR_INVALID_WEBHOOK => 'Ongeldige betaling notificatie ontvangen.',
        self::ERROR_PAYMENT_FAILED => 'Betaling kon niet worden verwerkt.',
        self::ERROR_REFUND_FAILED => 'Terugbetaling kon niet worden verwerkt.',
        self::ERROR_CONFIG_MISSING => 'Betalingsconfiguratie ontbreekt.',
    ];

    public static function apiFailure(string $technicalMessage, array $context = []): static
    {
        return new static(
            $technicalMessage,
            static::$defaultMessages[self::ERROR_API_FAILURE],
            $context,
            self::ERROR_API_FAILURE
        );
    }

    public static function invalidWebhook(string $reason, array $context = []): static
    {
        return new static(
            "Invalid webhook: {$reason}",
            static::$defaultMessages[self::ERROR_INVALID_WEBHOOK],
            $context,
            self::ERROR_INVALID_WEBHOOK
        );
    }

    public static function paymentFailed(string $reason, array $context = []): static
    {
        return new static(
            "Payment failed: {$reason}",
            static::$defaultMessages[self::ERROR_PAYMENT_FAILED],
            $context,
            self::ERROR_PAYMENT_FAILED
        );
    }

    public static function configMissing(string $what, array $context = []): static
    {
        return new static(
            "Mollie config missing: {$what}",
            static::$defaultMessages[self::ERROR_CONFIG_MISSING],
            $context,
            self::ERROR_CONFIG_MISSING
        );
    }
}
