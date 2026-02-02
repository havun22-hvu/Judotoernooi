<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Base exception for JudoToernooi application.
 *
 * Provides consistent error handling with:
 * - User-friendly messages (Dutch)
 * - Context for logging
 * - Error codes for categorization
 */
class JudoToernooiException extends Exception
{
    protected array $context = [];
    protected string $userMessage;
    protected string $logLevel = 'warning';

    public function __construct(
        string $message,
        string $userMessage = '',
        array $context = [],
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->userMessage = $userMessage ?: 'Er ging iets mis. Probeer het opnieuw.';
        $this->context = $context;
    }

    /**
     * Get user-friendly message (Dutch) for display.
     */
    public function getUserMessage(): string
    {
        return $this->userMessage;
    }

    /**
     * Get context for logging.
     */
    public function getContext(): array
    {
        return array_merge($this->context, [
            'exception_class' => static::class,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Get log level for this exception.
     */
    public function getLogLevel(): string
    {
        return $this->logLevel;
    }

    /**
     * Log this exception with appropriate level and context.
     */
    public function log(): void
    {
        $context = $this->getContext();
        $context['trace'] = $this->getTraceAsString();

        match ($this->logLevel) {
            'error' => Log::error($this->getMessage(), $context),
            'warning' => Log::warning($this->getMessage(), $context),
            'info' => Log::info($this->getMessage(), $context),
            default => Log::warning($this->getMessage(), $context),
        };
    }

    /**
     * Create exception with error-level logging.
     */
    public static function error(string $message, string $userMessage = '', array $context = []): static
    {
        $exception = new static($message, $userMessage, $context);
        $exception->logLevel = 'error';
        return $exception;
    }

    /**
     * Create exception with warning-level logging.
     */
    public static function warning(string $message, string $userMessage = '', array $context = []): static
    {
        $exception = new static($message, $userMessage, $context);
        $exception->logLevel = 'warning';
        return $exception;
    }
}
