<?php

namespace App\Http\Middleware;

use App\Http\Controllers\AuthController;
use App\Models\Toernooi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckToernooiRol
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$rollen  Toegestane rollen (admin, jury, weging, mat)
     */
    public function handle(Request $request, Closure $next, string ...$rollen): Response
    {
        $toernooi = $request->route('toernooi');

        // Debug logging
        \Log::info('CheckToernooiRol debug', [
            'toernooi_type' => gettype($toernooi),
            'toernooi_class' => is_object($toernooi) ? get_class($toernooi) : null,
            'organisator_check' => auth('organisator')->check(),
            'organisator_id' => auth('organisator')->id(),
            'expects_json' => $request->expectsJson(),
        ]);

        if (!$toernooi instanceof Toernooi) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Toernooi niet gevonden'], 404);
            }
            return redirect()->route('dashboard');
        }

        // Check of organisator ingelogd is via auth guard
        $organisator = auth('organisator')->user();
        if ($organisator && $organisator->hasAccessToToernooi($toernooi)) {
            return $next($request);
        }

        $huidigeRol = AuthController::getRol($request, $toernooi);

        // Niet ingelogd
        if (!$huidigeRol) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Niet ingelogd'], 401);
            }
            return redirect()
                ->route('toernooi.auth.login', $toernooi)
                ->with('error', 'Je moet eerst inloggen');
        }

        // Admin heeft altijd toegang
        if ($huidigeRol === 'admin') {
            return $next($request);
        }

        // Check of huidige rol toegang heeft
        if (!in_array($huidigeRol, $rollen)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Geen toegang'], 403);
            }
            return redirect()
                ->route('toernooi.auth.login', $toernooi)
                ->with('error', 'Je hebt geen toegang tot deze pagina');
        }

        return $next($request);
    }
}
