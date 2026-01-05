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

        // Update existing or create new
        $preset = GewichtsklassenPreset::updateOrCreate(
            ['organisator_id' => $organisatorId, 'naam' => $validated['naam']],
            ['configuratie' => $validated['configuratie']]
        );

        return response()->json([
            'success' => true,
            'preset' => $preset,
            'message' => 'Preset opgeslagen',
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
