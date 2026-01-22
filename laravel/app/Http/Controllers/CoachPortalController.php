<?php

namespace App\Http\Controllers;

use App\Models\ClubUitnodiging;
use App\Models\Coach;
use App\Models\CoachKaart;
use App\Models\Judoka;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Http\Controllers\PubliekController;
use App\Services\MollieService;

class CoachPortalController extends Controller
{
    public function __construct(
        private MollieService $mollieService
    ) {}

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

    private function parseTelefoon(?string $telefoon): ?string
    {
        if (empty($telefoon)) {
            return null;
        }

        // Remove all non-numeric characters except +
        $telefoon = preg_replace('/[^0-9+]/', '', $telefoon);

        // Convert 06 to +316
        if (str_starts_with($telefoon, '06')) {
            $telefoon = '+31' . substr($telefoon, 1);
        }
        // Convert 0031 to +31
        elseif (str_starts_with($telefoon, '0031')) {
            $telefoon = '+31' . substr($telefoon, 4);
        }

        return $telefoon ?: null;
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

        // Sort: young to old (high geboortejaar first), then light to heavy
        $judokas = Judoka::where('toernooi_id', $toernooi->id)
            ->where('club_id', $club->id)
            ->orderByDesc('geboortejaar')
            ->orderBy('gewicht')
            ->orderBy('naam')
            ->get();

        // Get category labels from toernooi config (NOT hardcoded enum)
        $config = $toernooi->getAlleGewichtsklassen();
        $leeftijdsklassen = collect($config)->pluck('label')->unique()->values()->all();

        // Calculate payment info
        $volledigeOnbetaald = $judokas->filter(fn($j) => $j->isKlaarVoorBetaling());
        $betaald = $judokas->filter(fn($j) => $j->isBetaald());

        return view('pages.coach.judokas', [
            'uitnodiging' => $uitnodiging,
            'toernooi' => $toernooi,
            'club' => $club,
            'judokas' => $judokas,
            'leeftijdsklassen' => $leeftijdsklassen,
            'gewichtsklassen' => $toernooi->getAlleGewichtsklassen(),
            'inschrijvingOpen' => $toernooi->isInschrijvingOpen(),
            'maxBereikt' => $toernooi->isMaxJudokasBereikt(),
            'bijna80ProcentVol' => $toernooi->isBijna80ProcentVol(),
            'bezettingsPercentage' => $toernooi->bezettings_percentage,
            'plaatsenOver' => $toernooi->plaatsen_over,
            'totaalJudokas' => $toernooi->judokas()->count(),
            'eliminatieGewichtsklassen' => $toernooi->eliminatie_gewichtsklassen ?? [],
            'wedstrijdSysteem' => $toernooi->wedstrijd_systeem ?? [],
            // Payment info
            'betalingActief' => $toernooi->betaling_actief,
            'inschrijfgeld' => $toernooi->inschrijfgeld,
            'volledigeOnbetaald' => $volledigeOnbetaald,
            'aantalBetaald' => $betaald->count(),
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
            'geboortejaar' => 'nullable|integer|min:1990|max:' . date('Y'),
            'geslacht' => 'nullable|in:M,V',
            'band' => 'nullable|string|max:20',
            'gewicht' => 'nullable|numeric|min:10|max:200',
            'gewichtsklasse' => 'nullable|string|max:10',
            'telefoon' => 'nullable|string|max:20',
        ]);

        // Calculate age class from toernooi config (NOT hardcoded enum)
        $leeftijdsklasse = null;
        $gewichtsklasse = $validated['gewichtsklasse'] ?? null;
        $band = $validated['band'] ?? null;

        if (!empty($validated['geboortejaar']) && !empty($validated['geslacht'])) {
            $leeftijd = date('Y') - $validated['geboortejaar'];
            $leeftijdsklasse = $toernooi->bepaalLeeftijdsklasse($leeftijd, $validated['geslacht'], $band);

            // Auto-calculate gewichtsklasse from gewicht if provided
            if (!empty($validated['gewicht']) && empty($gewichtsklasse)) {
                $gewichtsklasse = $toernooi->bepaalGewichtsklasse($validated['gewicht'], $leeftijd, $validated['geslacht'], $band);
            }
        }

        // Fallback: if still no gewichtsklasse but gewicht is provided, create from gewicht
        if (empty($gewichtsklasse) && !empty($validated['gewicht'])) {
            $gewichtsklasse = '-' . (int) $validated['gewicht'];
        }

        // Check for duplicate
        $bestaande = Judoka::where('toernooi_id', $toernooi->id)
            ->where('naam', $validated['naam'])
            ->where('geboortejaar', $validated['geboortejaar'])
            ->first();

        if ($bestaande) {
            return redirect()->route('coach.judokas', $token)
                ->with('error', 'Deze judoka bestaat al (zelfde naam en geboortejaar)');
        }

        $judoka = Judoka::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $uitnodiging->club_id,
            'naam' => $validated['naam'],
            'geboortejaar' => $validated['geboortejaar'] ?? null,
            'geslacht' => $validated['geslacht'] ?? null,
            'band' => $validated['band'] ?? null,
            'gewicht' => $validated['gewicht'] ?? null,
            'leeftijdsklasse' => $leeftijdsklasse,
            'gewichtsklasse' => $gewichtsklasse,
            'telefoon' => $this->parseTelefoon($validated['telefoon'] ?? null),
        ]);

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

        $toernooi = $uitnodiging->toernooi;

        $validated = $request->validate([
            'naam' => 'required|string|max:255',
            'geboortejaar' => 'nullable|integer|min:1990|max:' . date('Y'),
            'geslacht' => 'nullable|in:M,V',
            'band' => 'nullable|string|max:20',
            'gewicht' => 'nullable|numeric|min:10|max:200',
            'gewichtsklasse' => 'nullable|string|max:10',
            'telefoon' => 'nullable|string|max:20',
        ]);

        // Calculate age class from toernooi config (NOT hardcoded enum)
        $leeftijdsklasse = null;
        $gewichtsklasse = $validated['gewichtsklasse'] ?? null;
        $band = $validated['band'] ?? null;

        if (!empty($validated['geboortejaar']) && !empty($validated['geslacht'])) {
            $leeftijd = date('Y') - $validated['geboortejaar'];
            $leeftijdsklasse = $toernooi->bepaalLeeftijdsklasse($leeftijd, $validated['geslacht'], $band);

            // Auto-calculate gewichtsklasse from gewicht if provided
            if (!empty($validated['gewicht']) && empty($gewichtsklasse)) {
                $gewichtsklasse = $toernooi->bepaalGewichtsklasse($validated['gewicht'], $leeftijd, $validated['geslacht'], $band);
            }
        }

        // Fallback: if still no gewichtsklasse but gewicht is provided, create from gewicht
        if (empty($gewichtsklasse) && !empty($validated['gewicht'])) {
            $gewichtsklasse = '-' . (int) $validated['gewicht'];
        }

        $judoka->update([
            'naam' => $validated['naam'],
            'geboortejaar' => $validated['geboortejaar'] ?? null,
            'geslacht' => $validated['geslacht'] ?? null,
            'band' => $validated['band'] ?? null,
            'gewicht' => $validated['gewicht'] ?? null,
            'leeftijdsklasse' => $leeftijdsklasse,
            'gewichtsklasse' => $gewichtsklasse,
            'telefoon' => $this->parseTelefoon($validated['telefoon'] ?? null),
        ]);

        // Hervalideer import status als judoka problemen had
        $judoka->hervalideerImportStatus();

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

    public function weegkaarten(Request $request, string $token): View|RedirectResponse
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
            ->with(['poules.blok'])
            ->orderBy('sort_categorie')
            ->orderBy('sort_gewicht')
            ->orderBy('sort_band')
            ->orderBy('naam')
            ->get();

        return view('pages.coach.weegkaarten', [
            'uitnodiging' => $uitnodiging,
            'toernooi' => $toernooi,
            'club' => $club,
            'judokas' => $judokas,
        ]);
    }

    // ========================================
    // Payment methods (token-based, legacy)
    // ========================================

    public function afrekenen(Request $request, string $token): View|RedirectResponse
    {
        $uitnodiging = $this->getUitnodiging($token);

        if (!$uitnodiging || !$this->checkIngelogd($request, $uitnodiging)) {
            return redirect()->route('coach.portal', $token);
        }

        $toernooi = $uitnodiging->toernooi;

        if (!$toernooi->betaling_actief) {
            return redirect()->route('coach.judokas', $token)
                ->with('error', 'Betalingen zijn niet actief voor dit toernooi');
        }

        $club = $uitnodiging->club;

        $judokas = Judoka::where('toernooi_id', $toernooi->id)
            ->where('club_id', $club->id)
            ->orderBy('naam')
            ->get();

        $klaarVoorBetaling = $judokas->filter(fn($j) => $j->isKlaarVoorBetaling());
        $reedsBetaald = $judokas->filter(fn($j) => $j->isBetaald());

        $totaalBedrag = $klaarVoorBetaling->count() * $toernooi->inschrijfgeld;

        return view('pages.coach.afrekenen', [
            'uitnodiging' => $uitnodiging,
            'toernooi' => $toernooi,
            'club' => $club,
            'klaarVoorBetaling' => $klaarVoorBetaling,
            'reedsBetaald' => $reedsBetaald,
            'totaalBedrag' => $totaalBedrag,
            'inschrijfgeld' => $toernooi->inschrijfgeld,
        ]);
    }

    public function betalen(Request $request, string $token): RedirectResponse
    {
        $uitnodiging = $this->getUitnodiging($token);

        if (!$uitnodiging || !$this->checkIngelogd($request, $uitnodiging)) {
            abort(403);
        }

        $toernooi = $uitnodiging->toernooi;

        if (!$toernooi->betaling_actief) {
            return redirect()->route('coach.judokas', $token)
                ->with('error', 'Betalingen zijn niet actief');
        }

        $club = $uitnodiging->club;

        $klaarVoorBetaling = Judoka::where('toernooi_id', $toernooi->id)
            ->where('club_id', $club->id)
            ->get()
            ->filter(fn($j) => $j->isKlaarVoorBetaling());

        if ($klaarVoorBetaling->isEmpty()) {
            return redirect()->route('coach.judokas', $token)
                ->with('error', 'Geen judoka\'s om af te rekenen');
        }

        $totaalBedrag = $klaarVoorBetaling->count() * $toernooi->inschrijfgeld;

        // Description for bank statement
        $description = "{$toernooi->naam} - {$club->naam} - {$klaarVoorBetaling->count()} judoka's";

        // Create betaling record first
        $betaling = \App\Models\Betaling::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'mollie_payment_id' => 'pending_' . uniqid(),
            'bedrag' => $totaalBedrag,
            'aantal_judokas' => $klaarVoorBetaling->count(),
            'status' => \App\Models\Betaling::STATUS_OPEN,
        ]);

        // Link judokas to betaling
        foreach ($klaarVoorBetaling as $judoka) {
            $judoka->update(['betaling_id' => $betaling->id]);
        }

        // Check if simulation mode or real Mollie
        if ($this->mollieService->isSimulationMode()) {
            $payment = $this->mollieService->simulatePayment([
                'amount' => ['currency' => 'EUR', 'value' => number_format($totaalBedrag, 2, '.', '')],
                'description' => $description,
                'redirectUrl' => route('coach.betaling.succes', $token),
                'webhookUrl' => route('mollie.webhook'),
                'metadata' => ['betaling_id' => $betaling->id],
            ]);

            $betaling->update(['mollie_payment_id' => $payment->id]);

            return redirect($payment->_links->checkout->href);
        }

        // Real Mollie payment
        try {
            $this->mollieService->ensureValidToken($toernooi);

            $payment = $this->mollieService->createPayment($toernooi, [
                'amount' => ['currency' => 'EUR', 'value' => number_format($totaalBedrag, 2, '.', '')],
                'description' => $description,
                'redirectUrl' => route('coach.betaling.succes', $token),
                'webhookUrl' => route('mollie.webhook'),
                'metadata' => ['betaling_id' => $betaling->id],
            ]);

            $betaling->update(['mollie_payment_id' => $payment->id]);

            return redirect($payment->_links->checkout->href);
        } catch (\Exception $e) {
            \Log::error('Mollie payment creation failed', ['error' => $e->getMessage()]);
            $betaling->update(['status' => \App\Models\Betaling::STATUS_FAILED]);

            return redirect()->route('coach.judokas', $token)
                ->with('error', 'Fout bij aanmaken betaling: ' . $e->getMessage());
        }
    }

    public function betalingSucces(Request $request, string $token): View|RedirectResponse
    {
        $uitnodiging = $this->getUitnodiging($token);

        if (!$uitnodiging || !$this->checkIngelogd($request, $uitnodiging)) {
            return redirect()->route('coach.portal', $token);
        }

        return view('pages.coach.betaling-succes', [
            'uitnodiging' => $uitnodiging,
            'toernooi' => $uitnodiging->toernooi,
            'club' => $uitnodiging->club,
        ]);
    }

    public function betalingGeannuleerd(Request $request, string $token): RedirectResponse
    {
        return redirect()->route('coach.judokas', $token)
            ->with('warning', 'Betaling geannuleerd');
    }

    // ========================================
    // Portal code + PIN based methods (new system)
    // ========================================

    private function getCoachesByCode(string $code)
    {
        return Coach::where('portal_code', $code)
            ->with(['club', 'toernooi'])
            ->get();
    }

    private function getLoggedInCoach(Request $request, string $code): ?Coach
    {
        $coachId = $request->session()->get("coach_code_{$code}");
        if (!$coachId) {
            return null;
        }
        return Coach::with(['club', 'toernooi'])->find($coachId);
    }

    public function indexCode(Request $request, string $code): View|RedirectResponse
    {
        $coaches = $this->getCoachesByCode($code);

        if ($coaches->isEmpty()) {
            abort(404, 'Ongeldige school link');
        }

        $loggedInCoach = $this->getLoggedInCoach($request, $code);
        if ($loggedInCoach) {
            return redirect()->route('coach.portal.judokas', $code);
        }

        $firstCoach = $coaches->first();

        return view('pages.coach.login-pin', [
            'code' => $code,
            'club' => $firstCoach->club,
            'toernooi' => $firstCoach->toernooi,
        ]);
    }

    public function loginPin(Request $request, string $code): RedirectResponse
    {
        $coaches = $this->getCoachesByCode($code);

        if ($coaches->isEmpty()) {
            abort(404);
        }

        $validated = $request->validate([
            'pincode' => 'required|string|size:5',
        ]);

        // Find coach by PIN
        $coach = $coaches->first(fn($c) => $c->pincode === $validated['pincode']);

        if (!$coach) {
            return redirect()->route('coach.portal.code', $code)
                ->with('error', 'Onjuiste PIN code');
        }

        $request->session()->put("coach_code_{$code}", $coach->id);
        $coach->updateLaatstIngelogd();

        return redirect()->route('coach.portal.judokas', $code);
    }

    public function logoutCode(Request $request, string $code): RedirectResponse
    {
        $request->session()->forget("coach_code_{$code}");

        return redirect()->route('coach.portal.code', $code)
            ->with('success', 'Je bent uitgelogd');
    }

    public function judokasCode(Request $request, string $code): View|RedirectResponse
    {
        $coach = $this->getLoggedInCoach($request, $code);

        if (!$coach) {
            return redirect()->route('coach.portal.code', $code);
        }

        $toernooi = $coach->toernooi;
        $club = $coach->club;

        // Sort: young to old (high geboortejaar first), then light to heavy
        $judokas = Judoka::where('toernooi_id', $toernooi->id)
            ->where('club_id', $club->id)
            ->orderByDesc('geboortejaar')
            ->orderBy('gewicht')
            ->orderBy('naam')
            ->get();

        // Get category labels from toernooi config (NOT hardcoded enum)
        $config = $toernooi->getAlleGewichtsklassen();
        $leeftijdsklassen = collect($config)->pluck('label')->unique()->values()->all();

        // Calculate payment info
        $volledigeOnbetaald = $judokas->filter(fn($j) => $j->isKlaarVoorBetaling());
        $betaald = $judokas->filter(fn($j) => $j->isBetaald());

        return view('pages.coach.judokas', [
            'coach' => $coach,
            'toernooi' => $toernooi,
            'club' => $club,
            'judokas' => $judokas,
            'leeftijdsklassen' => $leeftijdsklassen,
            'gewichtsklassen' => $toernooi->getAlleGewichtsklassen(),
            'inschrijvingOpen' => $toernooi->isInschrijvingOpen(),
            'maxBereikt' => $toernooi->isMaxJudokasBereikt(),
            'bijna80ProcentVol' => $toernooi->isBijna80ProcentVol(),
            'bezettingsPercentage' => $toernooi->bezettings_percentage,
            'plaatsenOver' => $toernooi->plaatsen_over,
            'totaalJudokas' => $toernooi->judokas()->count(),
            'useCode' => true,
            'code' => $code,
            'eliminatieGewichtsklassen' => $toernooi->eliminatie_gewichtsklassen ?? [],
            'wedstrijdSysteem' => $toernooi->wedstrijd_systeem ?? [],
            // Payment info
            'betalingActief' => $toernooi->betaling_actief,
            'inschrijfgeld' => $toernooi->inschrijfgeld,
            'volledigeOnbetaald' => $volledigeOnbetaald,
            'aantalBetaald' => $betaald->count(),
        ]);
    }

    public function storeJudokaCode(Request $request, string $code): RedirectResponse
    {
        $coach = $this->getLoggedInCoach($request, $code);

        if (!$coach) {
            abort(403);
        }

        $toernooi = $coach->toernooi;

        if (!$toernooi->isInschrijvingOpen()) {
            return redirect()->route('coach.portal.judokas', $code)
                ->with('error', 'De inschrijving is gesloten');
        }

        if ($toernooi->isMaxJudokasBereikt()) {
            return redirect()->route('coach.portal.judokas', $code)
                ->with('error', 'Maximum aantal deelnemers bereikt');
        }

        $validated = $request->validate([
            'naam' => 'required|string|max:255',
            'geboortejaar' => 'nullable|integer|min:1990|max:' . date('Y'),
            'geslacht' => 'nullable|in:M,V',
            'band' => 'nullable|string|max:20',
            'gewicht' => 'nullable|numeric|min:10|max:200',
            'gewichtsklasse' => 'nullable|string|max:10',
            'telefoon' => 'nullable|string|max:20',
        ]);

        // Calculate age class and gewichtsklasse
        // Calculate age class from toernooi config (NOT hardcoded enum)
        $leeftijdsklasse = null;
        $gewichtsklasse = $validated['gewichtsklasse'] ?? null;
        $band = $validated['band'] ?? null;

        if (!empty($validated['geboortejaar']) && !empty($validated['geslacht'])) {
            $leeftijd = date('Y') - $validated['geboortejaar'];
            $leeftijdsklasse = $toernooi->bepaalLeeftijdsklasse($leeftijd, $validated['geslacht'], $band);

            // Auto-calculate gewichtsklasse from gewicht if provided
            if (!empty($validated['gewicht']) && empty($gewichtsklasse)) {
                $gewichtsklasse = $toernooi->bepaalGewichtsklasse($validated['gewicht'], $leeftijd, $validated['geslacht'], $band);
            }
        }

        // Fallback: if still no gewichtsklasse but gewicht is provided, create from gewicht
        if (empty($gewichtsklasse) && !empty($validated['gewicht'])) {
            $gewichtsklasse = '-' . (int) $validated['gewicht'];
        }

        $bestaande = Judoka::where('toernooi_id', $toernooi->id)
            ->where('naam', $validated['naam'])
            ->where('geboortejaar', $validated['geboortejaar'])
            ->first();

        if ($bestaande) {
            return redirect()->route('coach.portal.judokas', $code)
                ->with('error', 'Deze judoka bestaat al');
        }

        $judoka = Judoka::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $coach->club_id,
            'naam' => $validated['naam'],
            'geboortejaar' => $validated['geboortejaar'] ?? null,
            'geslacht' => $validated['geslacht'] ?? null,
            'band' => $validated['band'] ?? null,
            'gewicht' => $validated['gewicht'] ?? null,
            'leeftijdsklasse' => $leeftijdsklasse,
            'gewichtsklasse' => $gewichtsklasse,
            'telefoon' => $this->parseTelefoon($validated['telefoon'] ?? null),
        ]);

        return redirect()->route('coach.portal.judokas', $code)
            ->with('success', 'Judoka toegevoegd');
    }

    public function updateJudokaCode(Request $request, string $code, Judoka $judoka): RedirectResponse
    {
        $coach = $this->getLoggedInCoach($request, $code);

        if (!$coach) {
            abort(403);
        }

        if ($judoka->club_id !== $coach->club_id || $judoka->toernooi_id !== $coach->toernooi_id) {
            abort(403);
        }

        $toernooi = $coach->toernooi;

        if (!$toernooi->isInschrijvingOpen()) {
            return redirect()->route('coach.portal.judokas', $code)
                ->with('error', 'De inschrijving is gesloten');
        }

        $validated = $request->validate([
            'naam' => 'required|string|max:255',
            'geboortejaar' => 'nullable|integer|min:1990|max:' . date('Y'),
            'geslacht' => 'nullable|in:M,V',
            'band' => 'nullable|string|max:20',
            'gewicht' => 'nullable|numeric|min:10|max:200',
            'gewichtsklasse' => 'nullable|string|max:10',
            'telefoon' => 'nullable|string|max:20',
        ]);

        // Calculate age class from toernooi config (NOT hardcoded enum)
        $leeftijdsklasse = null;
        $gewichtsklasse = $validated['gewichtsklasse'] ?? null;
        $band = $validated['band'] ?? null;

        if (!empty($validated['geboortejaar']) && !empty($validated['geslacht'])) {
            $leeftijd = date('Y') - $validated['geboortejaar'];
            $leeftijdsklasse = $toernooi->bepaalLeeftijdsklasse($leeftijd, $validated['geslacht'], $band);

            if (!empty($validated['gewicht']) && empty($gewichtsklasse)) {
                $gewichtsklasse = $toernooi->bepaalGewichtsklasse($validated['gewicht'], $leeftijd, $validated['geslacht'], $band);
            }
        }

        // Fallback: if still no gewichtsklasse but gewicht is provided, create from gewicht
        if (empty($gewichtsklasse) && !empty($validated['gewicht'])) {
            $gewichtsklasse = '-' . (int) $validated['gewicht'];
        }

        $judoka->update([
            'naam' => $validated['naam'],
            'geboortejaar' => $validated['geboortejaar'] ?? null,
            'geslacht' => $validated['geslacht'] ?? null,
            'band' => $validated['band'] ?? null,
            'gewicht' => $validated['gewicht'] ?? null,
            'leeftijdsklasse' => $leeftijdsklasse,
            'gewichtsklasse' => $gewichtsklasse,
            'telefoon' => $this->parseTelefoon($validated['telefoon'] ?? null),
        ]);

        // Hervalideer import status als judoka problemen had
        $judoka->hervalideerImportStatus();

        return redirect()->route('coach.portal.judokas', $code)
            ->with('success', 'Judoka bijgewerkt');
    }

    public function destroyJudokaCode(Request $request, string $code, Judoka $judoka): RedirectResponse
    {
        $coach = $this->getLoggedInCoach($request, $code);

        if (!$coach) {
            abort(403);
        }

        if ($judoka->club_id !== $coach->club_id || $judoka->toernooi_id !== $coach->toernooi_id) {
            abort(403);
        }

        if (!$coach->toernooi->isInschrijvingOpen()) {
            return redirect()->route('coach.portal.judokas', $code)
                ->with('error', 'De inschrijving is gesloten');
        }

        $judoka->delete();

        return redirect()->route('coach.portal.judokas', $code)
            ->with('success', 'Judoka verwijderd');
    }

    public function weegkaartenCode(Request $request, string $code): View|RedirectResponse
    {
        $coach = $this->getLoggedInCoach($request, $code);

        if (!$coach) {
            return redirect()->route('coach.portal.code', $code);
        }

        $toernooi = $coach->toernooi;
        $club = $coach->club;

        $judokas = Judoka::where('toernooi_id', $toernooi->id)
            ->where('club_id', $club->id)
            ->with(['poules.blok'])
            ->orderBy('sort_categorie')
            ->orderBy('sort_gewicht')
            ->orderBy('sort_band')
            ->orderBy('naam')
            ->get();

        return view('pages.coach.weegkaarten', [
            'coach' => $coach,
            'toernooi' => $toernooi,
            'club' => $club,
            'judokas' => $judokas,
            'useCode' => true,
            'code' => $code,
        ]);
    }

    public function coachkaartenCode(Request $request, string $code): View|RedirectResponse
    {
        $coach = $this->getLoggedInCoach($request, $code);

        if (!$coach) {
            return redirect()->route('coach.portal.code', $code);
        }

        $toernooi = $coach->toernooi;
        $club = $coach->club;

        // Get number of judokas for this club
        $aantalJudokas = Judoka::where('toernooi_id', $toernooi->id)
            ->where('club_id', $club->id)
            ->count();

        // Calculate how many coach cards they get
        $benodigdAantal = $club->berekenAantalCoachKaarten($toernooi);

        // Get existing coach cards
        $coachKaarten = CoachKaart::where('toernooi_id', $toernooi->id)
            ->where('club_id', $club->id)
            ->orderBy('id')
            ->get();

        // Auto-generate cards if needed
        if ($coachKaarten->count() < $benodigdAantal) {
            for ($i = $coachKaarten->count(); $i < $benodigdAantal; $i++) {
                CoachKaart::create([
                    'toernooi_id' => $toernooi->id,
                    'club_id' => $club->id,
                ]);
            }
            // Refresh
            $coachKaarten = CoachKaart::where('toernooi_id', $toernooi->id)
                ->where('club_id', $club->id)
                ->orderBy('id')
                ->get();
        }

        // Get organisation coaches for this club (to suggest names)
        $organisatieCoaches = Coach::where('toernooi_id', $toernooi->id)
            ->where('club_id', $club->id)
            ->get();

        return view('pages.coach.coachkaarten', [
            'coach' => $coach,
            'toernooi' => $toernooi,
            'club' => $club,
            'coachKaarten' => $coachKaarten,
            'organisatieCoaches' => $organisatieCoaches,
            'aantalJudokas' => $aantalJudokas,
            'benodigdAantal' => $benodigdAantal,
            'judokasPerCoach' => $toernooi->judokas_per_coach ?? 5,
            'useCode' => true,
            'code' => $code,
        ]);
    }

    public function toewijzenCoachkaart(Request $request, string $code, CoachKaart $coachKaart): RedirectResponse
    {
        $coach = $this->getLoggedInCoach($request, $code);

        if (!$coach) {
            abort(403);
        }

        // Check ownership
        if ($coachKaart->club_id !== $coach->club_id || $coachKaart->toernooi_id !== $coach->toernooi_id) {
            abort(403);
        }

        $validated = $request->validate([
            'naam' => 'nullable|string|max:255',
            'organisatie_coach_id' => 'nullable|exists:coaches,id',
        ]);

        // If organisatie coach selected, copy name
        if (!empty($validated['organisatie_coach_id'])) {
            $orgCoach = Coach::find($validated['organisatie_coach_id']);
            if ($orgCoach && $orgCoach->club_id === $coach->club_id) {
                $coachKaart->update(['naam' => $orgCoach->naam]);
            }
        } elseif (!empty($validated['naam'])) {
            $coachKaart->update(['naam' => $validated['naam']]);
        }

        return redirect()->route('coach.portal.coachkaarten', $code)
            ->with('success', 'Coach kaart bijgewerkt');
    }

    /**
     * Sync judoka's - markeer complete judoka's als definitief ingeschreven
     */
    public function syncJudokasCode(Request $request, string $code): RedirectResponse
    {
        $coach = $this->getLoggedInCoach($request, $code);

        if (!$coach) {
            return redirect()->route('coach.portal.code', $code);
        }

        $club = $coach->club;
        $toernooi = $coach->toernooi;

        // Get all judokas for this club
        $judokas = Judoka::where('toernooi_id', $toernooi->id)
            ->where('club_id', $club->id)
            ->get();

        $synced = 0;
        $incomplete = 0;

        foreach ($judokas as $judoka) {
            if ($judoka->isVolledig()) {
                // Only update if not synced or changed after sync
                if (!$judoka->isSynced() || $judoka->isGewijzigdNaSync()) {
                    $judoka->synced_at = now();
                    $judoka->save();
                    $synced++;
                }
            } else {
                $incomplete++;
            }
        }

        if ($incomplete > 0) {
            return redirect()->route('coach.portal.judokas', $code)
                ->with('warning', "{$synced} judoka(s) gesynced. {$incomplete} judoka(s) zijn incompleet en niet gesynced.");
        }

        return redirect()->route('coach.portal.judokas', $code)
            ->with('success', "{$synced} judoka(s) succesvol gesynced!");
    }

    // ========================================
    // Payment methods (code-based)
    // ========================================

    public function afrekenCode(Request $request, string $code): View|RedirectResponse
    {
        $coach = $this->getLoggedInCoach($request, $code);

        if (!$coach) {
            return redirect()->route('coach.portal.code', $code);
        }

        $toernooi = $coach->toernooi;

        if (!$toernooi->betaling_actief) {
            return redirect()->route('coach.portal.judokas', $code)
                ->with('error', 'Betalingen zijn niet actief voor dit toernooi');
        }

        $club = $coach->club;

        // Get judokas ready for payment
        $judokas = Judoka::where('toernooi_id', $toernooi->id)
            ->where('club_id', $club->id)
            ->orderBy('naam')
            ->get();

        $klaarVoorBetaling = $judokas->filter(fn($j) => $j->isKlaarVoorBetaling());
        $reedsBetaald = $judokas->filter(fn($j) => $j->isBetaald());

        $totaalBedrag = $klaarVoorBetaling->count() * $toernooi->inschrijfgeld;

        return view('pages.coach.afrekenen', [
            'coach' => $coach,
            'toernooi' => $toernooi,
            'club' => $club,
            'klaarVoorBetaling' => $klaarVoorBetaling,
            'reedsBetaald' => $reedsBetaald,
            'totaalBedrag' => $totaalBedrag,
            'inschrijfgeld' => $toernooi->inschrijfgeld,
            'useCode' => true,
            'code' => $code,
        ]);
    }

    public function betalenCode(Request $request, string $code): RedirectResponse
    {
        $coach = $this->getLoggedInCoach($request, $code);

        if (!$coach) {
            abort(403);
        }

        $toernooi = $coach->toernooi;

        if (!$toernooi->betaling_actief) {
            return redirect()->route('coach.portal.judokas', $code)
                ->with('error', 'Betalingen zijn niet actief');
        }

        $club = $coach->club;

        // Get judokas ready for payment
        $klaarVoorBetaling = Judoka::where('toernooi_id', $toernooi->id)
            ->where('club_id', $club->id)
            ->get()
            ->filter(fn($j) => $j->isKlaarVoorBetaling());

        if ($klaarVoorBetaling->isEmpty()) {
            return redirect()->route('coach.portal.judokas', $code)
                ->with('error', 'Geen judoka\'s om af te rekenen');
        }

        $totaalBedrag = $klaarVoorBetaling->count() * $toernooi->inschrijfgeld;

        // Description for bank statement
        $description = "{$toernooi->naam} - {$club->naam} - {$klaarVoorBetaling->count()} judoka's";

        // Create betaling record first
        $betaling = \App\Models\Betaling::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'mollie_payment_id' => 'pending_' . uniqid(),
            'bedrag' => $totaalBedrag,
            'aantal_judokas' => $klaarVoorBetaling->count(),
            'status' => \App\Models\Betaling::STATUS_OPEN,
        ]);

        // Link judokas to betaling
        foreach ($klaarVoorBetaling as $judoka) {
            $judoka->update(['betaling_id' => $betaling->id]);
        }

        // Check if simulation mode or real Mollie
        if ($this->mollieService->isSimulationMode()) {
            // Simulation mode
            $payment = $this->mollieService->simulatePayment([
                'amount' => ['currency' => 'EUR', 'value' => number_format($totaalBedrag, 2, '.', '')],
                'description' => $description,
                'redirectUrl' => route('coach.portal.betaling.succes', $code),
                'webhookUrl' => route('mollie.webhook'),
                'metadata' => ['betaling_id' => $betaling->id],
            ]);

            $betaling->update(['mollie_payment_id' => $payment->id]);

            return redirect($payment->_links->checkout->href);
        }

        // Real Mollie payment
        try {
            $this->mollieService->ensureValidToken($toernooi);

            $payment = $this->mollieService->createPayment($toernooi, [
                'amount' => ['currency' => 'EUR', 'value' => number_format($totaalBedrag, 2, '.', '')],
                'description' => $description,
                'redirectUrl' => route('coach.portal.betaling.succes', $code),
                'webhookUrl' => route('mollie.webhook'),
                'metadata' => ['betaling_id' => $betaling->id],
            ]);

            $betaling->update(['mollie_payment_id' => $payment->id]);

            return redirect($payment->_links->checkout->href);
        } catch (\Exception $e) {
            \Log::error('Mollie payment creation failed', ['error' => $e->getMessage()]);
            $betaling->update(['status' => \App\Models\Betaling::STATUS_FAILED]);

            return redirect()->route('coach.portal.judokas', $code)
                ->with('error', 'Fout bij aanmaken betaling: ' . $e->getMessage());
        }
    }

    public function betalingSuccesCode(Request $request, string $code): View|RedirectResponse
    {
        $coach = $this->getLoggedInCoach($request, $code);

        if (!$coach) {
            return redirect()->route('coach.portal.code', $code);
        }

        return view('pages.coach.betaling-succes', [
            'coach' => $coach,
            'toernooi' => $coach->toernooi,
            'club' => $coach->club,
            'useCode' => true,
            'code' => $code,
        ]);
    }

    public function betalingGeannuleerdCode(Request $request, string $code): RedirectResponse
    {
        return redirect()->route('coach.portal.judokas', $code)
            ->with('warning', 'Betaling geannuleerd');
    }

    public function resultatenCode(Request $request, string $code): View|RedirectResponse
    {
        $coach = $this->getLoggedInCoach($request, $code);

        if (!$coach) {
            return redirect()->route('coach.portal.code', $code);
        }

        $toernooi = $coach->toernooi;
        $club = $coach->club;

        // Get results for this club using PubliekController
        $publiekController = new PubliekController();
        $clubResultaten = $publiekController->getClubResultaten($toernooi, $club->id);
        $clubRanking = $publiekController->getClubRanking($toernooi);

        // Find this club's position in rankings
        $clubPositieAbsoluut = null;
        $clubPositieRelatief = null;
        foreach ($clubRanking['absoluut'] as $index => $c) {
            if ($c['naam'] === $club->naam) {
                $clubPositieAbsoluut = $index + 1;
                break;
            }
        }
        foreach ($clubRanking['relatief'] as $index => $c) {
            if ($c['naam'] === $club->naam) {
                $clubPositieRelatief = $index + 1;
                break;
            }
        }

        // Count medals for this club
        $medailles = ['goud' => 0, 'zilver' => 0, 'brons' => 0];
        foreach ($clubResultaten as $r) {
            if ($r['plaats'] === 1) $medailles['goud']++;
            if ($r['plaats'] === 2) $medailles['zilver']++;
            if ($r['plaats'] === 3) $medailles['brons']++;
        }

        return view('pages.coach.resultaten', [
            'coach' => $coach,
            'toernooi' => $toernooi,
            'club' => $club,
            'resultaten' => $clubResultaten,
            'clubRanking' => $clubRanking,
            'clubPositieAbsoluut' => $clubPositieAbsoluut,
            'clubPositieRelatief' => $clubPositieRelatief,
            'medailles' => $medailles,
            'useCode' => true,
            'code' => $code,
        ]);
    }
}
