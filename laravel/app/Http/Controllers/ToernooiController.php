<?php

namespace App\Http\Controllers;

use App\Http\Requests\ToernooiRequest;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Toernooi;
use App\Models\ToernooiTemplate;
use App\Services\CategorieClassifier;
use App\Services\PouleIndelingService;
use App\Services\ToernooiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ToernooiController extends Controller
{
    public function __construct(
        private ToernooiService $toernooiService,
        private PouleIndelingService $pouleIndelingService
    ) {}

    public function index(): View
    {
        // Only sitebeheerder can access this page
        $user = auth('organisator')->user();
        if (!$user || !$user->isSitebeheerder()) {
            abort(403, 'Alleen sitebeheerders hebben toegang tot deze pagina.');
        }

        // Group toernooien by organisator for superadmin overview
        $organisatoren = Organisator::with(['toernooien' => function($q) {
            $q->withCount(['judokas', 'poules'])
              ->orderByDesc('datum');
        }])
        ->withCount(['clubs', 'toernooiTemplates'])
        ->orderBy('naam')
        ->get();

        // Also get toernooien without organisator (legacy/orphaned)
        $toernooienZonderOrganisator = Toernooi::whereDoesntHave('organisatoren')
            ->withCount(['judokas', 'poules'])
            ->orderByDesc('updated_at')
            ->get();

        return view('pages.toernooi.index', compact('organisatoren', 'toernooienZonderOrganisator'));
    }

    public function create(Organisator $organisator): View
    {
        $templates = $organisator->toernooiTemplates()->orderBy('naam')->get();

        return view('pages.toernooi.create', compact('organisator', 'templates'));
    }

    public function store(Organisator $organisator, ToernooiRequest $request): RedirectResponse
    {
        $toernooi = $this->toernooiService->initialiseerToernooi($request->validated());

        return redirect()
            ->route('toernooi.show', $toernooi->routeParams())
            ->with('success', 'Toernooi succesvol aangemaakt');
    }

    public function show(Organisator $organisator, Toernooi $toernooi): View
    {
        $statistieken = $this->toernooiService->getStatistieken($toernooi);

        return view('pages.toernooi.show', compact('toernooi', 'statistieken'));
    }

    public function edit(Organisator $organisator, Toernooi $toernooi): View
    {
        $blokken = $toernooi->blokken()->orderBy('nummer')->get();

        // Clubs voor noodplan tab (weegkaarten, coachkaarten per club)
        $clubs = \App\Models\Club::whereHas('judokas', fn($q) => $q->where('toernooi_id', $toernooi->id))
            ->orderBy('naam')
            ->get();

        try {
            $overlapWarning = $this->checkCategorieOverlap($toernooi);
        } catch (\Throwable $e) {
            \Log::error('Category overlap check failed: ' . $e->getMessage());
            $overlapWarning = null;
        }

        return view('pages.toernooi.edit', compact('toernooi', 'blokken', 'clubs', 'overlapWarning'));
    }

    public function update(Organisator $organisator, ToernooiRequest $request, Toernooi $toernooi): RedirectResponse|JsonResponse
    {
        $data = $request->validated();

        // Process gewichtsklassen from JSON input (includes leeftijdsgrenzen)
        if ($request->has('gewichtsklassen_json') && $request->input('gewichtsklassen_json')) {
            $jsonData = json_decode($request->input('gewichtsklassen_json'), true) ?? [];

            // Extract wedstrijd_systeem from each category and build separate array
            $wedstrijdSysteem = [];
            foreach ($jsonData as $key => $categorie) {
                if (is_array($categorie) && isset($categorie['wedstrijd_systeem'])) {
                    $wedstrijdSysteem[$key] = $categorie['wedstrijd_systeem'];
                    unset($jsonData[$key]['wedstrijd_systeem']); // Remove from gewichtsklassen
                }
            }
            if (!empty($wedstrijdSysteem)) {
                $data['wedstrijd_systeem'] = $wedstrijdSysteem;
            }

            $data['gewichtsklassen'] = $jsonData;
        } elseif (isset($data['gewichtsklassen'])) {
            // Fallback: process from individual form fields
            $gewichtsklassen = [];
            $standaard = Toernooi::getStandaardGewichtsklassen();
            $leeftijden = $request->input('gewichtsklassen_leeftijd', []);
            $labels = $request->input('gewichtsklassen_label', []);
            $geslachten = $request->input('gewichtsklassen_geslacht', []);
            $maxKgVerschillen = $request->input('gewichtsklassen_max_kg', []);
            $maxLftVerschillen = $request->input('gewichtsklassen_max_lft', []);

            foreach ($data['gewichtsklassen'] as $key => $value) {
                $gewichten = array_map('trim', explode(',', $value));
                $gewichten = array_filter($gewichten, fn($g) => !empty($g));
                $gewichtsklassen[$key] = [
                    'label' => $labels[$key] ?? $standaard[$key]['label'] ?? ucfirst(str_replace('_', ' ', $key)),
                    'max_leeftijd' => (int) ($leeftijden[$key] ?? $standaard[$key]['max_leeftijd'] ?? 99),
                    'geslacht' => $geslachten[$key] ?? 'gemengd',
                    'max_kg_verschil' => (float) ($maxKgVerschillen[$key] ?? 0),
                    'max_leeftijd_verschil' => (int) ($maxLftVerschillen[$key] ?? 0),
                    'gewichten' => array_values($gewichten),
                ];
            }

            $data['gewichtsklassen'] = $gewichtsklassen;
        }

        // Sort categories by max_leeftijd (youngest first)
        if (!empty($data['gewichtsklassen']) && is_array($data['gewichtsklassen'])) {
            uasort($data['gewichtsklassen'], function ($a, $b) {
                return ($a['max_leeftijd'] ?? 99) <=> ($b['max_leeftijd'] ?? 99);
            });
        }

        // Process wedstrijd_systeem from form (fallback if not in JSON)
        if (!isset($data['wedstrijd_systeem']) && $request->has('wedstrijd_systeem')) {
            $data['wedstrijd_systeem'] = $request->input('wedstrijd_systeem');
        }

        // Remove temporary fields from data
        unset($data['gewichtsklassen_leeftijd'], $data['gewichtsklassen_label'], $data['gewichtsklassen_geslacht'], $data['gewichtsklassen_max_kg'], $data['gewichtsklassen_max_lft']);

        // Handle gebruik_gewichtsklassen - keep existing value if not in form data
        // (the form doesn't have a checkbox for this, it's set during toernooi creation)
        if (!isset($data['gebruik_gewichtsklassen'])) {
            unset($data['gebruik_gewichtsklassen']); // Don't overwrite existing value
        } else {
            $data['gebruik_gewichtsklassen'] = (bool) $data['gebruik_gewichtsklassen'];
        }

        // Handle coach_incheck_actief checkbox
        $data['coach_incheck_actief'] = (bool) ($data['coach_incheck_actief'] ?? false);

        // Handle poule match settings checkboxes
        if (array_key_exists('dubbel_bij_2_judokas', $data)) {
            $data['dubbel_bij_2_judokas'] = (bool) $data['dubbel_bij_2_judokas'];
        }
        if (array_key_exists('best_of_three_bij_2', $data)) {
            $data['best_of_three_bij_2'] = (bool) $data['best_of_three_bij_2'];

            // Auto-update wedstrijd_schemas[2] based on best_of_three setting
            $schemas = $data['wedstrijd_schemas'] ?? $toernooi->wedstrijd_schemas ?? [];
            if ($data['best_of_three_bij_2']) {
                $schemas[2] = [[1, 2], [2, 1], [1, 2]]; // 3 wedstrijden
            } else {
                $schemas[2] = $data['dubbel_bij_2_judokas'] ?? true
                    ? [[1, 2], [2, 1]]  // 2 wedstrijden (dubbel)
                    : [[1, 2]];          // 1 wedstrijd (enkel)
            }
            $data['wedstrijd_schemas'] = $schemas;
        }
        if (array_key_exists('dubbel_bij_3_judokas', $data)) {
            $data['dubbel_bij_3_judokas'] = (bool) $data['dubbel_bij_3_judokas'];
        }
        if (array_key_exists('dubbel_bij_4_judokas', $data)) {
            $data['dubbel_bij_4_judokas'] = (bool) $data['dubbel_bij_4_judokas'];
        }

        // Check of categorieën zijn gewijzigd
        $oudeGewichtsklassen = $toernooi->gewichtsklassen ?? [];
        $nieuweGewichtsklassen = $data['gewichtsklassen'] ?? [];
        $categorieenGewijzigd = json_encode($oudeGewichtsklassen) !== json_encode($nieuweGewichtsklassen);

        $toernooi->update($data);

        // Auto-valideer judoka's als categorieën gewijzigd zijn
        if ($categorieenGewijzigd && $toernooi->judokas()->count() > 0) {
            app(JudokaController::class)->voerValidatieUit($organisator, $toernooi);
        }

        // Sync blokken and matten to match settings
        $blokkenResult = $this->toernooiService->syncBlokken($toernooi);
        $this->toernooiService->syncMatten($toernooi);

        // Build warning message for moved poules
        $blokkenWarning = null;
        if ($blokkenResult['verplaatste_poules'] > 0) {
            $blokkenWarning = "Let op: {$blokkenResult['verplaatste_poules']} poule(s) zijn naar het sleepvak verplaatst omdat hun blok is verwijderd.";
        }

        // Check for overlapping categories
        try {
            $overlapWarning = $this->checkCategorieOverlap($toernooi);
        } catch (\Throwable $e) {
            \Log::error('Category overlap check failed: ' . $e->getMessage());
            $overlapWarning = null;
        }

        // Return JSON for AJAX requests (auto-save)
        if ($request->ajax()) {
            // Refresh toernooi to get updated judoka counts after validation
            $toernooi->refresh();
            $nietGecategoriseerd = $toernooi->countNietGecategoriseerd();

            return response()->json([
                'success' => true,
                'overlapWarning' => $overlapWarning,
                'blokkenWarning' => $blokkenWarning,
                'nietGecategoriseerd' => $nietGecategoriseerd,
            ]);
        }

        $redirect = redirect()->route('toernooi.edit', $toernooi->routeParams());

        // Combine warnings
        $warnings = array_filter([$overlapWarning, $blokkenWarning]);
        if (!empty($warnings)) {
            return $redirect->with('warning', implode(' ', $warnings));
        }

        return $redirect->with('success', 'Toernooi bijgewerkt');
    }

    public function destroy(Organisator $organisator, Request $request, Toernooi $toernooi): RedirectResponse
    {
        $loggedIn = auth('organisator')->user();

        // Eigenaar of sitebeheerder mag verwijderen
        if (!$loggedIn || (!$loggedIn->isSitebeheerder() && !$loggedIn->ownsToernooi($toernooi))) {
            return redirect()
                ->route('organisator.dashboard', $organisator)
                ->with('error', 'Je hebt geen rechten om dit toernooi te verwijderen');
        }

        $naam = $toernooi->naam;
        $bewaarPresets = $request->boolean('bewaar_presets');

        // Delete all related data explicitly
        $pouleIds = $toernooi->poules()->pluck('id');
        \App\Models\Wedstrijd::whereIn('poule_id', $pouleIds)->delete();
        \DB::table('poule_judoka')->whereIn('poule_id', $pouleIds)->delete();
        $toernooi->poules()->delete();
        $toernooi->judokas()->delete();
        $toernooi->blokken()->delete();
        $toernooi->matten()->delete();

        // Presets alleen verwijderen als gebruiker dat wil
        if (!$bewaarPresets) {
            \App\Models\GewichtsklassenPreset::where('organisator_id', $loggedIn->id)->delete();
        }

        $toernooi->delete();

        $message = "Toernooi '{$naam}' volledig verwijderd";
        if ($bewaarPresets) {
            $message .= " (presets bewaard)";
        }

        // Sitebeheerder terug naar overzicht, organisator naar dashboard
        if ($loggedIn->isSitebeheerder()) {
            return redirect()->route('admin.index')->with('success', $message);
        }

        return redirect()
            ->route('organisator.dashboard', $organisator)
            ->with('success', $message);
    }

    /**
     * Reset tournament - keeps settings, deletes judokas/poules/wedstrijden
     * Access is controlled by route middleware - all organisators with access can reset
     */
    public function reset(Organisator $organisator, Toernooi $toernooi): RedirectResponse
    {
        // Delete wedstrijden and poules
        $pouleIds = $toernooi->poules()->pluck('id');
        $wedstrijdCount = \App\Models\Wedstrijd::whereIn('poule_id', $pouleIds)->delete();
        \DB::table('poule_judoka')->whereIn('poule_id', $pouleIds)->delete();
        $pouleCount = $toernooi->poules()->delete();

        // Delete all judokas
        $judokaCount = $toernooi->judokas()->delete();

        // Reset blokken weging status
        $toernooi->blokken()->update([
            'weging_gesloten' => false,
            'weging_gesloten_op' => null,
        ]);

        return redirect()
            ->route('organisator.dashboard', $organisator)
            ->with('success', "Toernooi '{$toernooi->naam}' gereset: {$judokaCount} judoka's, {$pouleCount} poules, {$wedstrijdCount} wedstrijden verwijderd.");
    }

    public function dashboard(): View
    {
        $toernooi = $this->toernooiService->getActiefToernooi();

        if (!$toernooi) {
            return view('pages.toernooi.geen-actief');
        }

        $statistieken = $this->toernooiService->getStatistieken($toernooi);

        return view('pages.toernooi.dashboard', compact('toernooi', 'statistieken'));
    }

    /**
     * Redirect to new URL structure for organisator dashboard
     */
    public function redirectToOrganisatorDashboard(): RedirectResponse
    {
        $organisator = auth('organisator')->user();
        return redirect()->route('organisator.dashboard', ['organisator' => $organisator->slug]);
    }

    /**
     * Dashboard for authenticated organisators (new URL: /{organisator-slug}/dashboard)
     */
    public function organisatorDashboard(\App\Models\Organisator $organisator): View
    {
        $loggedIn = auth('organisator')->user();

        // Verify access: either viewing own dashboard or is sitebeheerder
        if ($loggedIn->id !== $organisator->id && !$loggedIn->isSitebeheerder()) {
            abort(403, 'Je hebt geen toegang tot dit dashboard.');
        }

        // Fresh load to ensure we have latest toernooien (not cached from login)
        $organisator = $organisator->fresh();

        if ($loggedIn->isSitebeheerder() && $loggedIn->id === $organisator->id) {
            // Sitebeheerder viewing own dashboard sees all toernooien
            $toernooien = Toernooi::orderBy('datum', 'desc')->get();
        } else {
            // Regular organisator or sitebeheerder viewing another organisator
            $toernooien = $organisator->toernooien()->orderBy('datum', 'desc')->get();
        }

        return view('organisator.dashboard', compact('organisator', 'toernooien'));
    }

    public function updateWachtwoorden(Organisator $organisator, Request $request, Toernooi $toernooi): RedirectResponse
    {
        $rollen = ['admin', 'jury', 'weging', 'mat', 'spreker'];
        $updated = [];

        foreach ($rollen as $rol) {
            $wachtwoord = $request->input("wachtwoord_{$rol}");
            if ($wachtwoord && strlen($wachtwoord) > 0) {
                $toernooi->setWachtwoord($rol, $wachtwoord);
                $updated[] = ucfirst($rol);
            }
        }

        if (empty($updated)) {
            return redirect()
                ->route('toernooi.edit', $toernooi->routeParams())
                ->with('info', 'Geen wachtwoorden gewijzigd');
        }

        return redirect()
            ->route('toernooi.edit', $toernooi->routeParams())
            ->with('success', 'Wachtwoorden bijgewerkt voor: ' . implode(', ', $updated));
    }

    public function updateBloktijden(Organisator $organisator, Request $request, Toernooi $toernooi): RedirectResponse
    {
        $bloktijden = $request->input('blokken', []);

        foreach ($bloktijden as $blokId => $tijden) {
            $blok = $toernooi->blokken()->find($blokId);
            if ($blok) {
                $blok->update([
                    'weging_start' => $tijden['weging_start'] ?: null,
                    'weging_einde' => $tijden['weging_einde'] ?: null,
                    'starttijd' => $tijden['starttijd'] ?: null,
                ]);
            }
        }

        return redirect()
            ->route('toernooi.edit', $toernooi->routeParamsWith(['tab' => 'organisatie']))
            ->with('success', 'Bloktijden bijgewerkt');
    }

    public function updateBetalingInstellingen(Organisator $organisator, Request $request, Toernooi $toernooi): RedirectResponse
    {
        $validated = $request->validate([
            'betaling_actief' => 'boolean',
            'inschrijfgeld' => 'nullable|numeric|min:0|max:999.99',
        ]);

        $toernooi->update([
            'betaling_actief' => $validated['betaling_actief'] ?? false,
            'inschrijfgeld' => $validated['inschrijfgeld'] ?? null,
        ]);

        return redirect()
            ->route('toernooi.edit', $toernooi->routeParamsWith(['tab' => 'organisatie']))
            ->with('success', 'Betalingsinstellingen bijgewerkt');
    }

    public function updatePortaalInstellingen(Organisator $organisator, Request $request, Toernooi $toernooi): RedirectResponse
    {
        $validated = $request->validate([
            'portaal_modus' => 'required|in:uit,mutaties,volledig',
        ]);

        $toernooi->update([
            'portaal_modus' => $validated['portaal_modus'],
        ]);

        return redirect()
            ->route('toernooi.edit', $toernooi->routeParamsWith(['tab' => 'organisatie']))
            ->with('success', 'Portaalinstellingen bijgewerkt');
    }

    /**
     * Update local server and network settings
     */
    public function updateLocalServerIps(Organisator $organisator, Request $request, Toernooi $toernooi): RedirectResponse
    {
        $validated = $request->validate([
            'local_server_primary_ip' => 'nullable|ip',
            'local_server_standby_ip' => 'nullable|ip',
            'heeft_eigen_router' => 'boolean',
            'eigen_router_ssid' => 'nullable|string|max:100',
            'eigen_router_wachtwoord' => 'nullable|string|max:100',
            'hotspot_ssid' => 'nullable|string|max:100',
            'hotspot_wachtwoord' => 'nullable|string|max:100',
        ]);

        // Ensure boolean is set correctly
        $validated['heeft_eigen_router'] = $request->boolean('heeft_eigen_router');

        $toernooi->update($validated);

        return redirect()
            ->route('toernooi.noodplan.index', $toernooi->routeParams())
            ->with('success', 'Netwerkinstellingen opgeslagen');
    }

    /**
     * Emergency: Reopen preparation phase (reset weegkaarten_gemaakt_op)
     */
    public function heropenVoorbereiding(Organisator $organisator, Request $request, Toernooi $toernooi): RedirectResponse
    {
        $request->validate([
            'wachtwoord' => 'required|string',
        ]);

        // Verify password against logged-in organisator's password
        $loggedIn = auth('organisator')->user();
        if (!$loggedIn || !Hash::check($request->wachtwoord, $loggedIn->password)) {
            return redirect()
                ->route('toernooi.edit', $toernooi->routeParamsWith(['tab' => 'organisatie']))
                ->with('error', 'Onjuist wachtwoord. Voorbereiding niet heropend.');
        }

        // Reset weegkaarten_gemaakt_op
        $toernooi->update(['weegkaarten_gemaakt_op' => null]);

        return redirect()
            ->route('toernooi.edit', $toernooi->routeParamsWith(['tab' => 'organisatie']))
            ->with('success', '⚠️ Voorbereiding heropend! Vergeet niet om "Maak weegkaarten" opnieuw te klikken na wijzigingen.');
    }

    /**
     * Show tournament closing page with statistics
     */
    public function afsluiten(Organisator $organisator, Toernooi $toernooi): View
    {
        $statistieken = $this->getAfsluitStatistieken($toernooi);
        $clubRanking = $this->getClubRanking($toernooi);

        return view('pages.toernooi.afsluiten', compact('toernooi', 'statistieken', 'clubRanking'));
    }

    /**
     * Confirm closing of tournament
     */
    public function bevestigAfsluiten(Organisator $organisator, Request $request, Toernooi $toernooi): RedirectResponse
    {
        // Check permissions: only organisator of this tournament or sitebeheerder
        $loggedIn = auth('organisator')->user();
        if (!$loggedIn || (!$loggedIn->isSitebeheerder() && !$loggedIn->toernooien->contains($toernooi))) {
            return redirect()
                ->route('toernooi.afsluiten', $toernooi->routeParams())
                ->with('error', 'Je hebt geen rechten om dit toernooi af te sluiten');
        }

        if ($toernooi->isAfgesloten()) {
            return redirect()
                ->route('toernooi.afsluiten', $toernooi->routeParams())
                ->with('error', 'Dit toernooi is al afgesloten');
        }

        // Calculate reminder date (3 months before next year's tournament)
        $volgendJaar = $toernooi->datum->addYear();
        $herinneringDatum = $volgendJaar->subMonths(3);

        $toernooi->update([
            'afgesloten_at' => now(),
            'herinnering_datum' => $herinneringDatum,
            'herinnering_verstuurd' => false,
        ]);

        // Reset all device bindings for vrijwilligers
        $toernooi->deviceToegangen()->update([
            'device_token' => null,
            'device_info' => null,
            'gebonden_op' => null,
        ]);

        // Reset all coach kaart device bindings
        \App\Models\CoachKaart::where('toernooi_id', $toernooi->id)
            ->update([
                'device_token' => null,
                'device_info' => null,
                'gebonden_op' => null,
            ]);

        return redirect()
            ->route('toernooi.afsluiten', $toernooi->routeParams())
            ->with('success', 'Toernooi succesvol afgesloten! Alle device bindings zijn gereset.');
    }

    /**
     * Reopen a closed tournament
     */
    public function heropenen(Organisator $organisator, Toernooi $toernooi): RedirectResponse
    {
        // Check permissions: only organisator of this tournament or sitebeheerder
        $loggedIn = auth('organisator')->user();
        if (!$loggedIn || (!$loggedIn->isSitebeheerder() && !$loggedIn->toernooien->contains($toernooi))) {
            return redirect()
                ->route('toernooi.afsluiten', $toernooi->routeParams())
                ->with('error', 'Je hebt geen rechten om dit toernooi te heropenen');
        }

        $toernooi->update([
            'afgesloten_at' => null,
            'herinnering_datum' => null,
            'herinnering_verstuurd' => false,
        ]);

        return redirect()
            ->route('toernooi.show', $toernooi->routeParams())
            ->with('success', 'Toernooi heropend. Je kunt nu weer wijzigingen aanbrengen.');
    }

    /**
     * Get comprehensive statistics for tournament closing
     */
    private function getAfsluitStatistieken(Toernooi $toernooi): array
    {
        $judokas = $toernooi->judokas;
        $poules = $toernooi->poules()->with('wedstrijden')->get();

        // Basic counts
        $totaalJudokas = $judokas->count();
        $totaalClubs = $judokas->whereNotNull('club_id')->pluck('club_id')->unique()->count();
        $totaalPoules = $poules->where('type', '!=', 'eliminatie')->count();
        $totaalEliminaties = $poules->where('type', 'eliminatie')->count();
        $totaalWedstrijden = $poules->sum(fn($p) => $p->wedstrijden->count());
        $gespeeldeWedstrijden = $poules->sum(fn($p) => $p->wedstrijden->whereNotNull('winnaar_id')->count());

        // Leeftijdsklassen breakdown - sort by sort_categorie (young to old)
        $perLeeftijdsklasse = $judokas
            ->sortBy('sort_categorie')
            ->groupBy('leeftijdsklasse')
            ->map(fn($g) => $g->count());

        // Gender breakdown
        $jongens = $judokas->where('geslacht', 'M')->count();
        $meisjes = $judokas->where('geslacht', 'V')->count();

        // Weight statistics
        $gewogen = $judokas->whereNotNull('gewicht_gewogen')->count();

        // Medals (assuming 1st, 2nd, 3rd per poule)
        $aantalMedailles = ($totaalPoules + $totaalEliminaties) * 3;

        return [
            'totaal_judokas' => $totaalJudokas,
            'totaal_clubs' => $totaalClubs,
            'totaal_poules' => $totaalPoules,
            'totaal_eliminaties' => $totaalEliminaties,
            'totaal_wedstrijden' => $totaalWedstrijden,
            'gespeelde_wedstrijden' => $gespeeldeWedstrijden,
            'voltooiings_percentage' => $totaalWedstrijden > 0 ? round(($gespeeldeWedstrijden / $totaalWedstrijden) * 100) : 0,
            'per_leeftijdsklasse' => $perLeeftijdsklasse,
            'jongens' => $jongens,
            'meisjes' => $meisjes,
            'gewogen' => $gewogen,
            'niet_gewogen' => $totaalJudokas - $gewogen,
            'aantal_medailles' => $aantalMedailles,
            'aantal_blokken' => $toernooi->blokken->count(),
            'aantal_matten' => $toernooi->matten->count(),
        ];
    }

    /**
     * Calculate club ranking (copied from PubliekController for independence)
     */
    private function getClubRanking(Toernooi $toernooi): array
    {
        $publiekController = app(PubliekController::class);
        return $publiekController->getClubRanking($toernooi);
    }

    /**
     * Check for overlapping categories in tournament config.
     * Returns warning message if overlap found, null otherwise.
     */
    private function checkCategorieOverlap(Toernooi $toernooi): ?string
    {
        $config = $toernooi->gewichtsklassen ?? [];
        if (empty($config)) {
            return null;
        }

        $classifier = new CategorieClassifier($config);
        $overlaps = $classifier->detectOverlap();

        if (empty($overlaps)) {
            return null;
        }

        // Build warning message
        $warnings = [];
        foreach ($overlaps as $overlap) {
            $warnings[] = "⚠️ \"{$overlap['cat1']}\" en \"{$overlap['cat2']}\" overlappen: {$overlap['reden']}";
        }

        return 'Categorie overlap gedetecteerd! Judoka\'s kunnen in meerdere categorieën passen: ' . implode(' | ', $warnings);
    }
}
