<?php

namespace App\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Validates broadcast/Reverb configuration at boot time.
 *
 * Catches type mismatches and missing values BEFORE they cause
 * silent failures at runtime. Added after the 2026-04-05 outage
 * where allowed_origins as string crashed every broadcast.
 *
 * Only runs on production/staging — no overhead in local dev.
 */
class BroadcastConfigValidator extends ServiceProvider
{
    public function boot(): void
    {
        if (!$this->app->environment('production', 'staging')) {
            return;
        }

        // Only validate once per deploy, not every request
        $cacheKey = 'broadcast_config_validated:' . config('app.version', filemtime(base_path('composer.lock')));
        if (cache()->has($cacheKey)) {
            return;
        }

        $errors = $this->validate();

        if (!empty($errors)) {
            Log::critical('Broadcast config validation FAILED — events will not reach WebSocket clients', [
                'errors' => $errors,
            ]);
        }

        // Cache for 1 hour regardless of result — don't spam logs
        cache()->put($cacheKey, empty($errors) ? 'ok' : 'fail', 3600);
    }

    private function validate(): array
    {
        $errors = [];

        // The exact bug from 2026-04-05: allowed_origins must be array
        $origins = config('reverb.apps.apps.0.allowed_origins');
        if ($origins !== null && !is_array($origins)) {
            $errors[] = 'reverb.apps.apps.0.allowed_origins is ' . gettype($origins) . ', must be array';
        }

        // Empty driver means no broadcasts
        if (config('broadcasting.default') === 'null') {
            $errors[] = 'BROADCAST_CONNECTION is "null" — all events will be silently dropped';
        }

        // Key/secret missing = auth failures
        if (empty(config('broadcasting.connections.reverb.key'))) {
            $errors[] = 'REVERB_APP_KEY is empty';
        }

        // Port 0 = connection impossible (the env() after config:cache bug)
        $port = config('broadcasting.connections.reverb.options.port');
        if (empty($port) || (int) $port === 0) {
            $errors[] = "Reverb port is '{$port}' — likely env() returning null after config:cache";
        }

        return $errors;
    }
}
