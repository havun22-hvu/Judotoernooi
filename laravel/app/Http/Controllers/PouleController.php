<?php

namespace App\Http\Controllers;

use App\Models\Judoka;
use App\Models\Poule;
use App\Models\Toernooi;
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
        private WedstrijdSchemaService $wedstrijdService
    ) {}

    public function index(Toernooi $toernooi): View
    {
        // Define age class order (youngest to oldest)
        $leeftijdsklasseVolgorde = [
            "Mini's" => 1,
            'A-pupillen' => 2,
            'B-pupillen' => 3,
            'U9' => 1,
            'U11' => 2,
            'U13' => 3,
            'U15' => 4,
            'U18' => 5,
            'U21' => 6,
            'Senioren' => 7,
            'Dames -15' => 4,
            'Heren -15' => 4,
            'Dames -18' => 5,
            'Heren -18' => 5,
            'Dames' => 6,
            'Heren' => 6,
        ];

        $poules = $toernooi->poules()
            ->with(['blok', 'mat', 'judokas.club'])
            ->withCount('judokas')
            ->get();

        // Sort by: age class (youngest first), then weight class (lightest first)
        $poules = $poules->sortBy([
            fn ($a, $b) => ($leeftijdsklasseVolgorde[$a->leeftijdsklasse] ?? 99) <=> ($leeftijdsklasseVolgorde[$b->leeftijdsklasse] ?? 99),
            fn ($a, $b) => $this->parseGewicht($a->gewichtsklasse) <=> $this->parseGewicht($b->gewichtsklasse),
            fn ($a, $b) => $a->nummer <=> $b->nummer,
        ]);

        // Group by leeftijdsklasse (preserving sort order)
        $poulesPerKlasse = $poules->groupBy('leeftijdsklasse');

        return view('pages.poule.index', compact('toernooi', 'poules', 'poulesPerKlasse'));
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

    public function genereer(Toernooi $toernooi): RedirectResponse
    {
        // First recalculate judoka codes (ensures correct band/weight ordering)
        $this->pouleService->herberekenJudokaCodes($toernooi);

        $statistieken = $this->pouleService->genereerPouleIndeling($toernooi);

        $message = "Poule-indeling gegenereerd: {$statistieken['totaal_poules']} poules, " .
                   "{$statistieken['totaal_wedstrijden']} wedstrijden.";

        return redirect()
            ->route('toernooi.poule.index', $toernooi)
            ->with('success', $message);
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

        return response()->json([
            'success' => true,
            'message' => "{$judoka->naam} verplaatst naar {$naarPoule->titel}",
            'van_poule' => [
                'id' => $vanPoule->id,
                'nummer' => $vanPoule->nummer,
                'judokas_count' => $vanPoule->aantal_judokas,
                'aantal_wedstrijden' => $vanPoule->aantal_wedstrijden,
            ],
            'naar_poule' => [
                'id' => $naarPoule->id,
                'nummer' => $naarPoule->nummer,
                'judokas_count' => $naarPoule->aantal_judokas,
                'aantal_wedstrijden' => $naarPoule->aantal_wedstrijden,
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
}
