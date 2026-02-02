<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Base exception for all JudoToernooi specific exceptions.
 *
 * Provides consistent error handling with:
 * - User-friendly messages (safe to display)
 * - Technical details for logging
 * - Contextual data for debugging
 */
class JudoToernooiException extends Exception
{
    protected string $userMessage;
    protected array $context = [];

    public function __construct(
        string $technicalMessage,
        string $userMessage = 'Er ging iets mis. Probeer het opnieuw.',
        array $context = [],
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($technicalMessage, $code, $previous);
        $this->userMessage = $userMessage;
        $this->context = $context;
    }

    /**
     * Get safe message for display to users.
     */
    public function getUserMessage(): string
    {
        return $this->userMessage;
    }

    /**
     * Get context data for logging/debugging.
     */
    public function getContext(): array
    {
        return array_merge($this->context, [
            'exception_class' => static::class,
            'technical_message' => $this->getMessage(),
        ]);
    }

    /**
     * Log this exception with appropriate level and context.
     */
    public function log(string $level = 'warning'): void
    {
        $context = $this->getContext();

        if ($this->getPrevious()) {
            $context['previous_exception'] = $this->getPrevious()->getMessage();
        }

        Log::$level($this->getMessage(), $context);
    }

    /**
     * Create from another exception with user-friendly message.
     */
    public static function fromException(
        Exception $e,
        string $userMessage = 'Er ging iets mis. Probeer het opnieuw.',
        array $context = []
    ): static {
        return new static(
            $e->getMessage(),
            $userMessage,
            $context,
            (int) $e->getCode(),
            $e
        );
    }
}
