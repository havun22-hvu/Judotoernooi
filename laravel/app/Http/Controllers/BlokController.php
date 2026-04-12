<?php

namespace App\Http\Controllers;

use App\Models\Organisator;
use App\Models\Blok;
use App\Models\Club;
use App\Models\CoachKaart;
use App\Models\Toernooi;
use App\Services\ActivityLogger;
use App\Services\BackupService;
use App\Services\BlokMatVerdelingService;
use App\Services\VariabeleBlokVerdelingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Handles blok voorbereiding — variant generation, distribution, zaaloverzicht
 * and preparation sealing. Activation/reset flows live in BlokActivatieController,
 * spreker interface in BlokSprekerController.
 */
class BlokController extends Controller
{
    public function __construct(
        private BlokMatVerdelingService $verdelingService,
        private VariabeleBlokVerdelingService $variabeleService,
        private BackupService $backupService
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
        // Milestone backup before destructive operations (production only)
        $this->backupService->maakMilestoneBackup("voor-verdeling-matten-toernooi-{$toernooi->id}");

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
            ->route('toernooi.blok.zaaloverzicht', $toernooi->routeParams());
    }

    public function sluitWeging(Organisator $organisator, Toernooi $toernooi, Blok $blok): RedirectResponse
    {
        $blok->sluitWeging();

        ActivityLogger::log($toernooi, 'sluit_weging', "Weging gesloten voor {$blok->naam}", [
            'model' => $blok,
            'interface' => 'dashboard',
        ]);

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
     * Get category statuses for wedstrijddag overview
     * Returns: is_activated (has wedstrijden), is_sent (doorgestuurd_op set)
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
}
