<?php

namespace App\Http\Controllers;

use App\Models\DeviceToegang;
use App\Models\Organisator;
use App\Models\Blok;
use App\Models\Mat;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use App\Services\BracketLayoutService;
use App\Services\WedstrijdSchemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Handles mat-side page rendering and read-only JSON endpoints:
 * - index/show/interface/scoreboard views
 * - wedstrijd schema fetching
 * - bracket HTML rendering
 * - generate wedstrijden
 * - admin password check
 *
 * Result writes live in MatUitslagController,
 * bracket mutations live in MatBracketController.
 */
class MatController extends Controller
{
    public function __construct(
        private WedstrijdSchemaService $wedstrijdService,
        private BracketLayoutService $bracketLayoutService,
    ) {}

    public function index(Organisator $organisator, Toernooi $toernooi): View
    {
        $matten = $toernooi->matten;
        $blokken = $toernooi->blokken;

        return view('pages.mat.index', compact('toernooi', 'matten', 'blokken'));
    }

    public function show(Organisator $organisator, Toernooi $toernooi, Mat $mat, ?Blok $blok = null): View
    {
        if (!$blok) {
            // Get first non-closed block
            $blok = $toernooi->blokken()
                ->where('weging_gesloten', true)
                ->orderBy('nummer')
                ->first();
        }

        $schema = $blok
            ? $this->wedstrijdService->getSchemaVoorMat($blok, $mat)
            : [];

        return view('pages.mat.show', compact('toernooi', 'mat', 'blok', 'schema'));
    }

    public function interface(Organisator $organisator, Toernooi $toernooi): View
    {
        $blokken = $toernooi->blokken;
        $matten = $toernooi->matten;

        // Admin versie met layouts.app menu (zie docs: INTERFACES.md)
        return view('pages.mat.interface-admin', compact('toernooi', 'blokken', 'matten'));
    }

    public function getWedstrijden(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        return $this->doGetWedstrijden($request);
    }

    /**
     * Device-bound version - toernooi comes from device_toegang
     */
    public function getWedstrijdenDevice(Request $request): JsonResponse
    {
        return $this->doGetWedstrijden($request);
    }

    private function doGetWedstrijden(Request $request): JsonResponse
    {
        $blokId = $request->input('blok_id');
        $matId = $request->input('mat_id');

        // Manual validation with JSON error response
        if (!$blokId || !$matId) {
            return response()->json(['error' => 'blok_id en mat_id zijn verplicht'], 400);
        }

        $blok = Blok::find($blokId);
        $mat = Mat::find($matId);

        if (!$blok) {
            return response()->json(['error' => 'Blok niet gevonden - selecteer opnieuw', 'invalid_blok' => true], 404);
        }
        if (!$mat) {
            return response()->json(['error' => 'Mat niet gevonden - selecteer opnieuw', 'invalid_mat' => true], 404);
        }

        $schema = $this->wedstrijdService->getSchemaVoorMat($blok, $mat);

        return response()->json($schema);
    }

    /**
     * Scoreboard pagina - standalone of met wedstrijd
     */
    public function scoreboard(Organisator $organisator, Toernooi $toernooi, ?Wedstrijd $wedstrijd = null): View
    {
        return view('pages.mat.scoreboard', compact('toernooi', 'wedstrijd'));
    }

    /**
     * Build the match payload that scoreboard-live and scoreboardState share.
     */
    private function getFormattedCurrentMatch(Mat $matModel, Toernooi $toernooi): ?array
    {
        $matModel->load(['actieveWedstrijd.judokaWit.club', 'actieveWedstrijd.judokaBlauw.club', 'actieveWedstrijd.poule']);

        if (!$matModel->actieveWedstrijd) {
            return null;
        }

        $w = $matModel->actieveWedstrijd;

        return [
            'judoka_wit' => ['naam' => $w->judokaWit?->naam ?? 'WIT', 'club' => $w->judokaWit?->club?->naam ?? ''],
            'judoka_blauw' => ['naam' => $w->judokaBlauw?->naam ?? 'BLAUW', 'club' => $w->judokaBlauw?->club?->naam ?? ''],
            'poule_naam' => $w->poule?->titel ?? "Poule {$w->poule?->nummer}",
            'match_duration' => $toernooi->getMatchDurationForCategorie($w->poule?->categorie_key),
            ...$toernooi->getMatchRulesForCategorie($w->poule?->categorie_key),
        ];
    }

