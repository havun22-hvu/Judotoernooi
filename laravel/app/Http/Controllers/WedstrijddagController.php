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
        // Get all poules with blok info
        $poules = $toernooi->poules()
            ->with(['judokas.club', 'blok', 'mat'])
            ->get();

        // Find judokas that need to be re-pooled (weighed but outside weight class)
        $judokasNaarWachtruimte = Judoka::where('toernooi_id', $toernooi->id)
            ->whereNotNull('gewicht_gewogen')
            ->where('aanwezigheid', 'aanwezig')
            ->with('club')
            ->get()
            ->filter(fn($judoka) => !$judoka->isGewichtBinnenKlasse());

        // Group by blok first, then by category within each blok
        $blokken = $toernooi->blokken()->orderBy('nummer')->get()->map(function ($blok) use ($poules, $judokasNaarWachtruimte) {
            $blokPoules = $poules->where('blok_id', $blok->id);

            // Group by category and sort
            $leeftijdVolgorde = [
                "Mini's" => 1,
                'A-pupillen' => 2,
                'B-pupillen' => 3,
                'Dames -15' => 4,
                'Heren -15' => 5,
                'Dames -18' => 6,
                'Heren -18' => 7,
                'Dames' => 8,
                'Heren' => 9,
            ];

            $categories = $blokPoules
                ->filter(fn($poule) => $poule->judokas->count() > 0) // Filter lege poules
                ->groupBy(function ($poule) {
                    return $poule->leeftijdsklasse . '|' . $poule->gewichtsklasse;
                })->map(function ($categoryPoules, $key) use ($leeftijdVolgorde) {
                    [$leeftijdsklasse, $gewichtsklasse] = explode('|', $key);
                    // Extract numeric weight for sorting
                    $gewichtNum = floatval(preg_replace('/[^0-9.]/', '', $gewichtsklasse));
                    return [
                        'key' => $key,
                        'leeftijdsklasse' => $leeftijdsklasse,
                        'gewichtsklasse' => $gewichtsklasse,
                        'leeftijd_sort' => $leeftijdVolgorde[$leeftijdsklasse] ?? 99,
                        'gewicht_sort' => $gewichtNum,
                        'poules' => $categoryPoules->sortBy('nummer'),
                        'wachtruimte' => [],
                    ];
                })->sortBy([
                ['leeftijd_sort', 'asc'],
                ['gewicht_sort', 'asc'],
            ])->values();

            // Add wachtruimte judokas to their NEW category (based on actual weight)
            foreach ($judokasNaarWachtruimte as $judoka) {
                $nieuweKey = $this->bepaalNieuweCategorie($judoka);
                if (!$nieuweKey) continue;

                $categories = $categories->map(function ($cat) use ($judoka, $nieuweKey) {
                    if ($cat['key'] === $nieuweKey) {
                        $cat['wachtruimte'][] = $judoka;
                    }
                    return $cat;
                });
            }

            return [
                'id' => $blok->id,
                'nummer' => $blok->nummer,
                'naam' => $blok->naam,
                'categories' => $categories,
            ];
        });

        // Get sent-to-zaaloverzicht status from session
        $sentToZaaloverzicht = session("toernooi_{$toernooi->id}_wedstrijddag_sent", []);

        return view('pages.wedstrijddag.poules', compact('toernooi', 'blokken', 'sentToZaaloverzicht'));
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

        // Map label to config key
        $labelToKey = [
            "Mini's" => 'minis',
            'A-pupillen' => 'a_pupillen',
            'B-pupillen' => 'b_pupillen',
            'Dames -15' => 'dames_15',
            'Heren -15' => 'heren_15',
            'Dames -18' => 'dames_18',
            'Heren -18' => 'heren_18',
            'Dames' => 'dames',
            'Heren' => 'heren',
        ];

        $configKey = $labelToKey[$judoka->leeftijdsklasse] ?? null;
        if (!$configKey) return null;

        // Get weight classes from config
        $gewichtsklassen = config("toernooi.leeftijdsklassen.{$configKey}.gewichtsklassen", []);
        if (empty($gewichtsklassen)) return null;

        // Find matching weight class (gewichtsklassen are integers: -20, -23, 29, etc.)
        foreach ($gewichtsklassen as $klasse) {
            $isPlusKlasse = $klasse > 0;
            $limiet = abs($klasse);

            if ($isPlusKlasse) {
                // +29 means minimum 29kg
                if ($gewicht >= $limiet) {
                    return $judoka->leeftijdsklasse . '|+' . $limiet;
                }
            } else {
                // -20 means maximum 20kg
                if ($gewicht <= $limiet) {
                    return $judoka->leeftijdsklasse . '|-' . $limiet;
                }
            }
        }

        return null;
    }
}
