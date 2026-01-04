<?php

namespace App\Http\Middleware;

use App\Models\DeviceToegang;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckDeviceBinding
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $rol = null): Response
    {
        $toegangId = $request->route('toegang');

        if (!$toegangId) {
            abort(404, 'Geen toegang ID gevonden');
        }

        $toegang = DeviceToegang::find($toegangId);

        if (!$toegang) {
            abort(404, 'Toegang niet gevonden');
        }

        // Check role if specified
        if ($rol && $toegang->rol !== $rol) {
            abort(403, 'Geen toegang tot deze interface');
        }

        // Check device binding
        $deviceToken = $request->cookie('device_token_' . $toegang->id);

        if (!$deviceToken || $toegang->device_token !== $deviceToken) {
            // Not bound or wrong device, redirect to PIN entry
            return redirect()->route('toegang.show', $toegang->code);
        }

        // Update last active
        $toegang->updateLaatstActief();

        // Store toegang in request for controllers
        $request->merge(['device_toegang' => $toegang]);

        return $next($request);
    }
}
