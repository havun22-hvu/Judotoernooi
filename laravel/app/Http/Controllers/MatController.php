<?php

namespace App\Http\Controllers;

use App\Models\Blok;
use App\Models\Mat;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use App\Services\EliminatieService;
use App\Services\WedstrijdSchemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MatController extends Controller
{
    public function __construct(
        private WedstrijdSchemaService $wedstrijdService,
        private EliminatieService $eliminatieService
    ) {}

    public function index(Toernooi $toernooi): View
    {
        $matten = $toernooi->matten;
        $blokken = $toernooi->blokken;

        return view('pages.mat.index', compact('toernooi', 'matten', 'blokken'));
    }

    public function show(Toernooi $toernooi, Mat $mat, ?Blok $blok = null): View
    {
        if (!$blok) {
            // Get first non-closed block
            $blok = $toernooi->blokken()
                ->where('weging_gesloten', true)
                ->orderBy('nummer')
                ->first();
        }

        $schema = $blok
            ? $this->wedstrijdService->getSchemaVoorMat($blok, $mat)
            : [];

        return view('pages.mat.show', compact('toernooi', 'mat', 'blok', 'schema'));
    }

    public function interface(Toernooi $toernooi): View
    {
        $blokken = $toernooi->blokken;
        $matten = $toernooi->matten;

        return view('pages.mat.interface', compact('toernooi', 'blokken', 'matten'));
    }

    public function getWedstrijden(Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'blok_id' => 'required|exists:blokken,id',
            'mat_id' => 'required|exists:matten,id',
        ]);

        $blok = Blok::findOrFail($validated['blok_id']);
        $mat = Mat::findOrFail($validated['mat_id']);

        $schema = $this->wedstrijdService->getSchemaVoorMat($blok, $mat);

        return response()->json($schema);
    }

    public function registreerUitslag(Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'wedstrijd_id' => 'required|exists:wedstrijden,id',
            'winnaar_id' => 'nullable|exists:judokas,id',
            'score_wit' => 'nullable|string|max:20',
            'score_blauw' => 'nullable|string|max:20',
            'uitslag_type' => 'nullable|string|max:20',
        ]);

        $wedstrijd = Wedstrijd::findOrFail($validated['wedstrijd_id']);

        // Check if this is an elimination match (has groep field)
        if ($wedstrijd->groep) {
            $wedstrijd->update([
                'winnaar_id' => $validated['winnaar_id'],
                'is_gespeeld' => (bool) $validated['winnaar_id'],
                'uitslag_type' => $validated['uitslag_type'] ?? 'eliminatie',
                'gespeeld_op' => $validated['winnaar_id'] ? now() : null,
            ]);

            // Auto-advance: winnaar naar volgende ronde, verliezer naar B-poule
            if ($validated['winnaar_id']) {
                $this->eliminatieService->verwerkUitslag($wedstrijd, $validated['winnaar_id']);
            }

            return response()->json(['success' => true]);
        } else {
            // Regular pool match
            $this->wedstrijdService->registreerUitslag(
                $wedstrijd,
                $validated['winnaar_id'],
                $validated['score_wit'] ?? '',
                $validated['score_blauw'] ?? '',
                $validated['uitslag_type'] ?? 'beslissing'
            );
        }

        return response()->json(['success' => true]);
    }

    /**
     * Mark poule as ready for spreker (results announcement)
     */
    public function pouleKlaar(Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'poule_id' => 'required|exists:poules,id',
        ]);

        $poule = Poule::findOrFail($validated['poule_id']);

        // Verify poule belongs to this toernooi
        if ($poule->toernooi_id !== $toernooi->id) {
            return response()->json(['success' => false, 'error' => 'Poule hoort niet bij dit toernooi'], 403);
        }

        $poule->update(['spreker_klaar' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * Manually set current match for a poule (override automatic order)
     * Used when table staff needs to change order due to injuries etc.
     */
    public function setHuidigeWedstrijd(Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'poule_id' => 'required|exists:poules,id',
            'wedstrijd_id' => 'nullable|exists:wedstrijden,id',
        ]);

        $poule = Poule::findOrFail($validated['poule_id']);

        // Verify poule belongs to this toernooi
        if ($poule->toernooi_id !== $toernooi->id) {
            return response()->json(['success' => false, 'error' => 'Poule hoort niet bij dit toernooi'], 403);
        }

        // Verify wedstrijd belongs to this poule (if provided)
        if ($validated['wedstrijd_id']) {
            $wedstrijd = Wedstrijd::findOrFail($validated['wedstrijd_id']);
            if ($wedstrijd->poule_id !== $poule->id) {
                return response()->json(['success' => false, 'error' => 'Wedstrijd hoort niet bij deze poule'], 403);
            }
        }

        $poule->update(['huidige_wedstrijd_id' => $validated['wedstrijd_id']]);

        return response()->json(['success' => true]);
    }

    /**
     * Place a judoka in an elimination bracket slot (manual drag & drop)
     */
    public function plaatsJudoka(Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'wedstrijd_id' => 'required|exists:wedstrijden,id',
            'judoka_id' => 'required|exists:judokas,id',
            'positie' => 'required|in:wit,blauw',
        ]);

        $wedstrijd = Wedstrijd::findOrFail($validated['wedstrijd_id']);

        // Update the appropriate slot
        if ($validated['positie'] === 'wit') {
            $wedstrijd->update(['judoka_wit_id' => $validated['judoka_id']]);
        } else {
            $wedstrijd->update(['judoka_blauw_id' => $validated['judoka_id']]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Remove a judoka from an elimination bracket slot (drag to trash)
     */
    public function verwijderJudoka(Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'wedstrijd_id' => 'required|exists:wedstrijden,id',
            'judoka_id' => 'required|exists:judokas,id',
        ]);

        $wedstrijd = Wedstrijd::findOrFail($validated['wedstrijd_id']);

        // Remove judoka from the slot they were in
        if ($wedstrijd->judoka_wit_id == $validated['judoka_id']) {
            $wedstrijd->update(['judoka_wit_id' => null]);
        } elseif ($wedstrijd->judoka_blauw_id == $validated['judoka_id']) {
            $wedstrijd->update(['judoka_blauw_id' => null]);
        }

        return response()->json(['success' => true]);
    }
}
