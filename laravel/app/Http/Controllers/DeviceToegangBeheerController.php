<?php

namespace App\Http\Controllers;

use App\Models\DeviceToegang;
use App\Models\Toernooi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceToegangBeheerController extends Controller
{
    /**
     * Get all device toegangen for a toernooi.
     */
    public function index(Toernooi $toernooi): JsonResponse
    {
        $toegangen = $toernooi->deviceToegangen()
            ->orderBy('rol')
            ->orderBy('mat_nummer')
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'rol' => $t->rol,
                'mat_nummer' => $t->mat_nummer,
                'label' => $t->getLabel(),
                'code' => $t->code,
                'pincode' => $t->pincode,
                'url' => $t->getUrl(),
                'is_gebonden' => $t->isGebonden(),
                'device_info' => $t->device_info,
                'status' => $t->getStatusText(),
            ]);

        return response()->json($toegangen);
    }

    /**
     * Create a new device toegang.
     */
    public function store(Request $request, Toernooi $toernooi): JsonResponse
    {
        $request->validate([
            'rol' => 'required|in:hoofdjury,mat,weging,spreker,dojo',
            'mat_nummer' => 'nullable|integer|min:1',
        ]);

        $toegang = DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'rol' => $request->rol,
            'mat_nummer' => $request->rol === 'mat' ? $request->mat_nummer : null,
        ]);

        return response()->json([
            'id' => $toegang->id,
            'rol' => $toegang->rol,
            'mat_nummer' => $toegang->mat_nummer,
            'label' => $toegang->getLabel(),
            'code' => $toegang->code,
            'pincode' => $toegang->pincode,
            'url' => $toegang->getUrl(),
            'is_gebonden' => false,
            'device_info' => null,
            'status' => 'Wacht op binding',
        ], 201);
    }

    /**
     * Reset device binding.
     */
    public function reset(Request $request, DeviceToegang $toegang): JsonResponse
    {
        $toegang->reset();

        return response()->json([
            'success' => true,
            'message' => 'Device binding gereset',
        ]);
    }

    /**
     * Regenerate PIN.
     */
    public function regeneratePin(Request $request, DeviceToegang $toegang): JsonResponse
    {
        $toegang->update([
            'pincode' => DeviceToegang::generatePincode(),
        ]);
        $toegang->reset(); // Also reset binding when PIN changes

        return response()->json([
            'success' => true,
            'pincode' => $toegang->pincode,
        ]);
    }

    /**
     * Delete a device toegang.
     */
    public function destroy(DeviceToegang $toegang): JsonResponse
    {
        $toegang->delete();

        return response()->json([
            'success' => true,
            'message' => 'Toegang verwijderd',
        ]);
    }

    /**
     * Reset all device bindings for a toernooi (einde toernooi).
     */
    public function resetAll(Toernooi $toernooi): JsonResponse
    {
        $toernooi->deviceToegangen()->update([
            'device_token' => null,
            'device_info' => null,
            'gebonden_op' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Alle device bindings gereset',
        ]);
    }
}
