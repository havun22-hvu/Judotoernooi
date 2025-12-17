<?php

namespace App\Http\Controllers;

use App\Models\Blok;
use App\Models\Mat;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use App\Services\WedstrijdSchemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MatController extends Controller
{
    public function __construct(
        private WedstrijdSchemaService $wedstrijdService
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

        $this->wedstrijdService->registreerUitslag(
            $wedstrijd,
            $validated['winnaar_id'],
            $validated['score_wit'] ?? '',
            $validated['score_blauw'] ?? '',
            $validated['uitslag_type'] ?? 'beslissing'
        );

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
}
