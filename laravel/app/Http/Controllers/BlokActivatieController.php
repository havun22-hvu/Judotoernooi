<?php

namespace App\Http\Controllers;

use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Services\ActivityLogger;
use App\Services\EliminatieService;
use App\Services\WedstrijdSchemaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Handles activation, reset and nuclear-reset flows for categories, poules and
 * blokken during the wedstrijddag. Split out of BlokController so the main
 * controller can focus on voorbereiding and variant selection.
 */
class BlokActivatieController extends Controller
{
    public function __construct(
        private WedstrijdSchemaService $wedstrijdService,
        private EliminatieService $eliminatieService
    ) {}

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

        ActivityLogger::log($toernooi, 'activeer_categorie', "{$leeftijdsklasse} {$gewichtsklasse} geactiveerd (blok {$blokNummer}, {$totaalWedstrijden} wedstrijden)", [
            'model_type' => 'Toernooi',
            'model_id' => $toernooi->id,
            'properties' => ['leeftijdsklasse' => $leeftijdsklasse, 'gewichtsklasse' => $gewichtsklasse, 'blok' => $blokNummer, 'totaal_wedstrijden' => $totaalWedstrijden],
            'interface' => 'dashboard',
        ]);

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

        ActivityLogger::log($toernooi, 'reset_categorie', "{$leeftijdsklasse} {$gewichtsklasse} gereset (blok {$blokNummer}, {$totaalVerwijderd} wedstrijden verwijderd)", [
            'model_type' => 'Toernooi',
            'model_id' => $toernooi->id,
            'properties' => ['leeftijdsklasse' => $leeftijdsklasse, 'gewichtsklasse' => $gewichtsklasse, 'blok' => $blokNummer, 'wedstrijden_verwijderd' => $totaalVerwijderd],
            'interface' => 'dashboard',
        ]);

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
                // Remove judokas from poule
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

        ActivityLogger::log($toernooi, 'reset_blok', "Blok {$blokNummer} gereset: {$totaalWedstrijden} wedstrijden verwijderd, {$totaalPoules} poules terug", [
            'model' => $blok,
            'properties' => ['blok_nummer' => $blokNummer, 'wedstrijden_verwijderd' => $totaalWedstrijden, 'poules_gereset' => $totaalPoules, 'poules_verwijderd' => $verwijderdePoules],
            'interface' => 'dashboard',
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

        ActivityLogger::log($toernooi, 'reset_alles', "ALLES GERESET: {$totaalVerwijderd} wedstrijden verwijderd, alle matten leeg", [
            'model_type' => 'Toernooi',
            'model_id' => $toernooi->id,
            'properties' => ['wedstrijden_verwijderd' => $totaalVerwijderd],
            'interface' => 'dashboard',
        ]);

        return redirect()
            ->route('toernooi.edit', $toernooi->routeParams())
            ->with('success', "💥 ALLES GERESET - {$totaalVerwijderd} wedstrijden verwijderd, alle matten leeg, klaar voor nieuwe ronde!");
    }
}
