<?php

namespace App\Http\Controllers;

use App\Models\Organisator;
use App\Models\WimpelJudoka;
use App\Models\WimpelMilestone;
use App\Models\Toernooi;
use App\Services\WimpelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WimpelController extends Controller
{
    public function __construct(private WimpelService $wimpelService)
    {
    }

    public function index(Organisator $organisator): View
    {
        $this->authorizeAccess($organisator);

        $judokas = $organisator->wimpelJudokas()
            ->orderByDesc('punten_totaal')
            ->get();

        $milestones = $organisator->wimpelMilestones()->get();
        $onverwerkteToernooien = $this->wimpelService->getOnverwerkteToernooien($organisator);

        // Milestone-alerts: judoka's die recent een milestone hebben bereikt
        $milestoneAlerts = [];
        foreach ($judokas as $judoka) {
            $bereikt = $judoka->getBereikteMilestones();
            $volgende = $judoka->getEerstvolgeneMilestone();
            $judoka->bereikteMilestones = $bereikt;
            $judoka->volgendeMilestone = $volgende;
        }

        return view('organisator.wimpel.index', compact(
            'organisator', 'judokas', 'milestones', 'onverwerkteToernooien'
        ));
    }

    public function show(Organisator $organisator, WimpelJudoka $wimpelJudoka): View
    {
        $this->authorizeAccess($organisator);
        $this->authorizeJudoka($organisator, $wimpelJudoka);

        $wimpelJudoka->load('puntenLog.toernooi');
        $milestones = $organisator->wimpelMilestones()->get();

        return view('organisator.wimpel.show', compact(
            'organisator', 'wimpelJudoka', 'milestones'
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

    public function aanpassen(Request $request, Organisator $organisator, WimpelJudoka $wimpelJudoka): JsonResponse
    {
        $this->authorizeAccess($organisator);
        $this->authorizeJudoka($organisator, $wimpelJudoka);

        $validated = $request->validate([
            'punten' => 'required|integer',
            'notitie' => 'nullable|string|max:255',
        ]);

        $bereikt = $this->wimpelService->handmatigAanpassen(
            $wimpelJudoka,
            $validated['punten'],
            $validated['notitie'] ?? null
        );

        return response()->json([
            'success' => true,
            'punten_totaal' => $wimpelJudoka->fresh()->punten_totaal,
            'milestones_bereikt' => $bereikt,
        ]);
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

    private function authorizeAccess(Organisator $organisator): void
    {
        $loggedIn = auth('organisator')->user();
        if ($loggedIn->id !== $organisator->id && !$loggedIn->isSitebeheerder()) {
            abort(403);
        }
    }

    private function authorizeJudoka(Organisator $organisator, WimpelJudoka $wimpelJudoka): void
    {
        if ($wimpelJudoka->organisator_id !== $organisator->id) {
            abort(403);
        }
    }
}
