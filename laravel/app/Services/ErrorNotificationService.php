<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Service for sending error notifications to administrators.
 *
 * Used for critical production errors that require immediate attention.
 */
class ErrorNotificationService
{
    protected ?string $adminEmail;
    protected bool $enabled;

    public function __construct()
    {
        $this->adminEmail = config('mail.admin_email');
        $this->enabled = config('app.error_notifications', false);
    }

    /**
     * Notify admin about an exception.
     */
    public function notifyException(Throwable $e, array $context = []): void
    {
        if (!$this->shouldNotify()) {
            return;
        }

        $data = $this->formatExceptionData($e, $context);

        // Log critical errors (visible in Laravel log + admin panel)
        Log::error('Critical exception notification', $data);
    }

    /**
     * Notify about a critical event (not necessarily an exception).
     */
    public function notifyCritical(string $message, array $context = []): void
    {
        if (!$this->shouldNotify()) {
            return;
        }

        Log::critical($message, $context);
    }

    protected function shouldNotify(): bool
    {
        return $this->enabled || app()->environment('production');
    }

    protected function formatExceptionData(Throwable $e, array $context): array
    {
        return [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => collect($e->getTrace())->take(5)->map(function ($frame) {
                return [
                    'file' => $frame['file'] ?? 'unknown',
                    'line' => $frame['line'] ?? 0,
                    'function' => ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? ''),
                ];
            })->toArray(),
            'context' => $context,
            'timestamp' => now()->toIso8601String(),
            'environment' => app()->environment(),
        ];
    }

}
