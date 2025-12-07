<?php

namespace App\Http\Controllers;

use App\Enums\Leeftijdsklasse;
use App\Models\ClubUitnodiging;
use App\Models\Judoka;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoachPortalController extends Controller
{
    private function getUitnodiging(string $token): ?ClubUitnodiging
    {
        return ClubUitnodiging::where('token', $token)
            ->with(['club', 'toernooi'])
            ->first();
    }

    private function checkIngelogd(Request $request, ClubUitnodiging $uitnodiging): bool
    {
        return $request->session()->get("coach_ingelogd_{$uitnodiging->id}") === true;
    }

    public function index(Request $request, string $token): View|RedirectResponse
    {
        $uitnodiging = $this->getUitnodiging($token);

        if (!$uitnodiging) {
            abort(404, 'Ongeldige uitnodigingslink');
        }

        // If already logged in, redirect to judokas
        if ($this->checkIngelogd($request, $uitnodiging)) {
            return redirect()->route('coach.judokas', $token);
        }

        // Show registration or login form
        return view('pages.coach.index', [
            'uitnodiging' => $uitnodiging,
            'club' => $uitnodiging->club,
            'toernooi' => $uitnodiging->toernooi,
            'isGeregistreerd' => $uitnodiging->isGeregistreerd(),
        ]);
    }

    public function registreer(Request $request, string $token): RedirectResponse
    {
        $uitnodiging = $this->getUitnodiging($token);

        if (!$uitnodiging) {
            abort(404);
        }

        if ($uitnodiging->isGeregistreerd()) {
            return redirect()->route('coach.portal', $token)
                ->with('error', 'Account bestaat al. Log in met je wachtwoord.');
        }

        $validated = $request->validate([
            'wachtwoord' => 'required|string|min:6|confirmed',
        ], [
            'wachtwoord.required' => 'Wachtwoord is verplicht',
            'wachtwoord.min' => 'Wachtwoord moet minimaal 6 tekens zijn',
            'wachtwoord.confirmed' => 'Wachtwoorden komen niet overeen',
        ]);

        $uitnodiging->setWachtwoord($validated['wachtwoord']);

        // Auto-login after registration
        $request->session()->put("coach_ingelogd_{$uitnodiging->id}", true);
        $uitnodiging->updateLaatstIngelogd();

        return redirect()->route('coach.judokas', $token)
            ->with('success', 'Account aangemaakt! Je kunt nu judoka\'s opgeven.');
    }

    public function login(Request $request, string $token): RedirectResponse
    {
        $uitnodiging = $this->getUitnodiging($token);

        if (!$uitnodiging) {
            abort(404);
        }

        if (!$uitnodiging->isGeregistreerd()) {
            return redirect()->route('coach.portal', $token)
                ->with('error', 'Maak eerst een account aan.');
        }

        $validated = $request->validate([
            'wachtwoord' => 'required|string',
        ]);

        if (!$uitnodiging->checkWachtwoord($validated['wachtwoord'])) {
            return redirect()->route('coach.portal', $token)
                ->with('error', 'Onjuist wachtwoord');
        }

        $request->session()->put("coach_ingelogd_{$uitnodiging->id}", true);
        $uitnodiging->updateLaatstIngelogd();

        return redirect()->route('coach.judokas', $token);
    }

    public function logout(Request $request, string $token): RedirectResponse
    {
        $uitnodiging = $this->getUitnodiging($token);

        if ($uitnodiging) {
            $request->session()->forget("coach_ingelogd_{$uitnodiging->id}");
        }

        return redirect()->route('coach.portal', $token)
            ->with('success', 'Je bent uitgelogd');
    }

    public function judokas(Request $request, string $token): View|RedirectResponse
    {
        $uitnodiging = $this->getUitnodiging($token);

        if (!$uitnodiging) {
            abort(404);
        }

        if (!$this->checkIngelogd($request, $uitnodiging)) {
            return redirect()->route('coach.portal', $token);
        }

        $toernooi = $uitnodiging->toernooi;
        $club = $uitnodiging->club;

        $judokas = Judoka::where('toernooi_id', $toernooi->id)
            ->where('club_id', $club->id)
            ->orderBy('naam')
            ->get();

        $leeftijdsklassen = Leeftijdsklasse::cases();

        return view('pages.coach.judokas', [
            'uitnodiging' => $uitnodiging,
            'toernooi' => $toernooi,
            'club' => $club,
            'judokas' => $judokas,
            'leeftijdsklassen' => $leeftijdsklassen,
            'inschrijvingOpen' => $toernooi->isInschrijvingOpen(),
            'maxBereikt' => $toernooi->isMaxJudokasBereikt(),
            'bijna80ProcentVol' => $toernooi->isBijna80ProcentVol(),
            'bezettingsPercentage' => $toernooi->bezettings_percentage,
            'plaatsenOver' => $toernooi->plaatsen_over,
            'totaalJudokas' => $toernooi->judokas()->count(),
        ]);
    }

    public function storeJudoka(Request $request, string $token): RedirectResponse
    {
        $uitnodiging = $this->getUitnodiging($token);

        if (!$uitnodiging || !$this->checkIngelogd($request, $uitnodiging)) {
            abort(403);
        }

        $toernooi = $uitnodiging->toernooi;

        // Check if registration is still open
        if (!$toernooi->isInschrijvingOpen()) {
            return redirect()->route('coach.judokas', $token)
                ->with('error', 'De inschrijving is gesloten');
        }

        if ($toernooi->isMaxJudokasBereikt()) {
            return redirect()->route('coach.judokas', $token)
                ->with('error', 'Maximum aantal deelnemers bereikt');
        }

        $validated = $request->validate([
            'naam' => 'required|string|max:255',
            'geboortejaar' => 'required|integer|min:1990|max:' . date('Y'),
            'geslacht' => 'required|in:M,V',
            'band' => 'required|string|max:20',
            'gewichtsklasse' => 'required|string|max:10',
        ]);

        // Calculate age class
        $leeftijd = date('Y') - $validated['geboortejaar'];
        $leeftijdsklasse = Leeftijdsklasse::fromLeeftijdEnGeslacht($leeftijd, $validated['geslacht']);

        $judoka = Judoka::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $uitnodiging->club_id,
            'naam' => $validated['naam'],
            'geboortejaar' => $validated['geboortejaar'],
            'geslacht' => $validated['geslacht'],
            'band' => $validated['band'],
            'leeftijdsklasse' => $leeftijdsklasse->label(),
            'gewichtsklasse' => $validated['gewichtsklasse'],
        ]);

        // Generate temporary judoka code
        $judoka->update(['judoka_code' => $judoka->berekenJudokaCode(99)]);

        return redirect()->route('coach.judokas', $token)
            ->with('success', 'Judoka toegevoegd');
    }

    public function updateJudoka(Request $request, string $token, Judoka $judoka): RedirectResponse
    {
        $uitnodiging = $this->getUitnodiging($token);

        if (!$uitnodiging || !$this->checkIngelogd($request, $uitnodiging)) {
            abort(403);
        }

        // Check ownership
        if ($judoka->club_id !== $uitnodiging->club_id || $judoka->toernooi_id !== $uitnodiging->toernooi_id) {
            abort(403);
        }

        // Check if registration is still open
        if (!$uitnodiging->toernooi->isInschrijvingOpen()) {
            return redirect()->route('coach.judokas', $token)
                ->with('error', 'De inschrijving is gesloten');
        }

        $validated = $request->validate([
            'naam' => 'required|string|max:255',
            'geboortejaar' => 'required|integer|min:1990|max:' . date('Y'),
            'geslacht' => 'required|in:M,V',
            'band' => 'required|string|max:20',
            'gewichtsklasse' => 'required|string|max:10',
        ]);

        // Recalculate age class
        $leeftijd = date('Y') - $validated['geboortejaar'];
        $leeftijdsklasse = Leeftijdsklasse::fromLeeftijdEnGeslacht($leeftijd, $validated['geslacht']);

        $judoka->update(array_merge($validated, [
            'leeftijdsklasse' => $leeftijdsklasse->label(),
        ]));

        return redirect()->route('coach.judokas', $token)
            ->with('success', 'Judoka bijgewerkt');
    }

    public function destroyJudoka(Request $request, string $token, Judoka $judoka): RedirectResponse
    {
        $uitnodiging = $this->getUitnodiging($token);

        if (!$uitnodiging || !$this->checkIngelogd($request, $uitnodiging)) {
            abort(403);
        }

        // Check ownership
        if ($judoka->club_id !== $uitnodiging->club_id || $judoka->toernooi_id !== $uitnodiging->toernooi_id) {
            abort(403);
        }

        // Check if registration is still open
        if (!$uitnodiging->toernooi->isInschrijvingOpen()) {
            return redirect()->route('coach.judokas', $token)
                ->with('error', 'De inschrijving is gesloten');
        }

        $judoka->delete();

        return redirect()->route('coach.judokas', $token)
            ->with('success', 'Judoka verwijderd');
    }

}
