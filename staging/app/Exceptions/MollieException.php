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
    public const ERROR_API = 1001;
    public const ERROR_INVALID_WEBHOOK = 1002;
    public const ERROR_PAYMENT_FAILED = 1003;
    public const ERROR_PAYMENT_NOT_FOUND = 1004;
    public const ERROR_PAYMENT_CREATION = 1005;
    public const ERROR_OAUTH = 1006;
    public const ERROR_TIMEOUT = 1007;
    public const ERROR_TOKEN_EXPIRED = 1008;
    public const ERROR_CONFIG_MISSING = 1009;

    protected static array $defaultMessages = [
        self::ERROR_API => 'Betalingssysteem is tijdelijk niet beschikbaar.',
        self::ERROR_INVALID_WEBHOOK => 'Ongeldige betaling notificatie ontvangen.',
        self::ERROR_PAYMENT_FAILED => 'Betaling kon niet worden verwerkt.',
        self::ERROR_PAYMENT_NOT_FOUND => 'Betaling niet gevonden.',
        self::ERROR_PAYMENT_CREATION => 'Betaling aanmaken mislukt.',
        self::ERROR_OAUTH => 'Mollie koppeling mislukt.',
        self::ERROR_TIMEOUT => 'Betalingssysteem reageert niet.',
        self::ERROR_TOKEN_EXPIRED => 'Mollie koppeling verlopen. Koppel opnieuw.',
        self::ERROR_CONFIG_MISSING => 'Betalingsconfiguratie ontbreekt.',
    ];

    public static function apiError(string $endpoint, string $message, ?int $httpStatus = null): static
    {
        return new static(
            "Mollie API error on {$endpoint}: {$message}" . ($httpStatus ? " (HTTP {$httpStatus})" : ''),
            static::$defaultMessages[self::ERROR_API],
            ['endpoint' => $endpoint, 'http_status' => $httpStatus],
            self::ERROR_API
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

    public static function paymentNotFound(string $paymentId): static
    {
        return new static(
            "Payment not found: {$paymentId}",
            static::$defaultMessages[self::ERROR_PAYMENT_NOT_FOUND],
            ['payment_id' => $paymentId],
            self::ERROR_PAYMENT_NOT_FOUND
        );
    }

    public static function paymentCreationFailed(string $reason, ?int $toernooiId = null): static
    {
        return new static(
            "Payment creation failed: {$reason}",
            static::$defaultMessages[self::ERROR_PAYMENT_CREATION],
            ['toernooi_id' => $toernooiId],
            self::ERROR_PAYMENT_CREATION
        );
    }

    public static function oauthError(string $message): static
    {
        return new static(
            "OAuth error: {$message}",
            static::$defaultMessages[self::ERROR_OAUTH],
            [],
            self::ERROR_OAUTH
        );
    }

    public static function timeout(string $endpoint): static
    {
        return new static(
            "Mollie request timeout on {$endpoint}",
            static::$defaultMessages[self::ERROR_TIMEOUT],
            ['endpoint' => $endpoint],
            self::ERROR_TIMEOUT
        );
    }

    public static function tokenExpired(?int $toernooiId = null): static
    {
        return new static(
            "Mollie OAuth token expired" . ($toernooiId ? " for toernooi {$toernooiId}" : ''),
            static::$defaultMessages[self::ERROR_TOKEN_EXPIRED],
            ['toernooi_id' => $toernooiId],
            self::ERROR_TOKEN_EXPIRED
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
