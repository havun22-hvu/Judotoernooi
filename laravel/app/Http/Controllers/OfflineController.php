<?php

namespace App\Http\Controllers;

use App\Models\Toernooi;
use App\Models\Wedstrijd;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controller for the offline noodpakket app.
 * Only active when OFFLINE_MODE=true.
 */
class OfflineController extends Controller
{
    /**
     * Offline startpagina - toont toernooi info en mat selectie.
     */
    public function index()
    {
        $toernooi = $this->getToernooi();

        if (!$toernooi) {
            return response('Geen toernooi gevonden in de offline database.', 500);
        }

        $matten = $toernooi->matten()->orderBy('nummer')->get();
        $stats = [
            'judokas' => $toernooi->judokas()->count(),
            'poules' => $toernooi->poules()->count(),
            'wedstrijden_totaal' => Wedstrijd::whereHas('poule', fn($q) => $q->where('toernooi_id', $toernooi->id))->count(),
            'wedstrijden_gespeeld' => Wedstrijd::whereHas('poule', fn($q) => $q->where('toernooi_id', $toernooi->id))->where('is_gespeeld', true)->count(),
        ];

        return view('pages.offline.index', compact('toernooi', 'matten', 'stats'));
    }

    /**
     * Upload resultaten naar de cloud server na het toernooi.
     */
    public function uploadResultaten(Request $request): JsonResponse
    {
        $toernooi = $this->getToernooi();

        if (!$toernooi) {
            return response()->json(['error' => 'Geen toernooi gevonden'], 404);
        }

        // Collect all played matches
        $wedstrijden = Wedstrijd::whereHas('poule', fn($q) => $q->where('toernooi_id', $toernooi->id))
            ->where('is_gespeeld', true)
            ->get()
            ->map(fn($w) => [
                'wedstrijd_id' => $w->id,
                'winnaar_id' => $w->winnaar_id,
                'score_wit' => $w->score_wit,
                'score_blauw' => $w->score_blauw,
            ])
            ->values()
            ->toArray();

        return response()->json([
            'resultaten' => $wedstrijden,
            'toernooi_id' => $toernooi->id,
            'count' => count($wedstrijden),
        ]);
    }

    private function getToernooi(): ?Toernooi
    {
        $toernooiId = config('app.offline_toernooi_id');

        if ($toernooiId) {
            return Toernooi::find($toernooiId);
        }

        // Fallback: get the only tournament in the database
        return Toernooi::first();
    }
}
