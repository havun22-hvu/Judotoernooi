<?php

namespace App\Events\Concerns;

use App\Support\CircuitBreaker;
use Illuminate\Support\Facades\Log;

/**
 * Override dispatch() so broadcast events NEVER crash the app.
 *
 * Three layers of protection:
 * 1. Circuit breaker: after 3 failures, skip broadcasts for 30s (no timeout waiting)
 * 2. Try-catch: any unexpected exception is caught and logged
 * 3. Log throttling: only logs once per minute per event type (no log spam)
 *
 * Data is always saved to DB by the controller — broadcast is best-effort.
 * Add `use SafelyBroadcasts;` to any ShouldBroadcastNow event class.
 */
trait SafelyBroadcasts
{
    public static function dispatch(mixed ...$args): void
    {
        $breaker = new CircuitBreaker(
            service: 'reverb',
            failureThreshold: 3,
            recoveryTimeout: 30,
        );

        if (!$breaker->isAvailable()) {
            static::logThrottled('Broadcast skipped (circuit open): ' . class_basename(static::class));
            return;
        }

        try {
            $breaker->call(function () use ($args) {
                event(new static(...$args));
            });
        } catch (\Throwable $e) {
            static::logThrottled(
                'Broadcast failed: ' . class_basename(static::class) . ' — ' . $e->getMessage()
            );
        }
    }

    /**
     * Log at most once per minute per event class to prevent log spam.
     */
    private static function logThrottled(string $message): void
    {
        $key = 'broadcast_log:' . class_basename(static::class);

        if (cache()->has($key)) {
            return;
        }

        cache()->put($key, true, 60);
        Log::warning($message);
    }
}
