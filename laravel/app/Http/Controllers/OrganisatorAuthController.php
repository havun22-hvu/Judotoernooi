<?php

namespace App\Http\Controllers;

use App\Models\Organisator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OrganisatorAuthController extends Controller
{
    /**
     * Show login form
     */
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::guard('organisator')->check()) {
            return redirect()->route('organisator.dashboard');
        }

        // On local/staging, show PIN login option for superadmin
        $showPinLogin = app()->environment(['local', 'staging']);

        return view('organisator.auth.login', compact('showPinLogin'));
    }

    /**
     * Handle login attempt
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::guard('organisator')->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            /** @var Organisator $organisator */
            $organisator = Auth::guard('organisator')->user();
            $organisator->updateLaatsteLogin();

            return redirect()->intended(route('organisator.dashboard'));
        }

        return back()->withErrors([
            'email' => 'Deze gegevens komen niet overeen met onze records.',
        ])->onlyInput('email');
    }

    /**
     * Handle PIN login for superadmin on local/staging
     */
    public function pinLogin(Request $request): RedirectResponse
    {
        // Only allowed on local/staging
        if (!app()->environment(['local', 'staging'])) {
            abort(404);
        }

        $request->validate([
            'pin' => 'required|digits:4',
        ]);

        // Check PIN (stored in env or hardcoded for dev)
        $correctPin = config('toernooi.superadmin_pin', '1234');

        if ($request->pin !== $correctPin) {
            return back()->withErrors(['pin' => 'Ongeldige PIN']);
        }

        // Find or create superadmin
        $superadmin = Organisator::where('email', 'henkvu@gmail.com')->first();

        if (!$superadmin) {
            return back()->withErrors(['pin' => 'Superadmin account niet gevonden']);
        }

        Auth::guard('organisator')->login($superadmin, true);
        $request->session()->regenerate();
        $superadmin->updateLaatsteLogin();

        return redirect()->intended(route('organisator.dashboard'));
    }

    /**
     * Show registration form
     */
    public function showRegister(): View|RedirectResponse
    {
        if (Auth::guard('organisator')->check()) {
            return redirect()->route('organisator.dashboard');
        }

        return view('organisator.auth.register');
    }

    /**
     * Handle registration
     */
    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'naam' => 'required|string|max:255',
            'email' => 'required|email|unique:organisators,email',
            'telefoon' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $organisator = Organisator::create([
            'naam' => $validated['naam'],
            'email' => $validated['email'],
            'telefoon' => $validated['telefoon'] ?? null,
            'password' => $validated['password'],
        ]);

        Auth::guard('organisator')->login($organisator);

        return redirect()->route('organisator.dashboard')
            ->with('success', 'Account aangemaakt! Welkom bij JudoToernooi.');
    }

    /**
     * Log out
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('organisator')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('organisator.login')
            ->with('success', 'U bent uitgelogd.');
    }

    /**
     * Show forgot password form
     */
    public function showForgotPassword(): View
    {
        return view('organisator.auth.forgot-password');
    }

    /**
     * Send password reset link
     */
    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $status = Password::broker('organisators')->sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }

    /**
     * Show password reset form
     */
    public function showResetPassword(Request $request, string $token): View
    {
        return view('organisator.auth.reset-password', [
            'token' => $token,
            'email' => $request->email,
        ]);
    }

    /**
     * Handle password reset
     */
    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::broker('organisators')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Organisator $organisator, string $password) {
                $organisator->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('organisator.login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }
}
