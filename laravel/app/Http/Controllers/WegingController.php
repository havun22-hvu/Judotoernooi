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

        if (!($resultaat['success'] ?? true)) {
            return response()->json([
                'success' => false,
                'message' => $resultaat['error'] ?? 'Weging niet toegestaan',
            ], 400);
        }

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
                'gewogen' => $judoka->gewicht_gewogen !== null,
                'gewicht_gewogen' => $judoka->gewicht_gewogen,
                'aantal_wegingen' => $judoka->wegingen()->count(),
            ],
        ]);
    }

    public function interface(Toernooi $toernooi): View
    {
        $toernooi->load('blokken');

        // Admin versie: live weeglijst (zie docs: INTERFACES.md)
        $judokas = $this->getJudokasVoorLijst($toernooi);

        return view('pages.weging.interface-admin', compact('toernooi', 'judokas'));
    }

    /**
     * JSON endpoint voor live weeglijst auto-refresh
     */
    public function lijstJson(Toernooi $toernooi): JsonResponse
    {
        return response()->json($this->getJudokasVoorLijst($toernooi));
    }

    /**
     * Helper: haal judokas op in formaat voor weeglijst
     */
    private function getJudokasVoorLijst(Toernooi $toernooi): array
    {
        $judokas = $this->wegingService->getWeeglijst($toernooi);

        return $judokas->map(fn($j) => [
            'id' => $j->id,
            'naam' => $j->naam,
            'club' => $j->club?->naam,
            'gewichtsklasse' => $j->gewichtsklasse,
            'blok' => $j->poules->first()?->blok?->nummer,
            'gewogen' => $j->gewicht_gewogen !== null,
            'gewicht_gewogen' => $j->gewicht_gewogen,
            'gewogen_om' => $j->wegingen->first()?->created_at?->format('H:i'),
        ])->toArray();
    }
}
