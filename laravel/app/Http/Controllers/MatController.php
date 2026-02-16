<?php

namespace App\Http\Controllers;

use App\Events\MatUpdate;
use App\Http\Requests\WedstrijdUitslagRequest;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Blok;
use App\Models\Mat;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use App\Services\ActivityLogger;
use App\Services\BracketLayoutService;
use App\Services\EliminatieService;
use App\Services\WedstrijdSchemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class MatController extends Controller
{
    public function __construct(
        private WedstrijdSchemaService $wedstrijdService,
        private EliminatieService $eliminatieService,
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

                // Double check: wedstrijd met winnaar kan niet geselecteerd worden
                if ($wedstrijd->isEchtGespeeld()) {
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

    /**
     * Place a judoka in an elimination bracket slot (manual drag & drop)
     * Als bron_wedstrijd_id is meegegeven, registreer ook de uitslag
     * Bij correctie worden foute plaatsingen automatisch opgeruimd
     */
    public function plaatsJudokaDevice(Request $request): JsonResponse
    {
        // Device-bound: derive toernooi from wedstrijd
        $wedstrijd = Wedstrijd::findOrFail($request->input('wedstrijd_id'));
        $toernooi = $wedstrijd->poule?->blok?->toernooi ?? $wedstrijd->poule?->toernooi;
        return $this->doPlaatsJudoka($request, $toernooi);
    }

    public function plaatsJudoka(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        return $this->doPlaatsJudoka($request, $toernooi);
    }

    private function doPlaatsJudoka(Request $request, ?Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'wedstrijd_id' => 'required|exists:wedstrijden,id',
            'judoka_id' => 'required|exists:judokas,id',
            'positie' => 'required|in:wit,blauw',
            'bron_wedstrijd_id' => 'nullable|exists:wedstrijden,id',
            'is_correctie' => 'nullable|boolean',
        ]);

        $wedstrijd = Wedstrijd::findOrFail($validated['wedstrijd_id']);
        $correcties = [];
        $isCorrectie = $validated['is_correctie'] ?? false;

        // Check of bracket locked is (minimaal 1 wedstrijd gespeeld in deze poule)
        $isLocked = Wedstrijd::where('poule_id', $wedstrijd->poule_id)
            ->where('is_gespeeld', true)
            ->exists();

        $judokaId = $validated['judoka_id'];

        // STRENGE validatie als bracket locked is
        if ($isLocked) {
            // Zoek ALLE wedstrijden waar deze judoka in zit
            $judokaWedstrijden = Wedstrijd::where('poule_id', $wedstrijd->poule_id)
                ->where(function ($q) use ($judokaId) {
                    $q->where('judoka_wit_id', $judokaId)
                      ->orWhere('judoka_blauw_id', $judokaId);
                })
                ->get();

            foreach ($judokaWedstrijden as $bronWedstrijd) {
                // Skip als dit dezelfde wedstrijd is waar we naar toe slepen
                if ($bronWedstrijd->id == $wedstrijd->id) {
                    continue;
                }

                // Skip wedstrijden uit ANDERE groep (A vs B)
                // Bij B→B doorschuiven moeten we A-groep verlies negeren
                // Bij A→A doorschuiven moeten we B-groep negeren
                if ($bronWedstrijd->groep !== $wedstrijd->groep) {
                    continue;
                }

                // Skip B-groep wedstrijden bij correctie naar A-groep
                // Bij correctie willen we de A-groep bron gebruiken, niet de B-groep
                if ($isCorrectie && $bronWedstrijd->groep === 'B' && $wedstrijd->groep === 'A') {
                    continue;
                }

                // Skip wedstrijden waar deze judoka AL gewonnen heeft
                // Die zijn "afgerond" - we willen alleen de huidige ronde checken
                if ($bronWedstrijd->is_gespeeld && $bronWedstrijd->winnaar_id == $judokaId) {
                    continue;
                }

                // Skip niet-gespeelde wedstrijden als judoka AL in een latere ronde zit
                // Bijv: judoka zit in 1/8(1) en 1/8(2), 1/8(1) niet gespeeld maar 1/8(2) wel
                // We willen dan alleen de 1/8(2) wedstrijd checken, niet de 1/8(1)
                if (!$bronWedstrijd->is_gespeeld && $bronWedstrijd->volgende_wedstrijd_id) {
                    // Check of judoka al in de volgende ronde zit
                    $volgendeWed = Wedstrijd::find($bronWedstrijd->volgende_wedstrijd_id);
                    if ($volgendeWed && ($volgendeWed->judoka_wit_id == $judokaId || $volgendeWed->judoka_blauw_id == $judokaId)) {
                        continue; // Skip - judoka is al doorgeschoven
                    }
                }

                // Check: Heeft deze bron-wedstrijd een volgende_wedstrijd_id?
                if ($bronWedstrijd->volgende_wedstrijd_id) {
                    // Als wedstrijd AL gespeeld is en dit is NIET de winnaar:
                    // - Bij correctie: toegestaan (winnaar wordt gewijzigd)
                    // - Zonder correctie-flag: blokkeer
                    if ($bronWedstrijd->is_gespeeld && $bronWedstrijd->winnaar_id != $judokaId && !$isCorrectie) {
                        return response()->json([
                            'success' => false,
                            'error' => 'Dit is niet de winnaar! Alleen de winnaar mag naar de volgende ronde.',
                        ], 400);
                    }

                    // Mag ALLEEN naar die specifieke wedstrijd (skip check bij correctie)
                    if (!$isCorrectie && $bronWedstrijd->volgende_wedstrijd_id != $wedstrijd->id) {
                        return response()->json([
                            'success' => false,
                            'error' => 'Dit is niet het juiste vak! Deze judoka moet naar een ander vak in het schema.',
                        ], 400);
                    }

                    // Mag ALLEEN in het juiste slot (wit/blauw) - skip bij correctie
                    if (!$isCorrectie && $bronWedstrijd->winnaar_naar_slot && $bronWedstrijd->winnaar_naar_slot != $validated['positie']) {
                        return response()->json([
                            'success' => false,
                            'error' => 'Verkeerde positie! Plaats op ' . strtoupper($bronWedstrijd->winnaar_naar_slot) . '.',
                        ], 400);
                    }
                }
            }
        }

        // Extra check met bron_wedstrijd_id als die is meegegeven
        if ($isLocked && !empty($validated['bron_wedstrijd_id'])) {
            $bronWedstrijd = Wedstrijd::find($validated['bron_wedstrijd_id']);

            if ($bronWedstrijd && $bronWedstrijd->volgende_wedstrijd_id) {
                // Check: Zit de judoka wel in de bron wedstrijd?
                $judokaInBron = $bronWedstrijd->judoka_wit_id == $judokaId ||
                                $bronWedstrijd->judoka_blauw_id == $judokaId;

                if (!$judokaInBron) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Deze judoka zat niet in de geselecteerde wedstrijd!',
                    ], 400);
                }

                // Als wedstrijd AL gespeeld is en dit is NIET de winnaar:
                // - Bij correctie: toegestaan (winnaar wordt gewijzigd)
                // - Zonder correctie-flag: blokkeer
                if ($bronWedstrijd->is_gespeeld && $bronWedstrijd->winnaar_id != $judokaId && !$isCorrectie) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Dit is niet de winnaar! Alleen de winnaar mag naar de volgende ronde.',
                    ], 400);
                }

                // Check: Is dit de correcte volgende wedstrijd?
                // Bij correctie: skip deze check - we corrigeren de winnaar
                if (!$isCorrectie && $bronWedstrijd->volgende_wedstrijd_id != $wedstrijd->id) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Dit is niet het juiste vak! Plaats de winnaar alleen in het correcte volgende vak.',
                    ], 400);
                }

                // Check: Is dit de correcte positie (wit/blauw)?
                // Bij correctie: skip deze check - nieuwe winnaar gaat naar plek van oude winnaar
                if (!$isCorrectie && $bronWedstrijd->winnaar_naar_slot && $bronWedstrijd->winnaar_naar_slot != $validated['positie']) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Verkeerde positie! Plaats op ' . strtoupper($bronWedstrijd->winnaar_naar_slot) . '.',
                    ], 400);
                }
            }
        }

        // Update the appropriate slot
        if ($validated['positie'] === 'wit') {
            $wedstrijd->update(['judoka_wit_id' => $validated['judoka_id']]);
        } else {
            $wedstrijd->update(['judoka_blauw_id' => $validated['judoka_id']]);
        }

        // Als dit een doorschuif is vanuit een vorige wedstrijd, registreer de uitslag
        if (!empty($validated['bron_wedstrijd_id']) || $isCorrectie) {
            $bronWedstrijd = null;

            // Bij correctie: zoek de wedstrijd die naar deze wedstrijd wijst
            if ($isCorrectie) {
                // Zoek in dezelfde groep als de doel-wedstrijd
                $bronWedstrijd = Wedstrijd::where('poule_id', $wedstrijd->poule_id)
                    ->where('groep', $wedstrijd->groep)
                    ->where('volgende_wedstrijd_id', $wedstrijd->id)
                    ->where(function ($q) use ($judokaId) {
                        $q->where('judoka_wit_id', $judokaId)
                          ->orWhere('judoka_blauw_id', $judokaId);
                    })
                    ->first();
            }

            // Fallback naar meegestuurde bron_wedstrijd_id
            if (!$bronWedstrijd && !empty($validated['bron_wedstrijd_id'])) {
                $bronWedstrijd = Wedstrijd::find($validated['bron_wedstrijd_id']);
            }

            // Check of bronwedstrijd beide deelnemers heeft (= echte wedstrijd, geen seeding)
            $heeftBeideJudokas = $bronWedstrijd &&
                                 $bronWedstrijd->judoka_wit_id &&
                                 $bronWedstrijd->judoka_blauw_id;

            // Bij correctie: skip volgende_wedstrijd check (winnaar kan naar andere plek gaan)
            $volgendeWedstrijdKlopt = $isCorrectie || ($bronWedstrijd && $bronWedstrijd->volgende_wedstrijd_id == $wedstrijd->id);

            if ($heeftBeideJudokas && $volgendeWedstrijdKlopt) {
                $winnaarId = $validated['judoka_id'];

                // Block correction if the next round match is already played
                if ($isCorrectie && $bronWedstrijd->volgende_wedstrijd_id) {
                    $volgendeWedstrijd = Wedstrijd::find($bronWedstrijd->volgende_wedstrijd_id);
                    if ($volgendeWedstrijd && $volgendeWedstrijd->is_gespeeld) {
                        return response()->json([
                            'success' => false,
                            'error' => 'De volgende ronde is al gespeeld. De winnaar kan niet meer gewijzigd worden.',
                        ], 400);
                    }
                }

                // Bewaar oude winnaar VOOR update (voor correctie-logica)
                $oudeWinnaarId = $bronWedstrijd->winnaar_id;

                // Markeer de bron wedstrijd als gespeeld
                $bronWedstrijd->update([
                    'winnaar_id' => $winnaarId,
                    'is_gespeeld' => true,
                    'gespeeld_op' => now(),
                ]);

                // Gebruik EliminatieService voor correcte afhandeling (incl. correcties)
                // Dit plaatst ook de verliezer in de B-groep
                $eliminatieType = $toernooi->eliminatie_type ?? 'dubbel';
                try {
                    $correcties = $this->eliminatieService->verwerkUitslag($bronWedstrijd, $winnaarId, $oudeWinnaarId, $eliminatieType);
                } catch (\Throwable $e) {
                    report($e);
                    return response()->json([
                        'success' => false,
                        'error' => 'Fout bij verwerken eliminatie uitslag: ' . $e->getMessage(),
                    ], 500);
                }
            }
        }

        $judokaNaam = Judoka::find($validated['judoka_id'])?->naam ?? "#{$validated['judoka_id']}";
        $pouleNr = $wedstrijd->poule?->nummer;
        ActivityLogger::log($toernooi, 'plaats_judoka', "{$judokaNaam} geplaatst op {$validated['positie']} in poule {$pouleNr}", [
            'model' => $wedstrijd,
            'properties' => [
                'judoka_id' => $validated['judoka_id'],
                'positie' => $validated['positie'],
                'is_correctie' => $isCorrectie,
                'blok' => $wedstrijd->poule?->blok?->nummer,
                'mat' => $wedstrijd->poule?->mat?->nummer,
            ],
            'interface' => 'mat',
        ]);

        // Verzamel alle gewijzigde slots voor client-side DOM updates
        $alleWedstrijden = Wedstrijd::where('poule_id', $wedstrijd->poule_id)
            ->with(['judokaWit:id,naam', 'judokaBlauw:id,naam'])
            ->get();

        $updatedSlots = [];
        foreach ($alleWedstrijden as $w) {
            $isBye = $w->uitslag_type === 'bye';
            $updatedSlots[] = [
                'wedstrijd_id' => $w->id,
                'positie' => 'wit',
                'judoka' => $w->judokaWit ? ['id' => $w->judokaWit->id, 'naam' => $w->judokaWit->naam] : null,
                'is_winnaar' => (bool) ($w->is_gespeeld && $w->winnaar_id == $w->judoka_wit_id && !$isBye && $w->judokaWit),
                'is_gespeeld' => (bool) $w->is_gespeeld,
                'groep' => $w->groep,
                'volgende_wedstrijd_id' => $w->volgende_wedstrijd_id,
                'winnaar_naar_slot' => $w->winnaar_naar_slot,
                'poule_is_locked' => $isLocked,
                'updated_at' => $w->updated_at?->toISOString(),
            ];
            $updatedSlots[] = [
                'wedstrijd_id' => $w->id,
                'positie' => 'blauw',
                'judoka' => $w->judokaBlauw ? ['id' => $w->judokaBlauw->id, 'naam' => $w->judokaBlauw->naam] : null,
                'is_winnaar' => (bool) ($w->is_gespeeld && $w->winnaar_id == $w->judoka_blauw_id && !$isBye && $w->judokaBlauw),
                'is_gespeeld' => (bool) $w->is_gespeeld,
                'groep' => $w->groep,
                'volgende_wedstrijd_id' => $w->volgende_wedstrijd_id,
                'winnaar_naar_slot' => $w->winnaar_naar_slot,
                'poule_is_locked' => $isLocked,
                'updated_at' => $w->updated_at?->toISOString(),
            ];
        }

        // Broadcast bracket update
        $wedstrijd->load('poule.blok');
        if ($wedstrijd->poule && $wedstrijd->poule->mat_id) {
            MatUpdate::dispatch($toernooi->id, $wedstrijd->poule->mat_id, 'bracket', [
                'poule_id' => $wedstrijd->poule_id,
                'wedstrijd_id' => $wedstrijd->id,
                'actie' => 'plaats_judoka',
            ]);
        }

        return response()->json([
            'success' => true,
            'correcties' => $correcties,
            'updated_slots' => $updatedSlots,
        ]);
    }

    /**
     * Advance all byes in the first A-round to the next round.
     */
    public function advanceByes(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        return $this->doAdvanceByes($request, $toernooi);
    }

    public function advanceByesDevice(Request $request): JsonResponse
    {
        $poule = Poule::findOrFail($request->input('poule_id'));
        $toernooi = $poule->blok?->toernooi ?? $poule->mat?->blok?->toernooi;
        return $this->doAdvanceByes($request, $toernooi);
    }

    private function doAdvanceByes(Request $request, ?Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'poule_id' => 'required|exists:poules,id',
        ]);

        $pouleId = $validated['poule_id'];

        // Find all bye matches (A + B): wit filled, blauw null, not yet played
        $byes = Wedstrijd::where('poule_id', $pouleId)
            ->whereNotNull('judoka_wit_id')
            ->whereNull('judoka_blauw_id')
            ->where('is_gespeeld', false)
            ->get();

        $advanced = 0;

        foreach ($byes as $bye) {
            $winnaarId = $bye->judoka_wit_id;

            // Mark as bye
            $bye->update([
                'winnaar_id' => $winnaarId,
                'is_gespeeld' => true,
                'uitslag_type' => 'bye',
                'gespeeld_op' => now(),
            ]);

            // Advance to next round
            if ($bye->volgende_wedstrijd_id) {
                $volgende = Wedstrijd::find($bye->volgende_wedstrijd_id);
                if ($volgende) {
                    $slot = $bye->winnaar_naar_slot ?? 'wit';
                    $veld = ($slot === 'wit') ? 'judoka_wit_id' : 'judoka_blauw_id';
                    $volgende->update([$veld => $winnaarId]);
                }
            }

            $advanced++;
        }

        // Broadcast bracket update
        $poule = Poule::find($pouleId);
        if ($toernooi && $poule && $poule->mat_id) {
            MatUpdate::dispatch($toernooi->id, $poule->mat_id, 'bracket', [
                'poule_id' => $pouleId,
                'actie' => 'advance_byes',
            ]);
        }

        return response()->json([
            'success' => true,
            'advanced' => $advanced,
        ]);
    }

    /**
     * Plaats verliezer direct in de B-groep
     */
    private function plaatsVerliezerInB(Wedstrijd $bronWedstrijd, int $verliezerId): void
    {
        $pouleId = $bronWedstrijd->poule_id;

        // Bepaal target B-ronde op basis van A-ronde
        // A-groep heeft geen voorronde meer, alleen 1/16 met byes
        $targetRonde = match ($bronWedstrijd->ronde) {
            'zestiende_finale', 'achtste_finale' => 'b_start',
            'kwartfinale' => 'b_kwartfinale_2',
            'halve_finale' => 'b_halve_finale_2',
            default => null,
        };

        if (!$targetRonde) {
            return;
        }

        // Zoek lege plek in B-groep
        $legeWedstrijd = Wedstrijd::where('poule_id', $pouleId)
            ->where('groep', 'B')
            ->where('ronde', $targetRonde)
            ->where(function ($q) {
                $q->whereNull('judoka_wit_id')
                  ->orWhereNull('judoka_blauw_id');
            })
            ->first();

        // Fallback naar andere B-ronde als primaire vol is
        if (!$legeWedstrijd) {
            $fallbackRonde = $targetRonde === 'b_start' ? 'b_achtste_finale' : 'b_start';
            $legeWedstrijd = Wedstrijd::where('poule_id', $pouleId)
                ->where('groep', 'B')
                ->where('ronde', $fallbackRonde)
                ->where(function ($q) {
                    $q->whereNull('judoka_wit_id')
                      ->orWhereNull('judoka_blauw_id');
                })
                ->first();
        }

        if ($legeWedstrijd) {
            if ($legeWedstrijd->judoka_wit_id === null) {
                $legeWedstrijd->update(['judoka_wit_id' => $verliezerId]);
            } else {
                $legeWedstrijd->update(['judoka_blauw_id' => $verliezerId]);
            }
        }
    }

    /**
     * Remove a judoka from an elimination bracket slot (drag to trash)
     * Als deze judoka winnaar was van een vorige wedstrijd, reset die ook
     */
    public function verwijderJudokaDevice(Request $request): JsonResponse
    {
        // Device-bound: derive toernooi from wedstrijd
        $wedstrijd = Wedstrijd::findOrFail($request->input('wedstrijd_id'));
        $toernooi = $wedstrijd->poule?->blok?->toernooi ?? $wedstrijd->poule?->toernooi;
        return $this->doVerwijderJudoka($request, $toernooi);
    }

    public function verwijderJudoka(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        return $this->doVerwijderJudoka($request, $toernooi);
    }

    private function doVerwijderJudoka(Request $request, ?Toernooi $toernooi): JsonResponse
    {
        \Log::info('verwijderJudoka aangeroepen', $request->all());

        $validated = $request->validate([
            'wedstrijd_id' => 'required|exists:wedstrijden,id',
            'judoka_id' => 'nullable|exists:judokas,id',
            'positie' => 'nullable|in:wit,blauw',
            'alleen_positie' => 'nullable|boolean',
        ]);

        \Log::info('verwijderJudoka validated', $validated);

        $wedstrijd = Wedstrijd::findOrFail($validated['wedstrijd_id']);
        \Log::info('Wedstrijd gevonden', ['id' => $wedstrijd->id, 'wit' => $wedstrijd->judoka_wit_id, 'blauw' => $wedstrijd->judoka_blauw_id]);
        $alleenPositie = $validated['alleen_positie'] ?? false;

        // Verwijder op basis van positie (voor seeding) of judoka_id
        if (!empty($validated['positie'])) {
            $veld = $validated['positie'] === 'wit' ? 'judoka_wit_id' : 'judoka_blauw_id';
            $judokaId = $wedstrijd->$veld;
            $wedstrijd->update([$veld => null]);
            \Log::info('Verwijderd via positie', ['veld' => $veld, 'judokaId' => $judokaId]);
        } elseif (!empty($validated['judoka_id'])) {
            $judokaId = $validated['judoka_id'];
            \Log::info('Verwijder via judoka_id', [
                'judokaId' => $judokaId,
                'wit_id' => $wedstrijd->judoka_wit_id,
                'blauw_id' => $wedstrijd->judoka_blauw_id,
                'wit_match' => $wedstrijd->judoka_wit_id == $judokaId,
                'blauw_match' => $wedstrijd->judoka_blauw_id == $judokaId,
            ]);
            // Remove judoka from the slot they were in
            if ($wedstrijd->judoka_wit_id == $judokaId) {
                $wedstrijd->update(['judoka_wit_id' => null]);
                \Log::info('Verwijderd uit WIT slot');
            } elseif ($wedstrijd->judoka_blauw_id == $judokaId) {
                $wedstrijd->update(['judoka_blauw_id' => null]);
                \Log::info('Verwijderd uit BLAUW slot');
            } else {
                \Log::warning('Judoka niet gevonden in wit of blauw slot!');
            }
        } else {
            return response()->json(['success' => false, 'error' => 'Geen judoka_id of positie opgegeven'], 400);
        }

        // Bij alleen_positie: alleen de positie leegmaken, geen uitslag/B-groep wijzigingen
        // Dit wordt gebruikt bij seeding waar je judoka's verplaatst
        if ($alleenPositie) {
            return response()->json(['success' => true]);
        }

        // Zoek bronwedstrijd waarvan deze judoka de winnaar was
        $bronWedstrijd = Wedstrijd::where('poule_id', $wedstrijd->poule_id)
            ->where('volgende_wedstrijd_id', $wedstrijd->id)
            ->where('winnaar_id', $judokaId)
            ->first();

        // Reset bronwedstrijd als gevonden
        if ($bronWedstrijd) {
            // Bepaal de verliezer (de andere judoka in de bronwedstrijd)
            $verliezerId = ($bronWedstrijd->judoka_wit_id == $judokaId)
                ? $bronWedstrijd->judoka_blauw_id
                : $bronWedstrijd->judoka_wit_id;

            // Verwijder verliezer uit B-groep (die was daar geplaatst toen winnaar werd geregistreerd)
            if ($verliezerId) {
                $this->eliminatieService->verwijderUitB($wedstrijd->poule_id, $verliezerId);
            }

            // Reset de bronwedstrijd (groene stip verdwijnt)
            $bronWedstrijd->update([
                'winnaar_id' => null,
                'is_gespeeld' => false,
                'gespeeld_op' => null,
            ]);
        }

        // Verwijder judoka ook uit B-groep als die daar stond (voor het geval dat)
        $this->eliminatieService->verwijderUitB($wedstrijd->poule_id, $judokaId);

        $verwijderToernooi = $wedstrijd->poule?->blok?->toernooi ?? $wedstrijd->poule?->toernooi;
        if ($verwijderToernooi) {
            $judokaNaam = Judoka::find($judokaId)?->naam ?? "#{$judokaId}";
            $pouleNr = $wedstrijd->poule?->nummer;
            ActivityLogger::log($verwijderToernooi, 'verwijder_judoka', "{$judokaNaam} verwijderd uit poule {$pouleNr}", [
                'model' => $wedstrijd,
                'properties' => [
                    'judoka_id' => $judokaId,
                    'blok' => $wedstrijd->poule?->blok?->nummer,
                    'mat' => $wedstrijd->poule?->mat?->nummer,
                ],
                'interface' => 'mat',
            ]);
        }

        // Broadcast bracket update
        $wedstrijd->load('poule.blok');
        if ($wedstrijd->poule && $wedstrijd->poule->mat_id) {
            MatUpdate::dispatch($toernooi->id, $wedstrijd->poule->mat_id, 'bracket', [
                'poule_id' => $wedstrijd->poule_id,
                'wedstrijd_id' => $wedstrijd->id,
                'actie' => 'verwijder_judoka',
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Scoreboard pagina - standalone of met wedstrijd
     */
    public function scoreboard(Organisator $organisator, Toernooi $toernooi, ?Wedstrijd $wedstrijd = null): View
    {
        return view('pages.mat.scoreboard', compact('toernooi', 'wedstrijd'));
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

        // Accept: device pincode, toernooi admin/jury pin (bcrypt), organisator password
        $geldig = false;

        // 1. Any device pincode for this toernooi (plain text, 4 digits)
        if (!$geldig) {
            $geldig = $toernooi->deviceToegangen()
                ->where('pincode', $wachtwoord)
                ->exists();
        }

        // 2. Toernooi admin or jury pin (bcrypt)
        if (!$geldig) {
            $geldig = $toernooi->checkWachtwoord('admin', $wachtwoord)
                || $toernooi->checkWachtwoord('jury', $wachtwoord);
        }

        // 3. Organisator login password
        if (!$geldig) {
            $geldig = Hash::check($wachtwoord, $toernooi->organisator->password ?? '');
        }

        // 4. Logged-in user's password (e.g. sitebeheerder viewing another org's toernooi)
        if (!$geldig && auth('organisator')->check()) {
            $geldig = Hash::check($wachtwoord, auth('organisator')->user()->password ?? '');
        }

        return response()->json(['geldig' => $geldig]);
    }

    /**
     * Check for optimistic locking conflict.
     * Returns a conflict JsonResponse if the wedstrijd was modified since the client loaded it.
     */
    private function checkConflict(Wedstrijd $wedstrijd, ?string $clientUpdatedAt): ?JsonResponse
    {
        if (!$clientUpdatedAt || !$wedstrijd->updated_at) {
            return null;
        }

        $clientTime = \Carbon\Carbon::parse($clientUpdatedAt);
        // Allow 1 second tolerance for clock drift / serialization differences
        if ($wedstrijd->updated_at->gt($clientTime->copy()->addSecond())) {
            return response()->json([
                'success' => false,
                'conflict' => true,
                'message' => 'Deze wedstrijd is zojuist gewijzigd door een ander apparaat. De pagina wordt herladen.',
                'server_updated_at' => $wedstrijd->updated_at->toISOString(),
            ], 409);
        }

        return null;
    }
}
