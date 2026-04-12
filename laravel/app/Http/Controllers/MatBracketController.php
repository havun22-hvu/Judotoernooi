<?php

namespace App\Http\Controllers;

use App\Events\MatUpdate;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use App\Services\ActivityLogger;
use App\Services\EliminatieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles mat-side bracket manipulation:
 * - placing a judoka in an elimination slot (plaatsJudoka)
 * - advancing byes in the first round (advanceByes)
 * - removing a judoka from a slot, with cascade cleanup (verwijderJudoka)
 *
 * Split out of MatController to keep each controller focused and under 800 lines.
 */
class MatBracketController extends Controller
{
    public function __construct(
        private EliminatieService $eliminatieService,
    ) {}

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
}
