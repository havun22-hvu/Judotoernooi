<?php

namespace App\Events\Concerns;

use Illuminate\Support\Facades\Log;

/**
 * Trait for broadcast events that should not crash the app when Reverb is down.
 * Use safeBroadcast() instead of dispatch() — data is saved to DB regardless.
 */
trait SafelyBroadcasts
{
    public static function safeBroadcast(mixed ...$args): void
    {
        try {
            static::dispatch(...$args);
        } catch (\Throwable $e) {
            Log::debug("Broadcast skipped (Reverb down): " . class_basename(static::class));
        }
    }
}
