<?php

namespace App\Exceptions;

/**
 * Exception for Mollie payment errors.
 *
 * Handles:
 * - API errors (timeouts, rate limits, server errors)
 * - OAuth errors (token refresh, authorization)
 * - Payment errors (creation, status updates)
 */
class MollieException extends JudoToernooiException
{
    public const ERROR_API = 1001;
    public const ERROR_TIMEOUT = 1002;
    public const ERROR_OAUTH = 1003;
    public const ERROR_TOKEN_EXPIRED = 1004;
    public const ERROR_PAYMENT_CREATION = 1005;
    public const ERROR_PAYMENT_NOT_FOUND = 1006;
    public const ERROR_WEBHOOK = 1007;
    public const ERROR_INVALID_RESPONSE = 1008;

    protected string $logLevel = 'error';

    /**
     * API call failed.
     */
    public static function apiError(string $endpoint, string $error, ?int $statusCode = null): static
    {
        return new static(
            "Mollie API error on {$endpoint}: {$error}",
            'Er ging iets mis met de betaaldienst. Probeer het later opnieuw.',
            [
                'endpoint' => $endpoint,
                'error' => $error,
                'status_code' => $statusCode,
            ],
            self::ERROR_API
        );
    }

    /**
     * API call timed out.
     */
    public static function timeout(string $endpoint): static
    {
        return new static(
            "Mollie API timeout on {$endpoint}",
            'De betaaldienst reageert niet. Probeer het later opnieuw.',
            ['endpoint' => $endpoint],
            self::ERROR_TIMEOUT
        );
    }

    /**
     * OAuth token exchange failed.
     */
    public static function oauthError(string $error): static
    {
        return new static(
            "Mollie OAuth error: {$error}",
            'Fout bij koppelen met Mollie. Probeer opnieuw te verbinden.',
            ['error' => $error],
            self::ERROR_OAUTH
        );
    }

    /**
     * Token refresh failed.
     */
    public static function tokenExpired(int $toernooiId): static
    {
        return new static(
            "Mollie token refresh failed for toernooi {$toernooiId}",
            'De Mollie koppeling is verlopen. Verbind opnieuw in de toernooi instellingen.',
            ['toernooi_id' => $toernooiId],
            self::ERROR_TOKEN_EXPIRED
        );
    }

    /**
     * Payment creation failed.
     */
    public static function paymentCreationFailed(string $error, int $toernooiId): static
    {
        return new static(
            "Payment creation failed for toernooi {$toernooiId}: {$error}",
            'Betaling kon niet worden aangemaakt. Controleer de instellingen.',
            [
                'toernooi_id' => $toernooiId,
                'error' => $error,
            ],
            self::ERROR_PAYMENT_CREATION
        );
    }

    /**
     * Payment not found.
     */
    public static function paymentNotFound(string $paymentId): static
    {
        $exception = new static(
            "Payment not found: {$paymentId}",
            'Betaling niet gevonden.',
            ['payment_id' => $paymentId],
            self::ERROR_PAYMENT_NOT_FOUND
        );
        $exception->logLevel = 'warning';
        return $exception;
    }

    /**
     * Webhook processing error.
     */
    public static function webhookError(string $paymentId, string $error): static
    {
        return new static(
            "Webhook processing failed for payment {$paymentId}: {$error}",
            'Betaling status kon niet worden bijgewerkt.',
            [
                'payment_id' => $paymentId,
                'error' => $error,
            ],
            self::ERROR_WEBHOOK
        );
    }

    /**
     * Invalid response from Mollie.
     */
    public static function invalidResponse(string $endpoint, string $response): static
    {
        return new static(
            "Invalid response from Mollie {$endpoint}",
            'Onverwacht antwoord van de betaaldienst.',
            [
                'endpoint' => $endpoint,
                'response' => substr($response, 0, 500),
            ],
            self::ERROR_INVALID_RESPONSE
        );
    }
}
