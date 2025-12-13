<?php

namespace App\Http\Controllers;

use App\Models\Blok;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Services\BlokMatVerdelingService;
use App\Services\ToernooiService;
use App\Services\WedstrijdSchemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlokController extends Controller
{
    public function __construct(
        private BlokMatVerdelingService $verdelingService,
        private WedstrijdSchemaService $wedstrijdService,
        private ToernooiService $toernooiService
    ) {}

    public function index(Toernooi $toernooi): View
    {
        $blokken = $toernooi->blokken()->with('poules')->orderBy('nummer')->get();
        $toernooi->load('matten');
        $statistieken = $this->verdelingService->getVerdelingsStatistieken($toernooi);

        return view('pages.blok.index', compact('toernooi', 'blokken', 'statistieken'));
    }

    public function show(Toernooi $toernooi, Blok $blok): View
    {
        $blok->load(['poules.mat', 'poules.judokas']);

        return view('pages.blok.show', compact('toernooi', 'blok'));
    }

    /**
     * Generate block distribution variants and show selection UI
     */
    public function genereerVerdeling(Request $request, Toernooi $toernooi): RedirectResponse
    {
        try {
            // Get slider values from request (0-100 scale)
            $verdelingGewicht = (int) $request->input('verdeling_gewicht', 50);
            $aansluitingGewicht = (int) $request->input('aansluiting_gewicht', 50);

            // Store in session for persistence
            session(['blok_verdeling_gewicht' => $verdelingGewicht]);
            session(['blok_aansluiting_gewicht' => $aansluitingGewicht]);

            $result = $this->verdelingService->genereerVarianten($toernooi, $verdelingGewicht, $aansluitingGewicht);

            if (empty($result['varianten'])) {
                return redirect()
                    ->route('toernooi.blok.index', $toernooi)
                    ->with('info', $result['message'] ?? 'Geen varianten gegenereerd');
            }

            // Store variants in session for selection
            session(['blok_varianten' => $result['varianten']]);

            // Build info message with best variant stats
            $beste = $result['varianten'][0];
            $maxAfwijking = $beste['scores']['max_afwijking'] ?? 0;
            $breaks = $beste['scores']['breaks'] ?? 0;
            $msg = count($result['varianten']) . " varianten berekend (beste: max afwijking {$maxAfwijking} wed., {$breaks} breaks)";

            return redirect()
                ->route('toernooi.blok.index', ['toernooi' => $toernooi, 'kies' => 1])
                ->with('success', $msg);

        } catch (\Exception $e) {
            \Log::error('genereerVerdeling failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()
                ->route('toernooi.blok.index', $toernooi)
                ->with('error', 'Verdeling mislukt: ' . $e->getMessage());
        }
    }

    /**
     * Apply chosen variant (supports both form POST and JSON)
     */
    public function kiesVariant(Request $request, Toernooi $toernooi): RedirectResponse|JsonResponse
    {
        $variantIndex = (int) $request->input('variant', 0);
        $varianten = session('blok_varianten', []);

        if (!isset($varianten[$variantIndex])) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => 'Variant niet gevonden'], 404);
            }
            return redirect()
                ->route('toernooi.blok.index', $toernooi)
                ->with('error', 'Variant niet gevonden');
        }

        $variant = $varianten[$variantIndex];

        try {
            $this->verdelingService->pasVariantToe($toernooi, $variant['toewijzingen']);

            // Clear session
            session()->forget('blok_varianten');

            if ($request->wantsJson()) {
                return response()->json(['success' => true]);
            }

            return redirect()
                ->route('toernooi.blok.index', $toernooi)
                ->with('success', 'Variant ' . ($variantIndex + 1) . ' toegepast');

        } catch (\Exception $e) {
            \Log::error('kiesVariant failed', ['error' => $e->getMessage()]);
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
            }
            return redirect()
                ->route('toernooi.blok.index', $toernooi)
                ->with('error', 'Variant toepassen mislukt: ' . $e->getMessage());
        }
    }

    /**
     * Update gewenst wedstrijden for a block via AJAX
     */
    public function updateGewenst(Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'blok_id' => 'required|exists:blokken,id',
            'gewenst' => 'nullable|integer|min:0',
        ]);

        $blok = Blok::findOrFail($validated['blok_id']);

        // Ensure blok belongs to this toernooi
        if ($blok->toernooi_id !== $toernooi->id) {
            return response()->json(['success' => false, 'error' => 'Blok hoort niet bij dit toernooi'], 403);
        }

        // Empty or 0 = null (auto-calculate)
        $gewenst = !empty($validated['gewenst']) ? (int)$validated['gewenst'] : null;
        $blok->update(['gewenst_wedstrijden' => $gewenst]);

        return response()->json(['success' => true, 'gewenst' => $gewenst]);
    }

    /**
     * Distribute categories over mats and redirect to zaaloverzicht
     */
    public function zetOpMat(Toernooi $toernooi): RedirectResponse
    {
        $this->verdelingService->verdeelOverMatten($toernooi);

        return redirect()
            ->route('toernooi.blok.zaaloverzicht', $toernooi)
            ->with('success', 'Categorieën verdeeld over matten');
    }

    public function sluitWeging(Toernooi $toernooi, Blok $blok): RedirectResponse
    {
        $this->toernooiService->sluitWegingBlok($blok);

        return redirect()
            ->route('toernooi.blok.show', [$toernooi, $blok])
            ->with('success', "Weging voor {$blok->naam} gesloten");
    }

    public function genereerWedstrijdschemas(Toernooi $toernooi, Blok $blok): RedirectResponse
    {
        $gegenereerd = $this->wedstrijdService->genereerWedstrijdSchemas($blok);

        $totaal = array_sum($gegenereerd);

        return redirect()
            ->route('toernooi.blok.show', [$toernooi, $blok])
            ->with('success', "{$totaal} wedstrijden gegenereerd voor {$blok->naam}");
    }

    public function zaaloverzicht(Toernooi $toernooi): View
    {
        $overzicht = $this->verdelingService->getZaalOverzicht($toernooi);

        // Get category statuses for wedstrijddag
        $categories = $this->getCategoryStatuses($toernooi);
        $sentToZaaloverzicht = session("toernooi_{$toernooi->id}_wedstrijddag_sent", []);

        return view('pages.blok.zaaloverzicht', compact('toernooi', 'overzicht', 'categories', 'sentToZaaloverzicht'));
    }

    /**
     * Activate a category: generate match schedules and go to mat interface
     */
    public function activeerCategorie(Request $request, Toernooi $toernooi): RedirectResponse
    {
        $validated = $request->validate([
            'category' => 'required|string',
            'blok' => 'required|integer',
        ]);

        [$leeftijdsklasse, $gewichtsklasse] = explode('|', $validated['category']);
        $blokNummer = $validated['blok'];

        // Find all poules for this category in this blok
        $poules = $toernooi->poules()
            ->whereHas('blok', fn($q) => $q->where('nummer', $blokNummer))
            ->where('leeftijdsklasse', $leeftijdsklasse)
            ->where('gewichtsklasse', $gewichtsklasse)
            ->get();

        // Generate match schedules for each poule using the service
        $totaalWedstrijden = 0;
        foreach ($poules as $poule) {
            // Only generate if no wedstrijden exist yet
            if ($poule->wedstrijden()->count() === 0) {
                $wedstrijden = $this->wedstrijdService->genereerWedstrijdenVoorPoule($poule);
                $totaalWedstrijden += count($wedstrijden);
            }
        }

        // Redirect to mat interface
        return redirect()
            ->route('toernooi.mat.interface', $toernooi)
            ->with('success', "Categorie {$leeftijdsklasse} {$gewichtsklasse} geactiveerd" .
                ($totaalWedstrijden > 0 ? " ({$totaalWedstrijden} wedstrijden gegenereerd)" : ""));
    }

    /**
     * Get category statuses for wedstrijddag overview
     * Returns: wachtruimte_count, is_activated (has wedstrijden)
     */
    private function getCategoryStatuses(Toernooi $toernooi): array
    {
        $categories = [];

        // Get all unique categories with wedstrijd count
        $poules = $toernooi->poules()
            ->withCount('wedstrijden')
            ->get();

        // Get judokas that need re-pooling (outside weight class)
        $judokasNaarWachtruimte = \App\Models\Judoka::where('toernooi_id', $toernooi->id)
            ->whereNotNull('gewicht_gewogen')
            ->where('aanwezigheid', 'aanwezig')
            ->get()
            ->filter(fn($j) => !$j->isGewichtBinnenKlasse());

        // Group by target category
        $wachtruimtePerCategorie = [];
        foreach ($judokasNaarWachtruimte as $judoka) {
            $key = $judoka->leeftijdsklasse . '|' . $judoka->gewichtsklasse;
            $wachtruimtePerCategorie[$key] = ($wachtruimtePerCategorie[$key] ?? 0) + 1;
        }

        // Group poules by category and check if any have wedstrijden
        $wedstrijdenPerCategorie = [];
        foreach ($poules as $poule) {
            $key = $poule->leeftijdsklasse . '|' . $poule->gewichtsklasse;
            if (!isset($wedstrijdenPerCategorie[$key])) {
                $wedstrijdenPerCategorie[$key] = 0;
            }
            $wedstrijdenPerCategorie[$key] += $poule->wedstrijden_count;
        }

        foreach ($poules->unique(fn($p) => $p->leeftijdsklasse . '|' . $p->gewichtsklasse) as $poule) {
            $key = $poule->leeftijdsklasse . '|' . $poule->gewichtsklasse;
            $categories[$key] = [
                'leeftijdsklasse' => $poule->leeftijdsklasse,
                'gewichtsklasse' => $poule->gewichtsklasse,
                'wachtruimte_count' => $wachtruimtePerCategorie[$key] ?? 0,
                'is_activated' => ($wedstrijdenPerCategorie[$key] ?? 0) > 0,
            ];
        }

        return $categories;
    }

    public function sprekerInterface(Toernooi $toernooi): View
    {
        $overzicht = $this->verdelingService->getZaalOverzicht($toernooi);

        // Get poules that are ready for spreker (with results)
        $klarePoules = $toernooi->poules()
            ->whereNotNull('spreker_klaar')
            ->with(['mat', 'judokas.club', 'wedstrijden'])
            ->orderBy('spreker_klaar', 'asc')
            ->limit(10)
            ->get()
            ->map(function ($poule) {
                // Calculate WP and JP from wedstrijden for each judoka
                $standings = $poule->judokas->map(function ($judoka) use ($poule) {
                    $wp = 0;
                    $jp = 0;

                    foreach ($poule->wedstrijden as $wedstrijd) {
                        if ($wedstrijd->judoka_wit_id === $judoka->id) {
                            $wp += $wedstrijd->winnaar_id === $judoka->id ? 2 : 0;
                            $jp += (int) $wedstrijd->score_wit;
                        } elseif ($wedstrijd->judoka_blauw_id === $judoka->id) {
                            $wp += $wedstrijd->winnaar_id === $judoka->id ? 2 : 0;
                            $jp += (int) $wedstrijd->score_blauw;
                        }
                    }

                    return [
                        'judoka' => $judoka,
                        'wp' => $wp,
                        'jp' => $jp,
                    ];
                });

                // Sort by WP desc, then JP desc, then head-to-head
                $wedstrijden = $poule->wedstrijden;
                $poule->standings = $standings->sort(function ($a, $b) use ($wedstrijden) {
                    // First: compare WP (higher is better)
                    if ($a['wp'] !== $b['wp']) {
                        return $b['wp'] - $a['wp'];
                    }
                    // Second: compare JP (higher is better)
                    if ($a['jp'] !== $b['jp']) {
                        return $b['jp'] - $a['jp'];
                    }
                    // Third: head-to-head winner
                    foreach ($wedstrijden as $w) {
                        $isMatch = ($w->judoka_wit_id === $a['judoka']->id && $w->judoka_blauw_id === $b['judoka']->id)
                                || ($w->judoka_wit_id === $b['judoka']->id && $w->judoka_blauw_id === $a['judoka']->id);
                        if ($isMatch && $w->winnaar_id) {
                            return $w->winnaar_id === $a['judoka']->id ? -1 : 1;
                        }
                    }
                    return 0;
                })->values();

                return $poule;
            });

        return view('pages.spreker.interface', compact('toernooi', 'overzicht', 'klarePoules'));
    }

    public function verplaatsPoule(Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'poule_id' => 'required|exists:poules,id',
            'mat_id' => 'required|exists:matten,id',
        ]);

        $poule = Poule::findOrFail($validated['poule_id']);
        $poule->update(['mat_id' => $validated['mat_id']]);

        return response()->json([
            'success' => true,
            'message' => "Poule {$poule->nummer} verplaatst",
        ]);
    }

    /**
     * Reset blok toewijzingen - ALLES (ook vastgezette)
     */
    public function resetVerdeling(Toernooi $toernooi): JsonResponse
    {
        // Reset ALL categories (including pinned)
        $updated = $toernooi->poules()
            ->update(['blok_id' => null, 'blok_vast' => false]);

        // Clear any variant session
        session()->forget('blok_varianten');

        return response()->json(['success' => true, 'reset' => $updated]);
    }

    /**
     * Verplaats een categorie naar een blok (drag & drop)
     * vast parameter determines if category is pinned
     */
    public function verplaatsCategorie(Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'key' => 'required|string',
            'blok' => 'required|integer|min:0',
            'vast' => 'nullable|boolean',
        ]);

        // Parse key: "leeftijdsklasse|gewichtsklasse"
        $parts = explode('|', $validated['key']);
        if (count($parts) !== 2) {
            return response()->json(['success' => false, 'error' => 'Invalid key'], 400);
        }

        $leeftijdsklasse = $parts[0];
        $gewichtsklasse = $parts[1];
        $blokNummer = $validated['blok'];

        // Blok 0 = niet verdeeld (null), also unpin
        $blokId = null;
        $blokVast = false;

        if ($blokNummer > 0) {
            $blok = $toernooi->blokken()->where('nummer', $blokNummer)->first();
            if ($blok) {
                $blokId = $blok->id;
                // Use vast from request, default false (drag = not pinned)
                $blokVast = $validated['vast'] ?? false;
            }
        }

        // Update alle poules met deze categorie
        $updated = $toernooi->poules()
            ->where('leeftijdsklasse', $leeftijdsklasse)
            ->where('gewichtsklasse', $gewichtsklasse)
            ->update(['blok_id' => $blokId, 'blok_vast' => $blokVast]);

        return response()->json(['success' => true, 'updated' => $updated, 'vast' => $blokVast]);
    }
}
