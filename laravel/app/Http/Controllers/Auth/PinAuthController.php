<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuthDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class PinAuthController extends Controller
{
    public function checkDevice(Request $request): JsonResponse
    {
        $request->validate([
            'fingerprint' => 'required|string|size:64',
        ]);

        $device = AuthDevice::findRegisteredByFingerprint($request->fingerprint);

        if (!$device) {
            return response()->json([
                'has_device' => false,
                'has_pin' => false,
                'has_biometric' => false,
            ]);
        }

        return response()->json([
            'has_device' => true,
            'has_pin' => $device->hasPin(),
            'has_biometric' => $device->has_biometric,
            'user_name' => $device->organisator->naam ?? null,
        ]);
    }

    public function loginWithPin(Request $request): JsonResponse
    {
        $request->validate([
            'fingerprint' => 'required|string|size:64',
            'pin' => 'required|string|size:5',
        ]);

        $key = 'pin-login:' . $request->fingerprint;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'success' => false,
                'message' => "Te veel pogingen. Probeer over {$seconds} seconden.",
            ], 429);
        }

        $device = AuthDevice::findActiveByFingerprint($request->fingerprint);

        if (!$device || !$device->hasPin()) {
            RateLimiter::hit($key, 60);
            return response()->json([
                'success' => false,
                'message' => 'Apparaat niet gevonden. Log in met wachtwoord.',
            ], 404);
        }

        if (!$device->verifyPin($request->pin)) {
            RateLimiter::hit($key, 60);
            return response()->json([
                'success' => false,
                'message' => 'Onjuiste PIN',
            ], 401);
        }

        RateLimiter::clear($key);

        $organisator = $device->organisator;
        $device->touch();

        // Login with organisator guard - no session regenerate!
        Auth::guard('organisator')->login($organisator, true);
        session()->save();

        $organisator->updateLaatsteLogin();

        Log::info('PIN LOGIN - Success', [
            'organisator_id' => $organisator->id,
            'device_id' => $device->id,
        ]);

        // Determine redirect based on role
        $redirect = $organisator->isSitebeheerder()
            ? route('admin.index')
            : route('organisator.dashboard', ['organisator' => $organisator->slug]);

        return response()->json([
            'success' => true,
            'redirect' => $redirect,
        ]);
    }

    public function setupPin(Request $request): JsonResponse
    {
        $request->validate([
            'fingerprint' => 'required|string|size:64',
            'pin' => 'required|string|size:5|regex:/^[0-9]+$/',
        ]);

        $organisator = Auth::guard('organisator')->user();

        if (!$organisator) {
            return response()->json([
                'success' => false,
                'message' => 'Je moet ingelogd zijn om een PIN in te stellen.',
            ], 401);
        }

        $deviceInfo = [
            'name' => $this->detectDeviceName($request->userAgent()),
            'browser' => $this->detectBrowser($request->userAgent()),
            'os' => $this->detectOS($request->userAgent()),
            'ip' => $request->ip(),
        ];

        $device = AuthDevice::findOrCreateForOrganisator($organisator, $request->fingerprint, $deviceInfo);
        $device->setPin($request->pin);

        Log::info('PIN SETUP - Success', [
            'organisator_id' => $organisator->id,
            'device_id' => $device->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'PIN succesvol ingesteld!',
        ]);
    }

    public function enableBiometric(Request $request): JsonResponse
    {
        $request->validate([
            'fingerprint' => 'required|string|size:64',
        ]);

        $organisator = Auth::guard('organisator')->user();

        if (!$organisator) {
            return response()->json([
                'success' => false,
                'message' => 'Je moet ingelogd zijn.',
            ], 401);
        }

        $device = AuthDevice::findByFingerprint($organisator->id, $request->fingerprint);

        if (!$device) {
            $deviceInfo = [
                'name' => $this->detectDeviceName($request->userAgent()),
                'browser' => $this->detectBrowser($request->userAgent()),
                'os' => $this->detectOS($request->userAgent()),
                'ip' => $request->ip(),
            ];
            $device = AuthDevice::findOrCreateForOrganisator($organisator, $request->fingerprint, $deviceInfo);
        }

        $device->enableBiometric();

        return response()->json([
            'success' => true,
            'message' => 'Biometrische login ingeschakeld!',
        ]);
    }

    private function detectBrowser(string $ua): string
    {
        if (stripos($ua, 'Firefox') !== false) return 'Firefox';
        if (stripos($ua, 'Edg') !== false) return 'Edge';
        if (stripos($ua, 'Chrome') !== false) return 'Chrome';
        if (stripos($ua, 'Safari') !== false) return 'Safari';
        return 'Unknown';
    }

    private function detectOS(string $ua): string
    {
        if (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) return 'iOS';
        if (stripos($ua, 'Android') !== false) return 'Android';
        if (stripos($ua, 'Mac') !== false) return 'macOS';
        if (stripos($ua, 'Windows') !== false) return 'Windows';
        if (stripos($ua, 'Linux') !== false) return 'Linux';
        return 'Unknown';
    }

    private function detectDeviceName(string $ua): string
    {
        $os = $this->detectOS($ua);
        $browser = $this->detectBrowser($ua);
        return "$browser op $os";
    }
}
