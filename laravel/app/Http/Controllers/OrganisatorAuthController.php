<?php

namespace App\Http\Controllers;

use App\Mail\MagicLinkMail;
use App\Models\AuthDevice;
use App\Models\MagicLinkToken;
use App\Models\Organisator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OrganisatorAuthController extends Controller
{
    /**
     * Show login form
     */
    public function showLogin(): View|RedirectResponse
    {
        // Check if logged in, but also verify the user actually exists
        // This prevents redirect loops when session is corrupt
        if (Auth::guard('organisator')->check()) {
            $user = Auth::guard('organisator')->user();
            if ($user && $user->exists) {
                return redirect()->route('organisator.dashboard', ['organisator' => $user->slug]);
            }
            // Corrupt session - log out and show login
            Auth::guard('organisator')->logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }

        return view('organisator.auth.login');
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
            session()->save();

            /** @var Organisator $organisator */
            $organisator = Auth::guard('organisator')->user();
            $organisator->updateLaatsteLogin();

            // Restore saved locale preference
            if ($organisator->locale) {
                $request->session()->put('locale', $organisator->locale);
            }

            // Sitebeheerder goes to admin dashboard, regular organisator to their dashboard
            if ($organisator->isSitebeheerder()) {
                return redirect()->intended(route('admin.index'));
            }

            return redirect()->intended(route('organisator.dashboard', ['organisator' => $organisator->slug]));
        }

        return back()->withErrors([
            'email' => __('Deze gegevens komen niet overeen met onze records.'),
        ])->onlyInput('email');
    }

    /**
     * Show registration form (magic link)
     */
    public function showRegister(): View|RedirectResponse
    {
        if (Auth::guard('organisator')->check()) {
            $user = Auth::guard('organisator')->user();
            return redirect()->route('organisator.dashboard', ['organisator' => $user->slug]);
        }

        return view('organisator.auth.register');
    }

    /**
     * Send magic link for registration
     */
    public function sendRegisterLink(Request $request): RedirectResponse
    {
        $request->validate([
            'organisatie_naam' => 'required|string|max:255',
            'naam' => 'required|string|max:255',
            'email' => 'required|email',
            'telefoon' => ['nullable', 'string', 'max:20', 'regex:/^(\+31|0)[1-9][\d\s\-]{7,12}$/'],
        ], [
            'telefoon.regex' => __('Voer een geldig Nederlands telefoonnummer in (bijv. 06-12345678)'),
        ]);

        // Rate limit: max 3 per 10 minutes
        $key = 'magic-link:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->withErrors([
                'email' => __('Te veel verzoeken. Probeer het over :seconds seconden opnieuw.', [
                    'seconds' => RateLimiter::availableIn($key),
                ]),
            ])->withInput();
        }
        RateLimiter::hit($key, 600);

        $token = MagicLinkToken::generate($request->email, 'register', [
            'organisatie_naam' => $request->organisatie_naam,
            'naam' => $request->naam,
            'telefoon' => $request->telefoon,
        ]);

        Mail::to($request->email)->send(new MagicLinkMail($token));

        return redirect()->route('register.sent')->with([
            'email' => $request->email,
            'type' => 'register',
        ]);
    }

    /**
     * Show magic link sent confirmation
     */
    public function magicLinkSent(): View
    {
        return view('organisator.auth.magic-link-sent', [
            'email' => session('email', ''),
            'type' => session('type', 'register'),
        ]);
    }

    /**
     * Verify registration magic link
     */
    public function verifyRegister(string $token): RedirectResponse
    {
        $magicToken = MagicLinkToken::findValid($token, 'register');

        if (!$magicToken) {
            return redirect()->route('register')
                ->withErrors(['token' => __('Link is verlopen of al gebruikt. Vraag een nieuwe aan.')]);
        }

        $magicToken->markUsed();
        $metadata = $magicToken->metadata ?? [];

        // Check if organisator already exists
        $organisator = Organisator::where('email', $magicToken->email)->first();

        if ($organisator) {
            // Existing user - just log in
            Auth::guard('organisator')->login($organisator, true);
            session()->save();

            if ($organisator->isSitebeheerder()) {
                return redirect()->intended(route('admin.index'));
            }
            return redirect()->intended(route('organisator.dashboard', ['organisator' => $organisator->slug]));
        }

        // Create new organisator (no password yet)
        $organisator = Organisator::create([
            'organisatie_naam' => $metadata['organisatie_naam'] ?? 'Organisatie',
            'naam' => $metadata['naam'] ?? 'Organisator',
            'email' => $magicToken->email,
            'telefoon' => $metadata['telefoon'] ?? null,
            'password' => null,
            'email_verified_at' => now(),
        ]);

        Auth::guard('organisator')->login($organisator, true);
        session()->save();

        // Redirect to password setup
        return redirect()->route('password.setup')
            ->with('success', __('Account aangemaakt! Stel nu een wachtwoord in.'));
    }

    /**
     * Show password setup form (after magic link registration)
     */
    public function showSetupPassword(): View|RedirectResponse
    {
        $organisator = Auth::guard('organisator')->user();

        // If already has password, redirect to dashboard
        if ($organisator && $organisator->password) {
            return redirect()->route('organisator.dashboard', ['organisator' => $organisator->slug]);
        }

        return view('organisator.auth.setup-password');
    }

    /**
     * Handle password setup
     */
    public function setupPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $organisator = Auth::guard('organisator')->user();
        $organisator->update(['password' => Hash::make($request->password)]);

        return redirect()->route('organisator.dashboard', ['organisator' => $organisator->slug])
            ->with('success', __('Wachtwoord ingesteld!'));
    }

    /**
     * Show forgot password form (magic link)
     */
    public function showForgotPassword(): View
    {
        return view('organisator.auth.forgot-password');
    }

    /**
     * Send magic link for password reset
     */
    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Rate limit
        $key = 'password-reset:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->withErrors([
                'email' => __('Te veel verzoeken. Probeer het over :seconds seconden opnieuw.', [
                    'seconds' => RateLimiter::availableIn($key),
                ]),
            ]);
        }
        RateLimiter::hit($key, 600);

        // Always show success (email enumeration prevention)
        $organisator = Organisator::where('email', strtolower($request->email))->first();

        if ($organisator) {
            $token = MagicLinkToken::generate($request->email, 'password_reset');
            Mail::to($request->email)->send(new MagicLinkMail($token));
        }

        return redirect()->route('password.sent')->with([
            'email' => $request->email,
            'type' => 'password_reset',
        ]);
    }

    /**
     * Show password reset sent confirmation
     */
    public function resetSent(): View
    {
        return view('organisator.auth.magic-link-sent', [
            'email' => session('email', ''),
            'type' => session('type', 'password_reset'),
        ]);
    }

    /**
     * Show password reset form (via magic link)
     */
    public function showResetPassword(Request $request, string $token): View|RedirectResponse
    {
        $magicToken = MagicLinkToken::findValid($token, 'password_reset');

        if (!$magicToken) {
            return redirect()->route('password.request')
                ->withErrors(['token' => __('Link is verlopen of al gebruikt. Vraag een nieuwe aan.')]);
        }

        return view('organisator.auth.reset-password', [
            'token' => $token,
            'email' => $magicToken->email,
        ]);
    }

    /**
     * Handle password reset (via magic link)
     */
    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $magicToken = MagicLinkToken::findValid($request->token, 'password_reset');

        if (!$magicToken) {
            return redirect()->route('password.request')
                ->withErrors(['token' => __('Link is verlopen of al gebruikt.')]);
        }

        $organisator = Organisator::where('email', $magicToken->email)->first();

        if (!$organisator) {
            return redirect()->route('password.request')
                ->withErrors(['email' => __('Gebruiker niet gevonden.')]);
        }

        $organisator->update(['password' => Hash::make($request->password)]);
        $magicToken->markUsed();

        Auth::guard('organisator')->login($organisator, true);
        session()->save();

        if ($organisator->isSitebeheerder()) {
            return redirect()->intended(route('admin.index'))
                ->with('success', __('Wachtwoord gewijzigd!'));
        }

        return redirect()->intended(route('organisator.dashboard', ['organisator' => $organisator->slug]))
            ->with('success', __('Wachtwoord gewijzigd!'));
    }

    /**
     * Log out
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('organisator')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', __('U bent uitgelogd.'));
    }
}
