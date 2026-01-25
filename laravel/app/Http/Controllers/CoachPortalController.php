<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Coach;
use App\Models\CoachKaart;
use App\Models\Judoka;
use App\Models\Toernooi;
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

    // ========================================
    // Portal code + PIN based methods
    // ========================================

    private function getClubByCode(string $code): ?Club
    {
        return Club::where('portal_code', $code)->first();
    }

    private function getLoggedInClub(Request $request, string $code): ?Club
    {
        if (!$request->session()->get("club_logged_in_{$code}")) {
            return null;
        }
        return $this->getClubByCode($code);
    }

    private function redirectToLoginExpired(string $code): RedirectResponse
    {
        return redirect()->route('coach.portal.code', $code)
            ->with('error', 'Je sessie is verlopen. Log opnieuw in.');
    }

    private function getActiveToernooi(): ?Toernooi
    {
        return Toernooi::orderByDesc('created_at')->first();
    }

    public function indexCode(Request $request, string $code): View|RedirectResponse
    {
        $club = $this->getClubByCode($code);

        if (!$club) {
            abort(404, 'Ongeldige school link');
        }

        $loggedInClub = $this->getLoggedInClub($request, $code);
        if ($loggedInClub) {
            return redirect()->route('coach.portal.judokas', $code);
        }

        $toernooi = $this->getActiveToernooi();

        return view('pages.coach.login-pin', [
            'code' => $code,
            'club' => $club,
            'toernooi' => $toernooi,
        ]);
    }

    public function loginPin(Request $request, string $code): RedirectResponse
    {
        $club = $this->getClubByCode($code);

        if (!$club) {
            abort(404);
        }

        $validated = $request->validate([
            'pincode' => 'required|string|size:5',
        ]);

        if (!$club->checkPincode($validated['pincode'])) {
            return redirect()->route('coach.portal.code', $code)
                ->with('error', 'Onjuiste PIN code');
        }

        $request->session()->put("club_logged_in_{$code}", true);

        return redirect()->route('coach.portal.judokas', $code);
    }

    public function logoutCode(Request $request, string $code): RedirectResponse
    {
        $request->session()->forget("club_logged_in_{$code}");

        return redirect()->route('coach.portal.code', $code)
            ->with('success', 'Je bent uitgelogd');
    }

    public function judokasCode(Request $request, string $code): View|RedirectResponse
    {
        $club = $this->getLoggedInClub($request, $code);

        if (!$club) {
            return redirect()->route('coach.portal.code', $code);
        }

        $toernooi = $this->getActiveToernooi();

        // Sort: young to old (high geboortejaar first), then light to heavy
        $judokas = Judoka::where('toernooi_id', $toernooi->id)
            ->where('club_id', $club->id)
            ->orderByDesc('geboortejaar')
            ->orderBy('gewicht')
            ->orderBy('naam')
            ->get();

        // Get category labels from toernooi config
        $config = $toernooi->getAlleGewichtsklassen();
        $leeftijdsklassen = collect($config)->pluck('label')->unique()->values()->all();

        // Calculate payment info
        $volledigeOnbetaald = $judokas->filter(fn($j) => $j->isKlaarVoorBetaling());
        $betaald = $judokas->filter(fn($j) => $j->isBetaald());

        return view('pages.coach.judokas', [
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
            'betalingActief' => $toernooi->betaling_actief,
            'inschrijfgeld' => $toernooi->inschrijfgeld,
            'volledigeOnbetaald' => $volledigeOnbetaald,
            'aantalBetaald' => $betaald->count(),
            'magInschrijven' => $toernooi->portaalMagInschrijven(),
            'magWijzigen' => $toernooi->portaalMagWijzigen(),
        ]);
    }

    public function storeJudokaCode(Request $request, string $code): RedirectResponse
    {
        $club = $this->getLoggedInClub($request, $code);

        if (!$club) {
            return $this->redirectToLoginExpired($code);
        }

        $toernooi = $this->getActiveToernooi();

        if (!$toernooi->portaalMagInschrijven()) {
            return redirect()->route('coach.portal.judokas', $code)
                ->with('error', 'Nieuwe inschrijvingen zijn niet toegestaan via het portaal');
        }

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
            'geboortejaar' => 'nullable|integer|min:2000|max:' . date('Y'),
            'geslacht' => 'nullable|in:M,V',
            'band' => 'nullable|string|max:20',
            'gewicht' => 'nullable|numeric|min:10|max:200',
            'gewichtsklasse' => 'nullable|string|max:10',
            'telefoon' => 'nullable|string|max:20',
        ]);

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

        Judoka::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
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
        $club = $this->getLoggedInClub($request, $code);
        $toernooi = $this->getActiveToernooi();

        if (!$club) {
            return $this->redirectToLoginExpired($code);
        }

        if ($judoka->club_id !== $club->id || $judoka->toernooi_id !== $toernooi->id) {
            abort(403);
        }

        if (!$toernooi->portaalMagWijzigen()) {
            return redirect()->route('coach.portal.judokas', $code)
                ->with('error', 'Wijzigingen zijn niet toegestaan via het portaal');
        }

        if (!$toernooi->isInschrijvingOpen()) {
            return redirect()->route('coach.portal.judokas', $code)
                ->with('error', 'De inschrijving is gesloten');
        }

        $validated = $request->validate([
            'naam' => 'required|string|max:255',
            'geboortejaar' => 'nullable|integer|min:2000|max:' . date('Y'),
            'geslacht' => 'nullable|in:M,V',
            'band' => 'nullable|string|max:20',
            'gewicht' => 'nullable|numeric|min:10|max:200',
            'gewichtsklasse' => 'nullable|string|max:10',
            'telefoon' => 'nullable|string|max:20',
        ]);

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

        $judoka->hervalideerImportStatus();

        return redirect()->route('coach.portal.judokas', $code)
            ->with('success', 'Judoka bijgewerkt');
    }

    public function destroyJudokaCode(Request $request, string $code, Judoka $judoka): RedirectResponse
    {
        $club = $this->getLoggedInClub($request, $code);
        $toernooi = $this->getActiveToernooi();

        if (!$club) {
            return $this->redirectToLoginExpired($code);
        }

        if ($judoka->club_id !== $club->id || $judoka->toernooi_id !== $toernooi->id) {
            abort(403);
        }

        if (!$toernooi->portaalMagInschrijven()) {
            return redirect()->route('coach.portal.judokas', $code)
                ->with('error', 'Verwijderen is niet toegestaan via het portaal');
        }

        if (!$toernooi->isInschrijvingOpen()) {
            return redirect()->route('coach.portal.judokas', $code)
                ->with('error', 'De inschrijving is gesloten');
        }

        $judoka->delete();

        return redirect()->route('coach.portal.judokas', $code)
            ->with('success', 'Judoka verwijderd');
    }

    public function weegkaartenCode(Request $request, string $code): View|RedirectResponse
    {
        $club = $this->getLoggedInClub($request, $code);

        if (!$club) {
            return redirect()->route('coach.portal.code', $code);
        }

        $toernooi = $this->getActiveToernooi();

        $judokas = Judoka::where('toernooi_id', $toernooi->id)
            ->where('club_id', $club->id)
            ->with(['poules.blok'])
            ->orderBy('sort_categorie')
            ->orderBy('sort_gewicht')
            ->orderBy('sort_band')
            ->orderBy('naam')
            ->get();

        return view('pages.coach.weegkaarten', [
            'toernooi' => $toernooi,
            'club' => $club,
            'judokas' => $judokas,
            'useCode' => true,
            'code' => $code,
        ]);
    }

    public function coachkaartenCode(Request $request, string $code): View|RedirectResponse
    {
        $club = $this->getLoggedInClub($request, $code);

        if (!$club) {
            return redirect()->route('coach.portal.code', $code);
        }

        $toernooi = $this->getActiveToernooi();

        $judokas = Judoka::where('toernooi_id', $toernooi->id)
            ->where('club_id', $club->id)
            ->with('poules.blok')
            ->get();

        $aantalJudokas = $judokas->count();
        $blokkenIngedeeld = $judokas->contains(fn($j) => $j->poules->contains(fn($p) => $p->blok_id !== null));
        $benodigdAantal = 1;

        $coachKaarten = CoachKaart::where('toernooi_id', $toernooi->id)
            ->where('club_id', $club->id)
            ->with(['wisselingen', 'checkinsVandaag'])
            ->orderBy('id')
            ->get();

        if ($coachKaarten->isEmpty()) {
            CoachKaart::create([
                'toernooi_id' => $toernooi->id,
                'club_id' => $club->id,
            ]);
            $coachKaarten = CoachKaart::where('toernooi_id', $toernooi->id)
                ->where('club_id', $club->id)
                ->with(['wisselingen', 'checkinsVandaag'])
                ->orderBy('id')
                ->get();
        } elseif ($coachKaarten->count() > 1 && !$toernooi->voorbereiding_klaar_op) {
            $kaartToKeep = $coachKaarten->first();
            CoachKaart::where('toernooi_id', $toernooi->id)
                ->where('club_id', $club->id)
                ->where('id', '!=', $kaartToKeep->id)
                ->where('is_gescand', false)
                ->delete();
            $coachKaarten = CoachKaart::where('toernooi_id', $toernooi->id)
                ->where('club_id', $club->id)
                ->with(['wisselingen', 'checkinsVandaag'])
                ->orderBy('id')
                ->get();
        }

        $benodigdNaVoorbereiding = $club->berekenAantalCoachKaarten($toernooi, true);

        $organisatieCoaches = Coach::where('toernooi_id', $toernooi->id)
            ->where('club_id', $club->id)
            ->get();

        return view('pages.coach.coachkaarten', [
            'toernooi' => $toernooi,
            'club' => $club,
            'coachKaarten' => $coachKaarten,
            'organisatieCoaches' => $organisatieCoaches,
            'aantalJudokas' => $aantalJudokas,
            'benodigdAantal' => $coachKaarten->count(),
            'benodigdNaVoorbereiding' => $benodigdNaVoorbereiding,
            'judokasPerCoach' => $toernooi->judokas_per_coach ?? 5,
            'blokkenIngedeeld' => $blokkenIngedeeld,
            'voorbereidingAfgerond' => $toernooi->voorbereiding_klaar_op !== null,
            'useCode' => true,
            'code' => $code,
        ]);
    }

    public function toewijzenCoachkaart(Request $request, string $code, CoachKaart $coachKaart): RedirectResponse
    {
        $club = $this->getLoggedInClub($request, $code);
        $toernooi = $this->getActiveToernooi();

        if (!$club) {
            return $this->redirectToLoginExpired($code);
        }

        if ($coachKaart->club_id !== $club->id || $coachKaart->toernooi_id !== $toernooi->id) {
            abort(403);
        }

        $validated = $request->validate([
            'naam' => 'nullable|string|max:255',
            'organisatie_coach_id' => 'nullable|exists:coaches,id',
        ]);

        if (!empty($validated['organisatie_coach_id'])) {
            $orgCoach = Coach::find($validated['organisatie_coach_id']);
            if ($orgCoach && $orgCoach->club_id === $club->id) {
                $coachKaart->update(['naam' => $orgCoach->naam]);
            }
        } elseif (!empty($validated['naam'])) {
            $coachKaart->update(['naam' => $validated['naam']]);
        }

        return redirect()->route('coach.portal.coachkaarten', $code)
            ->with('success', 'Coach kaart bijgewerkt');
    }

    public function syncJudokasCode(Request $request, string $code): RedirectResponse
    {
        $club = $this->getLoggedInClub($request, $code);

        if (!$club) {
            return redirect()->route('coach.portal.code', $code);
        }

        $toernooi = $this->getActiveToernooi();

        $judokas = Judoka::where('toernooi_id', $toernooi->id)
            ->where('club_id', $club->id)
            ->get();

        $synced = 0;
        $incomplete = 0;
        $nietInCategorie = 0;

        foreach ($judokas as $judoka) {
            if ($judoka->isKlaarVoorSync()) {
                if (!$judoka->isSynced() || $judoka->isGewijzigdNaSync()) {
                    $judoka->synced_at = now();
                    $judoka->save();
                    $synced++;
                }
            } elseif ($judoka->isVolledig() && !$judoka->pastInCategorie()) {
                $nietInCategorie++;
            } else {
                $incomplete++;
            }
        }

        $messages = [];
        if ($synced > 0) {
            $messages[] = "{$synced} judoka(s) gesynced";
        }
        if ($incomplete > 0) {
            $messages[] = "{$incomplete} judoka(s) zijn incompleet";
        }
        if ($nietInCategorie > 0) {
            $messages[] = "{$nietInCategorie} judoka(s) passen niet in een categorie (te oud/jong)";
        }

        if ($nietInCategorie > 0 || $incomplete > 0) {
            return redirect()->route('coach.portal.judokas', $code)
                ->with('warning', implode('. ', $messages) . '.');
        }

        return redirect()->route('coach.portal.judokas', $code)
            ->with('success', "{$synced} judoka(s) succesvol gesynced!");
    }

    // ========================================
    // Payment methods
    // ========================================

    public function afrekenCode(Request $request, string $code): View|RedirectResponse
    {
        $club = $this->getLoggedInClub($request, $code);

        if (!$club) {
            return redirect()->route('coach.portal.code', $code);
        }

        $toernooi = $this->getActiveToernooi();

        if (!$toernooi->betaling_actief) {
            return redirect()->route('coach.portal.judokas', $code)
                ->with('error', 'Betalingen zijn niet actief voor dit toernooi');
        }

        $judokas = Judoka::where('toernooi_id', $toernooi->id)
            ->where('club_id', $club->id)
            ->orderBy('naam')
            ->get();

        $klaarVoorBetaling = $judokas->filter(fn($j) => $j->isKlaarVoorBetaling());
        $reedsBetaald = $judokas->filter(fn($j) => $j->isBetaald());

        $totaalBedrag = $klaarVoorBetaling->count() * $toernooi->inschrijfgeld;

        return view('pages.coach.afrekenen', [
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
        $club = $this->getLoggedInClub($request, $code);

        if (!$club) {
            return $this->redirectToLoginExpired($code);
        }

        $toernooi = $this->getActiveToernooi();

        if (!$toernooi->betaling_actief) {
            return redirect()->route('coach.portal.judokas', $code)
                ->with('error', 'Betalingen zijn niet actief');
        }

        $klaarVoorBetaling = Judoka::where('toernooi_id', $toernooi->id)
            ->where('club_id', $club->id)
            ->get()
            ->filter(fn($j) => $j->isKlaarVoorBetaling());

        if ($klaarVoorBetaling->isEmpty()) {
            return redirect()->route('coach.portal.judokas', $code)
                ->with('error', 'Geen judoka\'s om af te rekenen');
        }

        $totaalBedrag = $klaarVoorBetaling->count() * $toernooi->inschrijfgeld;
        $description = "{$toernooi->naam} - {$club->naam} - {$klaarVoorBetaling->count()} judoka's";

        $betaling = \App\Models\Betaling::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
            'mollie_payment_id' => 'pending_' . uniqid(),
            'bedrag' => $totaalBedrag,
            'aantal_judokas' => $klaarVoorBetaling->count(),
            'status' => \App\Models\Betaling::STATUS_OPEN,
        ]);

        foreach ($klaarVoorBetaling as $judoka) {
            $judoka->update(['betaling_id' => $betaling->id]);
        }

        if ($this->mollieService->isSimulationMode()) {
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
        $club = $this->getLoggedInClub($request, $code);

        if (!$club) {
            return redirect()->route('coach.portal.code', $code);
        }

        $toernooi = $this->getActiveToernooi();

        return view('pages.coach.betaling-succes', [
            'toernooi' => $toernooi,
            'club' => $club,
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
        $club = $this->getLoggedInClub($request, $code);

        if (!$club) {
            return redirect()->route('coach.portal.code', $code);
        }

        $toernooi = $this->getActiveToernooi();

        $publiekController = new PubliekController();
        $clubResultaten = $publiekController->getClubResultaten($toernooi, $club->id);
        $clubRanking = $publiekController->getClubRanking($toernooi);

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

        $medailles = ['goud' => 0, 'zilver' => 0, 'brons' => 0];
        foreach ($clubResultaten as $r) {
            if ($r['plaats'] === 1) $medailles['goud']++;
            if ($r['plaats'] === 2) $medailles['zilver']++;
            if ($r['plaats'] === 3) $medailles['brons']++;
        }

        return view('pages.coach.resultaten', [
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
