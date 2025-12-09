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

        // Load saved priorities with defaults
        $prioriteiten = $toernooi->verdeling_prioriteiten ?? [
            'spreiding' => 'hoog',
            'gewicht' => 'hoog',
            'matten' => 'normaal',
        ];

        return view('pages.blok.index', compact('toernooi', 'blokken', 'statistieken', 'prioriteiten'));
    }

    public function show(Toernooi $toernooi, Blok $blok): View
    {
        $blok->load(['poules.mat', 'poules.judokas']);

        return view('pages.blok.show', compact('toernooi', 'blok'));
    }

    public function genereerVerdeling(Request $request, Toernooi $toernooi): RedirectResponse
    {
        // Get priorities from request (hoog, normaal, laag)
        $prioriteiten = [
            'spreiding' => $request->input('prioriteit_spreiding', 'hoog'),
            'gewicht' => $request->input('prioriteit_gewicht', 'hoog'),
            'matten' => $request->input('prioriteit_matten', 'normaal'),
        ];

        // Save priorities to database
        $toernooi->update(['verdeling_prioriteiten' => $prioriteiten]);

        $statistieken = $this->verdelingService->genereerBlokMatVerdeling($toernooi, false, $prioriteiten);

        return redirect()
            ->route('toernooi.blok.index', $toernooi)
            ->with('success', 'Blok/Mat verdeling gegenereerd');
    }

    /**
     * Save distribution priorities via AJAX
     */
    public function savePrioriteiten(Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'spreiding' => 'required|in:hoog,normaal,laag',
            'gewicht' => 'required|in:hoog,normaal,laag',
            'matten' => 'required|in:hoog,normaal,laag',
        ]);

        $toernooi->update(['verdeling_prioriteiten' => $validated]);

        return response()->json(['success' => true]);
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

        return view('pages.blok.zaaloverzicht', compact('toernooi', 'overzicht'));
    }

    public function sprekerInterface(Toernooi $toernooi): View
    {
        $overzicht = $this->verdelingService->getZaalOverzicht($toernooi);

        return view('pages.spreker.interface', compact('toernooi', 'overzicht'));
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
     * Handmatige blok/mat verdeling opslaan
     */
    public function handmatigeVerdeling(Request $request, Toernooi $toernooi): RedirectResponse
    {
        $blokToewijzingen = $request->input('blok', []);
        $matToewijzingen = $request->input('mat', []);

        // Haal alle blokken op (cache)
        $blokken = $toernooi->blokken()->get()->keyBy('nummer');
        $matten = $toernooi->matten()->get()->keyBy('nummer');

        $gewijzigd = 0;

        // Loop door alle toewijzingen (key = "leeftijdsklasse gewichtsklasse")
        foreach ($blokToewijzingen as $key => $blokNummer) {
            if (empty($blokNummer)) continue;

            // Parse de key (bijv. "Mini's -24")
            $parts = explode(' ', $key, 2);
            if (count($parts) < 2) continue;

            $leeftijdsklasse = $parts[0];
            $gewichtsklasse = trim($parts[1]);

            // Haal het blok op
            $blok = $blokken->get((int)$blokNummer);
            if (!$blok) continue;

            // Haal optioneel de mat op
            $mat = null;
            if (!empty($matToewijzingen[$key])) {
                $mat = $matten->get((int)$matToewijzingen[$key]);
            }

            // Update alle poules met deze leeftijdsklasse en gewichtsklasse
            $poules = $toernooi->poules()
                ->where('leeftijdsklasse', $leeftijdsklasse)
                ->where('gewichtsklasse', $gewichtsklasse)
                ->get();

            foreach ($poules as $poule) {
                $updates = ['blok_id' => $blok->id];
                if ($mat) {
                    $updates['mat_id'] = $mat->id;
                }
                $poule->update($updates);
                $gewijzigd++;
            }
        }

        // Update timestamp
        $toernooi->update(['blokken_verdeeld_op' => now()]);

        return redirect()
            ->route('toernooi.blok.index', $toernooi)
            ->with('success', "Handmatige verdeling opgeslagen ({$gewijzigd} poules bijgewerkt)");
    }
}
