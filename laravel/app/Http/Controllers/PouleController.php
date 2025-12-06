<?php

namespace App\Http\Controllers;

use App\Models\Poule;
use App\Models\Toernooi;
use App\Services\PouleIndelingService;
use App\Services\WedstrijdSchemaService;
use Illuminate\Http\RedirectResponse;
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
            ->with(['blok', 'mat'])
            ->withCount('judokas')
            ->orderBy('nummer')
            ->paginate(25);

        return view('pages.poule.index', compact('toernooi', 'poules'));
    }

    public function show(Toernooi $toernooi, Poule $poule): View
    {
        $poule->load(['judokas.club', 'blok', 'mat', 'wedstrijden']);
        $stand = $this->wedstrijdService->getPouleStand($poule);

        return view('pages.poule.show', compact('toernooi', 'poule', 'stand'));
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
}
