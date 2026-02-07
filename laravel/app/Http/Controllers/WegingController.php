<?php

namespace App\Http\Controllers;

use App\Models\Organisator;
use App\Models\Judoka;
use App\Models\Toernooi;
use App\Services\ActivityLogger;
use App\Services\WegingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WegingController extends Controller
{
    public function __construct(
        private WegingService $wegingService
    ) {}

    public function index(Organisator $organisator, Toernooi $toernooi, ?int $blok = null): View
    {
        $judokas = $this->wegingService->getWeeglijst($toernooi, $blok);
        $toernooi->load('blokken');
        $blokGesloten = $toernooi->blokken->pluck('weging_gesloten', 'nummer')->toArray();

        return view('pages.weging.index', compact('toernooi', 'judokas', 'blok', 'blokGesloten'));
    }

    public function registreer(Organisator $organisator, Request $request, Toernooi $toernooi, Judoka $judoka): JsonResponse
    {
        $validated = $request->validate([
            'gewicht' => 'required|numeric|min:0|max:150',
        ]);

        // Gewicht 0 = judoka kan niet deelnemen (afwezig markeren)
        if ($validated['gewicht'] == 0) {
            $this->wegingService->markeerAfwezig($judoka);
            return response()->json([
                'success' => true,
                'afwezig' => true,
                'message' => 'Judoka gemarkeerd als afwezig',
            ]);
        }

        // Normale weging (min 15kg)
        if ($validated['gewicht'] < 15) {
            return response()->json([
                'success' => false,
                'message' => 'Gewicht moet minimaal 15kg zijn (of 0 voor afwezig)',
            ], 400);
        }

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

        ActivityLogger::log($toernooi, 'registreer_gewicht', "{$judoka->naam} gewogen: {$validated['gewicht']}kg", [
            'model' => $judoka,
            'properties' => ['gewicht' => $validated['gewicht'], 'binnen_klasse' => $resultaat['binnen_klasse']],
            'interface' => 'weging',
        ]);

        return response()->json([
            'success' => true,
            'binnen_klasse' => $resultaat['binnen_klasse'],
            'alternatieve_poule' => $resultaat['alternatieve_poule'],
            'opmerking' => $resultaat['opmerking'],
        ]);
    }

    public function markeerAanwezig(Organisator $organisator, Toernooi $toernooi, Judoka $judoka): JsonResponse
    {
        $this->wegingService->markeerAanwezig($judoka);

        ActivityLogger::log($toernooi, 'markeer_aanwezig', "{$judoka->naam} aanwezig gemarkeerd", [
            'model' => $judoka,
            'interface' => 'weging',
        ]);

        return response()->json(['success' => true]);
    }

    public function markeerAfwezig(Organisator $organisator, Toernooi $toernooi, Judoka $judoka): JsonResponse
    {
        $this->wegingService->markeerAfwezig($judoka);

        ActivityLogger::log($toernooi, 'markeer_afwezig', "{$judoka->naam} afwezig gemarkeerd", [
            'model' => $judoka,
            'interface' => 'weging',
        ]);

        return response()->json(['success' => true]);
    }

    public function scanQR(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
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

        $judoka->load(['club', 'poules.blok', 'poules.mat', 'wegingen']);

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
                'is_vaste_klasse' => $judoka->isVasteGewichtsklasse(),
                'gewicht' => $judoka->gewicht, // opgegeven gewicht bij aanmelding
                'blok' => $judoka->poules->first()?->blok?->nummer,
                'mat' => $judoka->poules->first()?->mat?->nummer,
                'aanwezig' => $judoka->isAanwezig(),
                'gewogen' => $judoka->gewicht_gewogen > 0,
                'gewicht_gewogen' => $judoka->gewicht_gewogen,
                'aantal_wegingen' => $aantalWegingen,
                'vorige_wegingen' => $judoka->wegingen->take(5)->map(fn($w) => [
                    'gewicht' => $w->gewicht,
                    'tijd' => $w->created_at->format('H:i'),
                ])->toArray(),
                'max_wegingen' => $maxWegingen,
                'max_bereikt' => $maxWegingen && $aantalWegingen >= $maxWegingen,
            ],
        ]);
    }

    public function interface(Organisator $organisator, Toernooi $toernooi): View
    {
        $toernooi->load('blokken');

        // Admin versie: live weeglijst (zie docs: INTERFACES.md)
        $judokas = $this->getJudokasVoorLijst($toernooi);

        return view('pages.weging.interface-admin', compact('toernooi', 'judokas'));
    }

    /**
     * JSON endpoint voor live weeglijst auto-refresh
     */
    public function lijstJson(Organisator $organisator, Toernooi $toernooi): JsonResponse
    {
        return response()->json($this->getJudokasVoorLijst($toernooi));
    }

    /**
     * Helper: haal judokas op in formaat voor weeglijst
     */
    private function getJudokasVoorLijst(Toernooi $toernooi): array
    {
        $judokas = $this->wegingService->getWeeglijst($toernooi);

        // Build lookup: which blokken are closed?
        $blokGesloten = $toernooi->blokken->pluck('weging_gesloten', 'nummer')->toArray();

        return $judokas->map(function ($j) use ($blokGesloten) {
            $blokNummer = $j->poules->first()?->blok?->nummer;
            $blokIsClosed = $blokNummer ? ($blokGesloten[$blokNummer] ?? false) : false;

            // Only show as afwezig if explicitly marked AND blok is closed
            $isAfwezig = $j->aanwezigheid === 'afwezig' && $blokIsClosed;

            return [
                'id' => $j->id,
                'naam' => $j->naam,
                'club' => $j->club?->naam,
                'leeftijdsklasse' => $j->leeftijdsklasse,
                'gewicht' => $j->gewicht,
                'gewichtsklasse' => $j->gewichtsklasse,
                'is_vaste_klasse' => $j->isVasteGewichtsklasse(),
                'blok' => $blokNummer,
                'gewogen' => $j->gewicht_gewogen > 0,
                'gewicht_gewogen' => $j->gewicht_gewogen,
                'gewogen_om' => $j->wegingen->first()?->created_at?->format('H:i'),
                'afwezig' => $isAfwezig,
            ];
        })->toArray();
    }
}
