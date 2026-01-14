<?php

namespace App\Http\Controllers;

use App\Models\Judoka;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use App\Services\EliminatieService;
use App\Services\PouleIndelingService;
use App\Services\WedstrijdSchemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PouleController extends Controller
{
    public function __construct(
        private PouleIndelingService $pouleService,
        private WedstrijdSchemaService $wedstrijdService,
        private EliminatieService $eliminatieService
    ) {}

    public function index(Toernooi $toernooi): View
    {
        // Get config and build dynamic ordering from preset
        $gewichtsklassenConfig = $toernooi->getAlleGewichtsklassen();

        // Build leeftijdsklasse volgorde from config (labels AND keys)
        $leeftijdsklasseVolgorde = [];
        $index = 0;
        foreach ($gewichtsklassenConfig as $key => $config) {
            $label = $config['label'] ?? $key;
            // Map both label and key to same index for flexible matching
            $leeftijdsklasseVolgorde[$label] = $index;
            $leeftijdsklasseVolgorde[$key] = $index;
            // Also map partial matches (e.g., "U7" matches "U7 Alles")
            if (preg_match('/^(U\d+|Mini|Pupil|Aspirant|Junior|Senior)/i', $label, $m)) {
                $leeftijdsklasseVolgorde[$m[1]] = $index;
            }
            $index++;
        }

        // Build labels mapping (for backwards compatibility in views)
        $leeftijdsklasseLabels = [];
        foreach ($gewichtsklassenConfig as $key => $config) {
            $label = $config['label'] ?? $key;
            $leeftijdsklasseLabels[$label] = $label;
        }

        $poules = $toernooi->poules()
            ->with(['blok', 'mat', 'judokas.club'])
            ->withCount('judokas')
            ->get();

        // Sort by: age class (youngest first), then weight class (lightest first)
        $poules = $poules->sortBy([
            fn ($a, $b) => $this->getLeeftijdsklasseVolgorde($a->leeftijdsklasse, $leeftijdsklasseVolgorde)
                          <=> $this->getLeeftijdsklasseVolgorde($b->leeftijdsklasse, $leeftijdsklasseVolgorde),
            fn ($a, $b) => $this->parseGewicht($a->gewichtsklasse) <=> $this->parseGewicht($b->gewichtsklasse),
            fn ($a, $b) => $a->nummer <=> $b->nummer,
        ]);

        // Group by leeftijdsklasse (preserving sort order)
        $poulesPerKlasse = $poules->groupBy('leeftijdsklasse');

        return view('pages.poule.index', compact('toernooi', 'poules', 'poulesPerKlasse', 'leeftijdsklasseLabels'));
    }

    /**
     * Get sort order for a leeftijdsklasse, with fallback to numeric parsing
     */
    private function getLeeftijdsklasseVolgorde(string $leeftijdsklasse, array $volgorde): int
    {
        // Direct match
        if (isset($volgorde[$leeftijdsklasse])) {
            return $volgorde[$leeftijdsklasse];
        }

        // Try prefix match (e.g., "U7 Alles" -> try "U7")
        if (preg_match('/^(U\d+|Mini|Pupil|Aspirant|Junior|Senior)/i', $leeftijdsklasse, $m)) {
            if (isset($volgorde[$m[1]])) {
                return $volgorde[$m[1]];
            }
        }

        // Fallback: parse numeric value from name (U7=7, U11=11, etc)
        if (preg_match('/U(\d+)/i', $leeftijdsklasse, $m)) {
            return (int) $m[1];
        }

        // Ultimate fallback
        return 99;
    }

    /**
     * Parse weight class to numeric value for sorting
     * -50 = up to 50kg, +50 = over 50kg, so +50 should sort after -50
     */
    private function parseGewicht(string $gewichtsklasse): int
    {
        if (preg_match('/([+-]?)(\d+)/', $gewichtsklasse, $matches)) {
            $sign = $matches[1] ?? '';
            $num = (int) ($matches[2] ?? 999);
            return $sign === '+' ? $num + 1000 : $num;
        }
        return 999;
    }

    /**
     * Delete an empty poule
     */
    public function destroy(Toernooi $toernooi, Poule $poule): JsonResponse
    {
        // Only allow deleting empty poules
        if ($poule->judokas()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Kan alleen lege poules verwijderen',
            ], 400);
        }

        $nummer = $poule->nummer;
        $poule->delete();

        return response()->json([
            'success' => true,
            'message' => "Poule #{$nummer} verwijderd",
        ]);
    }

    /**
     * Create a new empty poule
     */
    public function store(Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'leeftijdsklasse' => 'required|string',
            'gewichtsklasse' => 'required|string',
        ]);

        // Get next nummer for this tournament
        $maxNummer = $toernooi->poules()->max('nummer') ?? 0;
        $nieuweNummer = $maxNummer + 1;

        // Create the poule
        $poule = $toernooi->poules()->create([
            'nummer' => $nieuweNummer,
            'leeftijdsklasse' => $validated['leeftijdsklasse'],
            'gewichtsklasse' => $validated['gewichtsklasse'],
            'titel' => $validated['leeftijdsklasse'] . ' ' . $validated['gewichtsklasse'],
            'aantal_judokas' => 0,
            'aantal_wedstrijden' => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Poule #{$nieuweNummer} aangemaakt",
            'poule' => $poule,
        ]);
    }

    public function genereer(Toernooi $toernooi, Request $request): RedirectResponse
    {
        // First recalculate judoka codes (ensures correct band/weight ordering)
        $this->pouleService->herberekenJudokaCodes($toernooi);

        // Generate pool division
        $statistieken = $this->pouleService->genereerPouleIndeling($toernooi);

        $message = "Poule-indeling gegenereerd: {$statistieken['totaal_poules']} poules, " .
                   "{$statistieken['totaal_wedstrijden']} wedstrijden.";

        $redirect = redirect()->route('toernooi.poule.index', $toernooi);

        // Check for warnings about elimination participant counts
        $waarschuwingen = $statistieken['waarschuwingen'] ?? [];
        if (!empty($waarschuwingen)) {
            $errorMessages = [];
            $warningMessages = [];

            foreach ($waarschuwingen as $w) {
                if ($w['type'] === 'error') {
                    $errorMessages[] = $w['bericht'];
                } else {
                    $warningMessages[] = $w['bericht'];
                }
            }

            if (!empty($errorMessages)) {
                return $redirect
                    ->with('success', $message)
                    ->with('error', implode(' ', $errorMessages));
            }

            if (!empty($warningMessages)) {
                return $redirect
                    ->with('success', $message)
                    ->with('warning', implode(' ', $warningMessages));
            }
        }

        return $redirect->with('success', $message);
    }

    /**
     * Verify all poules and recalculate match counts
     */
    public function verifieer(Toernooi $toernooi): JsonResponse
    {
        $poules = $toernooi->poules()->withCount('judokas')->get();
        $problemen = [];
        $totaalWedstrijden = 0;
        $herberekend = 0;

        foreach ($poules as $poule) {
            $aantalJudokas = $poule->judokas_count;
            $verwachtWedstrijden = $aantalJudokas >= 2 ? ($aantalJudokas * ($aantalJudokas - 1)) / 2 : 0;

            // Check for problems (empty poules are ok)
            if ($aantalJudokas > 0 && $aantalJudokas < 3) {
                $problemen[] = [
                    'poule' => $poule->titel,
                    'type' => 'te_weinig',
                    'message' => "#{$poule->nummer} {$poule->leeftijdsklasse} / {$poule->gewichtsklasse} kg: {$aantalJudokas} judoka's (min. 3)",
                ];
            } elseif ($aantalJudokas > 6) {
                $problemen[] = [
                    'poule' => $poule->titel,
                    'type' => 'te_veel',
                    'message' => "#{$poule->nummer} {$poule->leeftijdsklasse} / {$poule->gewichtsklasse} kg: {$aantalJudokas} judoka's (max. 6)",
                ];
            }

            // Check and fix match count
            $huidigWedstrijden = $poule->wedstrijden()->count();
            if ($huidigWedstrijden !== $verwachtWedstrijden) {
                // Regenerate matches
                $poule->wedstrijden()->delete();
                $this->wedstrijdService->genereerWedstrijdenVoorPoule($poule);
                $poule->updateStatistieken();
                $herberekend++;
            }

            $totaalWedstrijden += $verwachtWedstrijden;
        }

        return response()->json([
            'success' => true,
            'totaal_poules' => $poules->count(),
            'totaal_wedstrijden' => $totaalWedstrijden,
            'herberekend' => $herberekend,
            'problemen' => $problemen,
        ]);
    }

    /**
     * API endpoint for drag-and-drop judoka move
     */
    public function verplaatsJudokaApi(Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'judoka_id' => 'required|exists:judokas,id',
            'van_poule_id' => 'required|exists:poules,id',
            'naar_poule_id' => 'required|exists:poules,id',
        ]);

        $judoka = Judoka::findOrFail($validated['judoka_id']);
        $vanPoule = Poule::findOrFail($validated['van_poule_id']);
        $naarPoule = Poule::findOrFail($validated['naar_poule_id']);

        // Skip if same poule
        if ($vanPoule->id === $naarPoule->id) {
            return response()->json(['success' => true, 'message' => 'Geen wijziging']);
        }

        // Remove from current poule
        $vanPoule->judokas()->detach($judoka->id);

        // Add to new poule
        $nieuwePositie = $naarPoule->judokas()->count() + 1;
        $naarPoule->judokas()->attach($judoka->id, ['positie' => $nieuwePositie]);

        // Refresh relations to get updated judoka lists
        $vanPoule->load('judokas');
        $naarPoule->load('judokas');

        // Regenerate matches for both poules
        $vanPoule->wedstrijden()->delete();
        $naarPoule->wedstrijden()->delete();
        $this->wedstrijdService->genereerWedstrijdenVoorPoule($vanPoule);
        $this->wedstrijdService->genereerWedstrijdenVoorPoule($naarPoule);

        // Update statistics after regenerating matches
        $vanPoule->updateStatistieken();
        $naarPoule->updateStatistieken();

        // Refresh models to get updated counts and judokas relation
        $vanPoule->refresh();
        $naarPoule->refresh();
        $vanPoule->load('judokas');
        $naarPoule->load('judokas');

        // Calculate ranges for both poules
        $huidigJaar = now()->year;

        $vanRanges = $this->berekenPouleRanges($vanPoule, $huidigJaar);
        $naarRanges = $this->berekenPouleRanges($naarPoule, $huidigJaar);

        // Update titel bij variabele categorieën
        $vanTitel = $this->updateDynamischeTitel($vanPoule, $vanRanges);
        $naarTitel = $this->updateDynamischeTitel($naarPoule, $naarRanges);

        return response()->json([
            'success' => true,
            'message' => "{$judoka->naam} verplaatst naar {$naarPoule->fresh()->titel}",
            'van_poule' => [
                'id' => $vanPoule->id,
                'nummer' => $vanPoule->nummer,
                'judokas_count' => $vanPoule->aantal_judokas,
                'aantal_wedstrijden' => $vanPoule->aantal_wedstrijden,
                'titel' => $vanTitel,
                ...$vanRanges,
            ],
            'naar_poule' => [
                'id' => $naarPoule->id,
                'nummer' => $naarPoule->nummer,
                'judokas_count' => $naarPoule->aantal_judokas,
                'aantal_wedstrijden' => $naarPoule->aantal_wedstrijden,
                'titel' => $naarTitel,
                ...$naarRanges,
            ],
        ]);
    }

    /**
     * Update kruisfinale plaatsen (how many qualify from each voorronde)
     */
    public function updateKruisfinale(Request $request, Toernooi $toernooi, Poule $poule): JsonResponse
    {
        if (!$poule->isKruisfinale()) {
            return response()->json(['success' => false, 'message' => 'Dit is geen kruisfinale poule'], 400);
        }

        $validated = $request->validate([
            'kruisfinale_plaatsen' => 'required|integer|min:1|max:3',
        ]);

        // Count how many voorrondepoules feed into this kruisfinale
        $aantalVoorrondepoules = Poule::where('toernooi_id', $toernooi->id)
            ->where('leeftijdsklasse', $poule->leeftijdsklasse)
            ->where('gewichtsklasse', $poule->gewichtsklasse)
            ->where('type', 'voorronde')
            ->count();

        $kruisfinalesPlaatsen = $validated['kruisfinale_plaatsen'];
        $aantalJudokas = $aantalVoorrondepoules * $kruisfinalesPlaatsen;

        // Calculate wedstrijden
        $aantalWedstrijden = $aantalJudokas <= 1 ? 0 : ($aantalJudokas === 3 ? 6 : intval(($aantalJudokas * ($aantalJudokas - 1)) / 2));

        $poule->update([
            'kruisfinale_plaatsen' => $kruisfinalesPlaatsen,
            'aantal_judokas' => $aantalJudokas,
            'aantal_wedstrijden' => $aantalWedstrijden,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Kruisfinale aangepast: top {$kruisfinalesPlaatsen} door ({$aantalJudokas} judoka's)",
            'aantal_judokas' => $aantalJudokas,
            'aantal_wedstrijden' => $aantalWedstrijden,
        ]);
    }

    /**
     * Show elimination bracket for a poule
     */
    public function eliminatie(Toernooi $toernooi, Poule $poule): View
    {
        $poule->load(['judokas.club', 'wedstrijden.judokaWit', 'wedstrijden.judokaBlauw', 'wedstrijden.winnaar']);

        $bracket = $this->eliminatieService->getBracketStructuur($poule);
        $heeftEliminatie = $poule->wedstrijden()->where('groep', 'A')->exists();

        return view('pages.poule.eliminatie', compact('toernooi', 'poule', 'bracket', 'heeftEliminatie'));
    }

    /**
     * Generate elimination bracket for a poule
     */
    public function genereerEliminatie(Toernooi $toernooi, Poule $poule): JsonResponse
    {
        $judokas = $poule->judokas;

        if ($judokas->count() < 2) {
            return response()->json([
                'success' => false,
                'message' => 'Minimaal 2 judoka\'s nodig voor eliminatie',
            ], 400);
        }

        // Alleen aanwezige judoka's (niet afwezig)
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
    public function opslaanEliminatieUitslag(Request $request, Toernooi $toernooi, Poule $poule): JsonResponse
    {
        $validated = $request->validate([
            'wedstrijd_id' => 'required|exists:wedstrijden,id',
            'winnaar_id' => 'required|exists:judokas,id',
            'uitslag_type' => 'nullable|string|in:ippon,wazari,yuko,beslissing,opgave',
        ]);

        $wedstrijd = Wedstrijd::findOrFail($validated['wedstrijd_id']);

        // Verify the winner is one of the participants
        if ($validated['winnaar_id'] != $wedstrijd->judoka_wit_id &&
            $validated['winnaar_id'] != $wedstrijd->judoka_blauw_id) {
            return response()->json([
                'success' => false,
                'message' => 'Winnaar moet een van de deelnemers zijn',
            ], 400);
        }

        // Update the match
        $wedstrijd->update([
            'winnaar_id' => $validated['winnaar_id'],
            'is_gespeeld' => true,
            'uitslag_type' => $validated['uitslag_type'] ?? 'ippon',
        ]);

        // Process advancement
        $eliminatieType = $toernooi->eliminatie_type ?? 'dubbel';
        $this->eliminatieService->verwerkUitslag($wedstrijd, $validated['winnaar_id'], null, $eliminatieType);

        return response()->json([
            'success' => true,
            'message' => 'Uitslag opgeslagen',
        ]);
    }

    /**
     * Verplaats judoka in B-groep (seeding)
     * Alleen toegestaan als de bracket nog in seeding fase is
     */
    public function seedingBGroep(Request $request, Toernooi $toernooi, Poule $poule): JsonResponse
    {
        $validated = $request->validate([
            'judoka_id' => 'required|exists:judokas,id',
            'van_wedstrijd_id' => 'required|exists:wedstrijden,id',
            'naar_wedstrijd_id' => 'required|exists:wedstrijden,id',
            'naar_slot' => 'required|in:wit,blauw',
        ]);

        $vanWedstrijd = Wedstrijd::findOrFail($validated['van_wedstrijd_id']);
        $naarWedstrijd = Wedstrijd::findOrFail($validated['naar_wedstrijd_id']);

        // Validatie: beide wedstrijden moeten in B-groep zijn
        if ($vanWedstrijd->groep !== 'B' || $naarWedstrijd->groep !== 'B') {
            return response()->json([
                'success' => false,
                'message' => 'Seeding is alleen mogelijk binnen de B-groep',
            ], 400);
        }

        // Validatie: beide wedstrijden moeten bij dezelfde poule horen
        if ($vanWedstrijd->poule_id !== $poule->id || $naarWedstrijd->poule_id !== $poule->id) {
            return response()->json([
                'success' => false,
                'message' => 'Wedstrijden horen niet bij deze poule',
            ], 400);
        }

        // Validatie: check of bracket nog in seeding fase is (geen wedstrijden gespeeld in B-groep)
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

        // Validatie: judoka moet in van_wedstrijd zitten
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

        // Validatie: doel slot moet leeg zijn
        $naarSlot = $validated['naar_slot'];
        if ($naarWedstrijd->{"judoka_{$naarSlot}_id"} !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Doel slot is niet leeg',
            ], 400);
        }

        // Check op potentiële rematch
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

        // Voer de verplaatsing uit
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
     * Haal B-groep seeding informatie op
     */
    public function getBGroepSeeding(Toernooi $toernooi, Poule $poule): JsonResponse
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

        // Verzamel potentiële rematches
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
     * Haal A-groep seeding informatie op
     */
    public function getSeedingStatus(Toernooi $toernooi, Poule $poule): JsonResponse
    {
        $poule->load(['wedstrijden.judokaWit.club', 'wedstrijden.judokaBlauw.club']);

        $eersteRonde = $poule->wedstrijden
            ->where('groep', 'A')
            ->whereIn('ronde', ['voorronde', 'achtste_finale', 'kwartfinale', 'zestiende_finale'])
            ->sortBy('bracket_positie');

        $isLocked = !$this->eliminatieService->isInSeedingFase($poule);

        // Groepeer clubgenoten die tegen elkaar moeten
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
     * Swap twee judoka's in de eerste ronde (A-groep seeding)
     * Alleen mogelijk in seeding-fase (voor eerste wedstrijd)
     */
    public function swapSeeding(Request $request, Toernooi $toernooi, Poule $poule): JsonResponse
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
     * Verplaats judoka naar lege plek in eerste ronde (A-groep seeding)
     * Alleen mogelijk in seeding-fase (voor eerste wedstrijd)
     */
    public function moveSeeding(Request $request, Toernooi $toernooi, Poule $poule): JsonResponse
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
     * Herstel B-groep koppelingen voor bestaande bracket
     */
    public function herstelBKoppelingen(Toernooi $toernooi, Poule $poule): \Illuminate\Http\JsonResponse
    {
        $hersteld = $this->eliminatieService->herstelBKoppelingen($poule->id);

        return response()->json([
            'success' => true,
            'message' => "{$hersteld} B-koppelingen hersteld",
            'hersteld' => $hersteld,
        ]);
    }

    /**
     * Diagnose B-koppelingen - toon huidige koppelingen zonder wijzigingen
     */
    public function diagnoseBKoppelingen(Toernooi $toernooi, Poule $poule): \Illuminate\Http\JsonResponse
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

    /**
     * Calculate min/max age and weight ranges for a poule
     */
    private function berekenPouleRanges(Poule $poule, int $huidigJaar): array
    {
        $judokas = $poule->judokas;

        if ($judokas->isEmpty()) {
            return [
                'leeftijd_range' => '',
                'gewicht_range' => '',
            ];
        }

        $leeftijden = $judokas->map(fn($j) => $j->geboortejaar ? $huidigJaar - $j->geboortejaar : null)->filter();

        // Gewichten: gewogen > ingeschreven > gewichtsklasse
        $gewichten = $judokas->map(function($j) {
            if ($j->gewicht_gewogen !== null) return $j->gewicht_gewogen;
            if ($j->gewicht !== null) return $j->gewicht;
            // Gewichtsklasse is bijv. "-38" of "+73" - extract getal
            if ($j->gewichtsklasse && preg_match('/(\d+)/', $j->gewichtsklasse, $m)) {
                return (float) $m[1];
            }
            return null;
        })->filter();

        $leeftijdRange = '';
        $gewichtRange = '';

        if ($leeftijden->count() > 0) {
            $minL = $leeftijden->min();
            $maxL = $leeftijden->max();
            $leeftijdRange = $minL === $maxL ? "{$minL}j" : "{$minL}-{$maxL}j";
        }

        if ($gewichten->count() > 0) {
            $minG = $gewichten->min();
            $maxG = $gewichten->max();
            $gewichtRange = $minG === $maxG ? "{$minG}kg" : "{$minG}-{$maxG}kg";
        }

        return [
            'leeftijd_range' => $leeftijdRange,
            'gewicht_range' => $gewichtRange,
        ];
    }

    /**
     * Update poule titel als deze dynamisch is (variabele categorie)
     * Retourneert de (eventueel bijgewerkte) titel
     *
     * Variabele categorieën worden herkend aan:
     * - max_leeftijd_verschil > 0 of max_kg_verschil > 0 in categorie config
     */
    private function updateDynamischeTitel(Poule $poule, array $ranges): string
    {
        $titel = $poule->titel;
        $toernooi = $poule->toernooi;

        // Get category config for this pool's leeftijdsklasse
        $config = $toernooi->getPresetConfig();
        $categorieConfig = null;

        // Find matching category config
        foreach ($config as $key => $data) {
            if (($data['label'] ?? '') === $poule->leeftijdsklasse) {
                $categorieConfig = $data;
                break;
            }
        }

        // Check if this is a variable category
        $maxLftVerschil = (int) ($categorieConfig['max_leeftijd_verschil'] ?? 0);
        $maxKgVerschil = (float) ($categorieConfig['max_kg_verschil'] ?? 0);

        if ($maxLftVerschil == 0 && $maxKgVerschil == 0) {
            // Fixed category - no title update needed
            return $titel;
        }

        // Build new title based on config
        $parts = [];

        // 1. Label (optional)
        $toonLabel = $categorieConfig['toon_label_in_titel'] ?? true;
        if ($toonLabel && !empty($categorieConfig['label'])) {
            $parts[] = $categorieConfig['label'];
        }

        // 2. Gender (if not mixed)
        $geslacht = $poule->geslacht;
        if ($geslacht && $geslacht !== 'gemengd') {
            $parts[] = $geslacht;
        }

        // 3. Age range (if variable)
        if ($maxLftVerschil > 0) {
            $leeftijdRange = $ranges['leeftijd_range'] ?? '';
            if ($leeftijdRange) {
                $parts[] = $leeftijdRange;
            }
        }

        // 4. Weight range (if variable)
        if ($maxKgVerschil > 0) {
            $gewichtRange = $ranges['gewicht_range'] ?? '';
            if ($gewichtRange) {
                $parts[] = $gewichtRange;
            }
        }

        $nieuweTitel = implode(' ', $parts) ?: $titel;

        // Update database if title changed
        if ($nieuweTitel !== $titel) {
            $poule->update(['titel' => $nieuweTitel]);
        }

        return $nieuweTitel;
    }
}
