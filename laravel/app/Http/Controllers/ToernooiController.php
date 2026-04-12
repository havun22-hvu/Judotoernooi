<?php

namespace App\Http\Controllers;

use App\Http\Requests\ToernooiRequest;
use App\Models\Organisator;
use App\Models\Toernooi;
use App\Services\ActivityLogger;
use App\Services\CategorieClassifier;
use App\Services\PouleIndelingService;
use App\Services\ToernooiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ToernooiController extends Controller
{
    public function __construct(
        private ToernooiService $toernooiService,
        private PouleIndelingService $pouleIndelingService
    ) {}

    public function create(Organisator $organisator): View
    {
        $templates = $organisator->toernooiTemplates()->orderBy('naam')->get();

        return view('pages.toernooi.create', compact('organisator', 'templates'));
    }

    public function store(Organisator $organisator, ToernooiRequest $request): RedirectResponse
    {
        $toernooi = $this->toernooiService->initialiseerToernooi($request->validated());

        // Activate wimpel_abo plan if org has subscription and checkbox is checked
        if ($request->boolean('is_wimpel_toernooi') && $organisator->heeftWimpelAbo()) {
            $toernooi->update([
                'plan_type' => 'wimpel_abo',
                'wedstrijd_systeem' => collect($toernooi->getAlleGewichtsklassen())
                    ->mapWithKeys(fn($v, $k) => [$k => 'punten_competitie'])
                    ->all(),
            ]);
        }

        return redirect()
            ->route('toernooi.show', $toernooi->routeParams())
            ->with('success', 'Toernooi succesvol aangemaakt');
    }

    public function show(Request $request, Organisator $organisator, Toernooi $toernooi): View|RedirectResponse
    {
        // Switch to toernooi's saved locale, or fall back to organisator's locale
        $locale = $toernooi->locale ?? $organisator->locale ?? config('app.locale');
        session()->put('locale', $locale);
        app()->setLocale($locale);

        // Auto-redirect smartphones to mobile view (unless user explicitly chose desktop)
        if (!$request->has('desktop') && $this->isSmartphone($request)) {
            return redirect()->route('toernooi.wedstrijddag.mobiel', $toernooi->routeParams());
        }

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

            // Extract wedstrijd_systeem and punten_competitie_wedstrijden from each category
            $wedstrijdSysteem = [];
            $puntenCompWedstrijden = [];
            foreach ($jsonData as $key => $categorie) {
                if (is_array($categorie) && isset($categorie['wedstrijd_systeem'])) {
                    $wedstrijdSysteem[$key] = $categorie['wedstrijd_systeem'];
                    unset($jsonData[$key]['wedstrijd_systeem']);
                }
                if (is_array($categorie) && isset($categorie['punten_competitie_wedstrijden'])) {
                    $puntenCompWedstrijden[$key] = (int) $categorie['punten_competitie_wedstrijden'];
                    unset($jsonData[$key]['punten_competitie_wedstrijden']);
                }
            }
            if (!empty($wedstrijdSysteem)) {
                $data['wedstrijd_systeem'] = $wedstrijdSysteem;
            }
            $data['punten_competitie_wedstrijden'] = !empty($puntenCompWedstrijden) ? $puntenCompWedstrijden : null;

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

        // Validate gewichtsklassen entries: detect missing commas (e.g. "-20 -23" should be "-20, -23")
        if (!empty($data['gewichtsklassen']) && is_array($data['gewichtsklassen'])) {
            foreach ($data['gewichtsklassen'] as $key => $categorie) {
                if (!is_array($categorie)) continue;
                foreach ($categorie['gewichten'] ?? [] as $gewicht) {
                    if (preg_match('/[+-]?\d+(\.\d+)?\s+[+-]?\d/', (string) $gewicht)) {
                        $label = $categorie['label'] ?? $key;
                        $errorMsg = __('Komma vergeten bij :label: ":gewicht". Gebruik komma\'s tussen gewichtsklassen (bijv. -20, -23).', [
                            'label' => $label,
                            'gewicht' => $gewicht,
                        ]);
                        if ($request->ajax() || $request->expectsJson()) {
                            return response()->json(['errors' => ['gewichtsklassen' => [$errorMsg]]], 422);
                        }
                        return back()->withErrors(['gewichtsklassen' => $errorMsg])->withInput();
                    }
                }
            }
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

        // Handle danpunten_actief checkbox
        $data['danpunten_actief'] = (bool) ($data['danpunten_actief'] ?? false);

        // Handle mat_voorkeuren (JSON field with boolean values)
        if (isset($data['mat_voorkeuren'])) {
            $data['mat_voorkeuren'] = array_map(fn($v) => (bool) $v, $data['mat_voorkeuren']);
        }

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

        // Enforce punten_competitie for wimpel_abo tournaments
        if ($toernooi->isWimpelAbo() && !empty($data['wedstrijd_systeem'])) {
            $data['wedstrijd_systeem'] = collect($data['wedstrijd_systeem'])
                ->map(fn() => 'punten_competitie')
                ->all();
        }

        // Check of categorieën zijn gewijzigd
        $oudeGewichtsklassen = $toernooi->gewichtsklassen ?? [];
        $nieuweGewichtsklassen = $data['gewichtsklassen'] ?? [];
        $categorieenGewijzigd = json_encode($oudeGewichtsklassen) !== json_encode($nieuweGewichtsklassen);

        // Bepaal gewijzigde velden voor logging
        $gewijzigdeVelden = array_keys(array_diff_assoc(
            array_map('json_encode', array_intersect_key($data, $toernooi->getAttributes())),
            array_map('json_encode', array_intersect_key($toernooi->getAttributes(), $data))
        ));

        $toernooi->update($data);

        ActivityLogger::log($toernooi, 'update_toernooi', "Toernooi-instellingen bijgewerkt" . (!empty($gewijzigdeVelden) ? ": " . implode(', ', array_slice($gewijzigdeVelden, 0, 5)) : ''), [
            'model' => $toernooi,
            'properties' => ['gewijzigde_velden' => $gewijzigdeVelden],
            'interface' => 'dashboard',
        ]);

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

    public function toggleArchiveer(Organisator $organisator, Toernooi $toernooi): RedirectResponse
    {
        $loggedIn = auth('organisator')->user();

        if (!$loggedIn || (!$loggedIn->isSitebeheerder() && !$loggedIn->ownsToernooi($toernooi))) {
            return redirect()
                ->route('organisator.dashboard', $organisator)
                ->with('error', 'Je hebt geen rechten om dit toernooi te archiveren');
        }

        $toernooi->update(['is_gearchiveerd' => !$toernooi->is_gearchiveerd]);

        $actie = $toernooi->is_gearchiveerd ? 'gearchiveerd' : 'teruggezet';

        return redirect()
            ->route('organisator.dashboard', $organisator)
            ->with('success', "Toernooi '{$toernooi->naam}' {$actie}");
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

        ActivityLogger::log($toernooi, 'verwijder_toernooi', "Toernooi '{$naam}' verwijderd", [
            'model' => $toernooi,
            'interface' => 'dashboard',
        ]);

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

    /**
     * Detect if request comes from a smartphone (not tablet, not desktop).
     */
    protected function isSmartphone(Request $request): bool
    {
        $ua = $request->userAgent() ?? '';

        // Must contain 'Mobile' — this covers iPhone, Android phones, etc.
        // Tablets (iPad, Android tablets) typically don't have 'Mobile' in UA
        return (bool) preg_match('/Mobile/i', $ua)
            && !preg_match('/iPad|Tablet/i', $ua);
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
