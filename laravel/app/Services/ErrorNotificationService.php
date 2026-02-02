<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

        // Log critical errors
        Log::error('Critical exception notification', $data);

        // Optional: Send email notification
        if ($this->adminEmail && config('app.env') === 'production') {
            $this->sendEmailNotification($e, $data);
        }
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

    protected function sendEmailNotification(Throwable $e, array $data): void
    {
        try {
            Mail::raw(
                $this->formatEmailBody($e, $data),
                function ($message) use ($e) {
                    $message->to($this->adminEmail)
                        ->subject('[JudoToernooi] Critical Error: ' . substr($e->getMessage(), 0, 50));
                }
            );
        } catch (Throwable $mailError) {
            // Don't let mail failures break error handling
            Log::warning('Failed to send error notification email', [
                'error' => $mailError->getMessage(),
            ]);
        }
    }

    protected function formatEmailBody(Throwable $e, array $data): string
    {
        return implode("\n", [
            'Exception: ' . get_class($e),
            'Message: ' . $e->getMessage(),
            'File: ' . $e->getFile() . ':' . $e->getLine(),
            '',
            'Context:',
            json_encode($data['context'] ?? [], JSON_PRETTY_PRINT),
            '',
            'Timestamp: ' . $data['timestamp'],
            'Environment: ' . $data['environment'],
        ]);
    }
}
