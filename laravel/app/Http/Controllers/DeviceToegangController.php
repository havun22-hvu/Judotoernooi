<?php

namespace App\Http\Controllers;

use App\Models\DeviceToegang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class DeviceToegangController extends Controller
{
    /**
     * Show PIN input page for device binding.
     */
    public function show(string $code)
    {
        $toegang = DeviceToegang::where('code', $code)->first();

        if (!$toegang) {
            return $this->vrijwilligerError('Deze link is niet meer actief. Vraag een nieuwe link bij de jurytafel.');
        }

        // Check if already bound to this device
        $deviceToken = request()->cookie('device_token_' . $toegang->id);

        if ($deviceToken && $toegang->device_token === $deviceToken) {
            // Already bound, redirect to interface
            return $this->redirectToInterface($toegang);
        }

        return view('pages.toegang.pin', [
            'toegang' => $toegang,
            'toernooi' => $toegang->toernooi,
        ]);
    }

    /**
     * Verify PIN and bind device.
     */
    public function verify(Request $request, string $code)
    {
        $toegang = DeviceToegang::where('code', $code)->first();

        if (!$toegang) {
            return $this->vrijwilligerError('Deze link is niet meer actief. Vraag een nieuwe link bij de jurytafel.');
        }

        $request->validate([
            'pincode' => 'required|digits:4',
        ]);

        if ($request->pincode !== $toegang->pincode) {
            return back()->withErrors(['pincode' => 'Ongeldige pincode']);
        }

        // Generate device token
        $deviceToken = DeviceToegang::generateDeviceToken();

        // Get device info from user agent
        $deviceInfo = $this->getDeviceInfo($request->userAgent());

        // Bind device
        $toegang->bind($deviceToken, $deviceInfo);

        // Set cookie (1 year expiry)
        $cookie = Cookie::make(
            'device_token_' . $toegang->id,
            $deviceToken,
            60 * 24 * 365, // 1 year
            '/',
            null,
            true, // secure
            true  // httpOnly
        );

        return $this->redirectToInterface($toegang)->withCookie($cookie);
    }

    /**
     * Redirect to the appropriate interface based on role.
     */
    protected function redirectToInterface(DeviceToegang $toegang)
    {
        $toegang->updateLaatstActief();

        return match ($toegang->rol) {
            'hoofdjury' => redirect()->route('jury.interface', $toegang->id),
            'mat' => redirect()->route('mat.interface', ['toegang' => $toegang->id]),
            'weging' => redirect()->route('weging.interface', ['toegang' => $toegang->id]),
            'spreker' => redirect()->route('spreker.interface', ['toegang' => $toegang->id]),
            'dojo' => redirect()->route('dojo.scanner', ['toegang' => $toegang->id]),
            default => redirect('/'),
        };
    }

    /**
     * Parse user agent to get device info.
     */
    protected function getDeviceInfo(?string $userAgent): string
    {
        if (!$userAgent) {
            return 'Onbekend device';
        }

        // Simple parsing
        $device = 'Onbekend';
        $browser = 'Onbekend';

        // Device detection
        if (str_contains($userAgent, 'iPhone')) {
            $device = 'iPhone';
        } elseif (str_contains($userAgent, 'iPad')) {
            $device = 'iPad';
        } elseif (str_contains($userAgent, 'Android')) {
            $device = 'Android';
        } elseif (str_contains($userAgent, 'Windows')) {
            $device = 'Windows';
        } elseif (str_contains($userAgent, 'Mac')) {
            $device = 'Mac';
        }

        // Browser detection
        if (str_contains($userAgent, 'Chrome') && !str_contains($userAgent, 'Edg')) {
            $browser = 'Chrome';
        } elseif (str_contains($userAgent, 'Safari') && !str_contains($userAgent, 'Chrome')) {
            $browser = 'Safari';
        } elseif (str_contains($userAgent, 'Firefox')) {
            $browser = 'Firefox';
        } elseif (str_contains($userAgent, 'Edg')) {
            $browser = 'Edge';
        }

        return "{$device} {$browser}";
    }

    /**
     * Show friendly error page for volunteers (not login page!)
     */
    protected function vrijwilligerError(string $message)
    {
        return response()->view('errors.vrijwilliger', [
            'message' => $message,
        ], 404);
    }
}
