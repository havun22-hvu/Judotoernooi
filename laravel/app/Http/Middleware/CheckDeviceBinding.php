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
            return $this->vrijwilligerError('De link is ongeldig. Vraag een nieuwe link bij de jurytafel.');
        }

        $toegang = DeviceToegang::find($toegangId);

        if (!$toegang) {
            return $this->vrijwilligerError('Deze toegang bestaat niet meer. Vraag een nieuwe link bij de jurytafel.');
        }

        // Check role if specified
        if ($rol && $toegang->rol !== $rol) {
            return $this->vrijwilligerError('Je hebt geen toegang tot deze interface. Vraag de juiste link bij de jurytafel.');
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

    /**
     * Show friendly error page for volunteers (not login page!)
     */
    private function vrijwilligerError(string $message): Response
    {
        return response()->view('errors.vrijwilliger', [
            'message' => $message,
        ], 404);
    }
}
