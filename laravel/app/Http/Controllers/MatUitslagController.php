<?php

namespace App\Http\Controllers;

use App\Events\MatUpdate;
use App\Events\ScoreboardAssignment;
use App\Events\ScoreboardEvent;
use App\Http\Controllers\Concerns\HandlesWedstrijdConflict;
use App\Models\Judoka;
use App\Models\Mat;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Organisator;
use App\Models\Wedstrijd;
use App\Services\ActivityLogger;
use App\Services\EliminatieService;
use App\Services\WedstrijdSchemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles mat-side result registration:
 * - pool match results (registreerUitslag)
 * - medal placement finales (finaleUitslag)
 * - marking poules ready for the spreker (pouleKlaar)
 * - setting the current/next/prepare match on a mat (setHuidigeWedstrijd)
 *
 * Split out of MatController to keep each controller focused and under 800 lines.
 */
class MatUitslagController extends Controller
{
    use HandlesWedstrijdConflict;

    public function __construct(
        private WedstrijdSchemaService $wedstrijdService,
        private EliminatieService $eliminatieService,
    ) {}

    public function registreerUitslag(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        return $this->doRegistreerUitslag($request);
    }

    /**
     * Device-bound version - toernooi comes from device_toegang
     */
    public function registreerUitslagDevice(Request $request): JsonResponse
    {
        return $this->doRegistreerUitslag($request);
    }

