<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Detects offline mode and adjusts app behavior.
 * When OFFLINE_MODE=true, skips normal auth and uses device PIN only.
 */
class OfflineMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!self::isOffline()) {
            return $next($request);
        }

        // Share offline status with all views
        view()->share('offlineMode', true);
        view()->share('offlineToernooiId', config('app.offline_toernooi_id'));

        return $next($request);
    }

    /**
     * Check if the app is running in offline mode.
     */
    public static function isOffline(): bool
    {
        return config('app.offline_mode', false);
    }
}
