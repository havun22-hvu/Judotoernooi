<?php

namespace App\Http\Controllers\Api;

use App\Events\ScoreboardAssignment;
use App\Events\ScoreboardState;
use App\Events\MatUpdate;
use App\Http\Controllers\Controller;
use App\Models\DeviceToegang;
use App\Models\Judoka;
use App\Models\Mat;
use App\Models\Wedstrijd;
use App\Services\ActivityLogger;
use App\Services\EliminatieService;
use App\Services\WedstrijdSchemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScoreboardController extends Controller
{
    public function __construct(
        private WedstrijdSchemaService $wedstrijdService,
        private EliminatieService $eliminatieService,
    ) {}

    /**
     * Authenticate scoreboard device with code + pincode.
     * Returns Bearer token + toernooi/mat config.
     */
    public function auth(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|size:12',
            'pincode' => 'required|string|size:4',
        ]);

        $toegang = DeviceToegang::where('code', $validated['code'])
            ->whereIn('rol', ['scoreboard', 'scoreboard-display'])
            ->first();

        if (!$toegang || $toegang->pincode !== $validated['pincode']) {
            return response()->json(['message' => 'Ongeldige code of pincode.'], 401);
        }

        // Generate API token
        $token = DeviceToegang::generateDeviceToken();
        $toegang->update([
            'api_token' => $token,
            'device_info' => $request->header('User-Agent', 'Scoreboard App'),
            'gebonden_op' => now(),
            'laatst_actief' => now(),
        ]);

        // Find the mat linked to this device
        $mat = Mat::where('toernooi_id', $toegang->toernooi_id)
            ->where('nummer', $toegang->mat_nummer)
            ->first();

        return response()->json([
            'token' => $token,
            'rol' => $toegang->rol,
            'toernooi_id' => $toegang->toernooi_id,
            'mat_id' => $mat?->id,
            'mat_naam' => $mat ? "Mat {$mat->nummer}" : null,
            'reverb_config' => [
                'host' => config('broadcasting.connections.reverb.options.host', parse_url(config('app.url'), PHP_URL_HOST)),
                'port' => (int) config('broadcasting.connections.reverb.options.port', 443),
                'scheme' => config('broadcasting.connections.reverb.options.scheme', 'https'),
                'app_key' => config('broadcasting.connections.reverb.key'),
            ],
        ]);
    }

    /**
     * Get the current active match for this scoreboard's mat.
     * Polling fallback for when WebSocket is unavailable.
     */
    public function currentMatch(Request $request): JsonResponse
    {
        $toegang = $request->get('device_toegang');

        $mat = Mat::where('toernooi_id', $toegang->toernooi_id)
            ->where('nummer', $toegang->mat_nummer)
            ->with(['actieveWedstrijd.judokaWit.club', 'actieveWedstrijd.judokaBlauw.club', 'actieveWedstrijd.poule'])
            ->first();

        if (!$mat || !$mat->actieveWedstrijd) {
            return response()->json(['match' => null]);
        }

        $wedstrijd = $mat->actieveWedstrijd;

        return response()->json([
            'match' => $this->formatMatch($wedstrijd),
            'updated_at' => $wedstrijd->updated_at?->toISOString(),
        ]);
    }

    /**
     * Register match result from scoreboard app.
     * Reuses the same logic as MatController::doRegistreerUitslag().
     */
    public function result(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'wedstrijd_id' => 'required|exists:wedstrijden,id',
            'winnaar_id' => 'required|exists:judokas,id',
            'score_wit' => 'nullable|array',
            'score_wit.wazaari' => 'nullable|integer|min:0|max:2',
            'score_wit.ippon' => 'nullable|boolean',
            'score_wit.shido' => 'nullable|integer|min:0|max:3',
            'score_blauw' => 'nullable|array',
            'score_blauw.wazaari' => 'nullable|integer|min:0|max:2',
            'score_blauw.ippon' => 'nullable|boolean',
            'score_blauw.shido' => 'nullable|integer|min:0|max:3',
            'uitslag_type' => 'required|string|max:20',
            'match_duration_actual' => 'nullable|integer|min:0',
            'golden_score' => 'nullable|boolean',
            'updated_at' => 'nullable|string',
        ]);

        $wedstrijd = Wedstrijd::findOrFail($validated['wedstrijd_id']);

        // Optimistic locking
        if ($validated['updated_at'] && $wedstrijd->updated_at) {
            $clientTime = $validated['updated_at'];
            $serverTime = $wedstrijd->updated_at->toISOString();
            if ($clientTime !== $serverTime) {
                return response()->json([
                    'success' => false,
                    'message' => 'Wedstrijd is ondertussen gewijzigd door een ander apparaat.',
                    'server_updated_at' => $serverTime,
                ], 409);
            }
        }

        // Validate winnaar is participant
        if ($validated['winnaar_id'] != $wedstrijd->judoka_wit_id &&
            $validated['winnaar_id'] != $wedstrijd->judoka_blauw_id) {
            return response()->json([
                'success' => false,
                'message' => 'Winnaar is geen deelnemer van deze wedstrijd.',
            ], 400);
        }

        // Convert structured scores to flat values for storage
        $scoreWit = $this->flattenScore($validated['score_wit'] ?? []);
        $scoreBlauw = $this->flattenScore($validated['score_blauw'] ?? []);

        // Handle elimination vs pool match
        if ($wedstrijd->groep) {
            $oudeWinnaarId = $wedstrijd->winnaar_id;

            $wedstrijd->update([
                'winnaar_id' => $validated['winnaar_id'],
                'is_gespeeld' => true,
                'score_wit' => $scoreWit,
                'score_blauw' => $scoreBlauw,
                'uitslag_type' => $validated['uitslag_type'],
                'gespeeld_op' => now(),
            ]);

            $correcties = [];
            $toernooi = $wedstrijd->poule?->blok?->toernooi;
            if ($toernooi) {
                $eliminatieType = $toernooi->eliminatie_type ?? 'dubbel';
                $correcties = $this->eliminatieService->verwerkUitslag(
                    $wedstrijd, $validated['winnaar_id'], $oudeWinnaarId, $eliminatieType
                );
            }
        } else {
            // Regular pool match
            $this->wedstrijdService->registreerUitslag(
                $wedstrijd,
                $validated['winnaar_id'],
                (string) $scoreWit,
                (string) $scoreBlauw,
                $validated['uitslag_type']
            );
        }

        // Activity log
        $toernooi = $wedstrijd->poule?->blok?->toernooi ?? $wedstrijd->poule?->toernooi;
        if ($toernooi) {
            $winnaarNaam = Judoka::find($validated['winnaar_id'])?->naam;
            ActivityLogger::log($toernooi, 'registreer_uitslag', "Scorebord: {$winnaarNaam} wint ({$validated['uitslag_type']})", [
                'model' => $wedstrijd,
                'properties' => [
                    'winnaar_id' => $validated['winnaar_id'],
                    'uitslag_type' => $validated['uitslag_type'],
                    'golden_score' => $validated['golden_score'] ?? false,
                    'match_duration_actual' => $validated['match_duration_actual'] ?? null,
                ],
                'interface' => 'scoreboard',
            ]);
        }

        // Broadcast score update
        $wedstrijd->load('poule.blok');
        if ($wedstrijd->poule && $wedstrijd->poule->mat_id) {
            $toernooiId = $wedstrijd->poule->blok?->toernooi_id ?? $wedstrijd->poule->toernooi_id;
            MatUpdate::dispatch($toernooiId, $wedstrijd->poule->mat_id, 'score', [
                'wedstrijd_id' => $wedstrijd->id,
                'poule_id' => $wedstrijd->poule_id,
                'winnaar_id' => $validated['winnaar_id'],
                'score_wit' => $scoreWit,
                'score_blauw' => $scoreBlauw,
                'is_gespeeld' => true,
                'bron' => 'scoreboard',
            ]);
        }

        // Auto-advance green slot: clear active match after result
        $toegang = $request->get('device_toegang');
        $mat = Mat::where('toernooi_id', $toegang->toernooi_id)
            ->where('nummer', $toegang->mat_nummer)
            ->first();

        if ($mat && $mat->actieve_wedstrijd_id === $wedstrijd->id) {
            $mat->update([
                'actieve_wedstrijd_id' => $mat->volgende_wedstrijd_id,
                'volgende_wedstrijd_id' => $mat->gereedmaken_wedstrijd_id,
                'gereedmaken_wedstrijd_id' => null,
            ]);

            $mat->refresh();
            MatUpdate::dispatch(
                $wedstrijd->poule->blok?->toernooi_id ?? $wedstrijd->poule->toernooi_id,
                $mat->id,
                'beurt',
                [
                    'actieve_wedstrijd_id' => $mat->actieve_wedstrijd_id,
                    'volgende_wedstrijd_id' => $mat->volgende_wedstrijd_id,
                    'gereedmaken_wedstrijd_id' => $mat->gereedmaken_wedstrijd_id,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'updated_at' => $wedstrijd->fresh()->updated_at?->toISOString(),
            'correcties' => $correcties ?? [],
        ]);
    }

    /**
     * Relay live scoreboard state to display devices via Reverb.
     */
    public function state(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'timer' => 'required|array',
            'timer.remaining' => 'required|numeric',
            'timer.running' => 'required|boolean',
            'timer.golden_score' => 'required|boolean',
            'scores' => 'required|array',
            'scores.wit' => 'required|array',
            'scores.blauw' => 'required|array',
            'osaekomi' => 'required|array',
            'osaekomi.active' => 'required|boolean',
            'osaekomi.judoka' => 'nullable|string|in:wit,blauw',
            'osaekomi.time' => 'required|integer',
            'winner' => 'nullable|string|in:wit,blauw',
        ]);

        $toegang = $request->get('device_toegang');

        $mat = Mat::where('toernooi_id', $toegang->toernooi_id)
            ->where('nummer', $toegang->mat_nummer)
            ->first();

        if (!$mat) {
            return response()->json(['message' => 'Mat niet gevonden.'], 404);
        }

        ScoreboardState::dispatch($toegang->toernooi_id, $mat->id, $validated);

        return response()->json(['success' => true]);
    }

    /**
     * Heartbeat to keep connection alive and track device status.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        // laatst_actief is already updated by CheckScoreboardToken middleware
        return response()->json(['ok' => true]);
    }

    /**
     * Convert structured score array to flat integer for database storage.
     */
    private function flattenScore(array $score): int
    {
        if (($score['ippon'] ?? false)) {
            return 10; // Ippon
        }

        return ($score['wazaari'] ?? 0);
    }

    /**
     * Format a Wedstrijd model to the scoreboard match structure.
     */
    private function formatMatch(Wedstrijd $wedstrijd): array
    {
        return [
            'id' => $wedstrijd->id,
            'judoka_wit' => [
                'id' => $wedstrijd->judokaWit?->id,
                'naam' => $wedstrijd->judokaWit?->naam ?? 'WIT',
                'club' => $wedstrijd->judokaWit?->club?->naam ?? '',
            ],
            'judoka_blauw' => [
                'id' => $wedstrijd->judokaBlauw?->id,
                'naam' => $wedstrijd->judokaBlauw?->naam ?? 'BLAUW',
                'club' => $wedstrijd->judokaBlauw?->club?->naam ?? '',
            ],
            'poule_naam' => $wedstrijd->poule?->titel ?? "Poule {$wedstrijd->poule?->nummer}",
            'ronde' => $wedstrijd->ronde,
            'groep' => $wedstrijd->groep,
            'match_duration' => 240, // Default 4 minutes, can be configured per toernooi later
            'updated_at' => $wedstrijd->updated_at?->toISOString(),
        ];
    }
}
