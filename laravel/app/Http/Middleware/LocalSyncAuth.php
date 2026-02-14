<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protects local-server sync routes from unauthorized access.
 *
 * Allows access when:
 * - App is in offline mode (local server, no internet)
 * - Request comes from a private/local IP (LAN)
 * - Request has a valid LOCAL_SYNC_TOKEN bearer token
 */
class LocalSyncAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        // Offline mode: local server, always allow
        if (config('app.offline_mode', false)) {
            return $next($request);
        }

        // Private/local IP: LAN access during tournament day
        if ($this->isPrivateIp($request->ip())) {
            return $next($request);
        }

        // Bearer token: remote access with shared secret
        $token = config('local-server.sync_token');
        if ($token && $request->bearerToken() === $token) {
            return $next($request);
        }

        abort(403, 'Unauthorized access to local sync routes.');
    }

    private function isPrivateIp(?string $ip): bool
    {
        if (!$ip) {
            return false;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE) === false
            || str_starts_with($ip, '192.168.')
            || str_starts_with($ip, '10.')
            || str_starts_with($ip, '172.16.')
            || $ip === '127.0.0.1'
            || $ip === '::1';
    }
}
