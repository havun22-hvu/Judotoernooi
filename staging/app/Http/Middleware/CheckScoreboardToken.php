<?php

namespace App\Http\Middleware;

use App\Models\DeviceToegang;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates Bearer token for scoreboard API requests.
 * Token is checked against device_toegangen.api_token.
 */
class CheckScoreboardToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Token ontbreekt.'], 401);
        }

        $toegang = DeviceToegang::where('api_token', $token)
            ->whereIn('rol', ['scoreboard', 'mat'])
            ->first();

        if (!$toegang) {
            return response()->json(['message' => 'Ongeldig token.'], 401);
        }

        // Update last active timestamp
        $toegang->updateLaatstActief();

        // Store toegang in request for controller access
        $request->merge(['device_toegang' => $toegang]);

        return $next($request);
    }
}
