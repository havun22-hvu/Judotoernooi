<?php

namespace App\Http\Controllers;

use App\Models\DeviceToegang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class DeviceToegangController extends Controller
{
    /**
     * Show the access link and (auto-)bind the current device.
     *
     * Security model: the 12-character random code in the URL is the secret.
     * If the device is already bound, redirect to the interface. Otherwise
     * bind this device automatically (first-device-wins) and redirect.
     * The old 4-digit PIN prompt has been removed — the 12-char code provides
     * ~71 bits of entropy, which is the only factor needed here.
     */
    public function show(Request $request, string $organisator, string $toernooi, string $code)
    {
        $toegang = DeviceToegang::where('code', $code)->first();

        if (!$toegang) {
            return $this->vrijwilligerError('Deze link is niet meer actief. Vraag een nieuwe link bij de jurytafel.');
        }

        $deviceToken = $request->cookie('device_token_' . $toegang->id);

        if ($deviceToken && $toegang->device_token === $deviceToken) {
            return $this->redirectToInterface($toegang);
        }

        // If a different device is already bound, the organisator must
        // explicitly reset the binding from the beheer UI before a new
        // device can take over — the role code alone is not enough to
        // override an existing binding.
        if ($toegang->isGebonden()) {
            return $this->vrijwilligerError('Deze toegang is al aan een ander apparaat gekoppeld. Vraag de organisator om de binding te resetten.');
        }

        $newToken = DeviceToegang::generateDeviceToken();
        $deviceInfo = $this->getDeviceInfo($request->userAgent());
        $toegang->bind($newToken, $deviceInfo);

        $cookie = Cookie::make(
            'device_token_' . $toegang->id,
            $newToken,
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
        $toernooi = $toegang->toernooi;
        $params = [
            'organisator' => $toernooi->organisator->slug,
            'toernooi' => $toernooi->slug,
            'toegang' => $toegang->id,
        ];

        return match ($toegang->rol) {
            'hoofdjury' => redirect()->route('jury.interface', $params),
            'mat' => redirect()->route('mat.interface', $params),
            'weging' => redirect()->route('weging.interface', $params),
            'spreker' => redirect()->route('spreker.interface', $params),
            'dojo' => redirect()->route('dojo.scanner', $params),
            default => redirect('/'),
        };
    }

    /**
     * Redirect legacy /toegang/{code} to new /{org}/{toernooi}/toegang/{code}
     */
    public function redirectToNew(string $code)
    {
        $toegang = DeviceToegang::where('code', $code)->first();

        if (!$toegang) {
            return $this->vrijwilligerError('Deze link is niet meer actief.');
        }

        $toernooi = $toegang->toernooi;

        return redirect()->route('toegang.show', [
            'organisator' => $toernooi->organisator->slug,
            'toernooi' => $toernooi->slug,
            'code' => $code,
        ]);
    }

    /**
     * Redirect legacy interface routes to new URL structure
     */
    public function redirectInterfaceToNew(int $toegangId, string $rol)
    {
        $toegang = DeviceToegang::find($toegangId);

        if (!$toegang) {
            return $this->vrijwilligerError('Deze link is niet meer actief.');
        }

        $toernooi = $toegang->toernooi;
        $params = [
            'organisator' => $toernooi->organisator->slug,
            'toernooi' => $toernooi->slug,
            'toegang' => $toegangId,
        ];

        $routeName = match ($rol) {
            'jury' => 'jury.interface',
            'mat' => 'mat.interface',
            'weging' => 'weging.interface',
            'spreker' => 'spreker.interface',
            'dojo' => 'dojo.scanner',
            default => null,
        };

        if (!$routeName) {
            return redirect('/');
        }

        return redirect()->route($routeName, $params);
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
