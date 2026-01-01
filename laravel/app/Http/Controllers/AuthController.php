<?php

namespace App\Http\Controllers;

use App\Models\Toernooi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Toon login pagina
     * Op production: redirect naar organisator login
     * Op local/staging: toon rol-keuze pagina (development mode)
     */
    public function loginForm(Toernooi $toernooi): View|RedirectResponse
    {
        // Op production: deze pagina is niet beschikbaar, redirect naar organisator login
        if (app()->environment('production')) {
            return redirect()->route('organisator.login');
        }

        $wachtwoordVereist = [
            'admin' => $toernooi->heeftWachtwoord('admin'),
            'jury' => $toernooi->heeftWachtwoord('jury'),
            'weging' => $toernooi->heeftWachtwoord('weging'),
            'mat' => $toernooi->heeftWachtwoord('mat'),
            'spreker' => $toernooi->heeftWachtwoord('spreker'),
        ];

        return view('pages.auth.login', compact('toernooi', 'wachtwoordVereist'));
    }

    /**
     * Service Login - alleen voor sitebeheerders
     * Snelle rol-keuze zonder wachtwoord voor troubleshooting
     */
    public function serviceLoginForm(Toernooi $toernooi): View|RedirectResponse
    {
        // Alleen voor ingelogde sitebeheerders
        $organisator = auth('organisator')->user();
        if (!$organisator || !$organisator->isSitebeheerder()) {
            abort(403, 'Alleen sitebeheerders hebben toegang tot Service Login');
        }

        return view('pages.auth.service-login', compact('toernooi'));
    }

    /**
     * Verwerk Service Login
     */
    public function serviceLogin(Request $request, Toernooi $toernooi): RedirectResponse
    {
        // Alleen voor ingelogde sitebeheerders
        $organisator = auth('organisator')->user();
        if (!$organisator || !$organisator->isSitebeheerder()) {
            abort(403, 'Alleen sitebeheerders hebben toegang tot Service Login');
        }

        $validated = $request->validate([
            'rol' => 'required|in:admin,jury,weging,mat,spreker',
            'mat_nummer' => 'nullable|integer|min:1',
        ]);

        $rol = $validated['rol'];

        // Sla sessie op (geen wachtwoord check voor sitebeheerder)
        $sessionKey = "toernooi_{$toernooi->id}_rol";
        $request->session()->put($sessionKey, $rol);

        // Voor mat login, sla ook mat nummer op
        if ($rol === 'mat' && isset($validated['mat_nummer'])) {
            $request->session()->put("toernooi_{$toernooi->id}_mat", $validated['mat_nummer']);
        }

        // Redirect naar juiste pagina
        return match($rol) {
            'admin' => redirect()->route('toernooi.show', $toernooi)->with('success', 'Service Login: Admin'),
            'jury' => redirect()->route('toernooi.blok.zaaloverzicht', $toernooi)->with('success', 'Service Login: Jury'),
            'weging' => redirect()->route('toernooi.weging.interface', $toernooi)->with('success', 'Service Login: Weging'),
            'mat' => redirect()->route('toernooi.mat.interface', $toernooi)->with('success', 'Service Login: Mat ' . ($validated['mat_nummer'] ?? '')),
            'spreker' => redirect()->route('toernooi.spreker.interface', $toernooi)->with('success', 'Service Login: Spreker'),
        };
    }

    /**
     * Verwerk login
     * Op production: niet beschikbaar (redirect naar organisator login)
     */
    public function login(Request $request, Toernooi $toernooi): RedirectResponse
    {
        // Op production: deze login methode is niet beschikbaar
        if (app()->environment('production')) {
            return redirect()->route('organisator.login');
        }

        $validated = $request->validate([
            'rol' => 'required|in:admin,jury,weging,mat,spreker',
            'wachtwoord' => 'nullable|string',
            'mat_nummer' => 'nullable|integer|min:1',
        ]);

        $rol = $validated['rol'];
        $wachtwoord = $validated['wachtwoord'] ?? null;

        // Check wachtwoord (alleen als er een wachtwoord is ingesteld)
        // In local/staging: skip password check for easier development
        if (!app()->environment(['local', 'staging']) && $toernooi->heeftWachtwoord($rol)) {
            if (!$toernooi->checkWachtwoord($rol, $wachtwoord)) {
                return back()->with('error', 'Onjuist wachtwoord');
            }
        }

        // Sla sessie op
        $sessionKey = "toernooi_{$toernooi->id}_rol";
        $request->session()->put($sessionKey, $rol);

        // Voor mat login, sla ook mat nummer op
        if ($rol === 'mat' && isset($validated['mat_nummer'])) {
            $request->session()->put("toernooi_{$toernooi->id}_mat", $validated['mat_nummer']);
        }

        // Redirect naar juiste pagina
        return match($rol) {
            'admin' => redirect()->route('toernooi.show', $toernooi)->with('success', 'Ingelogd als Admin'),
            'jury' => redirect()->route('toernooi.blok.zaaloverzicht', $toernooi)->with('success', 'Ingelogd als Jury'),
            'weging' => redirect()->route('toernooi.weging.interface', $toernooi)->with('success', 'Ingelogd voor Weging'),
            'mat' => redirect()->route('toernooi.mat.interface', $toernooi)->with('success', 'Ingelogd voor Mat ' . ($validated['mat_nummer'] ?? '')),
            'spreker' => redirect()->route('toernooi.spreker.interface', $toernooi)->with('success', 'Ingelogd als Spreker'),
        };
    }

    /**
     * Uitloggen
     */
    public function logout(Request $request, Toernooi $toernooi): RedirectResponse
    {
        $request->session()->forget("toernooi_{$toernooi->id}_rol");
        $request->session()->forget("toernooi_{$toernooi->id}_mat");

        // Op production: redirect naar organisator login (email/wachtwoord)
        // Op local/development: redirect naar toernooi rol-keuze pagina
        if (app()->environment('production')) {
            return redirect()->route('organisator.login')->with('success', 'Je bent uitgelogd');
        }

        return redirect()->route('toernooi.auth.login', $toernooi)->with('success', 'Je bent uitgelogd');
    }

    /**
     * Check of gebruiker ingelogd is met bepaalde rol
     */
    public static function checkRol(Request $request, Toernooi $toernooi, array $toegestaneRollen): bool
    {
        $sessionKey = "toernooi_{$toernooi->id}_rol";
        $huidigeRol = $request->session()->get($sessionKey);

        if (!$huidigeRol) {
            return false;
        }

        // Admin heeft altijd toegang
        if ($huidigeRol === 'admin') {
            return true;
        }

        return in_array($huidigeRol, $toegestaneRollen);
    }

    /**
     * Haal huidige rol op
     */
    public static function getRol(Request $request, Toernooi $toernooi): ?string
    {
        return $request->session()->get("toernooi_{$toernooi->id}_rol");
    }

    /**
     * Haal mat nummer op (voor mat login)
     */
    public static function getMatNummer(Request $request, Toernooi $toernooi): ?int
    {
        return $request->session()->get("toernooi_{$toernooi->id}_mat");
    }
}
