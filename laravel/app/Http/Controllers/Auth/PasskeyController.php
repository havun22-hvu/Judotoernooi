<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuthDevice;
use App\Models\Organisator;
use App\Models\QrLoginToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laragear\WebAuthn\Http\Requests\AssertedRequest;
use Laragear\WebAuthn\Http\Requests\AssertionRequest;
use Laragear\WebAuthn\Http\Requests\AttestationRequest;
use Laragear\WebAuthn\Http\Requests\AttestedRequest;
use Laragear\WebAuthn\Models\WebAuthnCredential;

class PasskeyController extends Controller
{
    public function registerOptions(AttestationRequest $request): JsonResponse
    {
        // Ensure laragear uses the organisator guard (belt-and-suspenders)
        $request->setUserResolver(function ($guard = null) {
            return Auth::guard('organisator')->user();
        });

        try {
            $options = $request->toCreate();
            Log::info('PASSKEY REGISTER OPTIONS - Success', [
                'organisator_id' => Auth::guard('organisator')->id(),
            ]);
            return response()->json($options);
        } catch (\Exception $e) {
            Log::error('PASSKEY REGISTER OPTIONS - Failed', [
                'error' => $e->getMessage(),
                'organisator_id' => Auth::guard('organisator')->id(),
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function register(AttestedRequest $request): JsonResponse
    {
        // Ensure laragear uses the organisator guard
        $request->setUserResolver(function ($guard = null) {
            return Auth::guard('organisator')->user();
        });

        try {
            $request->save();
            Log::info('PASSKEY REGISTER - Success', [
                'organisator_id' => Auth::guard('organisator')->id(),
            ]);
            return response()->json(['success' => true, 'message' => 'Passkey succesvol geregistreerd!']);
        } catch (\Exception $e) {
            Log::error('PASSKEY REGISTER - Failed', [
                'error' => $e->getMessage(),
                'organisator_id' => Auth::guard('organisator')->id(),
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function loginOptions(AssertionRequest $request): JsonResponse
    {
        return response()->json($request->toVerify());
    }

    public function login(AssertedRequest $request): JsonResponse
    {
        $credId = $request->input('id');
        $credential = WebAuthnCredential::find($credId);

        if (!$credential) {
            return response()->json(['success' => false, 'message' => 'Passkey niet herkend'], 401);
        }

        $organisator = $credential->authenticatable;

        if (!$organisator) {
            return response()->json(['success' => false, 'message' => 'Gebruiker niet gevonden'], 401);
        }

        $ua = $request->userAgent();
        $deviceInfo = [
            'name' => $this->detectDeviceName($ua),
            'browser' => $this->detectBrowser($ua),
            'os' => $this->detectOS($ua),
            'ip' => $request->ip(),
        ];

        $device = AuthDevice::createForOrganisator($organisator, $deviceInfo);

        Log::info('PASSKEY LOGIN - Success', [
            'organisator_id' => $organisator->id,
            'device_id' => $device->id,
        ]);

        return response()->json([
            'success' => true,
            'device_token' => $device->token,
        ]);
    }

    /**
     * Exchange device token for session (called after passkey/biometric login)
     */
    public function tokenLogin(string $token)
    {
        $device = AuthDevice::findByToken($token);

        if (!$device) {
            Log::warning('TOKEN LOGIN - Invalid token');
            return redirect()->route('organisator.login')->with('error', 'Ongeldige login link');
        }

        $organisator = $device->organisator;

        if (!$organisator) {
            Log::warning('TOKEN LOGIN - Organisator not found');
            return redirect()->route('organisator.login')->with('error', 'Gebruiker niet gevonden');
        }

        // Login with organisator guard - no session regenerate!
        Auth::guard('organisator')->login($organisator, true);
        $device->touch();
        session()->save();

        $organisator->updateLaatsteLogin();

        Log::info('TOKEN LOGIN - Success', [
            'organisator_id' => $organisator->id,
            'device_id' => $device->id,
        ]);

        if ($organisator->isSitebeheerder()) {
            return redirect()->route('admin.index');
        }

        return redirect()->route('organisator.dashboard', ['organisator' => $organisator->slug]);
    }

    public function qrGenerate(Request $request): JsonResponse
    {
        // Clean up expired tokens
        QrLoginToken::where('expires_at', '<', now())->where('status', 'pending')->update(['status' => 'expired']);

        $token = QrLoginToken::generate([
            'browser' => $request->input('browser'),
            'os' => $request->input('os'),
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'token' => $token->token,
            'expires_in' => 300,
            'approve_url' => url('/auth/qr/approve/' . $token->token),
        ]);
    }

    public function qrStatus(string $token): JsonResponse
    {
        $qrToken = QrLoginToken::where('token', $token)->first();

        if (!$qrToken) {
            return response()->json(['status' => 'invalid'], 404);
        }

        if ($qrToken->expires_at->isPast() && $qrToken->status === 'pending') {
            $qrToken->markExpired();
        }

        $data = ['status' => $qrToken->status];

        if ($qrToken->isApproved()) {
            // Create device token for desktop login
            $organisator = $qrToken->organisator;
            $device = AuthDevice::createForOrganisator($organisator);
            $data['complete_url'] = url('/auth/qr/complete/' . $token);
        }

        return response()->json($data);
    }

    public function qrApproveShow(string $token)
    {
        $qrToken = QrLoginToken::where('token', $token)->first();

        if (!$qrToken || !$qrToken->isValid()) {
            return redirect()->route('organisator.login')->with('error', 'QR code verlopen of ongeldig.');
        }

        return view('organisator.auth.qr-approve', [
            'token' => $qrToken,
            'deviceInfo' => $qrToken->device_info,
        ]);
    }

    public function qrApprove(Request $request, string $token): JsonResponse
    {
        $qrToken = QrLoginToken::where('token', $token)->first();

        if (!$qrToken || !$qrToken->isValid()) {
            return response()->json(['success' => false, 'message' => 'Token verlopen'], 400);
        }

        if (!Auth::guard('organisator')->check()) {
            return response()->json(['success' => false, 'message' => 'Je moet eerst ingelogd zijn'], 401);
        }

        $qrToken->approve(Auth::guard('organisator')->user());

        Log::info('QR APPROVE - Success', ['organisator_id' => Auth::guard('organisator')->id()]);

        return response()->json(['success' => true]);
    }

    public function qrComplete(string $token)
    {
        $qrToken = QrLoginToken::where('token', $token)->first();

        if (!$qrToken) {
            return redirect()->route('organisator.login')->with('error', 'Ongeldige link');
        }

        if (!$qrToken->isApproved()) {
            return redirect()->route('organisator.login')->with('error', 'Niet goedgekeurd');
        }

        $qrToken->markUsed();
        $organisator = $qrToken->organisator;

        // Login without regenerating session (causes cookie issues)
        Auth::guard('organisator')->login($organisator, true);
        session()->save();

        $organisator->updateLaatsteLogin();

        Log::info('QR COMPLETE - Success', [
            'organisator_id' => $organisator->id,
        ]);

        if ($organisator->isSitebeheerder()) {
            return redirect()->route('admin.index');
        }

        return redirect()->route('organisator.dashboard', ['organisator' => $organisator->slug]);
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
