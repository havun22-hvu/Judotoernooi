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
            ->with(['club', 'toernooi', 'poules.blok'])
            ->firstOrFail();

        // Get the first poule's blok (judoka typically in one blok)
        $blok = $judoka->poules->first()?->blok;

        return view('pages.weegkaart.show', compact('judoka', 'blok'));
    }
}
