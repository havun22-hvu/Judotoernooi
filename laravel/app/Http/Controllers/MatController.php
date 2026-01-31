<?php

namespace App\Http\Controllers;

use App\Models\Organisator;
use App\Models\Blok;
use App\Models\Mat;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use App\Services\EliminatieService;
use App\Services\WedstrijdSchemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MatController extends Controller
{
    public function __construct(
        private WedstrijdSchemaService $wedstrijdService,
        private EliminatieService $eliminatieService
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
            'score_wit' => 'nullable|string|max:20',
            'score_blauw' => 'nullable|string|max:20',
            'uitslag_type' => 'nullable|string|max:20',
        ]);

        $wedstrijd = Wedstrijd::findOrFail($validated['wedstrijd_id']);

        // Check if this is an elimination match (has groep field)
        if ($wedstrijd->groep) {
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
                // Get toernooi via wedstrijd->poule relationship
                $toernooi = $wedstrijd->poule->blok->toernooi;
                $eliminatieType = $toernooi->eliminatie_type ?? 'dubbel';
                $correcties = $this->eliminatieService->verwerkUitslag($wedstrijd, $validated['winnaar_id'], $oudeWinnaarId, $eliminatieType);
            }

            return response()->json([
                'success' => true,
                'correcties' => $correcties,
            ]);
        } else {
            // Regular pool match
            $this->wedstrijdService->registreerUitslag(
                $wedstrijd,
                $validated['winnaar_id'],
                $validated['score_wit'] ?? '',
                $validated['score_blauw'] ?? '',
                $validated['uitslag_type'] ?? 'beslissing'
            );
        }

        return response()->json(['success' => true]);
    }

    /**
     * Register finale/brons result via medal placement (drag to gold/silver/bronze)
     */
    public function finaleUitslag(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'wedstrijd_id' => 'required|exists:wedstrijden,id',
            'geplaatste_judoka_id' => 'required|exists:judokas,id',
            'medaille' => 'required|in:goud,zilver,brons',
        ]);

        $wedstrijd = Wedstrijd::findOrFail($validated['wedstrijd_id']);

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

            return response()->json([
                'success' => true,
                'barrage' => true,
                'message' => 'Barrage afgerond, originele poule naar spreker gestuurd',
                'originele_poule_id' => $originelePoule->id,
            ]);
        }

        $poule->update(['spreker_klaar' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * Manually set current/next match for a poule
     * - actieve_wedstrijd_id = green (currently playing)
     * - huidige_wedstrijd_id = yellow (next up)
     */
    public function setHuidigeWedstrijd(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        return $this->doSetHuidigeWedstrijd($request, $toernooi->id);
    }

    /**
     * Device-bound version - no toernooi check needed, poule validates itself
     */
    public function setHuidigeWedstrijdDevice(Request $request): JsonResponse
    {
        // Get toernooi from device_toegang
        $toegang = $request->get('device_toegang');
        return $this->doSetHuidigeWedstrijd($request, $toegang->toernooi_id);
    }

    private function doSetHuidigeWedstrijd(Request $request, int $toernooiId): JsonResponse
    {
        $validated = $request->validate([
            'poule_id' => 'required|exists:poules,id',
            'wedstrijd_id' => 'nullable|exists:wedstrijden,id',
            'type' => 'nullable|in:actief,volgend', // actief=green, volgend=yellow
        ]);

        $poule = Poule::findOrFail($validated['poule_id']);
        $type = $validated['type'] ?? 'volgend'; // Default to old behavior

        // Verify poule belongs to this toernooi
        if ($poule->toernooi_id !== $toernooiId) {
            return response()->json(['success' => false, 'error' => 'Poule hoort niet bij dit toernooi'], 403);
        }

        // Verify wedstrijd belongs to this poule (if provided)
        if ($validated['wedstrijd_id']) {
            $wedstrijd = Wedstrijd::findOrFail($validated['wedstrijd_id']);
            if ($wedstrijd->poule_id !== $poule->id) {
                return response()->json(['success' => false, 'error' => 'Wedstrijd hoort niet bij deze poule'], 403);
            }
        }

        if ($type === 'actief') {
            $poule->update(['actieve_wedstrijd_id' => $validated['wedstrijd_id']]);
        } else {
            $poule->update(['huidige_wedstrijd_id' => $validated['wedstrijd_id']]);
        }

        return response()->json([
            'success' => true,
            'actieve_wedstrijd_id' => $poule->actieve_wedstrijd_id,
            'huidige_wedstrijd_id' => $poule->huidige_wedstrijd_id,
        ]);
    }

    /**
     * Place a judoka in an elimination bracket slot (manual drag & drop)
     * Als bron_wedstrijd_id is meegegeven, registreer ook de uitslag
     * Bij correctie worden foute plaatsingen automatisch opgeruimd
     */
    public function plaatsJudoka(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
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
                $correcties = $this->eliminatieService->verwerkUitslag($bronWedstrijd, $winnaarId, $oudeWinnaarId, $eliminatieType);
            }
        }

        return response()->json([
            'success' => true,
            'correcties' => $correcties,
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
    public function verwijderJudoka(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
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

        return response()->json(['success' => true]);
    }
}
