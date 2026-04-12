<?php

namespace App\Http\Controllers;

use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Toernooi;
use App\Services\WegingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PubliekWegingController extends Controller
{
    public function __construct(
        private WegingService $wegingService
    ) {}

    /**
     * Scan QR code and return judoka info (public, read-only)
     */
    public function scanQR(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $qrCode = $request->input('qr_code', '');

        if (empty($qrCode)) {
            return response()->json(['success' => false, 'message' => 'Geen QR code']);
        }

        // Extract qr_code from URL if full URL is provided
        if (str_contains($qrCode, '/weegkaart/')) {
            $parts = explode('/weegkaart/', $qrCode);
            $qrCode = end($parts);
            $qrCode = strtok($qrCode, '?');
            $qrCode = strtok($qrCode, '#');
            $qrCode = rtrim($qrCode, '/');
        }

        $judoka = Judoka::where('toernooi_id', $toernooi->id)
            ->where('qr_code', $qrCode)
            ->with(['club', 'poules.blok', 'wegingen'])
            ->first();

        if (!$judoka) {
            return response()->json(['success' => false, 'message' => 'Judoka niet gevonden']);
        }

        $maxWegingen = $toernooi->max_wegingen;
        $aantalWegingen = $judoka->wegingen->count();

        return response()->json([
            'success' => true,
            'judoka' => [
                'id' => $judoka->id,
                'naam' => $judoka->naam,
                'club' => $judoka->club?->naam,
                'leeftijdsklasse' => $judoka->leeftijdsklasse,
                'gewichtsklasse' => $judoka->gewichtsklasse,
                'gewicht' => $judoka->gewicht, // opgegeven gewicht bij aanmelding
                'blok' => $judoka->poules->first()?->blok?->nummer,
                'gewogen' => $judoka->gewicht_gewogen > 0,
                'gewicht_gewogen' => $judoka->gewicht_gewogen,
                'vorige_wegingen' => $judoka->wegingen->take(5)->map(fn($w) => [
                    'gewicht' => $w->gewicht,
                    'tijd' => $w->created_at->format('H:i'),
                ])->toArray(),
                'aantal_wegingen' => $aantalWegingen,
                'max_wegingen' => $maxWegingen,
                'max_bereikt' => $maxWegingen && $aantalWegingen >= $maxWegingen,
            ],
        ]);
    }

    /**
     * Register weight for judoka (public route for PWA)
     * Uses WegingService to properly save weging records
     */
    public function registreerGewicht(Organisator $organisator, Request $request, Toernooi $toernooi, Judoka $judoka): JsonResponse
    {
        // Verify judoka belongs to this tournament
        if ($judoka->toernooi_id !== $toernooi->id) {
            return response()->json(['success' => false, 'message' => 'Judoka niet gevonden'], 404);
        }

        $validated = $request->validate([
            'gewicht' => 'required|numeric|min:10|max:200',
        ]);

        // Use WegingService to register weight (creates Weging record + updates judoka)
        $resultaat = $this->wegingService->registreerGewicht(
            $judoka,
            $validated['gewicht'],
            $request->user()?->name ?? 'PWA'
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
}