    private function doRegistreerUitslag(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'wedstrijd_id' => 'required|exists:wedstrijden,id',
            'winnaar_id' => 'nullable|exists:judokas,id',
            'score_wit' => 'nullable|integer|min:0|max:99',
            'score_blauw' => 'nullable|integer|min:0|max:99',
            'uitslag_type' => 'nullable|string|max:20',
            'updated_at' => 'nullable|string',
        ]);

        $wedstrijd = Wedstrijd::findOrFail($validated['wedstrijd_id']);

        // Optimistic locking: check if wedstrijd was modified by another device
        if ($conflict = $this->checkConflict($wedstrijd, $validated['updated_at'] ?? null)) {
            return $conflict;
        }

        // Check if this is an elimination match (has groep field)
        if ($wedstrijd->groep) {
            // Validatie: winnaar moet een deelnemer zijn van deze wedstrijd
            if ($validated['winnaar_id'] &&
                $validated['winnaar_id'] != $wedstrijd->judoka_wit_id &&
                $validated['winnaar_id'] != $wedstrijd->judoka_blauw_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Winnaar is geen deelnemer van deze wedstrijd!',
                ], 400);
            }

            // Bewaar oude winnaar VOOR update (voor correctie-logica)
            $oudeWinnaarId = $wedstrijd->winnaar_id;

            $wedstrijd->update([
                'winnaar_id' => $validated['winnaar_id'],
                'is_gespeeld' => (bool) $validated['winnaar_id'],
                'uitslag_type' => $validated['uitslag_type'] ?? 'eliminatie',
                'gespeeld_op' => $validated['winnaar_id'] ? now() : null,
            ]);

            // Auto-advance: winnaar naar volgende ronde, verliezer naar B-poule
            $correcties = [];
            if ($validated['winnaar_id']) {
                // Get toernooi via wedstrijd->poule relationship (with null safety)
                $toernooi = $wedstrijd->poule?->blok?->toernooi;
                if (!$toernooi) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Wedstrijd heeft geen gekoppeld toernooi',
                    ], 400);
                }
                $eliminatieType = $toernooi->eliminatie_type ?? 'dubbel';
                $correcties = $this->eliminatieService->verwerkUitslag($wedstrijd, $validated['winnaar_id'], $oudeWinnaarId, $eliminatieType);
            }

            $winnaarNaam = $validated['winnaar_id'] ? Judoka::find($validated['winnaar_id'])?->naam : null;
            $pouleNr = $wedstrijd->poule?->nummer;
            $rondeLabel = str_replace('_', ' ', $wedstrijd->ronde ?? '');
            ActivityLogger::log($toernooi, 'registreer_uitslag', "Eliminatie poule {$pouleNr}: {$rondeLabel}" . ($winnaarNaam ? " — winnaar {$winnaarNaam}" : ' gereset'), [
                'model' => $wedstrijd,
                'properties' => [
                    'winnaar_id' => $validated['winnaar_id'],
                    'groep' => $wedstrijd->groep,
                    'ronde' => $wedstrijd->ronde,
                    'blok' => $wedstrijd->poule?->blok?->nummer,
                    'mat' => $wedstrijd->poule?->mat?->nummer,
                ],
                'interface' => 'mat',
            ]);

            // Broadcast elimination score update to all listeners
            $wedstrijd->load('poule.blok');
            if ($wedstrijd->poule && $wedstrijd->poule->mat_id) {
                $toernooiId = $wedstrijd->poule->blok?->toernooi_id ?? $wedstrijd->poule->toernooi_id;
                MatUpdate::dispatch($toernooiId, $wedstrijd->poule->mat_id, 'score', [
                    'wedstrijd_id' => $wedstrijd->id,
                    'poule_id' => $wedstrijd->poule_id,
                    'winnaar_id' => $validated['winnaar_id'],
                    'is_gespeeld' => (bool) $validated['winnaar_id'],
                    'type' => 'eliminatie',
                ]);
            }

            return response()->json([
                'success' => true,
                'correcties' => $correcties,
                'updated_at' => $wedstrijd->fresh()->updated_at?->toISOString(),
            ]);
        } else {
            // Regular pool match
            $this->wedstrijdService->registreerUitslag(
                $wedstrijd,
                $validated['winnaar_id'],
                (string) ($validated['score_wit'] ?? ''),
                (string) ($validated['score_blauw'] ?? ''),
                $validated['uitslag_type'] ?? 'beslissing'
            );
        }

        $uitslagToernooi = $wedstrijd->poule?->blok?->toernooi ?? $wedstrijd->poule?->toernooi;
        if ($uitslagToernooi) {
            $winnaarNaam = $validated['winnaar_id'] ? Judoka::find($validated['winnaar_id'])?->naam : null;
            $pouleNr = $wedstrijd->poule?->nummer;
            $witNaam = $wedstrijd->judokaWit?->naam ?? '?';
            $blauwNaam = $wedstrijd->judokaBlauw?->naam ?? '?';
            ActivityLogger::log($uitslagToernooi, 'registreer_uitslag', "Poule {$pouleNr}: {$witNaam} vs {$blauwNaam}" . ($winnaarNaam ? " — winnaar {$winnaarNaam}" : ' gereset'), [
                'model' => $wedstrijd,
                'properties' => [
                    'winnaar_id' => $validated['winnaar_id'],
                    'score_wit' => $validated['score_wit'] ?? null,
                    'score_blauw' => $validated['score_blauw'] ?? null,
                    'blok' => $wedstrijd->poule?->blok?->nummer,
                    'mat' => $wedstrijd->poule?->mat?->nummer,
                ],
                'interface' => 'mat',
            ]);
        }

        // Broadcast score update to all listeners (jurytafel, publiek, spreker)
        $wedstrijd->load('poule.blok');
        if ($wedstrijd->poule && $wedstrijd->poule->mat_id) {
            $toernooiId = $wedstrijd->poule->blok?->toernooi_id ?? $wedstrijd->poule->toernooi_id;
            MatUpdate::dispatch($toernooiId, $wedstrijd->poule->mat_id, 'score', [
                'wedstrijd_id' => $wedstrijd->id,
                'poule_id' => $wedstrijd->poule_id,
                'winnaar_id' => $validated['winnaar_id'],
                'score_wit' => $validated['score_wit'] ?? null,
                'score_blauw' => $validated['score_blauw'] ?? null,
                'is_gespeeld' => $wedstrijd->fresh()->is_gespeeld,
            ]);
        }

        return response()->json([
            'success' => true,
            'updated_at' => $wedstrijd->fresh()->updated_at?->toISOString(),
        ]);
    }

    /**
     * Register finale/brons result via medal placement (drag to gold/silver/bronze)
     */
    public function finaleUitslagDevice(Request $request): JsonResponse
    {
        return $this->doFinaleUitslag($request);
    }

    public function finaleUitslag(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        return $this->doFinaleUitslag($request);
    }

    private function doFinaleUitslag(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'wedstrijd_id' => 'required|exists:wedstrijden,id',
            'geplaatste_judoka_id' => 'required|exists:judokas,id',
            'medaille' => 'required|in:goud,zilver,brons',
            'updated_at' => 'nullable|string',
        ]);

        $wedstrijd = Wedstrijd::findOrFail($validated['wedstrijd_id']);

        // Optimistic locking: check if wedstrijd was modified by another device
        if ($conflict = $this->checkConflict($wedstrijd, $validated['updated_at'] ?? null)) {
            return $conflict;
        }

        // Check of dit een finale of brons wedstrijd is
        $isMedailleWedstrijd = $wedstrijd->ronde === 'finale' ||
                               str_starts_with($wedstrijd->ronde ?? '', 'b_brons') ||
                               $wedstrijd->ronde === 'b_halve_finale_2';

        if (!$isMedailleWedstrijd) {
            return response()->json([
                'success' => false,
                'error' => 'Dit is geen finale of brons wedstrijd!',
            ], 400);
        }

        // Check of judoka in de wedstrijd zit
        $geplaatsteId = $validated['geplaatste_judoka_id'];
        if ($wedstrijd->judoka_wit_id != $geplaatsteId && $wedstrijd->judoka_blauw_id != $geplaatsteId) {
            return response()->json([
                'success' => false,
                'error' => 'Deze judoka zit niet in deze wedstrijd!',
            ], 400);
        }

        // Bepaal winnaar op basis van medaille
        // Goud/Brons = geplaatste judoka wint
        // Zilver = andere judoka wint (want die krijgt goud)
        if ($validated['medaille'] === 'goud' || $validated['medaille'] === 'brons') {
            $winnaarId = $geplaatsteId;
        } else {
            // Zilver: de ANDERE judoka wint
            $winnaarId = ($wedstrijd->judoka_wit_id == $geplaatsteId)
                ? $wedstrijd->judoka_blauw_id
                : $wedstrijd->judoka_wit_id;
        }

        // Update wedstrijd met winnaar
        $uitslagType = $validated['medaille'] === 'brons' ? 'brons' : 'finale';
        $wedstrijd->update([
            'winnaar_id' => $winnaarId,
            'is_gespeeld' => true,
            'uitslag_type' => $uitslagType,
            'gespeeld_op' => now(),
        ]);

        return response()->json([
            'success' => true,
            'winnaar_id' => $winnaarId,
            'updated_at' => $wedstrijd->fresh()->updated_at?->toISOString(),
        ]);
    }

    /**
     * Mark poule as ready for spreker (results announcement)
     *
     * For barrage poules: sends the ORIGINAL poule to spreker (with all judokas),
     * not the barrage itself. Barrage results determine final standings of tied judokas.
     */
    public function pouleKlaar(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        return $this->doPouleKlaar($request, $toernooi->id);
    }

    public function pouleKlaarDevice(Request $request): JsonResponse
    {
        $toegang = $request->get('device_toegang');
        return $this->doPouleKlaar($request, $toegang->toernooi_id);
    }

    private function doPouleKlaar(Request $request, int $toernooiId): JsonResponse
    {
        $validated = $request->validate([
            'poule_id' => 'required|exists:poules,id',
        ]);

        $poule = Poule::findOrFail($validated['poule_id']);

        // Verify poule belongs to this toernooi
        if ($poule->toernooi_id !== $toernooiId) {
            return response()->json(['success' => false, 'error' => 'Poule hoort niet bij dit toernooi'], 403);
        }

        // BARRAGE: Send original poule to spreker, not the barrage itself
        if ($poule->isBarrage()) {
            $originelePoule = $poule->originelePoule;

            if (!$originelePoule) {
                return response()->json(['success' => false, 'error' => 'Originele poule niet gevonden'], 404);
            }

            // Mark barrage as completed
            $poule->update(['spreker_klaar' => now()]);

            // Send ORIGINAL poule to spreker (includes all judokas)
            $originelePoule->update(['spreker_klaar' => now()]);

            // Broadcast poule klaar to spreker
            if ($originelePoule->mat_id) {
                MatUpdate::dispatch($toernooiId, $originelePoule->mat_id, 'poule_klaar', [
                    'poule_id' => $originelePoule->id,
                    'poule_nummer' => $originelePoule->nummer,
                    'barrage' => true,
                ]);
            }

            return response()->json([
                'success' => true,
                'barrage' => true,
                'message' => 'Barrage afgerond, originele poule naar spreker gestuurd',
                'originele_poule_id' => $originelePoule->id,
            ]);
        }

        // Eliminatie with split mats: check if ALL matches (A+B) are played
        if ($poule->type === 'eliminatie' && $poule->b_mat_id && $poule->b_mat_id != $poule->mat_id) {
            $alleWedstrijden = $poule->wedstrijden()->get();
            $ongespeeld = $alleWedstrijden->filter(fn($w) => !$w->is_gespeeld && ($w->judoka_wit_id || $w->judoka_blauw_id));
            if ($ongespeeld->isNotEmpty()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Niet alle wedstrijden (A+B) zijn gespeeld. Wacht tot beide groepen klaar zijn.',
                    'ongespeeld' => $ongespeeld->count(),
                ]);
            }
        }

        $poule->update(['spreker_klaar' => now()]);

        $klaarToernooi = Toernooi::find($toernooiId);
        if ($klaarToernooi) {
            ActivityLogger::log($klaarToernooi, 'poule_klaar', "Poule {$poule->nummer} klaar voor spreker", [
                'model' => $poule,
                'properties' => [
                    'blok' => $poule->blok?->nummer,
                    'mat' => $poule->mat?->nummer,
                ],
                'interface' => 'mat',
            ]);
        }

        // Wimpel: schrijf punten bij voor puntencompetitie poules
        $wimpelResult = [];
        try {
            $wimpelResult = app(\App\Services\WimpelService::class)->verwerkPoule($poule);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Wimpel poule scoring failed', [
                'poule_id' => $poule->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Broadcast poule klaar to spreker (both mats if B-groep on separate mat)
        if ($poule->mat_id) {
            MatUpdate::dispatch($toernooiId, $poule->mat_id, 'poule_klaar', [
                'poule_id' => $poule->id,
                'poule_nummer' => $poule->nummer,
            ]);
        }
        if ($poule->b_mat_id && $poule->b_mat_id != $poule->mat_id) {
            MatUpdate::dispatch($toernooiId, $poule->b_mat_id, 'poule_klaar', [
                'poule_id' => $poule->id,
                'poule_nummer' => $poule->nummer,
            ]);
        }

        $response = ['success' => true];
        if (!empty($wimpelResult['nieuwe_judokas'])) {
            $response['wimpel_nieuw'] = $wimpelResult['nieuwe_judokas'];
        }
        if (!empty($wimpelResult['milestones'])) {
            $response['wimpel_milestones'] = $wimpelResult['milestones'];
        }
        return response()->json($response);
    }

    /**
     * Set current/next/prepare match on MAT level
     * - actieve_wedstrijd_id = green (currently playing)
     * - volgende_wedstrijd_id = yellow (standing ready)
     * - gereedmaken_wedstrijd_id = blue (preparing)
     *
     * Only 1 green, 1 yellow, 1 blue per mat, regardless of number of poules
     */
    public function setHuidigeWedstrijd(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        return $this->doSetHuidigeWedstrijd($request, $toernooi->id);
    }

    /**
     * Device-bound version - no toernooi check needed, mat validates itself
     */
    public function setHuidigeWedstrijdDevice(Request $request): JsonResponse
    {
        $toegang = $request->get('device_toegang');
        return $this->doSetHuidigeWedstrijd($request, $toegang->toernooi_id);
    }

    private function doSetHuidigeWedstrijd(Request $request, int $toernooiId): JsonResponse
    {
        $validated = $request->validate([
            'mat_id' => 'required|exists:matten,id',
            'actieve_wedstrijd_id' => 'nullable|exists:wedstrijden,id',
            'volgende_wedstrijd_id' => 'nullable|exists:wedstrijden,id',
            'gereedmaken_wedstrijd_id' => 'nullable|exists:wedstrijden,id',
        ]);

        $mat = Mat::findOrFail($validated['mat_id']);

        // Verify mat belongs to this toernooi
        if ($mat->toernooi_id !== $toernooiId) {
            return response()->json(['success' => false, 'error' => 'Mat hoort niet bij dit toernooi'], 403);
        }

        // Verify wedstrijden belong to poules on this mat (if provided)
        // Also check if wedstrijd is already played (with winner) - cannot select those
        // Exception: wedstrijden die al in de huidige selectie staan worden niet opnieuw gecheckt
        // (bijv. actieve wedstrijd krijgt winnaar → gebruiker wil alleen blauw wijzigen)
        $currentSelection = [
            'actieve_wedstrijd_id' => $mat->actieve_wedstrijd_id,
            'volgende_wedstrijd_id' => $mat->volgende_wedstrijd_id,
            'gereedmaken_wedstrijd_id' => $mat->gereedmaken_wedstrijd_id,
        ];
        foreach (['actieve_wedstrijd_id', 'volgende_wedstrijd_id', 'gereedmaken_wedstrijd_id'] as $field) {
            if (!empty($validated[$field])) {
                $wedstrijd = Wedstrijd::with('poule')->findOrFail($validated[$field]);
                if ($wedstrijd->poule->mat_id !== $mat->id) {
                    $label = match ($field) {
                        'actieve_wedstrijd_id' => 'Actieve',
                        'volgende_wedstrijd_id' => 'Volgende',
                        'gereedmaken_wedstrijd_id' => 'Gereedmaken',
                    };
                    return response()->json(['success' => false, 'error' => "{$label} wedstrijd hoort niet bij deze mat"], 403);
                }

                // Double check: wedstrijd met winnaar kan niet NIEUW geselecteerd worden
                // Skip check als wedstrijd al in de huidige selectie stond
                $alInSelectie = in_array($validated[$field], array_values($currentSelection));
                if (!$alInSelectie && $wedstrijd->isEchtGespeeld()) {
                    $label = match ($field) {
                        'actieve_wedstrijd_id' => 'Actieve',
                        'volgende_wedstrijd_id' => 'Volgende',
                        'gereedmaken_wedstrijd_id' => 'Gereedmaken',
                    };
                    return response()->json(['success' => false, 'error' => "{$label} wedstrijd is al gespeeld (heeft winnaar)"], 400);
                }
            }
        }

        // Double check: geen dubbele wedstrijden in de selectie
        $selectedIds = array_filter([
            $validated['actieve_wedstrijd_id'] ?? null,
            $validated['volgende_wedstrijd_id'] ?? null,
            $validated['gereedmaken_wedstrijd_id'] ?? null,
        ]);
        if (count($selectedIds) !== count(array_unique($selectedIds))) {
            return response()->json(['success' => false, 'error' => 'Dezelfde wedstrijd kan niet in meerdere slots'], 400);
        }

        $mat->update([
            'actieve_wedstrijd_id' => $validated['actieve_wedstrijd_id'] ?? null,
            'volgende_wedstrijd_id' => $validated['volgende_wedstrijd_id'] ?? null,
            'gereedmaken_wedstrijd_id' => $validated['gereedmaken_wedstrijd_id'] ?? null,
        ]);

        // Refresh to get the actual saved values
        $mat->refresh();

        // Broadcast beurt update to all listeners (jurytafel, publiek, spreker)
        MatUpdate::dispatch($toernooiId, $mat->id, 'beurt', [
            'actieve_wedstrijd_id' => $mat->actieve_wedstrijd_id,
            'volgende_wedstrijd_id' => $mat->volgende_wedstrijd_id,
            'gereedmaken_wedstrijd_id' => $mat->gereedmaken_wedstrijd_id,
        ]);

        // Notify scoreboard app + LCD display when active match changes
        if ($mat->actieve_wedstrijd_id) {
            $actieveWedstrijd = Wedstrijd::with(['judokaWit.club', 'judokaBlauw.club', 'poule'])
                ->find($mat->actieve_wedstrijd_id);

            if ($actieveWedstrijd) {
                $matchData = [
                    'id' => $actieveWedstrijd->id,
                    'judoka_wit' => [
                        'id' => $actieveWedstrijd->judokaWit?->id,
                        'naam' => $actieveWedstrijd->judokaWit?->naam ?? 'WIT',
                        'club' => $actieveWedstrijd->judokaWit?->club?->naam ?? '',
                    ],
                    'judoka_blauw' => [
                        'id' => $actieveWedstrijd->judokaBlauw?->id,
                        'naam' => $actieveWedstrijd->judokaBlauw?->naam ?? 'BLAUW',
                        'club' => $actieveWedstrijd->judokaBlauw?->club?->naam ?? '',
                    ],
                    'poule_naam' => $actieveWedstrijd->poule?->titel ?? "Poule {$actieveWedstrijd->poule?->nummer}",
                    'ronde' => $actieveWedstrijd->ronde,
                    'groep' => $actieveWedstrijd->groep,
                    'match_duration' => $actieveWedstrijd->poule?->toernooi?->getMatchDurationForCategorie($actieveWedstrijd->poule?->categorie_key) ?? 180,
                    ...($actieveWedstrijd->poule?->toernooi?->getMatchRulesForCategorie($actieveWedstrijd->poule?->categorie_key) ?? []),
                    'updated_at' => $actieveWedstrijd->updated_at?->toISOString(),
                ];

                // Notify scoreboard app
                ScoreboardAssignment::dispatch($toernooiId, $mat->id, $matchData);

                // Notify LCD display directly (no app relay needed)
                ScoreboardEvent::dispatch($toernooiId, $mat->id, [
                    'event' => 'match.assign',
                    ...$matchData,
                ]);
            }
        } else {
            // Active match cleared — notify app and LCD to reset
            ScoreboardAssignment::dispatch($toernooiId, $mat->id, []);
            ScoreboardEvent::dispatch($toernooiId, $mat->id, [
                'event' => 'match.unassign',
            ]);
        }

        return response()->json([
            'success' => true,
            'mat' => [
                'id' => $mat->id,
                'actieve_wedstrijd_id' => $mat->actieve_wedstrijd_id,
                'volgende_wedstrijd_id' => $mat->volgende_wedstrijd_id,
                'gereedmaken_wedstrijd_id' => $mat->gereedmaken_wedstrijd_id,
            ],
        ]);
    }
}
