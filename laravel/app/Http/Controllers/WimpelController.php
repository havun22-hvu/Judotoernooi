<?php

namespace App\Http\Controllers;

use App\Exports\WimpelExport;
use App\Models\Organisator;
use App\Models\StamJudoka;
use App\Models\WimpelMilestone;
use App\Models\WimpelUitreiking;
use App\Models\Toernooi;
use App\Services\WimpelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class WimpelController extends Controller
{
    public function __construct(private WimpelService $wimpelService)
    {
    }

    public function index(Organisator $organisator): View
    {
        $this->authorizeAccess($organisator);

        $judokas = $organisator->stamJudokas()
            ->metWimpel()
            ->orderByDesc('wimpel_punten_totaal')
            ->get();

        $milestones = $organisator->wimpelMilestones()->get();
        $onverwerkteToernooien = $this->wimpelService->getOnverwerkteToernooien($organisator);

        foreach ($judokas as $judoka) {
            $bereikt = $judoka->getBereikteWimpelMilestones();
            $volgende = $judoka->getEerstvolgendeWimpelMilestone();
            $judoka->bereikteMilestones = $bereikt;
            $judoka->volgendeMilestone = $volgende;
        }

        // Open uitreikingen (milestone bereikt maar nog niet uitgereikt)
        $openUitreikingen = WimpelUitreiking::where('uitgereikt', false)
            ->whereHas('stamJudoka', fn($q) => $q->where('organisator_id', $organisator->id))
            ->with(['stamJudoka', 'milestone'])
            ->get()
            ->sortBy('milestone.punten')
            ->values();

        return view('organisator.wimpel.index', compact(
            'organisator', 'judokas', 'milestones', 'onverwerkteToernooien', 'openUitreikingen'
        ));
    }

    public function show(Organisator $organisator, StamJudoka $stamJudoka): View
    {
        $this->authorizeAccess($organisator);
        $this->authorizeJudoka($organisator, $stamJudoka);

        $stamJudoka->load('wimpelPuntenLog.toernooi', 'wimpelUitreikingen.milestone');
        $milestones = $organisator->wimpelMilestones()->get();

        return view('organisator.wimpel.show', compact(
            'organisator', 'stamJudoka', 'milestones'
        ));
    }

    public function instellingen(Organisator $organisator): View
    {
        $this->authorizeAccess($organisator);

        $milestones = $organisator->wimpelMilestones()->get();

        return view('organisator.wimpel.instellingen', compact('organisator', 'milestones'));
    }

    public function storeMilestone(Request $request, Organisator $organisator): JsonResponse
    {
        $this->authorizeAccess($organisator);

        $validated = $request->validate([
            'punten' => 'required|integer|min:1',
            'omschrijving' => 'required|string|max:255',
        ]);

        $volgorde = $organisator->wimpelMilestones()->max('volgorde') + 1;

        $milestone = WimpelMilestone::create([
            'organisator_id' => $organisator->id,
            'punten' => $validated['punten'],
            'omschrijving' => $validated['omschrijving'],
            'volgorde' => $volgorde,
        ]);

        return response()->json(['success' => true, 'milestone' => $milestone]);
    }

    public function updateMilestone(Request $request, Organisator $organisator, WimpelMilestone $milestone): JsonResponse
    {
        $this->authorizeAccess($organisator);

        if ($milestone->organisator_id !== $organisator->id) {
            abort(403);
        }

        $validated = $request->validate([
            'punten' => 'required|integer|min:1',
            'omschrijving' => 'required|string|max:255',
        ]);

        $milestone->update($validated);

        return response()->json(['success' => true, 'milestone' => $milestone]);
    }

    public function destroyMilestone(Organisator $organisator, WimpelMilestone $milestone): JsonResponse
    {
        $this->authorizeAccess($organisator);

        if ($milestone->organisator_id !== $organisator->id) {
            abort(403);
        }

        $milestone->delete();

        return response()->json(['success' => true]);
    }

    public function aanpassen(Request $request, Organisator $organisator, StamJudoka $stamJudoka): JsonResponse
    {
        $this->authorizeAccess($organisator);
        $this->authorizeJudoka($organisator, $stamJudoka);

        $validated = $request->validate([
            'punten' => 'required|integer',
            'notitie' => 'nullable|string|max:255',
        ]);

        $bereikt = $this->wimpelService->handmatigAanpassen(
            $stamJudoka,
            $validated['punten'],
            $validated['notitie'] ?? null
        );

        // Handmatig aanpassen = bevestigd (niet meer nieuw)
        if ($stamJudoka->wimpel_is_nieuw) {
            $stamJudoka->update(['wimpel_is_nieuw' => false]);
        }

        return response()->json([
            'success' => true,
            'punten_totaal' => $stamJudoka->fresh()->wimpel_punten_totaal,
            'milestones_bereikt' => $bereikt,
        ]);
    }

    public function export(Organisator $organisator, string $format = 'xlsx')
    {
        $this->authorizeAccess($organisator);

        $filename = sprintf('wimpel_%s_%s', $organisator->slug, now()->format('Y-m-d'));

        return match ($format) {
            'csv' => Excel::download(new WimpelExport($organisator), "{$filename}.csv", \Maatwebsite\Excel\Excel::CSV),
            default => Excel::download(new WimpelExport($organisator), "{$filename}.xlsx"),
        };
    }

    public function bevestigJudoka(Organisator $organisator, StamJudoka $stamJudoka): JsonResponse
    {
        $this->authorizeAccess($organisator);
        $this->authorizeJudoka($organisator, $stamJudoka);

        $stamJudoka->update(['wimpel_is_nieuw' => false]);

        return response()->json(['success' => true]);
    }

    public function verwerkToernooi(Request $request, Organisator $organisator): JsonResponse
    {
        $this->authorizeAccess($organisator);

        $validated = $request->validate([
            'toernooi_id' => 'required|exists:toernooien,id',
        ]);

        $toernooi = Toernooi::findOrFail($validated['toernooi_id']);

        if ($toernooi->organisator_id !== $organisator->id) {
            abort(403);
        }

        if ($this->wimpelService->isAlVerwerkt($toernooi)) {
            return response()->json(['error' => 'Dit toernooi is al verwerkt.'], 422);
        }

        $milestoneWarnings = $this->wimpelService->verwerkToernooi($toernooi);

        return response()->json([
            'success' => true,
            'milestones' => $milestoneWarnings,
            'message' => 'Punten bijgeschreven!',
        ]);
    }

    /**
     * Stuur wimpel-uitreiking handmatig naar de spreker queue
     */
    public function stuurNaarSpreker(Request $request, Organisator $organisator, StamJudoka $stamJudoka): JsonResponse
    {
        $this->authorizeAccess($organisator);
        $this->authorizeJudoka($organisator, $stamJudoka);

        $validated = $request->validate([
            'milestone_id' => 'required|exists:wimpel_milestones,id',
        ]);

        $milestone = WimpelMilestone::findOrFail($validated['milestone_id']);

        if ($milestone->organisator_id !== $organisator->id) {
            abort(403);
        }

        // Find active tournament for this organizer (most recent with wedstrijddag status)
        $toernooi = $organisator->toernooien()
            ->where('status', 'wedstrijddag')
            ->orderByDesc('datum')
            ->first();

        if (!$toernooi) {
            return response()->json(['error' => 'Geen actief toernooi gevonden. Start eerst een wedstrijddag.'], 422);
        }

        // Create or find uitreiking
        $uitreiking = WimpelUitreiking::firstOrCreate(
            [
                'stam_judoka_id' => $stamJudoka->id,
                'wimpel_milestone_id' => $milestone->id,
            ],
            [
                'toernooi_id' => $toernooi->id,
                'uitgereikt' => false,
            ]
        );

        // Reset to not-uitgereikt so it appears in the spreker queue again
        if ($uitreiking->uitgereikt) {
            $uitreiking->update([
                'uitgereikt' => false,
                'uitgereikt_at' => null,
                'toernooi_id' => $toernooi->id,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Uitreiking naar spreker gestuurd',
        ]);
    }

    public function handmatigUitreiken(Request $request, Organisator $organisator, StamJudoka $stamJudoka): JsonResponse
    {
        $this->authorizeAccess($organisator);
        $this->authorizeJudoka($organisator, $stamJudoka);

        $validated = $request->validate([
            'milestone_id' => 'required|exists:wimpel_milestones,id',
            'datum' => 'required|date|before_or_equal:today',
        ]);

        $milestone = WimpelMilestone::findOrFail($validated['milestone_id']);

        if ($milestone->organisator_id !== $organisator->id) {
            abort(403);
        }

        WimpelUitreiking::updateOrCreate(
            [
                'stam_judoka_id' => $stamJudoka->id,
                'wimpel_milestone_id' => $milestone->id,
            ],
            [
                'uitgereikt' => true,
                'uitgereikt_at' => $validated['datum'],
            ]
        );

        return response()->json(['success' => true]);
    }

    private function authorizeAccess(Organisator $organisator): void
    {
        $loggedIn = auth('organisator')->user();
        if ($loggedIn->id !== $organisator->id && !$loggedIn->isSitebeheerder()) {
            abort(403);
        }
    }

    private function authorizeJudoka(Organisator $organisator, StamJudoka $stamJudoka): void
    {
        if ($stamJudoka->organisator_id !== $organisator->id) {
            abort(403);
        }
    }
}
