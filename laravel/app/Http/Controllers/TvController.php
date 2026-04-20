<?php

namespace App\Http\Controllers;

use App\Events\TvLinked;
use App\Models\Toernooi;
use App\Models\TvKoppeling;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TvController extends Controller
{
    public function index()
    {
        TvKoppeling::cleanupExpired();

        $koppeling = TvKoppeling::create([
            'code' => TvKoppeling::generateCode(),
            'expires_at' => now()->addMinutes(10),
        ]);

        $qrUrl = url('/tv/qr/' . $koppeling->code);
        $qrSvg = QrCode::format('svg')->size(280)->margin(1)->generate($qrUrl);

        return view('pages.tv.koppel', [
            'code' => $koppeling->code,
            'koppelingId' => $koppeling->id,
            'qrSvg' => $qrSvg,
            'qrUrl' => $qrUrl,
        ]);
    }

    /**
     * QR-scan landing — organisator scant QR op TV → kiest mat → koppelt.
     * Code komt uit de URL (geen body) omdat deze route via QR-scan wordt geopend.
     */
    public function qrScan(Request $request, string $code)
    {
        TvKoppeling::cleanupExpired();

        $koppeling = TvKoppeling::where('code', $code)->first();

        if (!$koppeling || $koppeling->isExpired()) {
            return view('pages.tv.qr-scan', ['status' => 'expired', 'code' => $code]);
        }

        if ($koppeling->isLinked()) {
            return view('pages.tv.qr-scan', ['status' => 'already-linked', 'code' => $code]);
        }

        $user = $request->user();
        $toernooien = $user->is_sitebeheerder
            ? Toernooi::where('is_actief', true)->orderByDesc('datum')->get()
            : $user->toernooien()->where('is_actief', true)->orderByDesc('datum')->get();

        return view('pages.tv.qr-scan', [
            'status' => 'ready',
            'code' => $code,
            'toernooien' => $toernooien,
        ]);
    }

    public function link(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:4',
            'toernooi_id' => 'required|exists:toernooien,id',
            'mat_nummer' => 'required|integer|min:1',
        ]);

        $toernooi = Toernooi::with('organisator')->findOrFail($request->toernooi_id);
        $user = $request->user();

        if (!$user || (!$user->is_sitebeheerder && $toernooi->organisator_id !== $user->organisator_id)) {
            return response()->json([
                'success' => false,
                'message' => __('Geen toegang tot dit toernooi'),
            ], 403);
        }

        $koppeling = TvKoppeling::where('code', $request->code)
            ->where('expires_at', '>', now())
            ->whereNull('linked_at')
            ->first();

        if (!$koppeling) {
            return response()->json([
                'success' => false,
                'message' => __('Code ongeldig of verlopen'),
            ], 422);
        }

        $redirectUrl = $koppeling->linkToMat($toernooi->id, $request->mat_nummer);
        TvLinked::dispatch($koppeling->code, $redirectUrl);

        return response()->json([
            'success' => true,
            'message' => __('TV gekoppeld aan Mat') . ' ' . $request->mat_nummer,
        ]);
    }
}