    /**
     * Scoreboard live display — web-based display for TV/LCD
     * Listens to Reverb events from the Android bediening app
     */
    public function scoreboardLive(Organisator $organisator, Toernooi $toernooi, $mat): View
    {
        $matModel = $toernooi->matten()->where('nummer', $mat)->first();
        $matId = $matModel ? $matModel->id : $mat;
        $currentMatch = $matModel ? $this->getFormattedCurrentMatch($matModel, $toernooi) : null;
        $blauwRechts = (bool) ($toernooi->mat_voorkeuren['blauw_rechts'] ?? false);

        return view('pages.mat.scoreboard-live', [
            'toernooi' => $toernooi,
            'matId' => $matId,
            'matNummer' => $matModel?->nummer ?? $mat,
            'currentMatch' => $currentMatch,
            'blauwRechts' => $blauwRechts,
        ]);
    }

    /**
     * JSON endpoint for current match state (used by inline scoreboard tab).
     */
    public function scoreboardState(Organisator $organisator, Toernooi $toernooi, $mat): JsonResponse
    {
        $matModel = $toernooi->matten()->where('nummer', $mat)->first();

        if (!$matModel) {
            return response()->json(null);
        }

        $currentMatch = $this->getFormattedCurrentMatch($matModel, $toernooi);

        return response()->json($currentMatch
            ? [...$currentMatch, 'mat_id' => $matModel->id]
            : ['mat_id' => $matModel->id]
        );
    }

    /**
     * Short TV URL: /tv/{4-char code} → redirect to scoreboard-live
     */
    public function tvRedirect(string $code)
    {
        $toegang = DeviceToegang::findByDisplayCode($code);

        if (! $toegang || ! $toegang->mat_nummer) {
            abort(404, 'Ongeldige TV code');
        }

        $toernooi = $toegang->toernooi;
        $organisator = $toernooi->organisator;

        return redirect()->route('mat.scoreboard-live', [
            'organisator' => $organisator->slug,
            'toernooi' => $toernooi->slug,
            'mat' => $toegang->mat_nummer,
        ]);
    }

