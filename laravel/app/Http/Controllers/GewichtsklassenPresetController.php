<?php

namespace App\Http\Controllers;

use App\Models\GewichtsklassenPreset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GewichtsklassenPresetController extends Controller
{
    /**
     * Get all presets for the authenticated organisator.
     */
    public function index(): JsonResponse
    {
        $presets = GewichtsklassenPreset::where('organisator_id', Auth::guard('organisator')->id())
            ->orderBy('naam')
            ->get(['id', 'naam', 'configuratie']);

        return response()->json($presets);
    }

    /**
     * Store a new preset.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'naam' => 'required|string|max:100',
            'configuratie' => 'required|array',
        ]);

        $organisatorId = Auth::guard('organisator')->id();
        $organisator = Auth::guard('organisator')->user();

        // Check if this is a new preset (not an update) and if limit is reached
        $existingPreset = GewichtsklassenPreset::where('organisator_id', $organisatorId)
            ->where('naam', $validated['naam'])
            ->first();

        if (!$existingPreset && !$organisator->canAddMorePresets()) {
            return response()->json([
                'error' => 'Maximum aantal presets bereikt. Je mag maximaal 1 preset opslaan in de gratis versie.',
            ], 422);
        }

        // Sort categories by max_leeftijd (youngest first)
        $configuratie = $validated['configuratie'];
        uasort($configuratie, function ($a, $b) {
            return ($a['max_leeftijd'] ?? 99) <=> ($b['max_leeftijd'] ?? 99);
        });

        // Update existing or create new
        $preset = GewichtsklassenPreset::updateOrCreate(
            ['organisator_id' => $organisatorId, 'naam' => $validated['naam']],
            ['configuratie' => $configuratie]
        );

        return response()->json([
            'success' => true,
            'id' => $preset->id,
            'naam' => $preset->naam,
            'message' => $preset->wasRecentlyCreated ? 'Preset aangemaakt' : 'Preset bijgewerkt',
        ]);
    }

    /**
     * Delete a preset.
     */
    public function destroy(GewichtsklassenPreset $preset): JsonResponse
    {
        // Check ownership
        if ($preset->organisator_id !== Auth::guard('organisator')->id()) {
            return response()->json(['error' => 'Niet toegestaan'], 403);
        }

        $preset->delete();

        return response()->json([
            'success' => true,
            'message' => 'Preset verwijderd',
        ]);
    }
}
