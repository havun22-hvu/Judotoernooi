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

        $telefoon = preg_replace('/[^0-9+]/', '', $telefoon);

        if (str_starts_with($telefoon, '06')) {
            $telefoon = '+31' . substr($telefoon, 1);
        } elseif (str_starts_with($telefoon, '0031')) {
            $telefoon = '+31' . substr($telefoon, 4);
        }

        return $telefoon ?: null;
    }

    // ========================================
    // Helper methods
    // ========================================

    private function getToernooiFromRoute(string $organisator, string $toernooi): Toernooi
    {
        $org = \App\Models\Organisator::where('slug', $organisator)->firstOrFail();
        return Toernooi::where('organisator_id', $org->id)
            ->where('slug', $toernooi)
            ->firstOrFail();
    }

    private function getClubByCode(Toernooi $toernooi, string $code): ?Club
    {
        return $toernooi->getClubByPortalCode($code);
    }

    private function getClubByLegacyCode(string $code): ?Club
    {
        // Search in pivot table for portal_code
        $pivot = \DB::table('club_toernooi')->where('portal_code', $code)->first();
        if (!$pivot) {
            return null;
        }
        return Club::find($pivot->club_id);
    }

    private function getLoggedInClub(Request $request, Toernooi $toernooi, string $code): ?Club
    {
        $sessionKey = "club_logged_in_{$toernooi->id}_{$code}";
        if (!$request->session()->get($sessionKey)) {
            return null;
        }
        return $this->getClubByCode($toernooi, $code);
    }

    private function routeParams(string $organisator, string $toernooi, string $code): array
    {
        return [
            'organisator' => $organisator,
            'toernooi' => $toernooi,
            'code' => $code,
        ];
    }

    private function redirectToLoginExpired(string $organisator, string $toernooi, string $code): RedirectResponse
    {
        return redirect()->route('coach.portal.code', $this->routeParams($organisator, $toernooi, $code))
            ->with('error', 'Je sessie is verlopen. Log opnieuw in.');
    }

    // ========================================
    // Legacy redirect
    // ========================================

    public function redirectLegacy(string $code): RedirectResponse
    {
        // Search in pivot table for this portal_code
        $pivot = \DB::table('club_toernooi')->where('portal_code', $code)->first();

        if (!$pivot) {
            abort(404, 'Ongeldige school link');
        }

        $toernooi = Toernooi::with('organisator')->find($pivot->toernooi_id);

        if (!$toernooi) {
            abort(404, 'Toernooi niet gevonden');
        }

        return redirect()->route('coach.portal.code', [
            'organisator' => $toernooi->organisator->slug,
            'toernooi' => $toernooi->slug,
            'code' => $code,
        ]);
    }

    // ========================================
    // Portal code + PIN based methods
    // ========================================

    public function indexCode(Request $request, string $organisator, string $toernooi, string $code): View|RedirectResponse
    {
        $toernooiModel = $this->getToernooiFromRoute($organisator, $toernooi);
        $club = $this->getClubByCode($toernooiModel, $code);

        if (!$club) {
            abort(404, 'Ongeldige school link');
        }

        if ($this->getLoggedInClub($request, $toernooiModel, $code)) {
            return redirect()->route('coach.portal.judokas', $this->routeParams($organisator, $toernooi, $code));
        }

        return view('pages.coach.login-pin', [
            'code' => $code,
            'club' => $club,
            'toernooi' => $toernooiModel,
            'organisator' => $organisator,
            'toernooiSlug' => $toernooi,
        ]);
    }

    public function loginPin(Request $request, string $organisator, string $toernooi, string $code): RedirectResponse
    {
        $toernooiModel = $this->getToernooiFromRoute($organisator, $toernooi);
        $club = $this->getClubByCode($toernooiModel, $code);

        if (!$club) {
            abort(404);
        }

        $validated = $request->validate([
            'pincode' => 'required|string|size:5',
        ]);

        if (!$club->checkPincodeForToernooi($toernooiModel, $validated['pincode'])) {
            return redirect()->route('coach.portal.code', $this->routeParams($organisator, $toernooi, $code))
                ->with('error', 'Onjuiste PIN code');
        }

        $request->session()->put("club_logged_in_{$toernooiModel->id}_{$code}", true);

        return redirect()->route('coach.portal.judokas', $this->routeParams($organisator, $toernooi, $code));
    }

    public function logoutCode(Request $request, string $organisator, string $toernooi, string $code): RedirectResponse
    {
        $toernooiModel = $this->getToernooiFromRoute($organisator, $toernooi);
        $request->session()->forget("club_logged_in_{$toernooiModel->id}_{$code}");

        return redirect()->route('coach.portal.code', $this->routeParams($organisator, $toernooi, $code))
            ->with('success', 'Je bent uitgelogd');
    }

    public function judokasCode(Request $request, string $organisator, string $toernooi, string $code): View|RedirectResponse
    {
        $toernooiModel = $this->getToernooiFromRoute($organisator, $toernooi);
        $club = $this->getLoggedInClub($request, $toernooiModel, $code);

        if (!$club) {
            return redirect()->route('coach.portal.code', $this->routeParams($organisator, $toernooi, $code));
        }

        $judokas = Judoka::where('toernooi_id', $toernooiModel->id)
            ->where('club_id', $club->id)
            ->orderByDesc('geboortejaar')
            ->orderBy('gewicht')
            ->orderBy('naam')
            ->get();

        $config = $toernooiModel->getAlleGewichtsklassen();
        $leeftijdsklassen = collect($config)->pluck('label')->unique()->values()->all();

        $volledigeOnbetaald = $judokas->filter(fn($j) => $j->isKlaarVoorBetaling());
        $betaald = $judokas->filter(fn($j) => $j->isBetaald());

        return view('pages.coach.judokas', [
            'toernooi' => $toernooiModel,
            'club' => $club,
            'judokas' => $judokas,
            'leeftijdsklassen' => $leeftijdsklassen,
            'gewichtsklassen' => $toernooiModel->getAlleGewichtsklassen(),
            'inschrijvingOpen' => $toernooiModel->isInschrijvingOpen(),
            'maxBereikt' => $toernooiModel->isMaxJudokasBereikt(),
            'bijna80ProcentVol' => $toernooiModel->isBijna80ProcentVol(),
            'bezettingsPercentage' => $toernooiModel->bezettings_percentage,
            'plaatsenOver' => $toernooiModel->plaatsen_over,
            'totaalJudokas' => $toernooiModel->judokas()->count(),
            'useCode' => true,
            'code' => $code,
            'organisator' => $organisator,
            'toernooiSlug' => $toernooi,
            'eliminatieGewichtsklassen' => $toernooiModel->eliminatie_gewichtsklassen ?? [],
            'wedstrijdSysteem' => $toernooiModel->wedstrijd_systeem ?? [],
            'betalingActief' => $toernooiModel->betaling_actief,
            'inschrijfgeld' => $toernooiModel->inschrijfgeld,
            'volledigeOnbetaald' => $volledigeOnbetaald,
            'aantalBetaald' => $betaald->count(),
            'magInschrijven' => $toernooiModel->portaalMagInschrijven(),
            'magWijzigen' => $toernooiModel->portaalMagWijzigen(),
        ]);
    }

    public function storeJudokaCode(Request $request, string $organisator, string $toernooi, string $code): RedirectResponse
    {
        $toernooiModel = $this->getToernooiFromRoute($organisator, $toernooi);
        $club = $this->getLoggedInClub($request, $toernooiModel, $code);

        if (!$club) {
            return $this->redirectToLoginExpired($organisator, $toernooi, $code);
        }

        if (!$toernooiModel->portaalMagInschrijven()) {
            return redirect()->route('coach.portal.judokas', $this->routeParams($organisator, $toernooi, $code))
                ->with('error', 'Nieuwe inschrijvingen zijn niet toegestaan via het portaal');
        }

        if (!$toernooiModel->isInschrijvingOpen()) {
            return redirect()->route('coach.portal.judokas', $this->routeParams($organisator, $toernooi, $code))
                ->with('error', 'De inschrijving is gesloten');
        }

        if ($toernooiModel->isMaxJudokasBereikt()) {
            return redirect()->route('coach.portal.judokas', $this->routeParams($organisator, $toernooi, $code))
                ->with('error', 'Maximum aantal deelnemers bereikt');
        }

        // Check freemium judoka limit
        if (!$toernooiModel->canAddMoreJudokas()) {
            return redirect()->route('coach.portal.judokas', $this->routeParams($organisator, $toernooi, $code))
                ->with('error', 'Maximum aantal judoka\'s voor dit toernooi bereikt. Neem contact op met de organisator om te upgraden.');
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
            $leeftijdsklasse = $toernooiModel->bepaalLeeftijdsklasse($leeftijd, $validated['geslacht'], $band);

            if (!empty($validated['gewicht']) && empty($gewichtsklasse)) {
                $gewichtsklasse = $toernooiModel->bepaalGewichtsklasse($validated['gewicht'], $leeftijd, $validated['geslacht'], $band);
            }
        }

        if (empty($gewichtsklasse) && !empty($validated['gewicht'])) {
            $gewichtsklasse = '-' . (int) $validated['gewicht'];
        }

        $bestaande = Judoka::where('toernooi_id', $toernooiModel->id)
            ->where('naam', $validated['naam'])
            ->where('geboortejaar', $validated['geboortejaar'])
            ->first();

        if ($bestaande) {
            return redirect()->route('coach.portal.judokas', $this->routeParams($organisator, $toernooi, $code))
                ->with('error', 'Deze judoka bestaat al');
        }

        Judoka::create([
            'toernooi_id' => $toernooiModel->id,
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

        return redirect()->route('coach.portal.judokas', $this->routeParams($organisator, $toernooi, $code))
            ->with('success', 'Judoka toegevoegd');
    }

    public function updateJudokaCode(Request $request, string $organisator, string $toernooi, string $code, Judoka $judoka): RedirectResponse
    {
        $toernooiModel = $this->getToernooiFromRoute($organisator, $toernooi);
        $club = $this->getLoggedInClub($request, $toernooiModel, $code);

        if (!$club) {
            return $this->redirectToLoginExpired($organisator, $toernooi, $code);
        }

        if ($judoka->club_id !== $club->id || $judoka->toernooi_id !== $toernooiModel->id) {
            abort(403);
        }

        if (!$toernooiModel->portaalMagWijzigen()) {
            return redirect()->route('coach.portal.judokas', $this->routeParams($organisator, $toernooi, $code))
                ->with('error', 'Wijzigingen zijn niet toegestaan via het portaal');
        }

        if (!$toernooiModel->isInschrijvingOpen()) {
            return redirect()->route('coach.portal.judokas', $this->routeParams($organisator, $toernooi, $code))
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
            $leeftijdsklasse = $toernooiModel->bepaalLeeftijdsklasse($leeftijd, $validated['geslacht'], $band);

            if (!empty($validated['gewicht']) && empty($gewichtsklasse)) {
                $gewichtsklasse = $toernooiModel->bepaalGewichtsklasse($validated['gewicht'], $leeftijd, $validated['geslacht'], $band);
            }
        }

        if (empty($gewichtsklasse) && !empty($validated['gewicht'])) {
            $gewichtsklasse = '-' . (int) $validated['gewicht'];
        }

        // Free tier: naam cannot be changed after creation
        $updateData = [
            'geboortejaar' => $validated['geboortejaar'] ?? null,
            'geslacht' => $validated['geslacht'] ?? null,
            'band' => $validated['band'] ?? null,
            'gewicht' => $validated['gewicht'] ?? null,
            'leeftijdsklasse' => $leeftijdsklasse,
            'gewichtsklasse' => $gewichtsklasse,
            'telefoon' => $this->parseTelefoon($validated['telefoon'] ?? null),
        ];

        if (!$toernooiModel->isFreeTier()) {
            $updateData['naam'] = $validated['naam'];
        }

        $judoka->update($updateData);

        $judoka->hervalideerImportStatus();

        return redirect()->route('coach.portal.judokas', $this->routeParams($organisator, $toernooi, $code))
            ->with('success', 'Judoka bijgewerkt');
    }

    public function destroyJudokaCode(Request $request, string $organisator, string $toernooi, string $code, Judoka $judoka): RedirectResponse
    {
        $toernooiModel = $this->getToernooiFromRoute($organisator, $toernooi);
        $club = $this->getLoggedInClub($request, $toernooiModel, $code);

        if (!$club) {
            return $this->redirectToLoginExpired($organisator, $toernooi, $code);
        }

        if ($judoka->club_id !== $club->id || $judoka->toernooi_id !== $toernooiModel->id) {
            abort(403);
        }

        // Free tier: judokas cannot be deleted
        if ($toernooiModel->isFreeTier()) {
            return redirect()->route('coach.portal.judokas', $this->routeParams($organisator, $toernooi, $code))
                ->with('error', 'In de gratis versie kunnen judoka\'s niet verwijderd worden.');
        }

        if (!$toernooiModel->portaalMagInschrijven()) {
            return redirect()->route('coach.portal.judokas', $this->routeParams($organisator, $toernooi, $code))
                ->with('error', 'Verwijderen is niet toegestaan via het portaal');
        }

        if (!$toernooiModel->isInschrijvingOpen()) {
            return redirect()->route('coach.portal.judokas', $this->routeParams($organisator, $toernooi, $code))
                ->with('error', 'De inschrijving is gesloten');
        }

        $judoka->delete();

        return redirect()->route('coach.portal.judokas', $this->routeParams($organisator, $toernooi, $code))
            ->with('success', 'Judoka verwijderd');
    }

    public function weegkaartenCode(Request $request, string $organisator, string $toernooi, string $code): View|RedirectResponse
    {
        $toernooiModel = $this->getToernooiFromRoute($organisator, $toernooi);
        $club = $this->getLoggedInClub($request, $toernooiModel, $code);

        if (!$club) {
            return redirect()->route('coach.portal.code', $this->routeParams($organisator, $toernooi, $code));
        }

        $judokas = Judoka::where('toernooi_id', $toernooiModel->id)
            ->where('club_id', $club->id)
            ->with(['poules.blok'])
            ->orderBy('sort_categorie')
            ->orderBy('sort_gewicht')
            ->orderBy('sort_band')
            ->orderBy('naam')
            ->get();

        return view('pages.coach.weegkaarten', [
            'toernooi' => $toernooiModel,
            'club' => $club,
            'judokas' => $judokas,
            'useCode' => true,
            'code' => $code,
            'organisator' => $organisator,
            'toernooiSlug' => $toernooi,
        ]);
    }

    public function coachkaartenCode(Request $request, string $organisator, string $toernooi, string $code): View|RedirectResponse
    {
        $toernooiModel = $this->getToernooiFromRoute($organisator, $toernooi);
        $club = $this->getLoggedInClub($request, $toernooiModel, $code);

        if (!$club) {
            return redirect()->route('coach.portal.code', $this->routeParams($organisator, $toernooi, $code));
        }

        $judokas = Judoka::where('toernooi_id', $toernooiModel->id)
            ->where('club_id', $club->id)
            ->with('poules.blok')
            ->get();

        $aantalJudokas = $judokas->count();
        $blokkenIngedeeld = $judokas->contains(fn($j) => $j->poules->contains(fn($p) => $p->blok_id !== null));

        $coachKaarten = CoachKaart::where('toernooi_id', $toernooiModel->id)
            ->where('club_id', $club->id)
            ->with(['wisselingen', 'checkinsVandaag'])
            ->orderBy('id')
            ->get();

        if ($coachKaarten->isEmpty()) {
            CoachKaart::create([
                'toernooi_id' => $toernooiModel->id,
                'club_id' => $club->id,
            ]);
            $coachKaarten = CoachKaart::where('toernooi_id', $toernooiModel->id)
                ->where('club_id', $club->id)
                ->with(['wisselingen', 'checkinsVandaag'])
                ->orderBy('id')
                ->get();
        } elseif ($coachKaarten->count() > 1 && !$toernooiModel->voorbereiding_klaar_op) {
            $kaartToKeep = $coachKaarten->first();
            CoachKaart::where('toernooi_id', $toernooiModel->id)
                ->where('club_id', $club->id)
                ->where('id', '!=', $kaartToKeep->id)
                ->where('is_gescand', false)
                ->delete();
            $coachKaarten = CoachKaart::where('toernooi_id', $toernooiModel->id)
                ->where('club_id', $club->id)
                ->with(['wisselingen', 'checkinsVandaag'])
                ->orderBy('id')
                ->get();
        }

        $benodigdNaVoorbereiding = $club->berekenAantalCoachKaarten($toernooiModel, true);

        $organisatieCoaches = Coach::where('toernooi_id', $toernooiModel->id)
            ->where('club_id', $club->id)
            ->get();

        return view('pages.coach.coachkaarten', [
            'toernooi' => $toernooiModel,
            'club' => $club,
            'coachKaarten' => $coachKaarten,
            'organisatieCoaches' => $organisatieCoaches,
            'aantalJudokas' => $aantalJudokas,
            'benodigdAantal' => $coachKaarten->count(),
            'benodigdNaVoorbereiding' => $benodigdNaVoorbereiding,
            'judokasPerCoach' => $toernooiModel->judokas_per_coach ?? 5,
            'blokkenIngedeeld' => $blokkenIngedeeld,
            'voorbereidingAfgerond' => $toernooiModel->voorbereiding_klaar_op !== null,
            'useCode' => true,
            'code' => $code,
            'organisator' => $organisator,
            'toernooiSlug' => $toernooi,
        ]);
    }

    public function toewijzenCoachkaart(Request $request, string $organisator, string $toernooi, string $code, CoachKaart $coachKaart): RedirectResponse
    {
        $toernooiModel = $this->getToernooiFromRoute($organisator, $toernooi);
        $club = $this->getLoggedInClub($request, $toernooiModel, $code);

        if (!$club) {
            return $this->redirectToLoginExpired($organisator, $toernooi, $code);
        }

        if ($coachKaart->club_id !== $club->id || $coachKaart->toernooi_id !== $toernooiModel->id) {
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

        return redirect()->route('coach.portal.coachkaarten', $this->routeParams($organisator, $toernooi, $code))
            ->with('success', 'Coach kaart bijgewerkt');
    }

    public function syncJudokasCode(Request $request, string $organisator, string $toernooi, string $code): RedirectResponse
    {
        $toernooiModel = $this->getToernooiFromRoute($organisator, $toernooi);
        $club = $this->getLoggedInClub($request, $toernooiModel, $code);

        if (!$club) {
            return redirect()->route('coach.portal.code', $this->routeParams($organisator, $toernooi, $code));
        }

        if (!$toernooiModel->portaalMagWijzigen()) {
            return redirect()->route('coach.portal.judokas', $this->routeParams($organisator, $toernooi, $code))
                ->with('error', 'Sync is niet toegestaan via het portaal');
        }

        if (!$toernooiModel->isInschrijvingOpen()) {
            return redirect()->route('coach.portal.judokas', $this->routeParams($organisator, $toernooi, $code))
                ->with('error', 'De inschrijving is gesloten');
        }

        $judokas = Judoka::where('toernooi_id', $toernooiModel->id)
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
            return redirect()->route('coach.portal.judokas', $this->routeParams($organisator, $toernooi, $code))
                ->with('warning', implode('. ', $messages) . '.');
        }

        return redirect()->route('coach.portal.judokas', $this->routeParams($organisator, $toernooi, $code))
            ->with('success', "{$synced} judoka(s) succesvol gesynced!");
    }

    // ========================================
    // Payment methods
    // ========================================

    public function afrekenCode(Request $request, string $organisator, string $toernooi, string $code): View|RedirectResponse
    {
        $toernooiModel = $this->getToernooiFromRoute($organisator, $toernooi);
        $club = $this->getLoggedInClub($request, $toernooiModel, $code);

        if (!$club) {
            return redirect()->route('coach.portal.code', $this->routeParams($organisator, $toernooi, $code));
        }

        if (!$toernooiModel->betaling_actief) {
            return redirect()->route('coach.portal.judokas', $this->routeParams($organisator, $toernooi, $code))
                ->with('error', 'Betalingen zijn niet actief voor dit toernooi');
        }

        $judokas = Judoka::where('toernooi_id', $toernooiModel->id)
            ->where('club_id', $club->id)
            ->orderBy('naam')
            ->get();

        $klaarVoorBetaling = $judokas->filter(fn($j) => $j->isKlaarVoorBetaling());
        $reedsBetaald = $judokas->filter(fn($j) => $j->isBetaald());

        $totaalBedrag = $klaarVoorBetaling->count() * $toernooiModel->inschrijfgeld;

        return view('pages.coach.afrekenen', [
            'toernooi' => $toernooiModel,
            'club' => $club,
            'klaarVoorBetaling' => $klaarVoorBetaling,
            'reedsBetaald' => $reedsBetaald,
            'totaalBedrag' => $totaalBedrag,
            'inschrijfgeld' => $toernooiModel->inschrijfgeld,
            'useCode' => true,
            'code' => $code,
            'organisator' => $organisator,
            'toernooiSlug' => $toernooi,
        ]);
    }

    public function betalenCode(Request $request, string $organisator, string $toernooi, string $code): RedirectResponse
    {
        $toernooiModel = $this->getToernooiFromRoute($organisator, $toernooi);
        $club = $this->getLoggedInClub($request, $toernooiModel, $code);

        if (!$club) {
            return $this->redirectToLoginExpired($organisator, $toernooi, $code);
        }

        if (!$toernooiModel->betaling_actief) {
            return redirect()->route('coach.portal.judokas', $this->routeParams($organisator, $toernooi, $code))
                ->with('error', 'Betalingen zijn niet actief');
        }

        $klaarVoorBetaling = Judoka::where('toernooi_id', $toernooiModel->id)
            ->where('club_id', $club->id)
            ->get()
            ->filter(fn($j) => $j->isKlaarVoorBetaling());

        if ($klaarVoorBetaling->isEmpty()) {
            return redirect()->route('coach.portal.judokas', $this->routeParams($organisator, $toernooi, $code))
                ->with('error', 'Geen judoka\'s om af te rekenen');
        }

        $totaalBedrag = $klaarVoorBetaling->count() * $toernooiModel->inschrijfgeld;
        $description = "{$toernooiModel->naam} - {$club->naam} - {$klaarVoorBetaling->count()} judoka's";

        $betaling = \App\Models\Betaling::create([
            'toernooi_id' => $toernooiModel->id,
            'club_id' => $club->id,
            'mollie_payment_id' => 'pending_' . uniqid(),
            'bedrag' => $totaalBedrag,
            'aantal_judokas' => $klaarVoorBetaling->count(),
            'status' => \App\Models\Betaling::STATUS_OPEN,
        ]);

        foreach ($klaarVoorBetaling as $judoka) {
            $judoka->update(['betaling_id' => $betaling->id]);
        }

        $redirectUrl = route('coach.portal.betaling.succes', $this->routeParams($organisator, $toernooi, $code));

        if ($this->mollieService->isSimulationMode()) {
            $payment = $this->mollieService->simulatePayment([
                'amount' => ['currency' => 'EUR', 'value' => number_format($totaalBedrag, 2, '.', '')],
                'description' => $description,
                'redirectUrl' => $redirectUrl,
                'webhookUrl' => route('mollie.webhook'),
                'metadata' => ['betaling_id' => $betaling->id],
            ]);

            $betaling->update(['mollie_payment_id' => $payment->id]);

            return redirect($payment->_links->checkout->href);
        }

        try {
            $this->mollieService->ensureValidToken($toernooiModel);

            $payment = $this->mollieService->createPayment($toernooiModel, [
                'amount' => ['currency' => 'EUR', 'value' => number_format($totaalBedrag, 2, '.', '')],
                'description' => $description,
                'redirectUrl' => $redirectUrl,
                'webhookUrl' => route('mollie.webhook'),
                'metadata' => ['betaling_id' => $betaling->id],
            ]);

            $betaling->update(['mollie_payment_id' => $payment->id]);

            return redirect($payment->_links->checkout->href);
        } catch (\Exception $e) {
            \Log::error('Mollie payment creation failed', ['error' => $e->getMessage()]);
            $betaling->update(['status' => \App\Models\Betaling::STATUS_FAILED]);

            return redirect()->route('coach.portal.judokas', $this->routeParams($organisator, $toernooi, $code))
                ->with('error', 'Fout bij aanmaken betaling: ' . $e->getMessage());
        }
    }

    public function betalingSuccesCode(Request $request, string $organisator, string $toernooi, string $code): View|RedirectResponse
    {
        $toernooiModel = $this->getToernooiFromRoute($organisator, $toernooi);
        $club = $this->getLoggedInClub($request, $toernooiModel, $code);

        if (!$club) {
            return redirect()->route('coach.portal.code', $this->routeParams($organisator, $toernooi, $code));
        }

        return view('pages.coach.betaling-succes', [
            'toernooi' => $toernooiModel,
            'club' => $club,
            'useCode' => true,
            'code' => $code,
            'organisator' => $organisator,
            'toernooiSlug' => $toernooi,
        ]);
    }

    public function betalingGeannuleerdCode(Request $request, string $organisator, string $toernooi, string $code): RedirectResponse
    {
        return redirect()->route('coach.portal.judokas', $this->routeParams($organisator, $toernooi, $code))
            ->with('warning', 'Betaling geannuleerd');
    }

    public function resultatenCode(Request $request, string $organisator, string $toernooi, string $code): View|RedirectResponse
    {
        $toernooiModel = $this->getToernooiFromRoute($organisator, $toernooi);
        $club = $this->getLoggedInClub($request, $toernooiModel, $code);

        if (!$club) {
            return redirect()->route('coach.portal.code', $this->routeParams($organisator, $toernooi, $code));
        }

        $publiekController = app(PubliekController::class);
        $clubResultaten = $publiekController->getClubResultaten($toernooiModel->organisator, $toernooiModel, $club->id);
        $clubRanking = $publiekController->getClubRanking($toernooiModel);

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
            'toernooi' => $toernooiModel,
            'club' => $club,
            'resultaten' => $clubResultaten,
            'clubRanking' => $clubRanking,
            'clubPositieAbsoluut' => $clubPositieAbsoluut,
            'clubPositieRelatief' => $clubPositieRelatief,
            'medailles' => $medailles,
            'useCode' => true,
            'code' => $code,
            'organisator' => $organisator,
            'toernooiSlug' => $toernooi,
        ]);
    }
}
