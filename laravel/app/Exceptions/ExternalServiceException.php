<?php

namespace App\Exceptions;

/**
 * Exception for external service errors.
 *
 * Handles:
 * - Python solver errors (DynamischeIndelingService)
 * - HTTP client errors
 * - External API timeouts
 */
class ExternalServiceException extends JudoToernooiException
{
    public const ERROR_TIMEOUT = 3001;
    public const ERROR_CONNECTION = 3002;
    public const ERROR_PROCESS = 3003;
    public const ERROR_INVALID_RESPONSE = 3004;

    protected string $logLevel = 'error';

    /**
     * Service timed out.
     */
    public static function timeout(string $service, int $timeoutSeconds): static
    {
        return new static(
            "Service {$service} timed out after {$timeoutSeconds}s",
            'De bewerking duurde te lang. Probeer het opnieuw.',
            [
                'service' => $service,
                'timeout' => $timeoutSeconds,
            ],
            self::ERROR_TIMEOUT
        );
    }

    /**
     * Connection failed.
     */
    public static function connectionFailed(string $service, string $error): static
    {
        return new static(
            "Connection to {$service} failed: {$error}",
            'Verbinding mislukt. Probeer het later opnieuw.',
            [
                'service' => $service,
                'error' => $error,
            ],
            self::ERROR_CONNECTION
        );
    }

    /**
     * External process error (Python, etc.).
     */
    public static function processError(string $process, int $exitCode, string $stderr = ''): static
    {
        return new static(
            "Process {$process} failed with exit code {$exitCode}",
            'Berekening mislukt. Er wordt een alternatieve methode gebruikt.',
            [
                'process' => $process,
                'exit_code' => $exitCode,
                'stderr' => substr($stderr, 0, 1000),
            ],
            self::ERROR_PROCESS
        );
    }

    /**
     * Invalid response from service.
     */
    public static function invalidResponse(string $service, string $response): static
    {
        return new static(
            "Invalid response from {$service}",
            'Onverwacht antwoord ontvangen.',
            [
                'service' => $service,
                'response' => substr($response, 0, 500),
            ],
            self::ERROR_INVALID_RESPONSE
        );
    }

    /**
     * Python solver error.
     */
    public static function pythonSolverError(int $exitCode, string $stderr = ''): static
    {
        $exception = new static(
            "Python poule solver failed with exit code {$exitCode}",
            'Automatische indeling mislukt. Handmatige indeling beschikbaar.',
            [
                'exit_code' => $exitCode,
                'stderr' => substr($stderr, 0, 1000),
            ],
            self::ERROR_PROCESS
        );
        $exception->logLevel = 'warning'; // Fallback exists
        return $exception;
    }
}
