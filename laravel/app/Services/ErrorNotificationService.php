<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service for sending critical error notifications to remote monitoring.
 *
 * Allows HavunCore to receive real-time alerts about critical errors
 * so they can be investigated and resolved quickly.
 *
 * Configure webhook URL in .env: ERROR_NOTIFICATION_WEBHOOK=https://...
 */
class ErrorNotificationService
{
    private const TIMEOUT = 5; // Quick timeout - don't block the app
    private const MAX_CONTEXT_LENGTH = 5000;

    /**
     * Notify about a critical error.
     *
     * @param string $title Short error title
     * @param string $message Detailed error message
     * @param array $context Additional context (user, request, etc.)
     * @param string $severity 'critical', 'error', 'warning'
     */
    public function notify(string $title, string $message, array $context = [], string $severity = 'error'): void
    {
        $webhookUrl = config('services.error_notification.webhook_url');

        // Guard: skip if not configured
        if (empty($webhookUrl)) {
            return;
        }

        // Build notification payload
        $payload = $this->buildPayload($title, $message, $context, $severity);

        // Send async (fire and forget)
        try {
            Http::timeout(self::TIMEOUT)
                ->connectTimeout(2)
                ->async()
                ->post($webhookUrl, $payload);
        } catch (\Exception $e) {
            // Log locally but don't throw - notification failure shouldn't break the app
            Log::warning('Failed to send error notification', [
                'error' => $e->getMessage(),
                'original_title' => $title,
            ]);
        }
    }

    /**
     * Notify about a critical error from an exception.
     */
    public function notifyException(\Throwable $exception, array $context = []): void
    {
        $title = get_class($exception) . ': ' . substr($exception->getMessage(), 0, 100);

        $context = array_merge($context, [
            'exception_class' => get_class($exception),
            'exception_code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $this->formatTrace($exception),
        ]);

        $this->notify(
            $title,
            $exception->getMessage(),
            $context,
            'critical'
        );
    }

    /**
     * Build the notification payload.
     */
    private function buildPayload(string $title, string $message, array $context, string $severity): array
    {
        return [
            'app' => 'JudoToernooi',
            'environment' => config('app.env'),
            'server' => gethostname(),
            'timestamp' => now()->toIso8601String(),
            'severity' => $severity,
            'title' => $title,
            'message' => substr($message, 0, 2000),
            'context' => $this->sanitizeContext($context),
            'url' => request()?->fullUrl(),
            'method' => request()?->method(),
            'user_id' => auth()->id(),
            'ip' => request()?->ip(),
        ];
    }

    /**
     * Sanitize context to remove sensitive data and limit size.
     */
    private function sanitizeContext(array $context): array
    {
        // Remove sensitive keys
        $sensitiveKeys = ['password', 'token', 'secret', 'key', 'authorization', 'cookie'];

        $sanitized = [];
        foreach ($context as $key => $value) {
            // Skip sensitive data
            if ($this->isSensitiveKey($key, $sensitiveKeys)) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }

            // Convert objects/arrays to strings if too complex
            if (is_array($value)) {
                $value = json_encode($value, JSON_PRETTY_PRINT);
            } elseif (is_object($value)) {
                $value = get_class($value) . ': ' . (method_exists($value, '__toString') ? (string)$value : '[object]');
            }

            $sanitized[$key] = substr((string)$value, 0, 1000);
        }

        // Limit total context size
        $json = json_encode($sanitized);
        if (strlen($json) > self::MAX_CONTEXT_LENGTH) {
            return ['truncated' => true, 'keys' => array_keys($sanitized)];
        }

        return $sanitized;
    }

    /**
     * Check if a key contains sensitive data.
     */
    private function isSensitiveKey(string $key, array $sensitiveKeys): bool
    {
        $lower = strtolower($key);
        foreach ($sensitiveKeys as $sensitive) {
            if (str_contains($lower, $sensitive)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Format stack trace (limit lines).
     */
    private function formatTrace(\Throwable $exception): string
    {
        $trace = $exception->getTraceAsString();
        $lines = explode("\n", $trace);

        // Keep only first 15 lines
        if (count($lines) > 15) {
            $lines = array_slice($lines, 0, 15);
            $lines[] = '... (truncated)';
        }

        return implode("\n", $lines);
    }

    /**
     * Send immediate notification (synchronous, for critical errors).
     */
    public function notifyImmediate(string $title, string $message, array $context = []): bool
    {
        $webhookUrl = config('services.error_notification.webhook_url');

        if (empty($webhookUrl)) {
            return false;
        }

        $payload = $this->buildPayload($title, $message, $context, 'critical');

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->connectTimeout(2)
                ->post($webhookUrl, $payload);

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('Failed to send immediate error notification', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
