<?php

namespace App\Http\Controllers;

use App\Models\Blok;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Services\BlokMatVerdelingService;
use App\Services\EliminatieService;
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
        private ToernooiService $toernooiService,
        private EliminatieService $eliminatieService
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
            // Clear old variants first
            session()->forget(['blok_varianten', 'blok_stats']);

            // Get balans slider value (0-100)
            // 0 = 100% verdeling, 0% aansluiting
            // 100 = 0% verdeling, 100% aansluiting
            $balans = (int) $request->input('balans', 50);

            // Store in session for persistence
            session(['blok_balans' => $balans]);

            // Reset non-pinned categories so they can be redistributed
            $toernooi->poules()->where('blok_vast', false)->update(['blok_id' => null]);

            // Calculate weights from balans
            $verdelingGewicht = 100 - $balans;  // 0 at right, 100 at left
            $aansluitingGewicht = $balans;       // 0 at left, 100 at right

            $result = $this->verdelingService->genereerVarianten($toernooi, $verdelingGewicht, $aansluitingGewicht);

            if (empty($result['varianten'])) {
                // Check if there's an error (e.g., 25% limit exceeded)
                if (isset($result['error'])) {
                    return redirect()
                        ->route('toernooi.blok.index', $toernooi)
                        ->with('error', $result['error']);
                }
                return redirect()
                    ->route('toernooi.blok.index', $toernooi)
                    ->with('info', $result['message'] ?? 'Geen varianten gegenereerd');
            }

            // Store variants and stats in session for selection
            session(['blok_varianten' => $result['varianten']]);
            session(['blok_stats' => $result['stats'] ?? []]);

            // Auto-apply variant #1 direct na berekening
            if (!empty($result['varianten'][0]['toewijzingen'])) {
                $this->verdelingService->pasVariantToe($toernooi, $result['varianten'][0]['toewijzingen']);
            }

            return redirect()
                ->route('toernooi.blok.index', ['toernooi' => $toernooi, 'kies' => 1]);

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
        // Accept either direct toewijzingen (from DOM) or variant index (legacy)
        $toewijzingen = $request->input('toewijzingen');

        if (!$toewijzingen) {
            // Legacy: get from session by variant index
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

            $toewijzingen = $varianten[$variantIndex]['toewijzingen'];
        }

        try {
            $this->verdelingService->pasVariantToe($toernooi, $toewijzingen);

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
     * Distribute poules over mats and redirect to zaaloverzicht (voorbereiding)
     * Organizer can still adjust mat assignments before sealing with "Maak weegkaarten"
     */
    public function zetOpMat(Toernooi $toernooi): RedirectResponse
    {
        // Remove all existing wedstrijden (categories should be INACTIVE in voorbereiding)
        // After overpoulen, judokas may have changed, so old wedstrijden are invalid
        \App\Models\Wedstrijd::whereHas('poule', fn($q) => $q->where('toernooi_id', $toernooi->id))->delete();

        // Reset alle poule statussen - we starten opnieuw!
        $toernooi->poules()->update([
            'doorgestuurd_op' => null,
            'spreker_klaar' => null,
            'afgeroepen_at' => null,
        ]);

        // Update aantal_judokas en aantal_wedstrijden voor alle poules
        // Dit voorkomt dat poules weggefilterd worden door verouderde tellingen
        foreach ($toernooi->poules as $poule) {
            $poule->updateStatistieken();
        }

        // Automatische verdeling over matten (organisator kan nog aanpassen)
        $this->verdelingService->verdeelOverMatten($toernooi);

        return redirect()
            ->route('toernooi.blok.zaaloverzicht', $toernooi)
            ->with('success', 'Poules verdeeld over matten. Controleer en pas aan indien nodig, klik dan "Maak weegkaarten".');
    }

    public function sluitWeging(Toernooi $toernooi, Blok $blok): RedirectResponse
    {
        $this->toernooiService->sluitWegingBlok($blok);

        // Redirect back to weging interface
        return redirect()
            ->route('toernooi.weging.interface', $toernooi)
            ->with('success', "Weging voor {$blok->naam} gesloten. Niet-gewogen judoka's zijn als afwezig gemarkeerd.");
    }

    public function zaaloverzicht(Toernooi $toernooi): View
    {
        $overzicht = $this->verdelingService->getZaalOverzicht($toernooi);

        // Get category statuses for wedstrijddag (includes doorgestuurd_op from database)
        $categories = $this->getCategoryStatuses($toernooi);

        return view('pages.blok.zaaloverzicht', compact('toernooi', 'overzicht', 'categories'));
    }

    /**
     * Seal preparation: mark weegkaarten as created
     * After this, preparation is "sealed" and weegkaarten show mat info
     */
    public function maakWeegkaarten(Toernooi $toernooi): RedirectResponse
    {
        // Check if all poules have mat_id assigned
        $poulesZonderMat = $toernooi->poules()->whereNull('mat_id')->count();
        if ($poulesZonderMat > 0) {
            return redirect()
                ->route('toernooi.blok.zaaloverzicht', $toernooi)
                ->with('error', "Nog {$poulesZonderMat} poules zonder mat. Wijs eerst alle poules aan een mat toe.");
        }

        // Seal preparation
        $toernooi->update(['weegkaarten_gemaakt_op' => now()]);

        // No flash message needed - indicator next to title is sufficient
        return redirect()->route('toernooi.blok.zaaloverzicht', $toernooi);
    }

    /**
     * Activate a category: generate match schedules (mats already assigned in voorbereiding)
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

        // Generate match schedules for each poule (mats already assigned)
        $totaalWedstrijden = 0;
        $isEliminatie = false;

        foreach ($poules as $poule) {
            // Only generate if no wedstrijden exist yet
            if ($poule->wedstrijden()->count() === 0) {
                if ($poule->type === 'eliminatie') {
                    // Generate elimination bracket (alleen aanwezige judoka's!)
                    $isEliminatie = true;
                    $judokaIds = $poule->judokas()
                        ->where(function ($q) {
                            $q->whereNull('aanwezigheid')
                              ->orWhere('aanwezigheid', '!=', 'afwezig');
                        })
                        ->pluck('judokas.id')
                        ->toArray();
                    $eliminatieType = $toernooi->eliminatie_type ?? 'dubbel';
                    $stats = $this->eliminatieService->genereerBracket($poule, $judokaIds, $eliminatieType);
                    $totaalWedstrijden += $stats['totaal_wedstrijden'] ?? 0;
                } else {
                    // Generate round-robin matches
                    $wedstrijden = $this->wedstrijdService->genereerWedstrijdenVoorPoule($poule);
                    $totaalWedstrijden += count($wedstrijden);
                }
            }
        }

        // Stay on zaaloverzicht (chip turns green to indicate activation)
        $typeLabel = $isEliminatie ? 'Eliminatie bracket' : 'Poules';
        return redirect()
            ->route('toernooi.blok.zaaloverzicht', $toernooi)
            ->with('success', "✓ {$leeftijdsklasse} {$gewichtsklasse} geactiveerd - {$typeLabel}" .
                ($totaalWedstrijden > 0 ? " ({$totaalWedstrijden} wedstrijden)" : ""));
    }

    /**
     * Reset een categorie: verwijder wedstrijden en haal van mat
     * Categorie wordt weer inactief en kan opnieuw geactiveerd worden
     */
    public function resetCategorie(Request $request, Toernooi $toernooi): RedirectResponse
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

        $totaalVerwijderd = 0;

        foreach ($poules as $poule) {
            // Verwijder alle wedstrijden
            $verwijderd = $poule->wedstrijden()->delete();
            $totaalVerwijderd += $verwijderd;

            // Reset poule status
            $poule->update([
                'mat_id' => null,
                'doorgestuurd_op' => now(),
                'spreker_klaar' => null,
                'afgeroepen_at' => null,
                'aantal_wedstrijden' => 0,
            ]);
        }

        return redirect()
            ->route('toernooi.blok.zaaloverzicht', $toernooi)
            ->with('success', "✓ {$leeftijdsklasse} {$gewichtsklasse} gereset - {$totaalVerwijderd} wedstrijden verwijderd, klaar voor nieuwe ronde");
    }

    /**
     * NUCLEAR OPTION: Reset ALLES - alle wedstrijden, alle matten, alle blokken
     */
    public function resetAlles(Toernooi $toernooi): RedirectResponse
    {
        $poules = $toernooi->poules()->get();
        $totaalVerwijderd = 0;

        foreach ($poules as $poule) {
            // Verwijder alle wedstrijden
            $verwijderd = $poule->wedstrijden()->delete();
            $totaalVerwijderd += $verwijderd;

            // Reset poule status
            $poule->update([
                'mat_id' => null,
                'doorgestuurd_op' => null,
                'spreker_klaar' => null,
                'afgeroepen_at' => null,
                'aantal_wedstrijden' => 0,
            ]);
        }

        return redirect()
            ->route('toernooi.edit', $toernooi)
            ->with('success', "💥 ALLES GERESET - {$totaalVerwijderd} wedstrijden verwijderd, alle matten leeg, klaar voor nieuwe ronde!");
    }

    /**
     * Get category statuses for wedstrijddag overview
     * Returns: wachtruimte_count, is_activated (has wedstrijden), is_sent (doorgestuurd_op set)
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

        // Group poules by category and check status
        $wedstrijdenPerCategorie = [];
        $doorgestuurdPerCategorie = [];
        foreach ($poules as $poule) {
            $key = $poule->leeftijdsklasse . '|' . $poule->gewichtsklasse;
            if (!isset($wedstrijdenPerCategorie[$key])) {
                $wedstrijdenPerCategorie[$key] = 0;
            }
            $wedstrijdenPerCategorie[$key] += $poule->wedstrijden_count;

            // If any poule in category has doorgestuurd_op set, category is sent
            if ($poule->doorgestuurd_op) {
                $doorgestuurdPerCategorie[$key] = true;
            }
        }

        foreach ($poules->unique(fn($p) => $p->leeftijdsklasse . '|' . $p->gewichtsklasse) as $poule) {
            $key = $poule->leeftijdsklasse . '|' . $poule->gewichtsklasse;
            $categories[$key] = [
                'leeftijdsklasse' => $poule->leeftijdsklasse,
                'gewichtsklasse' => $poule->gewichtsklasse,
                'wachtruimte_count' => $wachtruimtePerCategorie[$key] ?? 0,
                'is_activated' => ($wedstrijdenPerCategorie[$key] ?? 0) > 0,
                'is_sent' => $doorgestuurdPerCategorie[$key] ?? false,
            ];
        }

        return $categories;
    }

    public function sprekerInterface(Toernooi $toernooi): View
    {
        $overzicht = $this->verdelingService->getZaalOverzicht($toernooi);

        // Get poules that are ready for spreker (with results) but not yet announced
        $klarePoules = $toernooi->poules()
            ->whereNotNull('spreker_klaar')
            ->whereNull('afgeroepen_at')
            ->with(['mat', 'blok', 'judokas.club', 'wedstrijden'])
            ->orderBy('spreker_klaar', 'asc')  // Oldest first (longest waiting at top)
            ->get()
            ->map(function ($poule) {
                // ELIMINATIE: Haal medaille winnaars direct uit bracket
                if ($poule->type === 'eliminatie') {
                    $poule->standings = $this->getEliminatieStandings($poule);
                    $poule->is_eliminatie = true;
                    return $poule;
                }

                // POULE: Calculate WP and JP from wedstrijden for each judoka
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

                $poule->is_eliminatie = false;
                return $poule;
            });

        // Recent afgeroepen poules (laatste 30 minuten) - voor "Terug" functie
        $afgeroepen = $toernooi->poules()
            ->whereNotNull('afgeroepen_at')
            ->where('afgeroepen_at', '>=', now()->subMinutes(30))
            ->with(['mat', 'blok', 'judokas.club', 'wedstrijden'])
            ->orderBy('afgeroepen_at', 'desc')
            ->get()
            ->map(function ($poule) {
                if ($poule->type === 'eliminatie') {
                    $poule->standings = $this->getEliminatieStandings($poule);
                    $poule->is_eliminatie = true;
                }
                return $poule;
            });

        // Admin versie met layouts.app menu (zie docs: INTERFACES.md)
        return view('pages.spreker.interface-admin', compact('toernooi', 'klarePoules', 'afgeroepen'));
    }

    /**
     * Get standings for elimination bracket (medal winners only)
     * Returns: 1=Goud (finale winnaar), 2=Zilver (finale verliezer), 3=Brons (1 of 2)
     */
    private function getEliminatieStandings($poule): \Illuminate\Support\Collection
    {
        $standings = collect();

        // 1. GOUD = Finale winnaar (A-groep)
        $finale = $poule->wedstrijden->first(fn($w) => $w->groep === 'A' && $w->ronde === 'finale');
        if ($finale && $finale->is_gespeeld && $finale->winnaar_id) {
            $goud = $finale->winnaar_id === $finale->judoka_wit_id
                ? $poule->judokas->firstWhere('id', $finale->judoka_wit_id)
                : $poule->judokas->firstWhere('id', $finale->judoka_blauw_id);
            if ($goud) {
                $standings->push(['judoka' => $goud, 'wp' => null, 'jp' => null, 'plaats' => 1]);
            }

            // 2. ZILVER = Finale verliezer
            $zilver = $finale->winnaar_id === $finale->judoka_wit_id
                ? $poule->judokas->firstWhere('id', $finale->judoka_blauw_id)
                : $poule->judokas->firstWhere('id', $finale->judoka_wit_id);
            if ($zilver) {
                $standings->push(['judoka' => $zilver, 'wp' => null, 'jp' => null, 'plaats' => 2]);
            }
        }

        // 3. BRONS = Winnaars van b_halve_finale_2 of b_brons of b_finale
        $bronsWedstrijden = $poule->wedstrijden->filter(fn($w) =>
            in_array($w->ronde, ['b_halve_finale_2', 'b_brons', 'b_finale']) && $w->is_gespeeld && $w->winnaar_id
        );

        foreach ($bronsWedstrijden as $bronsWed) {
            $brons = $bronsWed->winnaar_id === $bronsWed->judoka_wit_id
                ? $poule->judokas->firstWhere('id', $bronsWed->judoka_wit_id)
                : $poule->judokas->firstWhere('id', $bronsWed->judoka_blauw_id);
            if ($brons && !$standings->contains(fn($s) => $s['judoka']?->id === $brons->id)) {
                $standings->push(['judoka' => $brons, 'wp' => null, 'jp' => null, 'plaats' => 3]);
            }
        }

        return $standings;
    }

    /**
     * Mark poule as announced (prizes awarded) - moves to archive
     */
    public function markeerAfgeroepen(Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'poule_id' => 'required|exists:poules,id',
        ]);

        $poule = Poule::findOrFail($validated['poule_id']);
        $poule->update(['afgeroepen_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => "Poule {$poule->nummer} afgeroepen",
        ]);
    }

    /**
     * Zet afgeroepen poule terug naar klaar (undo)
     */
    public function zetAfgeroepenTerug(Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'poule_id' => 'required|exists:poules,id',
        ]);

        $poule = Poule::findOrFail($validated['poule_id']);
        $poule->update(['afgeroepen_at' => null]);

        return response()->json([
            'success' => true,
            'message' => "Poule {$poule->nummer} teruggezet",
        ]);
    }

    public function verplaatsPoule(Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'poule_id' => 'required|exists:poules,id',
            'mat_id' => 'required|exists:matten,id',
        ]);

        $poule = Poule::findOrFail($validated['poule_id']);

        // Reset spreker status als poule opnieuw op mat wordt gezet
        // (bv. volgende dag of na correctie)
        $poule->update([
            'mat_id' => $validated['mat_id'],
            'spreker_klaar' => null,
            'afgeroepen_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Poule {$poule->nummer} verplaatst",
        ]);
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
