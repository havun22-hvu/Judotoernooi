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
            return redirect()->route('toegang.show', [
                'organisator' => $toegang->toernooi->organisator->slug,
                'toernooi' => $toegang->toernooi->slug,
                'code' => $toegang->code,
            ]);
        }

        // Update last active
        $toegang->updateLaatstActief();

        // attributes, not merge(): merge() puts the model in the input bag, where it rides
        // along with $request->all() into anything that echoes input back -- that is how the
        // api_token leaked onto a public channel (f3445e46). Attributes stay out of input.
        $request->attributes->set('device_toegang', $toegang);

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
