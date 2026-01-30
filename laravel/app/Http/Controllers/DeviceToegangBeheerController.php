<?php

namespace App\Http\Controllers;

use App\Models\Organisator;
use App\Models\DeviceToegang;
use App\Models\Toernooi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceToegangBeheerController extends Controller
{
    /**
     * Get all device toegangen for a toernooi.
     */
    public function index(Organisator $organisator, Toernooi $toernooi): JsonResponse
    {
        $toegangen = $toernooi->deviceToegangen()
            ->orderBy('naam')
            ->orderBy('rol')
            ->orderBy('mat_nummer')
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'naam' => $t->naam,
                'telefoon' => $t->telefoon,
                'email' => $t->email,
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
    public function store(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $request->validate([
            'naam' => 'nullable|string|max:255',
            'telefoon' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'rol' => 'required|in:hoofdjury,mat,weging,spreker,dojo',
            'mat_nummer' => 'nullable|integer|min:1',
        ]);

        $toegang = DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'naam' => $request->naam ?? '',
            'telefoon' => $request->telefoon,
            'email' => $request->email,
            'rol' => $request->rol,
            'mat_nummer' => $request->rol === 'mat' ? $request->mat_nummer : null,
        ]);

        return response()->json([
            'id' => $toegang->id,
            'naam' => $toegang->naam,
            'telefoon' => $toegang->telefoon,
            'email' => $toegang->email,
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
     * Update a device toegang.
     */
    public function update(Request $request, DeviceToegang $toegang): JsonResponse
    {
        $request->validate([
            'naam' => 'nullable|string|max:255',
            'telefoon' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'rol' => 'sometimes|in:hoofdjury,mat,weging,spreker,dojo',
            'mat_nummer' => 'nullable|integer|min:1',
        ]);

        // Only update fields that are provided
        $data = ['naam' => $request->naam ?? ''];

        if ($request->has('telefoon')) {
            $data['telefoon'] = $request->telefoon;
        }
        if ($request->has('email')) {
            $data['email'] = $request->email;
        }
        if ($request->has('rol')) {
            $data['rol'] = $request->rol;
            $data['mat_nummer'] = $request->rol === 'mat' ? $request->mat_nummer : null;
        }

        $toegang->update($data);

        return response()->json([
            'id' => $toegang->id,
            'naam' => $toegang->naam,
            'telefoon' => $toegang->telefoon,
            'email' => $toegang->email,
            'rol' => $toegang->rol,
            'mat_nummer' => $toegang->mat_nummer,
            'label' => $toegang->getLabel(),
            'code' => $toegang->code,
            'pincode' => $toegang->pincode,
            'url' => $toegang->getUrl(),
            'is_gebonden' => $toegang->isGebonden(),
            'device_info' => $toegang->device_info,
            'status' => $toegang->getStatusText(),
        ]);
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
    public function resetAll(Organisator $organisator, Toernooi $toernooi): JsonResponse
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
