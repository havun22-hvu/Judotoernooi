<?php

namespace App\Http\Controllers;

use App\Models\Judoka;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Services\PouleIndelingService;
use App\Services\WedstrijdSchemaService;
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
        $poules = $toernooi->poules()
            ->with(['blok', 'mat', 'judokas.club'])
            ->withCount('judokas')
            ->orderBy('leeftijdsklasse')
            ->orderBy('gewichtsklasse')
            ->orderBy('nummer')
            ->get();

        // Group by leeftijdsklasse
        $poulesPerKlasse = $poules->groupBy('leeftijdsklasse');

        return view('pages.poule.index', compact('toernooi', 'poules', 'poulesPerKlasse'));
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

        // Remove from current poule
        $poule->judokas()->detach($judoka->id);

        // Add to new poule
        $nieuwePositie = $naarPoule->judokas()->count() + 1;
        $naarPoule->judokas()->attach($judoka->id, ['positie' => $nieuwePositie]);

        // Update statistics
        $poule->updateStatistieken();
        $naarPoule->updateStatistieken();

        // Delete matches from old poule (need regeneration)
        $poule->wedstrijden()->delete();
        $naarPoule->wedstrijden()->delete();

        return redirect()
            ->route('toernooi.poule.show', [$toernooi, $poule])
            ->with('success', "{$judoka->naam} verplaatst naar {$naarPoule->titel}");
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
