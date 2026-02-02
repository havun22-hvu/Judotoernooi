<?php

namespace App\Exceptions;

/**
 * Exception for external service failures.
 *
 * Used for API calls, webhooks, and third-party integrations.
 */
class ExternalServiceException extends JudoToernooiException
{
    public const ERROR_TIMEOUT = 3001;
    public const ERROR_CONNECTION = 3002;
    public const ERROR_INVALID_RESPONSE = 3003;
    public const ERROR_RATE_LIMITED = 3004;
    public const ERROR_AUTHENTICATION = 3005;

    protected ?string $serviceName = null;
    protected ?int $httpStatusCode = null;

    public static function timeout(string $service, int $timeoutSeconds, array $context = []): static
    {
        $exception = new static(
            "{$service} request timed out after {$timeoutSeconds}s",
            'Externe service reageert niet. Probeer het later opnieuw.',
            array_merge($context, ['service' => $service, 'timeout' => $timeoutSeconds]),
            self::ERROR_TIMEOUT
        );
        $exception->serviceName = $service;
        return $exception;
    }

    public static function connection(string $service, string $reason, array $context = []): static
    {
        $exception = new static(
            "{$service} connection failed: {$reason}",
            'Kan geen verbinding maken met externe service.',
            array_merge($context, ['service' => $service]),
            self::ERROR_CONNECTION
        );
        $exception->serviceName = $service;
        return $exception;
    }

    public static function invalidResponse(string $service, string $reason, ?int $statusCode = null, array $context = []): static
    {
        $exception = new static(
            "{$service} returned invalid response: {$reason}",
            'Onverwacht antwoord van externe service.',
            array_merge($context, ['service' => $service, 'status_code' => $statusCode]),
            self::ERROR_INVALID_RESPONSE
        );
        $exception->serviceName = $service;
        $exception->httpStatusCode = $statusCode;
        return $exception;
    }

    public static function rateLimited(string $service, ?int $retryAfter = null, array $context = []): static
    {
        $exception = new static(
            "{$service} rate limit exceeded" . ($retryAfter ? ", retry after {$retryAfter}s" : ''),
            'Te veel verzoeken. Wacht even en probeer opnieuw.',
            array_merge($context, ['service' => $service, 'retry_after' => $retryAfter]),
            self::ERROR_RATE_LIMITED
        );
        $exception->serviceName = $service;
        return $exception;
    }

    public static function authentication(string $service, string $reason, array $context = []): static
    {
        $exception = new static(
            "{$service} authentication failed: {$reason}",
            'Authenticatie met externe service mislukt.',
            array_merge($context, ['service' => $service]),
            self::ERROR_AUTHENTICATION
        );
        $exception->serviceName = $service;
        return $exception;
    }

    public function getServiceName(): ?string
    {
        return $this->serviceName;
    }

    public function getHttpStatusCode(): ?int
    {
        return $this->httpStatusCode;
    }
}
