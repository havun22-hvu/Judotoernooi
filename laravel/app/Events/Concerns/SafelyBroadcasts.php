<?php

namespace App\Events\Concerns;

use Illuminate\Support\Facades\Log;

/**
 * Override dispatch() so broadcast events NEVER crash the app when Reverb is down.
 * Data is always saved to DB by the controller — broadcast is best-effort.
 *
 * Add `use SafelyBroadcasts;` to any ShouldBroadcastNow event class.
 * No special method needed — just call ::dispatch() as normal.
 */
trait SafelyBroadcasts
{
    public static function dispatch(mixed ...$args): void
    {
        try {
            event(new static(...$args));
        } catch (\Throwable $e) {
            Log::debug('Broadcast skipped (Reverb down): ' . class_basename(static::class));
        }
    }
}
