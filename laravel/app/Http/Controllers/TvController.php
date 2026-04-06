<?php

namespace App\Http\Controllers;

use App\Models\TvKoppeling;
use Illuminate\Http\Request;

class TvController extends Controller
{
    /**
     * TV koppelpagina — toont code, wacht op koppeling.
     */
    public function index()
    {
        // Clean up expired codes
        TvKoppeling::where('expires_at', '<', now())->delete();

        // Generate a new code
        $koppeling = TvKoppeling::create([
            'code' => TvKoppeling::generateCode(),
            'expires_at' => now()->addMinutes(10),
        ]);

        return view('pages.tv.koppel', [
            'code' => $koppeling->code,
            'koppelingId' => $koppeling->id,
        ]);
    }

    /**
     * Poll endpoint — TV checkt of de code gekoppeld is.
     */
    public function poll(TvKoppeling $koppeling)
    {
        if ($koppeling->isExpired()) {
            return response()->json(['status' => 'expired']);
        }

        if ($koppeling->isLinked()) {
            $toernooi = $koppeling->toernooi;
            $organisator = $toernooi->organisator;

            $redirectUrl = route('mat.scoreboard-live', [
                'organisator' => $organisator->slug,
                'toernooi' => $toernooi->slug,
                'mat' => $koppeling->mat_nummer,
            ]);

            return response()->json([
                'status' => 'linked',
                'redirect' => $redirectUrl,
            ]);
        }

        return response()->json(['status' => 'waiting']);
    }

    /**
     * Link endpoint — organisator koppelt code aan mat.
     */
    public function link(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:4',
            'toernooi_id' => 'required|exists:toernooien,id',
            'mat_nummer' => 'required|integer|min:1',
        ]);

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

        $koppeling->update([
            'toernooi_id' => $request->toernooi_id,
            'mat_nummer' => $request->mat_nummer,
            'linked_at' => now(),
        ]);

        $toernooi = $koppeling->toernooi;
        $toegang = $toernooi->deviceToegangen()
            ->where('rol', 'mat')
            ->where('mat_nummer', $request->mat_nummer)
            ->first();

        $redirectUrl = $toegang
            ? url('/tv/' . substr($toegang->code, 0, 4))
            : route('mat.scoreboard-live', [
                'organisator' => $toernooi->organisator->slug,
                'toernooi' => $toernooi->slug,
                'mat' => $request->mat_nummer,
            ]);

        \App\Events\TvLinked::dispatch($koppeling->code, $redirectUrl);

        return response()->json([
            'success' => true,
            'message' => __('TV gekoppeld aan Mat') . ' ' . $request->mat_nummer,
        ]);
    }
}
