<?php

namespace App\Http\Controllers;

use App\Models\Organisator;
use App\Models\Toernooi;
use App\Models\Vrijwilliger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VrijwilligerController extends Controller
{
    /**
     * Get all vrijwilligers for the organisator (via toernooi context).
     */
    public function index(Organisator $organisator, Toernooi $toernooi): JsonResponse
    {
        $vrijwilligers = $toernooi->organisator->vrijwilligers()
            ->orderBy('functie')
            ->orderBy('voornaam')
            ->get()
            ->map(fn($v) => [
                'id' => $v->id,
                'voornaam' => $v->voornaam,
                'telefoonnummer' => $v->telefoonnummer,
                'functie' => $v->functie,
                'functie_label' => $v->getFunctieLabel(),
            ]);

        return response()->json($vrijwilligers);
    }

    /**
     * Create a new vrijwilliger.
     */
    public function store(Organisator $organisator, Toernooi $toernooi, Request $request): JsonResponse
    {
        $request->validate([
            'voornaam' => 'required|string|max:255',
            'telefoonnummer' => 'nullable|string|max:20',
            'functie' => 'required|in:' . implode(',', Vrijwilliger::FUNCTIES),
        ]);

        $vrijwilliger = Vrijwilliger::create([
            'organisator_id' => $toernooi->organisator->id,
            'voornaam' => $request->voornaam,
            'telefoonnummer' => $request->telefoonnummer,
            'functie' => $request->functie,
        ]);

        return response()->json([
            'id' => $vrijwilliger->id,
            'voornaam' => $vrijwilliger->voornaam,
            'telefoonnummer' => $vrijwilliger->telefoonnummer,
            'functie' => $vrijwilliger->functie,
            'functie_label' => $vrijwilliger->getFunctieLabel(),
        ], 201);
    }

    /**
     * Update a vrijwilliger.
     */
    public function update(Organisator $organisator, Toernooi $toernooi, Vrijwilliger $vrijwilliger, Request $request): JsonResponse
    {
        // Verify vrijwilliger belongs to this organisator
        if ($vrijwilliger->organisator_id !== $toernooi->organisator->id) {
            abort(403);
        }

        $request->validate([
            'voornaam' => 'required|string|max:255',
            'telefoonnummer' => 'nullable|string|max:20',
            'functie' => 'required|in:' . implode(',', Vrijwilliger::FUNCTIES),
        ]);

        $vrijwilliger->update([
            'voornaam' => $request->voornaam,
            'telefoonnummer' => $request->telefoonnummer,
            'functie' => $request->functie,
        ]);

        return response()->json([
            'id' => $vrijwilliger->id,
            'voornaam' => $vrijwilliger->voornaam,
            'telefoonnummer' => $vrijwilliger->telefoonnummer,
            'functie' => $vrijwilliger->functie,
            'functie_label' => $vrijwilliger->getFunctieLabel(),
        ]);
    }

    /**
     * Delete a vrijwilliger.
     */
    public function destroy(Organisator $organisator, Toernooi $toernooi, Vrijwilliger $vrijwilliger): JsonResponse
    {
        // Verify vrijwilliger belongs to this organisator
        if ($vrijwilliger->organisator_id !== $toernooi->organisator->id) {
            abort(403);
        }

        $vrijwilliger->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vrijwilliger verwijderd',
        ]);
    }
}