    /**
     * Genereer wedstrijden voor een poule
     */
    public function genereerWedstrijden(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'poule_id' => 'required|exists:poules,id',
        ]);

        $poule = Poule::findOrFail($validated['poule_id']);

        // Genereer wedstrijden
        $this->wedstrijdService->genereerWedstrijden($poule);

        return response()->json(['success' => true]);
    }

    /**
     * Geef bracket HTML voor een specifieke poule + groep.
     * Wordt via AJAX opgehaald door de mat interface.
     */
    public function getBracketHtml(Organisator $organisator, Request $request, Toernooi $toernooi): \Illuminate\Http\Response
    {
        return $this->doGetBracketHtml($request);
    }

    public function getBracketHtmlDevice(Request $request): \Illuminate\Http\Response
    {
        return $this->doGetBracketHtml($request);
    }

    private function doGetBracketHtml(Request $request): \Illuminate\Http\Response
    {
        $validated = $request->validate([
            'poule_id' => 'required|exists:poules,id',
            'groep' => 'required|in:A,B',
            'debug_slots' => 'nullable|boolean',
            'start_ronde' => 'nullable|integer|min:0',
        ]);

        $poule = Poule::with(['wedstrijden' => function ($q) {
            $q->with(['judokaWit:id,naam', 'judokaBlauw:id,naam']);
        }])->findOrFail($validated['poule_id']);

        $groep = $validated['groep'];
        $isLocked = $poule->wedstrijden->where('is_gespeeld', true)->isNotEmpty();

        // Bouw wedstrijd-arrays in hetzelfde formaat als getSchemaVoorMat
        $wedstrijden = $poule->wedstrijden
            ->where('groep', $groep)
            ->map(function ($w) {
                return [
                    'id' => $w->id,
                    'volgorde' => $w->volgorde,
                    'wit' => $w->judokaWit ? ['id' => $w->judokaWit->id, 'naam' => $w->judokaWit->naam] : null,
                    'blauw' => $w->judokaBlauw ? ['id' => $w->judokaBlauw->id, 'naam' => $w->judokaBlauw->naam] : null,
                    'is_gespeeld' => (bool) $w->is_gespeeld,
                    'winnaar_id' => $w->winnaar_id,
                    'score_wit' => $w->score_wit,
                    'score_blauw' => $w->score_blauw,
                    'groep' => $w->groep,
                    'ronde' => $w->ronde,
                    'bracket_positie' => $w->bracket_positie,
                    'volgende_wedstrijd_id' => $w->volgende_wedstrijd_id,
                    'winnaar_naar_slot' => $w->winnaar_naar_slot,
                    'uitslag_type' => $w->uitslag_type,
                    'locatie_wit' => $w->locatie_wit,
                    'locatie_blauw' => $w->locatie_blauw,
                ];
            })
            ->values()
            ->toArray();

        try {
            if ($groep === 'A') {
                $startRonde = (int) ($validated['start_ronde'] ?? 0);
                $layout = $this->bracketLayoutService->berekenABracketLayout($wedstrijden, $startRonde);
                $view = 'pages.mat.partials._bracket';
            } else {
                $startRonde = (int) ($validated['start_ronde'] ?? 0);
                $layout = $this->bracketLayoutService->berekenBBracketLayout($wedstrijden, $startRonde);
                $view = 'pages.mat.partials._bracket-b';
            }

            $html = view($view, [
                'layout' => $layout,
                'pouleId' => $poule->id,
                'isLocked' => $isLocked,
                'debugSlots' => (bool) ($validated['debug_slots'] ?? false),
            ])->render();

            return response($html, 200)->header('Content-Type', 'text/html');
        } catch (\Throwable $e) {
            report($e);
            return response('<div class="text-red-500 text-sm py-2">Fout bij bracket rendering</div>', 500)
                ->header('Content-Type', 'text/html');
        }
    }

    /**
     * Verify admin password for bracket operations (server-side bcrypt check).
     */
    public function checkAdminWachtwoord(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        return $this->doCheckAdminWachtwoord($request, $toernooi);
    }

    public function checkAdminWachtwoordDevice(Request $request): JsonResponse
    {
        $toernooi = $request->device_toegang->toernooi;
        return $this->doCheckAdminWachtwoord($request, $toernooi);
    }

    private function doCheckAdminWachtwoord(Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'wachtwoord' => 'required|string',
        ]);

        $wachtwoord = $validated['wachtwoord'];

        // Accept: toernooi admin/jury pin (bcrypt), organisator password.
        // Device PIN has been removed — device-bound routes already require
        // an authenticated device binding via the 12-character role code.
        $geldig = false;

        // 1. Toernooi admin or jury pin (bcrypt)
        if (!$geldig) {
            $geldig = $toernooi->checkWachtwoord('admin', $wachtwoord)
                || $toernooi->checkWachtwoord('jury', $wachtwoord);
        }

        // 2. Organisator login password
        if (!$geldig) {
            $geldig = Hash::check($wachtwoord, $toernooi->organisator->password ?? '');
        }

        // 3. Logged-in user's password (e.g. sitebeheerder viewing another org's toernooi)
        if (!$geldig && auth('organisator')->check()) {
            $geldig = Hash::check($wachtwoord, auth('organisator')->user()->password ?? '');
        }

        return response()->json(['geldig' => $geldig]);
    }
}
