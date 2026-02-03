<?php

namespace App\Http\Controllers;

use App\Models\Organisator;
use App\Models\Blok;
use App\Models\Club;
use App\Models\CoachKaart;
use App\Models\Mat;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Services\BlokMatVerdelingService;
use App\Services\EliminatieService;
use App\Services\VariabeleBlokVerdelingService;
use App\Services\WedstrijdSchemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlokController extends Controller
{
    public function __construct(
        private BlokMatVerdelingService $verdelingService,
        private VariabeleBlokVerdelingService $variabeleService,
        private WedstrijdSchemaService $wedstrijdService,
        private EliminatieService $eliminatieService
    ) {}

    public function index(Organisator $organisator, Toernooi $toernooi): View
    {
        $blokken = $toernooi->blokken()->with('poules')->orderBy('nummer')->get();
        $toernooi->load('matten');
        $statistieken = $this->verdelingService->getVerdelingsStatistieken($toernooi);

        return view('pages.blok.index', compact('toernooi', 'blokken', 'statistieken'));
    }

    public function show(Organisator $organisator, Toernooi $toernooi, Blok $blok): View
    {
        $blok->load(['poules.mat', 'poules.judokas']);

        return view('pages.blok.show', compact('toernooi', 'blok'));
    }

    /**
     * Generate block distribution variants and show selection UI
     */
    public function genereerVerdeling(Organisator $organisator, Request $request, Toernooi $toernooi): RedirectResponse
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
                        ->route('toernooi.blok.index', $toernooi->routeParams())
                        ->with('error', $result['error']);
                }
                return redirect()
                    ->route('toernooi.blok.index', $toernooi->routeParams())
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
                ->route('toernooi.blok.index', array_merge($toernooi->routeParams(), ['kies' => 1]));

        } catch (\Exception $e) {
            \Log::error('genereerVerdeling failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()
                ->route('toernooi.blok.index', $toernooi->routeParams())
                ->with('error', 'Verdeling mislukt: ' . $e->getMessage());
        }
    }

    /**
     * Apply chosen variant (supports both form POST and JSON)
     */
    public function kiesVariant(Organisator $organisator, Request $request, Toernooi $toernooi): RedirectResponse|JsonResponse
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
                    ->route('toernooi.blok.index', $toernooi->routeParams())
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
                ->route('toernooi.blok.index', $toernooi->routeParams())
                ->with('success', 'Variant ' . ($variantIndex + 1) . ' toegepast');

        } catch (\Exception $e) {
            \Log::error('kiesVariant failed', ['error' => $e->getMessage()]);
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
            }
            return redirect()
                ->route('toernooi.blok.index', $toernooi->routeParams())
                ->with('error', 'Variant toepassen mislukt: ' . $e->getMessage());
        }
    }

    /**
     * Generate block distribution for variable categories (max wedstrijden based)
     * Uses simple algorithm: sort by age/weight, distribute evenly with connection
     */
    public function genereerVariabeleVerdeling(Organisator $organisator, Request $request, Toernooi $toernooi): RedirectResponse|JsonResponse
    {
        try {
            // Reset non-pinned poules
            $toernooi->poules()->where('blok_vast', false)->update(['blok_id' => null]);

            // Calculate target wedstrijden per blok
            $totaalWedstrijden = $toernooi->poules()->sum('aantal_wedstrijden');
            $aantalBlokken = $toernooi->blokken()->count();
            $defaultMax = $aantalBlokken > 0 ? (int) ceil($totaalWedstrijden / $aantalBlokken) : 100;

            $maxPerBlok = (int) $request->input('max_per_blok', $defaultMax);

            // Use simple variabele service for ALL tournaments
            $result = $this->variabeleService->verdeelOpMaxWedstrijden($toernooi, $maxPerBlok);

            if (empty($result['toewijzingen'])) {
                $message = $result['message'] ?? 'Geen poules gevonden';
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => $message]);
                }
                return redirect()
                    ->route('toernooi.blok.index', $toernooi->routeParams())
                    ->with('info', $message);
            }

            // Apply distribution
            $this->variabeleService->pasVerdelingMetLabelsToe(
                $toernooi,
                $result['toewijzingen'],
                $result['blok_labels']
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'stats' => $result['stats'],
                    'blok_labels' => $result['blok_labels'],
                ]);
            }

            return redirect()
                ->route('toernooi.blok.index', $toernooi->routeParams())
                ->with('success', "Verdeling toegepast: {$result['stats']['gebruikte_blokken']} blokken gebruikt");

        } catch (\Exception $e) {
            \Log::error('genereerVariabeleVerdeling failed', ['error' => $e->getMessage()]);
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
            }
            return redirect()
                ->route('toernooi.blok.index', $toernooi->routeParams())
                ->with('error', 'Verdeling mislukt: ' . $e->getMessage());
        }
    }

    /**
     * Update gewenst wedstrijden for a block via AJAX
     */
    public function updateGewenst(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
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
    public function zetOpMat(Organisator $organisator, Toernooi $toernooi): RedirectResponse
    {
        // Remove all existing wedstrijden (categories should be INACTIVE in voorbereiding)
        // After overpoulen, judokas may have changed, so old wedstrijden are invalid
        \App\Models\Wedstrijd::whereHas('poule', fn($q) => $q->where('toernooi_id', $toernooi->id))->delete();

        // Reset alle poule statussen - we starten opnieuw!
        $toernooi->poules()->update([
            'doorgestuurd_op' => null,
            'spreker_klaar' => null,
            'afgeroepen_at' => null,
            'huidige_wedstrijd_id' => null,
            'actieve_wedstrijd_id' => null,
        ]);

        // Update aantal_judokas en aantal_wedstrijden voor alle poules
        // Dit voorkomt dat poules weggefilterd worden door verouderde tellingen
        foreach ($toernooi->poules as $poule) {
            $poule->updateStatistieken();
        }

        // Automatische verdeling over matten (organisator kan nog aanpassen)
        $this->verdelingService->verdeelOverMatten($toernooi);

        return redirect()
            ->route('toernooi.blok.zaaloverzicht', $toernooi->routeParams())
            ->with('success', 'Poules verdeeld over matten. Controleer en pas aan indien nodig, klik dan "Maak weegkaarten".');
    }

    public function sluitWeging(Organisator $organisator, Toernooi $toernooi, Blok $blok): RedirectResponse
    {
        $blok->sluitWeging();

        return redirect()
            ->route('toernooi.weging.interface', $toernooi->routeParams())
            ->with('success', "Weging voor {$blok->naam} gesloten. Niet-gewogen judoka's zijn als afwezig gemarkeerd.");
    }

    public function zaaloverzicht(Organisator $organisator, Toernooi $toernooi): View
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
    public function maakWeegkaarten(Organisator $organisator, Toernooi $toernooi): RedirectResponse
    {
        // Check if all poules have mat_id assigned
        $poulesZonderMat = $toernooi->poules()->whereNull('mat_id')->count();
        if ($poulesZonderMat > 0) {
            return redirect()
                ->route('toernooi.blok.zaaloverzicht', $toernooi->routeParams())
                ->with('error', "Nog {$poulesZonderMat} poules zonder mat. Wijs eerst alle poules aan een mat toe.");
        }

        // Seal preparation
        $toernooi->update(['weegkaarten_gemaakt_op' => now()]);

        // No flash message needed - indicator next to title is sufficient
        return redirect()->route('toernooi.blok.zaaloverzicht', $toernooi->routeParams());
    }

    /**
     * Einde voorbereiding: valideer alles, herbereken coachkaarten
     * Dit is het punt waarop de voorbereiding klaar is en info naar clubs kan
     */
    public function eindeVoorbereiding(Organisator $organisator, Toernooi $toernooi): RedirectResponse
    {
        $errors = [];

        // 1. Check: alle judoka's hebben een poule
        $judokasZonderPoule = $toernooi->judokas()
            ->whereDoesntHave('poules')
            ->count();
        if ($judokasZonderPoule > 0) {
            $errors[] = "{$judokasZonderPoule} judoka's zonder poule";
        }

        // 2. Check: alle poules hebben een blok
        $poulesZonderBlok = $toernooi->poules()
            ->whereNull('blok_id')
            ->where('aantal_judokas', '>', 1)
            ->count();
        if ($poulesZonderBlok > 0) {
            $errors[] = "{$poulesZonderBlok} poules zonder blok";
        }

        // 3. Check: alle poules hebben een mat
        $poulesZonderMat = $toernooi->poules()
            ->whereNull('mat_id')
            ->where('aantal_judokas', '>', 1)
            ->count();
        if ($poulesZonderMat > 0) {
            $errors[] = "{$poulesZonderMat} poules zonder mat";
        }

        if (!empty($errors)) {
            return redirect()
                ->route('toernooi.blok.zaaloverzicht', $toernooi->routeParams())
                ->with('error', 'Voorbereiding niet compleet: ' . implode(', ', $errors));
        }

        // 4. Herbereken coachkaarten voor alle clubs
        $clubs = Club::whereHas('judokas', fn($q) => $q->where('toernooi_id', $toernooi->id))->get();
        $totaalAangemaakt = 0;
        $totaalVerwijderd = 0;

        foreach ($clubs as $club) {
            $benodigdAantal = $club->berekenAantalCoachKaarten($toernooi, true);
            $huidigeKaarten = CoachKaart::where('club_id', $club->id)
                ->where('toernooi_id', $toernooi->id)
                ->orderBy('id')
                ->get();
            $huidigAantal = $huidigeKaarten->count();

            // Maak ontbrekende kaarten aan
            if ($huidigAantal < $benodigdAantal) {
                for ($i = $huidigAantal; $i < $benodigdAantal; $i++) {
                    CoachKaart::create([
                        'toernooi_id' => $toernooi->id,
                        'club_id' => $club->id,
                    ]);
                    $totaalAangemaakt++;
                }
            }

            // Verwijder overtollige kaarten (alleen niet-gescande)
            if ($huidigAantal > $benodigdAantal) {
                $teVerwijderen = $huidigeKaarten->skip($benodigdAantal);
                foreach ($teVerwijderen as $kaart) {
                    if (!$kaart->gescand_at) {
                        $kaart->delete();
                        $totaalVerwijderd++;
                    }
                }
            }
        }

        // 5. Markeer voorbereiding als klaar
        $toernooi->update([
            'voorbereiding_klaar_op' => now(),
            'weegkaarten_gemaakt_op' => $toernooi->weegkaarten_gemaakt_op ?? now(),
        ]);

        $message = 'Voorbereiding afgerond!';
        if ($totaalAangemaakt > 0 || $totaalVerwijderd > 0) {
            $message .= " Coachkaarten: {$totaalAangemaakt} aangemaakt, {$totaalVerwijderd} verwijderd.";
        }

        return redirect()
            ->route('toernooi.blok.zaaloverzicht', $toernooi->routeParams())
            ->with('success', $message);
    }

    /**
     * Activate a category: generate match schedules (mats already assigned in voorbereiding)
     */
    public function activeerCategorie(Organisator $organisator, Request $request, Toernooi $toernooi): RedirectResponse
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
            ->route('toernooi.blok.zaaloverzicht', $toernooi->routeParams())
            ->with('success', "✓ {$leeftijdsklasse} {$gewichtsklasse} geactiveerd - {$typeLabel}" .
                ($totaalWedstrijden > 0 ? " ({$totaalWedstrijden} wedstrijden)" : ""));
    }

    /**
     * Reset een categorie: verwijder wedstrijden en haal van mat
     * Categorie wordt weer inactief en kan opnieuw geactiveerd worden
     */
    public function resetCategorie(Organisator $organisator, Request $request, Toernooi $toernooi): RedirectResponse
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
            ->route('toernooi.blok.zaaloverzicht', $toernooi->routeParams())
            ->with('success', "✓ {$leeftijdsklasse} {$gewichtsklasse} gereset - {$totaalVerwijderd} wedstrijden verwijderd, klaar voor nieuwe ronde");
    }

    /**
     * Activate a single poule: generate match schedule
     */
    public function activeerPoule(Organisator $organisator, Request $request, Toernooi $toernooi): RedirectResponse
    {
        $validated = $request->validate([
            'poule_id' => 'required|integer|exists:poules,id',
        ]);

        $poule = Poule::findOrFail($validated['poule_id']);

        // Verify poule belongs to this tournament
        if ($poule->toernooi_id !== $toernooi->id) {
            return redirect()
                ->route('toernooi.blok.zaaloverzicht', $toernooi->routeParams())
                ->with('error', 'Poule niet gevonden');
        }

        // Only generate if no wedstrijden exist yet
        $totaalWedstrijden = 0;
        $isEliminatie = false;

        if ($poule->wedstrijden()->count() === 0) {
            if ($poule->type === 'eliminatie') {
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
                $totaalWedstrijden = $stats['totaal_wedstrijden'] ?? 0;
            } else {
                $wedstrijden = $this->wedstrijdService->genereerWedstrijdenVoorPoule($poule);
                $totaalWedstrijden = count($wedstrijden);
            }
        }

        $typeLabel = $isEliminatie ? 'Eliminatie bracket' : 'Poule';
        return redirect()
            ->route('toernooi.blok.zaaloverzicht', $toernooi->routeParams())
            ->with('success', "✓ {$poule->titel} geactiveerd - {$typeLabel}" .
                ($totaalWedstrijden > 0 ? " ({$totaalWedstrijden} wedstrijden)" : ""));
    }

    /**
     * Reset a single poule: delete wedstrijden
     */
    public function resetPoule(Organisator $organisator, Request $request, Toernooi $toernooi): RedirectResponse
    {
        $validated = $request->validate([
            'poule_id' => 'required|integer|exists:poules,id',
        ]);

        $poule = Poule::findOrFail($validated['poule_id']);

        // Verify poule belongs to this tournament
        if ($poule->toernooi_id !== $toernooi->id) {
            return redirect()
                ->route('toernooi.blok.zaaloverzicht', $toernooi->routeParams())
                ->with('error', 'Poule niet gevonden');
        }

        // Verwijder alle wedstrijden
        $verwijderd = $poule->wedstrijden()->delete();

        // Reset poule status (keep mat_id!)
        $poule->update([
            'spreker_klaar' => null,
            'afgeroepen_at' => null,
            'aantal_wedstrijden' => 0,
        ]);

        return redirect()
            ->route('toernooi.blok.zaaloverzicht', $toernooi->routeParams())
            ->with('success', "✓ Poule {$poule->nummer} gereset - {$verwijderd} wedstrijden verwijderd");
    }

    /**
     * Reset entire blok to end-of-preparation state
     * Deletes all matches, resets doorgestuurd_op AND mat assignments (zaaloverzicht leeg)
     */
    public function resetBlok(Organisator $organisator, Request $request, Toernooi $toernooi): RedirectResponse
    {
        $validated = $request->validate([
            'blok_nummer' => 'required|integer',
        ]);

        $blokNummer = $validated['blok_nummer'];

        // Find the blok
        $blok = $toernooi->blokken()->where('nummer', $blokNummer)->first();
        if (!$blok) {
            return redirect()->back()->with('error', "Blok {$blokNummer} niet gevonden");
        }

        // Find all poules in this blok
        $poules = $toernooi->poules()
            ->where('blok_id', $blok->id)
            ->get();

        $totaalWedstrijden = 0;
        $totaalPoules = 0;
        $verwijderdePoules = 0;

        foreach ($poules as $poule) {
            // Delete all matches
            $verwijderd = $poule->wedstrijden()->delete();
            $totaalWedstrijden += $verwijderd;

            // Check if poule was created AFTER weging was closed (wedstrijddag poule)
            // These should be deleted entirely, not just reset
            if ($blok->weging_gesloten_op && $poule->created_at > $blok->weging_gesloten_op) {
                // Move judokas back to wachtruimte (remove poule_id)
                $poule->judokas()->update(['poule_id' => null]);
                $poule->delete();
                $verwijderdePoules++;
            } else {
                // Reset voorbereiding poule status - including mat assignment (zaaloverzicht leeg)
                $totaalPoules++;
                $poule->update([
                    'doorgestuurd_op' => null,
                    'spreker_klaar' => null,
                    'afgeroepen_at' => null,
                    'huidige_wedstrijd_id' => null,
                    'actieve_wedstrijd_id' => null,
                    'aantal_wedstrijden' => 0,
                    'mat_id' => null,  // Reset mat toewijzing - zaaloverzicht wordt leeg
                ]);
            }
        }

        // Reset blok weging status
        $blok->update([
            'weging_gesloten' => false,
            'weging_gesloten_op' => null,
        ]);

        $message = "✓ Blok {$blokNummer} gereset - {$totaalWedstrijden} wedstrijden verwijderd, {$totaalPoules} poules terug naar eind voorbereiding";
        if ($verwijderdePoules > 0) {
            $message .= ", {$verwijderdePoules} wedstrijddag-poules verwijderd";
        }

        return redirect()
            ->route('toernooi.blok.zaaloverzicht', $toernooi->routeParams())
            ->with('success', $message);
    }

    /**
     * NUCLEAR OPTION: Reset ALLES - alle wedstrijden, alle matten, alle blokken
     */
    public function resetAlles(Organisator $organisator, Toernooi $toernooi): RedirectResponse
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
            ->route('toernooi.edit', $toernooi->routeParams())
            ->with('success', "💥 ALLES GERESET - {$totaalVerwijderd} wedstrijden verwijderd, alle matten leeg, klaar voor nieuwe ronde!");
    }

    /**
     * Get category statuses for wedstrijddag overview
     * Returns: wachtruimte_count, is_activated (has wedstrijden), is_sent (doorgestuurd_op set)
     */
    private function getCategoryStatuses(Toernooi $toernooi): array
    {
        $statuses = [];

        // Get all poules with blok info and wedstrijd count
        $poules = $toernooi->poules()
            ->with('blok')
            ->withCount(['wedstrijden', 'judokas'])
            ->get();

        // Build status per POULE (not per category)
        // Key = "poule_" + poule_id
        foreach ($poules as $poule) {
            $blokNummer = $poule->blok?->nummer ?? 0;
            $pouleKey = 'poule_' . $poule->id;

            $statuses[$pouleKey] = [
                'poule_id' => $poule->id,
                'blok_nummer' => $blokNummer,
                'nummer' => $poule->nummer,
                'leeftijdsklasse' => $poule->leeftijdsklasse,
                'gewichtsklasse' => $poule->gewichtsklasse,
                'titel' => $poule->getDisplayTitel(),
                'judokas_count' => $poule->judokas_count,
                'is_activated' => $poule->wedstrijden_count > 0,
                'is_sent' => $poule->doorgestuurd_op !== null,
            ];
        }

        return $statuses;
    }

    public function sprekerInterface(Organisator $organisator, Toernooi $toernooi): View
    {
        $overzicht = $this->verdelingService->getZaalOverzicht($toernooi);

        // Get poules that are ready for spreker (with results) but not yet announced
        // EXCLUDE barrage poules - they are only used to determine standings in original poule
        $klarePoules = $toernooi->poules()
            ->whereNotNull('spreker_klaar')
            ->whereNull('afgeroepen_at')
            ->where('type', '!=', 'barrage')  // Don't show barrage poules separately
            ->with(['mat', 'blok', 'judokas.club', 'wedstrijden'])
            ->orderBy('spreker_klaar', 'asc')  // Oldest first (longest waiting at top)
            ->get()
            ->map(function ($poule) use ($toernooi) {
                // ELIMINATIE: Haal medaille winnaars direct uit bracket
                if ($poule->type === 'eliminatie') {
                    $poule->standings = $this->getEliminatieStandings($poule);
                    $poule->is_eliminatie = true;
                    return $poule;
                }

                // Check if there's a completed barrage for this poule
                $barrage = $toernooi->poules()
                    ->where('barrage_van_poule_id', $poule->id)
                    ->whereNotNull('spreker_klaar')
                    ->with(['wedstrijden', 'judokas'])
                    ->first();

                // POULE: Calculate WP and JP from wedstrijden + barrage for each judoka
                // Filter out absent judokas (not weighed or marked afwezig)
                $activeJudokas = $poule->judokas->filter(function ($judoka) {
                    return $judoka->gewicht_gewogen !== null && $judoka->aanwezigheid !== 'afwezig';
                });

                $standings = $activeJudokas->map(function ($judoka) use ($poule, $barrage) {
                    $wp = 0;
                    $jp = 0;

                    // Points from original poule
                    foreach ($poule->wedstrijden as $wedstrijd) {
                        if ($wedstrijd->judoka_wit_id === $judoka->id) {
                            $wp += $wedstrijd->winnaar_id === $judoka->id ? 2 : 0;
                            $jp += (int) preg_replace('/[^0-9]/', '', $wedstrijd->score_wit ?? '');
                        } elseif ($wedstrijd->judoka_blauw_id === $judoka->id) {
                            $wp += $wedstrijd->winnaar_id === $judoka->id ? 2 : 0;
                            $jp += (int) preg_replace('/[^0-9]/', '', $wedstrijd->score_blauw ?? '');
                        }
                    }

                    // ADD barrage points if judoka participated in barrage
                    if ($barrage && $barrage->judokas->contains('id', $judoka->id)) {
                        foreach ($barrage->wedstrijden as $w) {
                            if ($w->judoka_wit_id === $judoka->id) {
                                $wp += $w->winnaar_id === $judoka->id ? 2 : 0;
                                $jp += (int) preg_replace('/[^0-9]/', '', $w->score_wit ?? '');
                            } elseif ($w->judoka_blauw_id === $judoka->id) {
                                $wp += $w->winnaar_id === $judoka->id ? 2 : 0;
                                $jp += (int) preg_replace('/[^0-9]/', '', $w->score_blauw ?? '');
                            }
                        }
                    }

                    return [
                        'judoka' => $judoka,
                        'wp' => (int) $wp,
                        'jp' => (int) $jp,
                    ];
                });

                // Sort by WP desc, then JP desc, then head-to-head
                $wedstrijden = $poule->wedstrijden;
                $poule->standings = $standings->sort(function ($a, $b) use ($wedstrijden) {
                    // First: compare WP (higher is better)
                    $wpA = (int) $a['wp'];
                    $wpB = (int) $b['wp'];
                    if ($wpA !== $wpB) {
                        return $wpB - $wpA;
                    }
                    // Second: compare JP (higher is better)
                    $jpA = (int) $a['jp'];
                    $jpB = (int) $b['jp'];
                    if ($jpA !== $jpB) {
                        return $jpB - $jpA;
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
                $poule->has_barrage = $barrage !== null;
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

        // Poules per blok/mat voor "Oproepen" tab (alleen doorgestuurde poules)
        $blokken = $toernooi->blokken()
            ->with(['poules' => function ($q) {
                $q->with(['mat', 'judokas.club'])
                    ->whereNotNull('mat_id')
                    ->whereNotNull('doorgestuurd_op')
                    ->orderBy('mat_id')
                    ->orderBy('nummer');
            }])
            ->orderBy('nummer')
            ->get();

        // Groepeer poules per blok per mat
        $poulesPerBlok = $blokken->mapWithKeys(function ($blok) {
            $poulesPerMat = $blok->poules->groupBy('mat_id')->map(function ($poules, $matId) {
                return [
                    'mat' => $poules->first()->mat,
                    'poules' => $poules,
                ];
            })->sortKeys();
            return [$blok->nummer => ['blok' => $blok, 'matten' => $poulesPerMat]];
        });

        // Admin versie met layouts.app menu (zie docs: INTERFACES.md)
        return view('pages.spreker.interface-admin', compact('toernooi', 'klarePoules', 'afgeroepen', 'poulesPerBlok'));
    }

    /**
     * Calculate poule standings (WP/JP sorted)
     */
    private function berekenPouleStand($poule): \Illuminate\Support\Collection
    {
        // Filter out absent judokas (not weighed or marked afwezig)
        $activeJudokas = $poule->judokas->filter(function ($judoka) {
            return $judoka->gewicht_gewogen !== null && $judoka->aanwezigheid !== 'afwezig';
        });

        $standings = $activeJudokas->map(function ($judoka) use ($poule) {
            $wp = 0;
            $jp = 0;

            foreach ($poule->wedstrijden as $wedstrijd) {
                if ($wedstrijd->judoka_wit_id === $judoka->id) {
                    $wp += $wedstrijd->winnaar_id === $judoka->id ? 2 : 0;
                    $jp += (int) preg_replace('/[^0-9]/', '', $wedstrijd->score_wit ?? '');
                } elseif ($wedstrijd->judoka_blauw_id === $judoka->id) {
                    $wp += $wedstrijd->winnaar_id === $judoka->id ? 2 : 0;
                    $jp += (int) preg_replace('/[^0-9]/', '', $wedstrijd->score_blauw ?? '');
                }
            }

            return [
                'judoka' => $judoka,
                'wp' => (int) $wp,
                'jp' => (int) $jp,
            ];
        });

        $wedstrijden = $poule->wedstrijden;
        return $standings->sort(function ($a, $b) use ($wedstrijden) {
            if ($a['wp'] !== $b['wp']) return $b['wp'] - $a['wp'];
            if ($a['jp'] !== $b['jp']) return $b['jp'] - $a['jp'];
            foreach ($wedstrijden as $w) {
                $isMatch = ($w->judoka_wit_id === $a['judoka']->id && $w->judoka_blauw_id === $b['judoka']->id)
                        || ($w->judoka_wit_id === $b['judoka']->id && $w->judoka_blauw_id === $a['judoka']->id);
                if ($isMatch && $w->winnaar_id) {
                    return $w->winnaar_id === $a['judoka']->id ? -1 : 1;
                }
            }
            return 0;
        })->values();
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
    public function markeerAfgeroepen(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
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
    public function zetAfgeroepenTerug(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
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

    /**
     * Get poule standings for speaker interface (view previously announced)
     */
    public function getPouleStandings(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'poule_id' => 'required|exists:poules,id',
        ]);

        $poule = Poule::with(['judokas.club', 'wedstrijden'])->findOrFail($validated['poule_id']);
        $isEliminatie = $poule->type === 'eliminatie';

        if ($isEliminatie) {
            $standings = $this->getEliminatieStandings($poule);
        } else {
            $standings = $this->berekenPouleStand($poule);
        }

        return response()->json([
            'success' => true,
            'poule' => [
                'id' => $poule->id,
                'nummer' => $poule->nummer,
                'leeftijdsklasse' => $poule->leeftijdsklasse,
                'gewichtsklasse' => $poule->gewichtsklasse,
                'type' => $poule->type,
                'is_eliminatie' => $isEliminatie,
            ],
            'standings' => $standings->map(fn($s) => [
                'naam' => $s['judoka']->naam,
                'club' => $s['judoka']->club?->naam ?? '-',
                'wp' => $s['wp'],
                'jp' => $s['jp'],
                'plaats' => $s['plaats'] ?? null,
            ])->toArray(),
        ]);
    }

    /**
     * Save speaker notes to tournament (persisted for next year)
     */
    public function saveNotities(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'notities' => 'nullable|string|max:10000',
        ]);

        $toernooi->update(['spreker_notities' => $validated['notities']]);

        return response()->json([
            'success' => true,
            'message' => 'Notities opgeslagen',
        ]);
    }

    /**
     * Get speaker notes from tournament
     */
    public function getNotities(Organisator $organisator, Toernooi $toernooi): JsonResponse
    {
        return response()->json([
            'success' => true,
            'notities' => $toernooi->spreker_notities ?? '',
        ]);
    }

    public function verplaatsPoule(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'poule_id' => 'required|exists:poules,id',
            'mat_id' => 'required|exists:matten,id',
        ]);

        $poule = Poule::findOrFail($validated['poule_id']);
        $oudeMatId = $poule->mat_id;
        $nieuweMatId = $validated['mat_id'];

        // Reset geel (volgende_wedstrijd) op oude mat als het een wedstrijd van deze poule was
        // Groen blijft staan - mat-jury moet handmatig stoppen
        if ($oudeMatId && $oudeMatId != $nieuweMatId) {
            $oudeMat = Mat::find($oudeMatId);
            if ($oudeMat) {
                $oudeMat->resetWedstrijdSelectieVoorPoule($poule->id);
            }
        }

        // Mat_id wijzigen - wedstrijden en scores blijven intact
        $poule->update(['mat_id' => $nieuweMatId]);

        return response()->json([
            'success' => true,
            'message' => "Poule {$poule->nummer} verplaatst",
        ]);
    }

    /**
     * Verplaats een categorie naar een blok (drag & drop)
     * vast parameter determines if category is pinned
     */
    public function verplaatsCategorie(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'key' => 'required|string',
            'blok' => 'required|integer|min:0',
            'vast' => 'nullable|boolean',
        ]);

        $key = $validated['key'];
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

        // Check key format: "poule_123" (single poule) or "leeftijd|gewicht" (category)
        if (str_starts_with($key, 'poule_')) {
            // Single poule by ID
            $pouleId = (int) substr($key, 6);
            $updated = $toernooi->poules()
                ->where('id', $pouleId)
                ->update(['blok_id' => $blokId, 'blok_vast' => $blokVast]);
        } else {
            // Category: "leeftijdsklasse|gewichtsklasse"
            $parts = explode('|', $key);
            if (count($parts) !== 2) {
                return response()->json(['success' => false, 'error' => 'Invalid key'], 400);
            }

            $leeftijdsklasse = $parts[0];
            $gewichtsklasse = $parts[1];

            // Update alle poules met deze categorie
            $updated = $toernooi->poules()
                ->where('leeftijdsklasse', $leeftijdsklasse)
                ->where('gewichtsklasse', $gewichtsklasse)
                ->update(['blok_id' => $blokId, 'blok_vast' => $blokVast]);
        }

        return response()->json(['success' => true, 'updated' => $updated, 'vast' => $blokVast]);
    }

    /**
     * Maak een barrage poule voor judoka's met gelijke stand (3-weg gelijkspel)
     * Judoka's blijven in originele poule, worden TOEGEVOEGD aan barrage (niet verplaatst)
     */
    public function maakBarrage(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'poule_id' => 'required|exists:poules,id',
            'judoka_ids' => 'required|array|min:2',
            'judoka_ids.*' => 'exists:judokas,id',
        ]);

        $originelePoule = Poule::with(['mat', 'blok', 'judokas'])->findOrFail($validated['poule_id']);

        // Verify poule belongs to this toernooi
        if ($originelePoule->toernooi_id !== $toernooi->id) {
            return response()->json(['success' => false, 'error' => 'Poule hoort niet bij dit toernooi'], 403);
        }

        // Get hoogste poule nummer voor nummering
        $maxNummer = $toernooi->poules()->max('nummer') ?? 0;

        // Maak barrage poule
        $barragePoule = Poule::create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $originelePoule->blok_id,
            'mat_id' => $originelePoule->mat_id,
            'nummer' => $maxNummer + 1,
            'leeftijdsklasse' => $originelePoule->leeftijdsklasse,
            'gewichtsklasse' => $originelePoule->gewichtsklasse,
            'type' => 'barrage', // Speciaal type
            'titel' => 'Barrage ' . $originelePoule->leeftijdsklasse . ' ' . $originelePoule->gewichtsklasse,
            'categorie_key' => $originelePoule->categorie_key,
            'barrage_van_poule_id' => $originelePoule->id, // Link naar originele poule
        ]);

        // Voeg judoka's toe aan barrage (NIET detach uit originele!)
        $positie = 1;
        foreach ($validated['judoka_ids'] as $judokaId) {
            $barragePoule->judokas()->attach($judokaId, ['positie' => $positie++]);
        }

        // Update statistieken
        $barragePoule->updateStatistieken();

        // Genereer wedstrijdschema
        $wedstrijden = $this->wedstrijdService->genereerWedstrijdenVoorPoule($barragePoule);

        // Doorsturen naar zaaloverzicht (zelfde mat als origineel)
        $barragePoule->update(['doorgestuurd_op' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Barrage poule aangemaakt',
            'barrage_poule' => [
                'id' => $barragePoule->id,
                'nummer' => $barragePoule->nummer,
                'titel' => $barragePoule->titel,
                'mat_id' => $barragePoule->mat_id,
                'aantal_judokas' => count($validated['judoka_ids']),
                'aantal_wedstrijden' => count($wedstrijden),
            ],
        ]);
    }
}
