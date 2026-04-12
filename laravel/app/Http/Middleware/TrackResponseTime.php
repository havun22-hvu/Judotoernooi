<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Track response time for tournament-critical routes.
 * Logs requests taking >1s to help identify performance issues on tournament day.
 */
class TrackResponseTime
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        $durationMs = round((microtime(true) - $start) * 1000, 2);

        if ($durationMs > 1000) {
            $toernooiId = $request->route('toernooi');

            Log::channel('response-time')->warning('Slow response', [
                'path' => $request->path(),
                'response_time_ms' => $durationMs,
                'toernooi_id' => $toernooiId,
                'method' => $request->method(),
            ]);
        }

        return $response;
    }
}
