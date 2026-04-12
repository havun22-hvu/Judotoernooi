<?php

namespace App\Http\Controllers;

use App\Models\Organisator;
use App\Models\Toernooi;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ToernooiAfsluitenController extends Controller
{
    /**
     * Show tournament closing page with statistics
     */
    public function afsluiten(Organisator $organisator, Toernooi $toernooi): View
    {
        $statistieken = $this->getAfsluitStatistieken($toernooi);
        $clubRanking = $this->getClubRanking($toernooi);

        return view('pages.toernooi.afsluiten', compact('toernooi', 'statistieken', 'clubRanking'));
    }

    /**
     * Confirm closing of tournament
     */
    public function bevestigAfsluiten(Organisator $organisator, Request $request, Toernooi $toernooi): RedirectResponse
    {
        // Check permissions: only organisator of this tournament or sitebeheerder
        $loggedIn = auth('organisator')->user();
        if (!$loggedIn || (!$loggedIn->isSitebeheerder() && !$loggedIn->toernooien->contains($toernooi))) {
            return redirect()
                ->route('toernooi.afsluiten', $toernooi->routeParams())
                ->with('error', 'Je hebt geen rechten om dit toernooi af te sluiten');
        }

        if ($toernooi->isAfgesloten()) {
            return redirect()
                ->route('toernooi.afsluiten', $toernooi->routeParams())
                ->with('error', 'Dit toernooi is al afgesloten');
        }

        // Calculate reminder date (3 months before next year's tournament)
        $volgendJaar = $toernooi->datum->addYear();
        $herinneringDatum = $volgendJaar->subMonths(3);

        ActivityLogger::log($toernooi, 'toernooi_afgesloten', "Toernooi '{$toernooi->naam}' afgesloten", [
            'model' => $toernooi,
            'interface' => 'dashboard',
        ]);

        $toernooi->update([
            'afgesloten_at' => now(),
            'herinnering_datum' => $herinneringDatum,
            'herinnering_verstuurd' => false,
        ]);

        // Reset all device bindings for vrijwilligers
        $toernooi->deviceToegangen()->update([
            'device_token' => null,
            'device_info' => null,
            'gebonden_op' => null,
        ]);

        // Reset all coach kaart device bindings
        \App\Models\CoachKaart::where('toernooi_id', $toernooi->id)
            ->update([
                'device_token' => null,
                'device_info' => null,
                'gebonden_op' => null,
            ]);

        // Auto-score wimpeltoernooi punten (non-blocking)
        try {
            $wimpelWarnings = app(\App\Services\WimpelService::class)->verwerkToernooi($toernooi);
            if (!empty($wimpelWarnings)) {
                $namen = collect($wimpelWarnings)->pluck('judoka')->implode(', ');
                return redirect()
                    ->route('toernooi.afsluiten', $toernooi->routeParams())
                    ->with('success', "Toernooi afgesloten! Wimpelpunten bijgeschreven. Milestone bereikt: {$namen}");
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Wimpel auto-scoring failed', [
                'toernooi_id' => $toernooi->id,
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('toernooi.afsluiten', $toernooi->routeParams())
            ->with('success', 'Toernooi succesvol afgesloten! Alle device bindings zijn gereset.');
    }

    /**
     * Reopen a closed tournament
     */
    public function heropenen(Organisator $organisator, Toernooi $toernooi): RedirectResponse
    {
        // Check permissions: only organisator of this tournament or sitebeheerder
        $loggedIn = auth('organisator')->user();
        if (!$loggedIn || (!$loggedIn->isSitebeheerder() && !$loggedIn->toernooien->contains($toernooi))) {
            return redirect()
                ->route('toernooi.afsluiten', $toernooi->routeParams())
                ->with('error', 'Je hebt geen rechten om dit toernooi te heropenen');
        }

        $toernooi->update([
            'afgesloten_at' => null,
            'herinnering_datum' => null,
            'herinnering_verstuurd' => false,
        ]);

        return redirect()
            ->route('toernooi.show', $toernooi->routeParams())
            ->with('success', 'Toernooi heropend. Je kunt nu weer wijzigingen aanbrengen.');
    }

    /**
     * Get comprehensive statistics for tournament closing
     */
    private function getAfsluitStatistieken(Toernooi $toernooi): array
    {
        $judokas = $toernooi->judokas;
        $poules = $toernooi->poules()->with('wedstrijden')->get();

        // Basic counts
        $totaalJudokas = $judokas->count();
        $totaalClubs = $judokas->whereNotNull('club_id')->pluck('club_id')->unique()->count();
        $totaalPoules = $poules->where('type', '!=', 'eliminatie')->count();
        $totaalEliminaties = $poules->where('type', 'eliminatie')->count();
        $totaalWedstrijden = $poules->sum(fn($p) => $p->wedstrijden->count());
        $gespeeldeWedstrijden = $poules->sum(fn($p) => $p->wedstrijden->whereNotNull('winnaar_id')->count());

        // Leeftijdsklassen breakdown - sort by sort_categorie (young to old)
        $perLeeftijdsklasse = $judokas
            ->sortBy('sort_categorie')
            ->groupBy('leeftijdsklasse')
            ->map(fn($g) => $g->count());

        // Gender breakdown
        $jongens = $judokas->where('geslacht', 'M')->count();
        $meisjes = $judokas->where('geslacht', 'V')->count();

        // Weight statistics
        $gewogen = $judokas->whereNotNull('gewicht_gewogen')->count();

        // Medals (assuming 1st, 2nd, 3rd per poule)
        $aantalMedailles = ($totaalPoules + $totaalEliminaties) * 3;

        return [
            'totaal_judokas' => $totaalJudokas,
            'totaal_clubs' => $totaalClubs,
            'totaal_poules' => $totaalPoules,
            'totaal_eliminaties' => $totaalEliminaties,
            'totaal_wedstrijden' => $totaalWedstrijden,
            'gespeelde_wedstrijden' => $gespeeldeWedstrijden,
            'voltooiings_percentage' => $totaalWedstrijden > 0 ? round(($gespeeldeWedstrijden / $totaalWedstrijden) * 100) : 0,
            'per_leeftijdsklasse' => $perLeeftijdsklasse,
            'jongens' => $jongens,
            'meisjes' => $meisjes,
            'gewogen' => $gewogen,
            'niet_gewogen' => $totaalJudokas - $gewogen,
            'aantal_medailles' => $aantalMedailles,
            'aantal_blokken' => $toernooi->blokken->count(),
            'aantal_matten' => $toernooi->matten->count(),
        ];
    }

    /**
     * Calculate club ranking (delegated to PubliekResultatenController)
     */
    private function getClubRanking(Toernooi $toernooi): array
    {
        $publiekController = app(\App\Http\Controllers\PubliekResultatenController::class);
        return $publiekController->getClubRanking($toernooi);
    }
}
