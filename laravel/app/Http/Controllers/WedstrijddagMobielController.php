<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Organisator;
use App\Models\Judoka;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Handles the mobile organizer view and judoka mutations on match day
 * (afmelden, toevoegen, herstellen). Split out of WedstrijddagController
 * to keep the main controller focused on poule management and doorsturen.
 */
class WedstrijddagMobielController extends Controller
{
    /**
     * Mobile organizer view - quick-actions for walking around on tournament day
     */
    public function mobiel(Organisator $organisator, Toernooi $toernooi): View
    {
        $statistieken = app(\App\Services\ToernooiService::class)->getStatistieken($toernooi);

        $matten = $toernooi->matten()->orderBy('nummer')->get();
        $matVoortgang = $matten->map(fn($mat) => $this->buildMatVoortgang($mat, $toernooi));

        $clubs = Club::whereHas('judokas', fn($q) => $q->where('toernooi_id', $toernooi->id))
            ->orderBy('naam')
            ->get();

        return view('pages.toernooi.mobiel', compact('toernooi', 'statistieken', 'matVoortgang', 'clubs'));
    }

    /**
     * API: poule list for mobile view dropdowns
     */
    public function poulesApi(Organisator $organisator, Toernooi $toernooi): JsonResponse
    {
        $poules = Poule::where('toernooi_id', $toernooi->id)
            ->with('blok')
            ->orderBy('nummer')
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'nummer' => $p->nummer,
                'leeftijdsklasse' => $p->leeftijdsklasse,
                'gewichtsklasse' => $p->gewichtsklasse,
                'blok' => $p->blok?->nummer,
                'aantal_judokas' => $p->aantal_judokas,
            ]);

        return response()->json($poules);
    }

    /**
     * API: mat progress data for mobile view (refreshable)
     */
    public function matVoortgangApi(Organisator $organisator, Toernooi $toernooi): JsonResponse
    {
        $matten = $toernooi->matten()->orderBy('nummer')->get();
        $data = $matten->map(fn($mat) => $this->buildMatVoortgang($mat, $toernooi));

        return response()->json($data);
    }

    /**
     * Meld judoka af (kan niet deelnemen)
     */
    public function meldJudokaAf(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'judoka_id' => 'required|integer|exists:judokas,id',
        ]);

        $judoka = Judoka::where('toernooi_id', $toernooi->id)
            ->where('id', $validated['judoka_id'])
            ->first();

        if (!$judoka) {
            return response()->json(['success' => false, 'message' => 'Judoka niet gevonden'], 404);
        }

        return DB::transaction(function () use ($judoka, $toernooi) {
            $pouleIds = $judoka->poules()->pluck('poules.id');

            $judoka->update(['aanwezigheid' => 'afwezig']);
            $judoka->poules()->detach();

            foreach ($pouleIds as $pouleId) {
                $poule = Poule::find($pouleId);
                if ($poule) {
                    $poule->updateStatistieken();
                }
            }

            ActivityLogger::log($toernooi, 'meld_af', "{$judoka->naam} afgemeld", [
                'model' => $judoka,
                'interface' => 'hoofdjury',
            ]);

            return response()->json([
                'success' => true,
                'message' => $judoka->naam . ' is afgemeld',
            ]);
        });
    }

    /**
     * Quick-add judoka on match day and attach to poule
     */
    public function nieuweJudoka(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'naam' => 'required|string|max:255',
            'band' => 'nullable|string|max:20',
            'gewicht' => 'nullable|numeric|min:10|max:200',
            'geboortejaar' => 'nullable|integer|min:1990|max:' . date('Y'),
            'club_id' => 'nullable|exists:clubs,id',
            'poule_id' => 'required|exists:poules,id',
        ]);

        if (!$toernooi->canAddMoreJudokas()) {
            return response()->json(['success' => false, 'message' => 'Maximum aantal judoka\'s bereikt'], 400);
        }

        $poule = Poule::where('toernooi_id', $toernooi->id)
            ->where('id', $validated['poule_id'])
            ->first();

        if (!$poule) {
            return response()->json(['success' => false, 'message' => 'Poule niet gevonden'], 404);
        }

        return DB::transaction(function () use ($validated, $poule, $toernooi) {
            $leeftijdsklasse = $poule->leeftijdsklasse;
            $gewichtsklasse = $poule->gewichtsklasse;

            if (!empty($validated['geboortejaar'])) {
                $toernooiJaar = $toernooi->datum ? $toernooi->datum->year : (int) date('Y');
                $leeftijd = $toernooiJaar - $validated['geboortejaar'];

                if (!empty($validated['gewicht'])) {
                    $bepaaldeGewichtsklasse = $toernooi->bepaalGewichtsklasse($validated['gewicht'], $leeftijd, null, $validated['band'] ?? null);
                    if ($bepaaldeGewichtsklasse) {
                        $gewichtsklasse = $bepaaldeGewichtsklasse;
                    }
                }
            }

            $judoka = Judoka::create([
                'toernooi_id' => $toernooi->id,
                'club_id' => $validated['club_id'] ?? null,
                'naam' => $validated['naam'],
                'geboortejaar' => $validated['geboortejaar'] ?? null,
                'band' => $validated['band'] ?? null,
                'gewicht' => $validated['gewicht'] ?? null,
                'gewicht_gewogen' => $validated['gewicht'] ?? null,
                'leeftijdsklasse' => $leeftijdsklasse,
                'gewichtsklasse' => $gewichtsklasse,
                'aanwezigheid' => 'aanwezig',
            ]);

            $maxPositie = $poule->judokas()->max('positie') ?? 0;
            $poule->judokas()->attach($judoka->id, ['positie' => $maxPositie + 1]);
            $poule->updateStatistieken();

            ActivityLogger::log($toernooi, 'nieuwe_judoka', "{$judoka->naam} toegevoegd aan poule {$poule->nummer}", [
                'model' => $judoka,
                'properties' => ['poule_id' => $poule->id],
                'interface' => 'hoofdjury',
            ]);

            return response()->json([
                'success' => true,
                'message' => $judoka->naam . ' toegevoegd aan poule ' . $poule->nummer,
                'judoka' => $judoka,
            ]);
        });
    }

    /**
     * Herstel afgemelde judoka (terugzetten naar actief)
     */
    public function herstelJudoka(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'judoka_id' => 'required|integer|exists:judokas,id',
        ]);

        $judoka = Judoka::where('toernooi_id', $toernooi->id)
            ->where('id', $validated['judoka_id'])
            ->first();

        if (!$judoka) {
            return response()->json(['success' => false, 'message' => 'Judoka niet gevonden'], 404);
        }

        $judoka->update(['aanwezigheid' => null]);

        ActivityLogger::log($toernooi, 'herstel_judoka', "{$judoka->naam} hersteld", [
            'model' => $judoka,
            'interface' => 'hoofdjury',
        ]);

        return response()->json([
            'success' => true,
            'message' => $judoka->naam . ' is hersteld',
        ]);
    }

    /**
     * Build mat progress data (shared between mobiel view and API).
     */
    private function buildMatVoortgang($mat, Toernooi $toernooi): array
    {
        $poules = Poule::where('toernooi_id', $toernooi->id)
            ->where('mat_id', $mat->id)
            ->with('wedstrijden')
            ->get();

        $totaalWedstrijden = 0;
        $gespeeld = 0;
        $pouleDetails = [];

        foreach ($poules as $poule) {
            $pouleTotal = $poule->wedstrijden->count();
            $poulePlayed = $poule->wedstrijden->where('is_gespeeld', true)->count();
            $totaalWedstrijden += $pouleTotal;
            $gespeeld += $poulePlayed;

            $pouleDetails[] = [
                'id' => $poule->id,
                'nummer' => $poule->nummer,
                'leeftijdsklasse' => $poule->leeftijdsklasse,
                'totaal' => $pouleTotal,
                'gespeeld' => $poulePlayed,
                'resterend' => $pouleTotal - $poulePlayed,
            ];
        }

        return [
            'id' => $mat->id,
            'nummer' => $mat->nummer,
            'naam' => $mat->naam ?? "Mat {$mat->nummer}",
            'totaal_wedstrijden' => $totaalWedstrijden,
            'gespeeld' => $gespeeld,
            'resterend' => $totaalWedstrijden - $gespeeld,
            'poules' => $pouleDetails,
        ];
    }
}
