<?php

namespace App\Http\Controllers;

use App\Models\Toernooi;
use App\Models\ToernooiTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ToernooiTemplateController extends Controller
{
    /**
     * List all templates for the logged-in organisator
     */
    public function index(): JsonResponse
    {
        $organisator = auth('organisator')->user();

        $templates = $organisator->toernooiTemplates()
            ->orderBy('naam')
            ->get();

        return response()->json($templates);
    }

    /**
     * Save current toernooi settings as a new template
     */
    public function store(Request $request, Toernooi $toernooi): JsonResponse
    {
        $organisator = auth('organisator')->user();

        if (!$organisator->hasAccessToToernooi($toernooi)) {
            return response()->json(['error' => 'Geen toegang'], 403);
        }

        $validated = $request->validate([
            'naam' => 'required|string|max:100',
            'beschrijving' => 'nullable|string|max:500',
        ]);

        // Check if template with this name already exists
        $bestaand = $organisator->toernooiTemplates()
            ->where('naam', $validated['naam'])
            ->first();

        if ($bestaand) {
            return response()->json([
                'error' => 'Er bestaat al een template met deze naam'
            ], 422);
        }

        $template = ToernooiTemplate::createFromToernooi(
            $toernooi,
            $validated['naam'],
            $validated['beschrijving'] ?? null
        );

        return response()->json([
            'success' => true,
            'template' => $template,
            'message' => "Template '{$template->naam}' opgeslagen",
        ]);
    }

    /**
     * Update an existing template from current toernooi settings
     */
    public function update(Request $request, ToernooiTemplate $template, Toernooi $toernooi): JsonResponse
    {
        $organisator = auth('organisator')->user();

        if ($template->organisator_id !== $organisator->id) {
            return response()->json(['error' => 'Geen toegang'], 403);
        }

        if (!$organisator->hasAccessToToernooi($toernooi)) {
            return response()->json(['error' => 'Geen toegang tot toernooi'], 403);
        }

        $validated = $request->validate([
            'naam' => 'sometimes|string|max:100',
            'beschrijving' => 'nullable|string|max:500',
        ]);

        // Update instellingen from toernooi
        $instellingen = [
            'gewichtsklassen' => $toernooi->gewichtsklassen,
            'gewichtsklassen_is_preset' => $toernooi->gewichtsklassen_is_preset,
            'eliminatie_gewichtsklassen' => $toernooi->eliminatie_gewichtsklassen,
            'max_per_poule' => $toernooi->max_per_poule,
            'wedstrijdtijd' => $toernooi->wedstrijdtijd,
            'wedstrijdtijd_finale' => $toernooi->wedstrijdtijd_finale,
            'pauze_tussen_wedstrijden' => $toernooi->pauze_tussen_wedstrijden,
            'golden_score_tijd' => $toernooi->golden_score_tijd,
            'gewicht_tolerantie' => $toernooi->gewicht_tolerantie,
            'betaling_actief' => $toernooi->betaling_actief,
            'inschrijfgeld' => $toernooi->inschrijfgeld,
            'mollie_mode' => $toernooi->mollie_mode,
            'portal_modus' => $toernooi->portal_modus,
            'max_judokas' => $toernooi->max_judokas,
            'judokas_per_coach' => $toernooi->judokas_per_coach,
            'toon_clubs_publiek' => $toernooi->toon_clubs_publiek,
        ];

        $template->update([
            'naam' => $validated['naam'] ?? $template->naam,
            'beschrijving' => $validated['beschrijving'] ?? $template->beschrijving,
            'instellingen' => $instellingen,
            'max_judokas' => $toernooi->max_judokas,
            'inschrijfgeld' => $toernooi->inschrijfgeld,
            'betaling_actief' => $toernooi->betaling_actief,
            'portal_modus' => $toernooi->portal_modus,
        ]);

        return response()->json([
            'success' => true,
            'template' => $template,
            'message' => "Template '{$template->naam}' bijgewerkt",
        ]);
    }

    /**
     * Delete a template
     */
    public function destroy(ToernooiTemplate $template): JsonResponse
    {
        $organisator = auth('organisator')->user();

        if ($template->organisator_id !== $organisator->id) {
            return response()->json(['error' => 'Geen toegang'], 403);
        }

        $naam = $template->naam;
        $template->delete();

        return response()->json([
            'success' => true,
            'message' => "Template '{$naam}' verwijderd",
        ]);
    }

    /**
     * Get a single template
     */
    public function show(ToernooiTemplate $template): JsonResponse
    {
        $organisator = auth('organisator')->user();

        if ($template->organisator_id !== $organisator->id) {
            return response()->json(['error' => 'Geen toegang'], 403);
        }

        return response()->json($template);
    }
}
