<?php

namespace App\Http\Controllers;

use App\Models\Judoka;
use Illuminate\View\View;

class WeegkaartController extends Controller
{
    /**
     * Show weegkaart for a judoka (public, accessed via QR code token)
     */
    public function show(string $token): View
    {
        $judoka = Judoka::where('qr_code', $token)
            ->with(['club', 'toernooi', 'poules.blok', 'poules.mat'])
            ->firstOrFail();

        // Get the first poule's blok and mat (judoka typically in one blok)
        $poule = $judoka->poules->first();
        $blok = $poule?->blok;
        $mat = $poule?->mat;

        return view('pages.weegkaart.show', compact('judoka', 'blok', 'mat'));
    }
}
