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
        // Extract sign and numeric value from weight class like "-38", "+70", "-38 kg"
        if (preg_match('/([+-]?)(\d+)/', $gewichtsklasse, $matches)) {
            $sign = $matches[1] ?? '';
            $num = (int) ($matches[2] ?? 999);
            // Add 1000 to + categories so they sort after - categories
            return $sign === '+' ? $num + 1000 : $num;
        }
        return 999;
    }

    public function show(Toernooi $toernooi, Poule $poule): View
    {
        $poule->load(['judokas.club', 'blok', 'mat', 'wedstrijden']);
        $stand = $this->wedstrijdService->getPouleStand($poule);

        // Find compatible poules (same leeftijdsklasse) for merging/moving
        $compatibelePoules = $toernooi->poules()
            ->where('id', '!=', $poule->id)
            ->where('leeftijdsklasse', $poule->leeftijdsklasse)
            ->withCount('judokas')
            ->orderBy('nummer')
            ->get();

        return view('pages.poule.show', compact('toernooi', 'poule', 'stand', 'compatibelePoules'));
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

    public function wedstrijdschema(Toernooi $toernooi, Poule $poule): View
    {
        $poule->load(['judokas.club', 'wedstrijden.judokaWit', 'wedstrijden.judokaBlauw']);

        return view('pages.poule.wedstrijdschema', compact('toernooi', 'poule'));
    }

    public function genereerWedstrijden(Toernooi $toernooi, Poule $poule): RedirectResponse
    {
        $wedstrijden = $this->wedstrijdService->genereerWedstrijdenVoorPoule($poule);

        return redirect()
            ->route('toernooi.poule.wedstrijdschema', [$toernooi, $poule])
            ->with('success', count($wedstrijden) . ' wedstrijden gegenereerd');
    }

    public function verplaatsJudoka(Request $request, Toernooi $toernooi, Poule $poule): RedirectResponse
    {
        $validated = $request->validate([
            'judoka_id' => 'required|exists:judokas,id',
            'naar_poule_id' => 'required|exists:poules,id',
        ]);

        $judoka = Judoka::findOrFail($validated['judoka_id']);
        $naarPoule = Poule::findOrFail($validated['naar_poule_id']);

        $this->doVerplaatsJudoka($judoka, $poule, $naarPoule);

        return redirect()
            ->route('toernooi.poule.show', [$toernooi, $poule])
            ->with('success', "{$judoka->naam} verplaatst naar {$naarPoule->titel}");
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

        $this->doVerplaatsJudoka($judoka, $vanPoule, $naarPoule);

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

    /**
     * Move judoka between poules (shared logic)
     */
    private function doVerplaatsJudoka(Judoka $judoka, Poule $vanPoule, Poule $naarPoule): void
    {
        // Remove from current poule
        $vanPoule->judokas()->detach($judoka->id);

        // Add to new poule
        $nieuwePositie = $naarPoule->judokas()->count() + 1;
        $naarPoule->judokas()->attach($judoka->id, ['positie' => $nieuwePositie]);

        // Update statistics
        $vanPoule->updateStatistieken();
        $naarPoule->updateStatistieken();

        // Delete matches from both poules (need regeneration)
        $vanPoule->wedstrijden()->delete();
        $naarPoule->wedstrijden()->delete();
    }

    public function samenvoegen(Request $request, Toernooi $toernooi, Poule $poule): RedirectResponse
    {
        $validated = $request->validate([
            'andere_poule_id' => 'required|exists:poules,id',
        ]);

        $anderePoule = Poule::findOrFail($validated['andere_poule_id']);

        // Move all judokas from other poule to this poule
        $huidigePositie = $poule->judokas()->count();
        foreach ($anderePoule->judokas as $judoka) {
            $huidigePositie++;
            $poule->judokas()->attach($judoka->id, ['positie' => $huidigePositie]);
        }

        // Delete matches
        $poule->wedstrijden()->delete();
        $anderePoule->wedstrijden()->delete();

        // Delete the other poule
        $anderePouleTitel = $anderePoule->titel;
        $anderePoule->judokas()->detach();
        $anderePoule->delete();

        // Update statistics
        $poule->updateStatistieken();

        return redirect()
            ->route('toernooi.poule.show', [$toernooi, $poule])
            ->with('success', "Poule {$anderePouleTitel} samengevoegd met {$poule->titel}");
    }

    public function verwijderJudokaUitPoule(Request $request, Toernooi $toernooi, Poule $poule, Judoka $judoka): RedirectResponse
    {
        $poule->judokas()->detach($judoka->id);
        $poule->wedstrijden()->delete();
        $poule->updateStatistieken();

        return redirect()
            ->route('toernooi.poule.show', [$toernooi, $poule])
            ->with('success', "{$judoka->naam} verwijderd uit poule");
    }
}
