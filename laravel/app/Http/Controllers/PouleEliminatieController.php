<?php

namespace App\Http\Controllers;

use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use App\Services\EliminatieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Handles elimination bracket operations — viewing, generation, result
 * registration, seeding (A+B group) and bracket repair. Split out of
 * PouleController to keep the main controller focused on poule CRUD
 * and round-robin management.
 */
class PouleEliminatieController extends Controller
{
    public function __construct(
        private EliminatieService $eliminatieService
    ) {}

    /**
     * Show elimination bracket for a poule
     */
    public function eliminatie(Organisator $organisator, Toernooi $toernooi, Poule $poule): View
    {
        $poule->load(['judokas.club', 'wedstrijden.judokaWit', 'wedstrijden.judokaBlauw', 'wedstrijden.winnaar']);

        $bracket = $this->eliminatieService->getBracketStructuur($poule);
        $heeftEliminatie = $poule->wedstrijden()->where('groep', 'A')->exists();

        return view('pages.poule.eliminatie', compact('toernooi', 'poule', 'bracket', 'heeftEliminatie'));
    }

    /**
     * Generate elimination bracket for a poule
     */
    public function genereerEliminatie(Organisator $organisator, Toernooi $toernooi, Poule $poule): JsonResponse
    {
        $judokas = $poule->judokas;

        if ($judokas->count() < 2) {
            return response()->json([
                'success' => false,
                'message' => 'Minimaal 2 judoka\'s nodig voor eliminatie',
            ], 400);
        }

        $judokaIds = $judokas
            ->filter(fn($j) => $j->aanwezigheid !== 'afwezig')
            ->pluck('id')
            ->toArray();
        $eliminatieType = $toernooi->eliminatie_type ?? 'dubbel';
        $statistieken = $this->eliminatieService->genereerBracket($poule, $judokaIds, $eliminatieType);

        if (isset($statistieken['error'])) {
            return response()->json([
                'success' => false,
                'message' => $statistieken['error'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => "Eliminatie bracket gegenereerd: {$statistieken['totaal_wedstrijden']} wedstrijden",
            'statistieken' => $statistieken,
        ]);
    }

    /**
     * Save match result in elimination bracket
     */
    public function opslaanEliminatieUitslag(Organisator $organisator, Request $request, Toernooi $toernooi, Poule $poule): JsonResponse
    {
        $validated = $request->validate([
            'wedstrijd_id' => 'required|exists:wedstrijden,id',
            'winnaar_id' => 'required|exists:judokas,id',
            'uitslag_type' => 'nullable|string|in:ippon,wazari,yuko,beslissing,opgave',
        ]);

        $wedstrijd = Wedstrijd::findOrFail($validated['wedstrijd_id']);

        if ($validated['winnaar_id'] != $wedstrijd->judoka_wit_id &&
            $validated['winnaar_id'] != $wedstrijd->judoka_blauw_id) {
            return response()->json([
                'success' => false,
                'message' => 'Winnaar moet een van de deelnemers zijn',
            ], 400);
        }

        $wedstrijd->update([
            'winnaar_id' => $validated['winnaar_id'],
            'is_gespeeld' => true,
            'uitslag_type' => $validated['uitslag_type'] ?? 'ippon',
        ]);

        $eliminatieType = $toernooi->eliminatie_type ?? 'dubbel';
        $this->eliminatieService->verwerkUitslag($wedstrijd, $validated['winnaar_id'], null, $eliminatieType);

        return response()->json([
            'success' => true,
            'message' => 'Uitslag opgeslagen',
        ]);
    }

    /**
     * Move judoka in B-group seeding.
     * Only allowed when bracket is still in seeding phase.
     */
    public function seedingBGroep(Organisator $organisator, Request $request, Toernooi $toernooi, Poule $poule): JsonResponse
    {
        $validated = $request->validate([
            'judoka_id' => 'required|exists:judokas,id',
            'van_wedstrijd_id' => 'required|exists:wedstrijden,id',
            'naar_wedstrijd_id' => 'required|exists:wedstrijden,id',
            'naar_slot' => 'required|in:wit,blauw',
        ]);

        $vanWedstrijd = Wedstrijd::findOrFail($validated['van_wedstrijd_id']);
        $naarWedstrijd = Wedstrijd::findOrFail($validated['naar_wedstrijd_id']);

        if ($vanWedstrijd->groep !== 'B' || $naarWedstrijd->groep !== 'B') {
            return response()->json([
                'success' => false,
                'message' => 'Seeding is alleen mogelijk binnen de B-groep',
            ], 400);
        }

        if ($vanWedstrijd->poule_id !== $poule->id || $naarWedstrijd->poule_id !== $poule->id) {
            return response()->json([
                'success' => false,
                'message' => 'Wedstrijden horen niet bij deze poule',
            ], 400);
        }

        $bWedstrijdenGespeeld = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'B')
            ->where('is_gespeeld', true)
            ->exists();

        if ($bWedstrijdenGespeeld) {
            return response()->json([
                'success' => false,
                'message' => 'Bracket is vergrendeld - er zijn al wedstrijden gespeeld in de B-groep',
            ], 400);
        }

        $judokaId = $validated['judoka_id'];
        $vanSlot = null;
        if ($vanWedstrijd->judoka_wit_id == $judokaId) {
            $vanSlot = 'wit';
        } elseif ($vanWedstrijd->judoka_blauw_id == $judokaId) {
            $vanSlot = 'blauw';
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Judoka zit niet in de bron wedstrijd',
            ], 400);
        }

