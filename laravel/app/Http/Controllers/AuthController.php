<?php

namespace App\Http\Controllers;

use App\Models\Toernooi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
class AuthController extends Controller
{
    /**
     * Toon login info pagina
     * Redirect naar organisator login - service login is verwijderd
     */
    public function loginForm(Toernooi $toernooi): RedirectResponse
    {
        // Altijd redirect naar organisator login
        // Vrijwilligers gebruiken device binding URLs (via Instellingen → Organisatie)
        return redirect()->route('organisator.login');
    }

    /**
     * Verwerk login - niet meer beschikbaar
     * Service login is vervangen door device binding
     */
    public function login(Request $request, Toernooi $toernooi): RedirectResponse
    {
        // Service login is verwijderd, redirect naar organisator login
        return redirect()->route('organisator.login')
            ->with('info', 'Gebruik je persoonlijke toegangslink of log in als organisator.');
    }

    /**
     * Uitloggen
     */
    public function logout(Request $request, Toernooi $toernooi): RedirectResponse
    {
        $request->session()->forget("toernooi_{$toernooi->id}_rol");
        $request->session()->forget("toernooi_{$toernooi->id}_mat");

        return redirect()->route('organisator.login')->with('success', 'Je bent uitgelogd');
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
