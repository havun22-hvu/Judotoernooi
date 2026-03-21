<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRolSessie
{
    /**
     * Handle an incoming request.
     * Check if user has valid role session from /team/{code} access.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $toernooiId = $request->session()->get('rol_toernooi_id');
        $rol = $request->session()->get('rol_type');

        if (!$toernooiId || !$rol) {
            abort(403, 'Geen toegang. Gebruik de link die je hebt ontvangen.');
        }

        return $next($request);
    }
}
