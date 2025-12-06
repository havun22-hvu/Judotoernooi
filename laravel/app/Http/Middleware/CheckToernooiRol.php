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

        if (!$toernooi instanceof Toernooi) {
            return redirect()->route('dashboard');
        }

        $huidigeRol = AuthController::getRol($request, $toernooi);

        // Niet ingelogd
        if (!$huidigeRol) {
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
            return redirect()
                ->route('toernooi.auth.login', $toernooi)
                ->with('error', 'Je hebt geen toegang tot deze pagina');
        }

        return $next($request);
    }
}
