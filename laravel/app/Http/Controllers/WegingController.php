<?php

namespace App\Http\Controllers;

use App\Models\Judoka;
use App\Models\Toernooi;
use App\Services\WegingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WegingController extends Controller
{
    public function __construct(
        private WegingService $wegingService
    ) {}

    public function index(Toernooi $toernooi, ?int $blok = null): View
    {
        $judokas = $this->wegingService->getWeeglijst($toernooi, $blok);

        return view('pages.weging.index', compact('toernooi', 'judokas', 'blok'));
    }

    public function registreer(Request $request, Toernooi $toernooi, Judoka $judoka): JsonResponse
    {
        $validated = $request->validate([
            'gewicht' => 'required|numeric|min:15|max:150',
        ]);

        $resultaat = $this->wegingService->registreerGewicht(
            $judoka,
            $validated['gewicht'],
            $request->user()?->name
        );

        return response()->json([
            'success' => true,
            'binnen_klasse' => $resultaat['binnen_klasse'],
            'alternatieve_poule' => $resultaat['alternatieve_poule'],
            'opmerking' => $resultaat['opmerking'],
        ]);
    }

    public function markeerAanwezig(Toernooi $toernooi, Judoka $judoka): JsonResponse
    {
        $this->wegingService->markeerAanwezig($judoka);

        return response()->json(['success' => true]);
    }

    public function markeerAfwezig(Toernooi $toernooi, Judoka $judoka): JsonResponse
    {
        $this->wegingService->markeerAfwezig($judoka);

        return response()->json(['success' => true]);
    }

    public function scanQR(Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'qr_code' => 'required|string',
        ]);

        $judoka = $this->wegingService->vindJudokaViaQR($validated['qr_code']);

        if (!$judoka || $judoka->toernooi_id !== $toernooi->id) {
            return response()->json([
                'success' => false,
                'message' => 'Judoka niet gevonden',
            ], 404);
        }

        $judoka->load(['club', 'poules.blok', 'poules.mat']);

        return response()->json([
            'success' => true,
            'judoka' => [
                'id' => $judoka->id,
                'naam' => $judoka->naam,
                'club' => $judoka->club?->naam,
                'leeftijdsklasse' => $judoka->leeftijdsklasse,
                'gewichtsklasse' => $judoka->gewichtsklasse,
                'blok' => $judoka->poules->first()?->blok?->nummer,
                'mat' => $judoka->poules->first()?->mat?->nummer,
                'aanwezig' => $judoka->isAanwezig(),
                'gewicht_gewogen' => $judoka->gewicht_gewogen,
            ],
        ]);
    }

    public function interface(Toernooi $toernooi): View
    {
        return view('pages.weging.interface', compact('toernooi'));
    }
}
