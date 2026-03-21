<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckFreemiumPrint
{
    /**
     * Handle an incoming request.
     * Block print/noodplan routes for free tier tournaments.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $toernooi = $request->route('toernooi');

        if (!$toernooi) {
            return $next($request);
        }

        // Check if toernooi is on free tier
        if ($toernooi->isFreeTier()) {
            // Return a view explaining the limitation
            return response()->view('pages.noodplan.upgrade-required', [
                'toernooi' => $toernooi,
            ], 403);
        }

        return $next($request);
    }
}
