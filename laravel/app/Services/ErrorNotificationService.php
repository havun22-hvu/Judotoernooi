<?php

namespace App\Services;

use App\Models\AutofixProposal;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Service for logging critical errors to the admin panel.
 *
 * Errors are stored in the autofix_proposals table with status 'error'
 * and visible at /admin/autofix alongside AutoFix proposals.
 */
class ErrorNotificationService
{
    protected bool $enabled;

    public function __construct()
    {
        $this->enabled = config('app.error_notifications', false);
    }

    /**
     * Log an exception to the admin panel.
     */
    public function notifyException(Throwable $e, array $context = []): void
    {
        if (!$this->shouldNotify()) {
            return;
        }

        Log::error('Critical exception notification', [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile() . ':' . $e->getLine(),
        ]);

        $this->storeError($e, $context);
    }

    /**
     * Log a critical event (not necessarily an exception).
     */
    public function notifyCritical(string $message, array $context = []): void
    {
        if (!$this->shouldNotify()) {
            return;
        }

        Log::critical($message, $context);

        $this->storeCritical($message, $context);
    }

    protected function shouldNotify(): bool
    {
        return $this->enabled || app()->environment('production');
    }

    protected function storeError(Throwable $e, array $context): void
    {
        try {
            // Rate limit: don't store duplicate errors within 10 minutes
            if (AutofixProposal::recentlyAnalyzed(get_class($e), $e->getFile(), $e->getLine())) {
                return;
            }

            $trace = collect($e->getTrace())->take(10)->map(function ($frame) {
                $file = $frame['file'] ?? 'unknown';
                $line = $frame['line'] ?? 0;
                $func = ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '');
                return "{$file}:{$line} {$func}";
            })->implode("\n");

            AutofixProposal::create([
                'exception_class' => get_class($e),
                'exception_message' => Str::limit($e->getMessage(), 1000),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'stack_trace' => $trace,
                'code_context' => json_encode($context, JSON_PRETTY_PRINT),
                'approval_token' => Str::random(64),
                'status' => 'error',
                'url' => request()->fullUrl() ?? null,
                'http_method' => request()->method() ?? null,
                'route_name' => request()->route()?->getName(),
                'organisator_id' => auth('organisator')->id() ?? null,
                'organisator_naam' => auth('organisator')->user()?->naam ?? null,
                'toernooi_id' => $context['toernooi_id'] ?? null,
                'toernooi_naam' => $context['toernooi_naam'] ?? null,
            ]);
        } catch (Throwable $storeError) {
            // Don't let storage failures break error handling
            Log::warning('Failed to store error notification', [
                'error' => $storeError->getMessage(),
            ]);
        }
    }

    protected function storeCritical(string $message, array $context): void
    {
        try {
            AutofixProposal::create([
                'exception_class' => 'CriticalEvent',
                'exception_message' => Str::limit($message, 1000),
                'file' => $context['file'] ?? 'unknown',
                'line' => $context['line'] ?? 0,
                'stack_trace' => '',
                'code_context' => json_encode($context, JSON_PRETTY_PRINT),
                'approval_token' => Str::random(64),
                'status' => 'error',
                'url' => request()->fullUrl() ?? null,
                'http_method' => request()->method() ?? null,
                'route_name' => request()->route()?->getName(),
            ]);
        } catch (Throwable $storeError) {
            Log::warning('Failed to store critical notification', [
                'error' => $storeError->getMessage(),
            ]);
        }
    }
}
