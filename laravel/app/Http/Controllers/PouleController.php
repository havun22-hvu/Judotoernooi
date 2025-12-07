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

    public function genereer(Toernooi $toernooi): RedirectResponse
    {
        $statistieken = $this->pouleService->genereerPouleIndeling($toernooi);

        $message = "Poule-indeling gegenereerd: {$statistieken['totaal_poules']} poules, " .
                   "{$statistieken['totaal_wedstrijden']} wedstrijden.";

        return redirect()
            ->route('toernooi.poule.index', $toernooi)
            ->with('success', $message);
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

        // Update statistics
        $vanPoule->updateStatistieken();
        $naarPoule->updateStatistieken();

        // Regenerate matches for both poules
        $vanPoule->wedstrijden()->delete();
        $naarPoule->wedstrijden()->delete();
        $this->wedstrijdService->genereerWedstrijdenVoorPoule($vanPoule);
        $this->wedstrijdService->genereerWedstrijdenVoorPoule($naarPoule);

        return response()->json([
            'success' => true,
            'message' => "{$judoka->naam} verplaatst naar {$naarPoule->titel}",
            'van_poule' => [
                'id' => $vanPoule->id,
                'judokas_count' => $vanPoule->fresh()->judokas()->count(),
                'aantal_wedstrijden' => $vanPoule->fresh()->aantal_wedstrijden,
            ],
            'naar_poule' => [
                'id' => $naarPoule->id,
                'judokas_count' => $naarPoule->fresh()->judokas()->count(),
                'aantal_wedstrijden' => $naarPoule->fresh()->aantal_wedstrijden,
            ],
        ]);
    }
}
