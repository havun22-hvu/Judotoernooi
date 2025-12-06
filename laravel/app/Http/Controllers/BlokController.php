<?php

namespace App\Http\Controllers;

use App\Models\Blok;
use App\Models\Toernooi;
use App\Services\BlokMatVerdelingService;
use App\Services\ToernooiService;
use App\Services\WedstrijdSchemaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BlokController extends Controller
{
    public function __construct(
        private BlokMatVerdelingService $verdelingService,
        private WedstrijdSchemaService $wedstrijdService,
        private ToernooiService $toernooiService
    ) {}

    public function index(Toernooi $toernooi): View
    {
        $blokken = $toernooi->blokken()->with('poules')->get();
        $statistieken = $this->verdelingService->getVerdelingsStatistieken($toernooi);

        return view('pages.blok.index', compact('toernooi', 'blokken', 'statistieken'));
    }

    public function show(Toernooi $toernooi, Blok $blok): View
    {
        $blok->load(['poules.mat', 'poules.judokas']);

        return view('pages.blok.show', compact('toernooi', 'blok'));
    }

    public function genereerVerdeling(Toernooi $toernooi): RedirectResponse
    {
        $statistieken = $this->verdelingService->genereerBlokMatVerdeling($toernooi);

        return redirect()
            ->route('toernooi.blok.index', $toernooi)
            ->with('success', 'Blok/Mat verdeling gegenereerd');
    }

    public function sluitWeging(Toernooi $toernooi, Blok $blok): RedirectResponse
    {
        $this->toernooiService->sluitWegingBlok($blok);

        return redirect()
            ->route('toernooi.blok.show', [$toernooi, $blok])
            ->with('success', "Weging voor {$blok->naam} gesloten");
    }

    public function genereerWedstrijdschemas(Toernooi $toernooi, Blok $blok): RedirectResponse
    {
        $gegenereerd = $this->wedstrijdService->genereerWedstrijdSchemas($blok);

        $totaal = array_sum($gegenereerd);

        return redirect()
            ->route('toernooi.blok.show', [$toernooi, $blok])
            ->with('success', "{$totaal} wedstrijden gegenereerd voor {$blok->naam}");
    }

    public function zaaloverzicht(Toernooi $toernooi): View
    {
        $overzicht = $this->verdelingService->getZaalOverzicht($toernooi);

        return view('pages.blok.zaaloverzicht', compact('toernooi', 'overzicht'));
    }
}
