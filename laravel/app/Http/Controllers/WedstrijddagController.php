<?php

namespace App\Http\Controllers;

use App\Models\Judoka;
use App\Models\Poule;
use App\Models\Toernooi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WedstrijddagController extends Controller
{
    public function poules(Toernooi $toernooi): View
    {
        // Get all poules grouped by category (leeftijdsklasse + gewichtsklasse)
        $poules = $toernooi->poules()
            ->with(['judokas.club', 'blok', 'mat'])
            ->orderBy('leeftijdsklasse')
            ->orderBy('gewichtsklasse')
            ->get();

        // Group poules by category
        $categories = $poules->groupBy(function ($poule) {
            return $poule->leeftijdsklasse . '|' . $poule->gewichtsklasse;
        })->map(function ($categoryPoules, $key) {
            [$leeftijdsklasse, $gewichtsklasse] = explode('|', $key);
            return [
                'key' => $key,
                'leeftijdsklasse' => $leeftijdsklasse,
                'gewichtsklasse' => $gewichtsklasse,
                'poules' => $categoryPoules,
                'wachtruimte' => [], // Will be filled with judokas waiting to be re-pooled
            ];
        });

        // Find judokas that need to be re-pooled (weighed but outside weight class)
        $judokasNaarWachtruimte = Judoka::where('toernooi_id', $toernooi->id)
            ->whereNotNull('gewicht_gewogen')
            ->where('aanwezigheid', 'aanwezig')
            ->with('club')
            ->get()
            ->filter(function ($judoka) {
                return !$judoka->isGewichtBinnenKlasse();
            });

        // Add to wachtruimte per category
        foreach ($judokasNaarWachtruimte as $judoka) {
            $targetCategory = $this->bepaalNieuweCategorie($judoka);
            if ($targetCategory && isset($categories[$targetCategory])) {
                $categories[$targetCategory]['wachtruimte'][] = $judoka;
            }
        }

        // Get sent-to-zaaloverzicht status from session
        $sentToZaaloverzicht = session("toernooi_{$toernooi->id}_wedstrijddag_sent", []);

        return view('pages.wedstrijddag.poules', compact('toernooi', 'categories', 'sentToZaaloverzicht'));
    }

    public function verplaatsJudoka(Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'judoka_id' => 'required|exists:judokas,id',
            'poule_id' => 'required|exists:poules,id',
            'from_poule_id' => 'nullable|exists:poules,id',
        ]);

        $judoka = Judoka::findOrFail($validated['judoka_id']);
        $nieuwePoule = Poule::findOrFail($validated['poule_id']);

        // Remove from old poule if specified
        if (!empty($validated['from_poule_id'])) {
            $oudePoule = Poule::findOrFail($validated['from_poule_id']);
            $oudePoule->judokas()->detach($judoka->id);
            $oudePoule->updateStatistieken();
        }

        // Add to new poule
        $maxPositie = $nieuwePoule->judokas()->max('poule_judoka.positie') ?? 0;
        $nieuwePoule->judokas()->attach($judoka->id, ['positie' => $maxPositie + 1]);
        $nieuwePoule->updateStatistieken();

        // Mark judoka as re-pooled
        $judoka->update(['opmerking' => 'Overgepouled']);

        return response()->json(['success' => true]);
    }

    public function naarZaaloverzicht(Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'category' => 'required|string',
        ]);

        // Store in session that this category has been sent to zaaloverzicht
        $sent = session("toernooi_{$toernooi->id}_wedstrijddag_sent", []);
        $sent[$validated['category']] = true;
        session(["toernooi_{$toernooi->id}_wedstrijddag_sent" => $sent]);

        return response()->json(['success' => true]);
    }

    public function nieuwePoule(Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'leeftijdsklasse' => 'required|string',
            'gewichtsklasse' => 'required|string',
        ]);

        // Find max nummer for this category
        $maxNummer = Poule::where('toernooi_id', $toernooi->id)
            ->where('leeftijdsklasse', $validated['leeftijdsklasse'])
            ->where('gewichtsklasse', $validated['gewichtsklasse'])
            ->max('nummer') ?? 0;

        // Find the blok for this category
        $existingPoule = Poule::where('toernooi_id', $toernooi->id)
            ->where('leeftijdsklasse', $validated['leeftijdsklasse'])
            ->whereNotNull('blok_id')
            ->first();

        $poule = Poule::create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $existingPoule?->blok_id,
            'leeftijdsklasse' => $validated['leeftijdsklasse'],
            'gewichtsklasse' => $validated['gewichtsklasse'],
            'nummer' => $maxNummer + 1,
            'titel' => $validated['leeftijdsklasse'] . ' ' . $validated['gewichtsklasse'] . ' Poule ' . ($maxNummer + 1),
            'type' => 'voorronde',
            'aantal_judokas' => 0,
            'aantal_wedstrijden' => 0,
        ]);

        return response()->json([
            'success' => true,
            'poule' => $poule,
        ]);
    }

    /**
     * Determine new category for judoka based on actual weight
     */
    private function bepaalNieuweCategorie(Judoka $judoka): ?string
    {
        $gewicht = $judoka->gewicht_gewogen;
        if (!$gewicht) return null;

        // Get all weight classes for this age group
        $gewichtsklassen = config("toernooi.gewichtsklassen.{$judoka->leeftijdsklasse}.{$judoka->geslacht}", []);

        // Find matching weight class
        foreach ($gewichtsklassen as $klasse) {
            $isPlusKlasse = str_starts_with($klasse, '+');
            $limiet = floatval(preg_replace('/[^0-9.]/', '', $klasse));

            if ($isPlusKlasse) {
                if ($gewicht >= $limiet) {
                    return $judoka->leeftijdsklasse . '|' . $klasse;
                }
            } else {
                if ($gewicht <= $limiet) {
                    return $judoka->leeftijdsklasse . '|' . $klasse;
                }
            }
        }

        return null;
    }
}