        $naarSlot = $validated['naar_slot'];
        if ($naarWedstrijd->{"judoka_{$naarSlot}_id"} !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Doel slot is niet leeg',
            ], 400);
        }

        // Check for potential rematch
        $potentieleTegenstander = $naarSlot === 'wit'
            ? $naarWedstrijd->judoka_blauw_id
            : $naarWedstrijd->judoka_wit_id;

        $waarschuwing = null;
        if ($potentieleTegenstander) {
            if ($this->eliminatieService->heeftAlGespeeld($poule->id, $judokaId, $potentieleTegenstander)) {
                $tegenstander = \App\Models\Judoka::find($potentieleTegenstander);
                $waarschuwing = "Let op: dit veroorzaakt een rematch met {$tegenstander->naam}";
            }
        }

        $vanWedstrijd->update(["judoka_{$vanSlot}_id" => null]);
        $naarWedstrijd->update(["judoka_{$naarSlot}_id" => $judokaId]);

        $judoka = \App\Models\Judoka::find($judokaId);

        return response()->json([
            'success' => true,
            'message' => "{$judoka->naam} verplaatst naar {$naarWedstrijd->ronde}",
            'waarschuwing' => $waarschuwing,
        ]);
    }

    /**
     * Get B-group seeding information
     */
    public function getBGroepSeeding(Organisator $organisator, Toernooi $toernooi, Poule $poule): JsonResponse
    {
        $poule->load(['wedstrijden.judokaWit', 'wedstrijden.judokaBlauw']);

        $bWedstrijden = $poule->wedstrijden
            ->where('groep', 'B')
            ->sortBy('bracket_positie')
            ->groupBy('ronde');

        $isLocked = $poule->wedstrijden
            ->where('groep', 'B')
            ->where('is_gespeeld', true)
            ->isNotEmpty();

        $rematches = [];
        foreach ($bWedstrijden as $ronde => $wedstrijden) {
            foreach ($wedstrijden as $wed) {
                if ($wed->judoka_wit_id && $wed->judoka_blauw_id) {
                    if ($this->eliminatieService->heeftAlGespeeld($poule->id, $wed->judoka_wit_id, $wed->judoka_blauw_id)) {
                        $rematches[] = [
                            'wedstrijd_id' => $wed->id,
                            'ronde' => $ronde,
                            'judoka_wit' => $wed->judokaWit->naam,
                            'judoka_blauw' => $wed->judokaBlauw->naam,
                        ];
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'is_locked' => $isLocked,
            'rematches' => $rematches,
            'wedstrijden' => $bWedstrijden,
        ]);
    }

    /**
     * Get A-group seeding status (club conflicts)
     */
    public function getSeedingStatus(Organisator $organisator, Toernooi $toernooi, Poule $poule): JsonResponse
    {
        $poule->load(['wedstrijden.judokaWit.club', 'wedstrijden.judokaBlauw.club']);

        $eersteRonde = $poule->wedstrijden
            ->where('groep', 'A')
            ->whereIn('ronde', ['voorronde', 'achtste_finale', 'kwartfinale', 'zestiende_finale'])
            ->sortBy('bracket_positie');

        $isLocked = !$this->eliminatieService->isInSeedingFase($poule);

        $clubConflicten = [];
        foreach ($eersteRonde as $wed) {
            if ($wed->judoka_wit_id && $wed->judoka_blauw_id) {
                $clubWit = $wed->judokaWit->club_id ?? null;
                $clubBlauw = $wed->judokaBlauw->club_id ?? null;
                if ($clubWit && $clubWit === $clubBlauw) {
                    $clubConflicten[] = [
                        'wedstrijd_id' => $wed->id,
                        'ronde' => $wed->ronde,
                        'judoka_wit' => $wed->judokaWit->naam,
                        'judoka_blauw' => $wed->judokaBlauw->naam,
                        'club' => $wed->judokaWit->club->naam ?? 'Onbekend',
                    ];
                }
            }
        }

        return response()->json([
            'success' => true,
            'is_locked' => $isLocked,
            'club_conflicten' => $clubConflicten,
            'wedstrijden' => $eersteRonde->values(),
        ]);
    }

    /**
     * Swap two judokas in first round (A-group seeding)
     */
    public function swapSeeding(Organisator $organisator, Request $request, Toernooi $toernooi, Poule $poule): JsonResponse
    {
        $validated = $request->validate([
            'judoka_a_id' => 'required|exists:judokas,id',
            'judoka_b_id' => 'required|exists:judokas,id',
        ]);

        $result = $this->eliminatieService->swapJudokas(
            $poule,
            $validated['judoka_a_id'],
            $validated['judoka_b_id']
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Move judoka to empty slot in first round (A-group seeding)
     */
    public function moveSeeding(Organisator $organisator, Request $request, Toernooi $toernooi, Poule $poule): JsonResponse
    {
        $validated = $request->validate([
            'judoka_id' => 'required|exists:judokas,id',
            'naar_wedstrijd_id' => 'required|exists:wedstrijden,id',
            'naar_positie' => 'required|in:wit,blauw',
        ]);

        $result = $this->eliminatieService->moveJudokaNaarLegePlek(
            $poule,
            $validated['judoka_id'],
            $validated['naar_wedstrijd_id'],
            $validated['naar_positie']
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Repair B-group bracket links for existing bracket
     */
    public function herstelBKoppelingen(Organisator $organisator, Toernooi $toernooi, Poule $poule): JsonResponse
    {
        $hersteld = $this->eliminatieService->herstelBKoppelingen($poule->id);

        return response()->json([
            'success' => true,
            'message' => "{$hersteld} B-koppelingen hersteld",
            'hersteld' => $hersteld,
        ]);
    }

    /**
     * Diagnose B-group bracket links without modifications
     */
    public function diagnoseBKoppelingen(Organisator $organisator, Toernooi $toernooi, Poule $poule): JsonResponse
    {
        $wedstrijden = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'B')
            ->orderBy('volgorde')
            ->get(['id', 'ronde', 'bracket_positie', 'volgende_wedstrijd_id', 'winnaar_naar_slot', 'locatie_wit', 'locatie_blauw']);

        $perRonde = [];
        foreach ($wedstrijden as $wed) {
            $perRonde[$wed->ronde][] = [
                'id' => $wed->id,
                'pos' => $wed->bracket_positie,
                'volgende' => $wed->volgende_wedstrijd_id,
                'slot' => $wed->winnaar_naar_slot,
                'loc_wit' => $wed->locatie_wit,
                'loc_blauw' => $wed->locatie_blauw,
            ];
        }

        return response()->json([
            'poule' => $poule->naam,
            'rondes' => array_keys($perRonde),
            'koppelingen' => $perRonde,
        ]);
    }
}
