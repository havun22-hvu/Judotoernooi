<?php

namespace App\Http\Middleware;

use App\Models\ClubApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates the Bearer token for the HavunClub integration API.
 *
 * The token is matched against club_api_tokens and resolves to exactly one
 * Organisator (the tenant). The resolved token + organisator are stored on the
 * request so controllers never need a tenant parameter.
 *
 * Mirrors {@see CheckScoreboardToken} on purpose: same JSON-401 shape.
 */
class CheckClubToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if (!$bearer) {
            return response()->json(['message' => 'Token ontbreekt.'], 401);
        }

        $token = ClubApiToken::where('token', $bearer)
            ->where('actief', true)
            ->with('organisator')
            ->first();

        if (!$token || !$token->organisator) {
            return response()->json(['message' => 'Ongeldig token.'], 401);
        }

        $token->markUsed();

        // Expose the authenticated tenant to controllers.
        $request->attributes->set('club_token', $token);
        $request->attributes->set('club_organisator', $token->organisator);

        return $next($request);
    }
}
