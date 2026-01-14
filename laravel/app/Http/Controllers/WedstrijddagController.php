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
        // Herbereken kruisfinale aantallen op basis van actueel aantal voorrondepoules
        $this->herberkenKruisfinales($toernooi);

        // Get all poules with blok info
        $poules = $toernooi->poules()
            ->with(['judokas.club', 'judokas.toernooi', 'blok', 'mat'])
            ->get();

        // Find judokas that need to be re-pooled (weighed but outside weight class)
        // Gewogen = automatisch aanwezig (je kunt niet wegen zonder er te zijn)
        $judokasNaarWachtruimte = Judoka::where('toernooi_id', $toernooi->id)
            ->whereNotNull('gewicht_gewogen')
            ->with('club')
            ->get()
            ->filter(fn($judoka) => !$judoka->isGewichtBinnenKlasse());

        $tolerantie = $toernooi->gewicht_tolerantie ?? 0.5;

        // Laad gewichtsklassen config voor custom labels
        $gewichtsklassenConfig = $toernooi->gewichtsklassen ?? [];

        // Build label to key mapping from preset config
        $leeftijdsklasseToKey = [];
        foreach ($toernooi->getAlleGewichtsklassen() as $key => $data) {
            $label = $data['label'] ?? $key;
            $leeftijdsklasseToKey[$label] = $key;
        }

        // Group by blok first, then by category within each blok
        $blokken = $toernooi->blokken()->orderBy('nummer')->get()->map(function ($blok) use ($poules, $judokasNaarWachtruimte, $tolerantie, $gewichtsklassenConfig, $leeftijdsklasseToKey) {
            $blokPoules = $poules->where('blok_id', $blok->id);

            // Get sort order from preset config
            $leeftijdVolgorde = $gewichtsklassenConfig ? array_flip(array_map(fn($d) => $d['label'] ?? '', $gewichtsklassenConfig)) : [];

            $categories = $blokPoules
                // Toon ALLE poules (ook lege) - lege poules zijn nodig voor overpoelen
                ->groupBy(function ($poule) {
                    return $poule->leeftijdsklasse . '|' . $poule->gewichtsklasse;
                })->map(function ($categoryPoules, $key) use ($leeftijdVolgorde, $gewichtsklassenConfig, $leeftijdsklasseToKey) {
                    [$leeftijdsklasse, $gewichtsklasse] = explode('|', $key);
                    // Extract numeric weight for sorting
                    $gewichtNum = floatval(preg_replace('/[^0-9.]/', '', $gewichtsklasse));
                    // Check if this is an elimination category
                    $isEliminatie = $categoryPoules->first()?->type === 'eliminatie';

                    // Bepaal custom label uit config
                    $configKey = $leeftijdsklasseToKey[$leeftijdsklasse] ?? null;
                    $label = $leeftijdsklasse; // default: JBN label
                    if ($configKey && isset($gewichtsklassenConfig[$configKey]['label'])) {
                        $label = $gewichtsklassenConfig[$configKey]['label'];
                    }

                    // Bij lft-kg label: vervang door actuele leeftijd range
                    if (stripos($label, 'lft-kg') !== false) {
                        $huidigJaar = now()->year;
                        $alleLeeftijden = $categoryPoules->flatMap(fn($p) => $p->judokas->pluck('geboortejaar'))
                            ->filter()
                            ->map(fn($gj) => $huidigJaar - $gj);
                        if ($alleLeeftijden->isNotEmpty()) {
                            $minL = $alleLeeftijden->min();
                            $maxL = $alleLeeftijden->max();
                            $leeftijdRange = $minL === $maxL ? "{$minL}j" : "{$minL}-{$maxL}j";
                            $label = str_ireplace('lft-kg', $leeftijdRange, $label);
                        }
                    }

                    return [
                        'key' => $key,
                        'leeftijdsklasse' => $leeftijdsklasse,
                        'label' => $label,
                        'gewichtsklasse' => $gewichtsklasse,
                        'leeftijd_sort' => $leeftijdVolgorde[$leeftijdsklasse] ?? 99,
                        'gewicht_sort' => $gewichtNum,
                        'poules' => $categoryPoules->sortBy('nummer'),
                        'wachtruimte' => [],
                        'is_eliminatie' => $isEliminatie,
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

        // Get sent-to-zaaloverzicht status from database (doorgestuurd_op column)
        $sentToZaaloverzicht = $toernooi->poules()
            ->whereNotNull('doorgestuurd_op')
            ->get()
            ->groupBy(fn($p) => $p->leeftijdsklasse . '|' . $p->gewichtsklasse)
            ->map(fn() => true)
            ->toArray();

        return view('pages.wedstrijddag.poules', compact('toernooi', 'blokken', 'sentToZaaloverzicht'));
    }

    public function verplaatsJudoka(Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'judoka_id' => 'required|exists:judokas,id',
            'poule_id' => 'required|exists:poules,id',
            'from_poule_id' => 'nullable|exists:poules,id',
            'positions' => 'nullable|array',
            'positions.*.id' => 'required|exists:judokas,id',
            'positions.*.positie' => 'required|integer|min:1',
        ]);

        $judoka = Judoka::findOrFail($validated['judoka_id']);
        $nieuwePoule = Poule::findOrFail($validated['poule_id']);
        $oudePouleData = null;

        $tolerantie = $toernooi->gewicht_tolerantie ?? 0.5;

        // Remove from old poule(s)
        if (!empty($validated['from_poule_id'])) {
            // From specific poule (drag within poules)
            $oudePoule = Poule::findOrFail($validated['from_poule_id']);
            $oudePoule->judokas()->detach($judoka->id);
            $oudePoule->updateStatistieken();
            $oudePoule->load('judokas'); // Refresh judokas collection

            // Calculate active count for old poule
            $actieveJudokasOud = $oudePoule->judokas->filter(function($j) use ($tolerantie) {
                $isAfwezig = $j->aanwezigheid === 'afwezig';
                $isAfwijkend = $j->gewicht_gewogen !== null && !$j->isGewichtBinnenKlasse(null, $tolerantie);
                return !$isAfwezig && !$isAfwijkend;
            })->count();

            $oudePouleData = [
                'id' => $oudePoule->id,
                'aantal_judokas' => $actieveJudokasOud,
                'aantal_wedstrijden' => $oudePoule->berekenAantalWedstrijden($actieveJudokasOud),
            ];
        } else {
            // From wachtruimte - detach from ALL current poules
            foreach ($judoka->poules as $oudePoule) {
                $oudePoule->judokas()->detach($judoka->id);
                $oudePoule->updateStatistieken();
            }
        }

        // Update judoka's gewichtsklasse FIRST to match new poule (removes strikethrough)
        $judoka->update([
            'gewichtsklasse' => $nieuwePoule->gewichtsklasse,
            'opmerking' => 'Overgepouled',
        ]);

        // Check if judoka already in target poule (reordering within same poule)
        $alreadyInPoule = $nieuwePoule->judokas()->where('judoka_id', $judoka->id)->exists();

        if (!$alreadyInPoule) {
            // Add to new poule
            $nieuwePoule->judokas()->attach($judoka->id, ['positie' => 999]);
        }

        // Update positions if provided
        if (!empty($validated['positions'])) {
            foreach ($validated['positions'] as $pos) {
                $nieuwePoule->judokas()->updateExistingPivot($pos['id'], ['positie' => $pos['positie']]);
            }
        }

        $nieuwePoule->updateStatistieken();
        $nieuwePoule->load('judokas'); // Refresh judokas collection

        // Calculate active count (excluding afwezig/afwijkend gewicht)
        $actieveJudokasNieuw = $nieuwePoule->judokas->filter(function($j) use ($tolerantie) {
            $isAfwezig = $j->aanwezigheid === 'afwezig';
            $isAfwijkend = $j->gewicht_gewogen !== null && !$j->isGewichtBinnenKlasse(null, $tolerantie);
            return !$isAfwezig && !$isAfwijkend;
        })->count();

        return response()->json([
            'success' => true,
            'van_poule' => $oudePouleData,
            'naar_poule' => [
                'id' => $nieuwePoule->id,
                'aantal_judokas' => $actieveJudokasNieuw,
                'aantal_wedstrijden' => $nieuwePoule->berekenAantalWedstrijden($actieveJudokasNieuw),
            ],
        ]);
    }

    public function naarZaaloverzicht(Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'category' => 'required|string',
        ]);

        // Parse category key (leeftijdsklasse|gewichtsklasse)
        [$leeftijdsklasse, $gewichtsklasse] = explode('|', $validated['category']);

        // Zorg dat kruisfinales een mat_id hebben (kopieer van voorrondepoule)
        $voorrondeMatId = $toernooi->poules()
            ->where('leeftijdsklasse', $leeftijdsklasse)
            ->where('gewichtsklasse', $gewichtsklasse)
            ->where('type', 'voorronde')
            ->whereNotNull('mat_id')
            ->value('mat_id');

        if ($voorrondeMatId) {
            $toernooi->poules()
                ->where('leeftijdsklasse', $leeftijdsklasse)
                ->where('gewichtsklasse', $gewichtsklasse)
                ->where('type', 'kruisfinale')
                ->whereNull('mat_id')
                ->update(['mat_id' => $voorrondeMatId]);
        }

        // Update all poules for this category with doorgestuurd_op timestamp
        $updated = $toernooi->poules()
            ->where('leeftijdsklasse', $leeftijdsklasse)
            ->where('gewichtsklasse', $gewichtsklasse)
            ->update(['doorgestuurd_op' => now()]);

        return response()->json(['success' => true, 'updated' => $updated]);
    }

    public function nieuwePoule(Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'leeftijdsklasse' => 'required|string',
            'gewichtsklasse' => 'required|string',
        ]);

        // Find max nummer across entire tournament (nummer must be unique per toernooi)
        $maxNummer = Poule::where('toernooi_id', $toernooi->id)->max('nummer') ?? 0;

        // Find the blok for this category (same leeftijdsklasse + gewichtsklasse)
        $existingPoule = Poule::where('toernooi_id', $toernooi->id)
            ->where('leeftijdsklasse', $validated['leeftijdsklasse'])
            ->where('gewichtsklasse', $validated['gewichtsklasse'])
            ->whereNotNull('blok_id')
            ->first();

        // New poules always go to mat 1 (will be redistributed in zaaloverzicht)
        $mat1 = $toernooi->matten()->orderBy('nummer')->first();

        $poule = Poule::create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $existingPoule?->blok_id,
            'mat_id' => $mat1?->id,
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
     * Verwijder judoka definitief uit poule (voor doorgestreepte judoka's)
     */
    public function verwijderUitPoule(Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'judoka_id' => 'required|exists:judokas,id',
            'poule_id' => 'required|exists:poules,id',
        ]);

        $judoka = Judoka::findOrFail($validated['judoka_id']);
        $poule = Poule::findOrFail($validated['poule_id']);

        // Verwijder uit poule
        $poule->judokas()->detach($judoka->id);

        // Bereken actuele statistieken (alleen actieve judoka's)
        $tolerantie = $toernooi->gewicht_tolerantie ?? 0.5;
        $actieveJudokas = $poule->judokas()
            ->with('toernooi')
            ->get()
            ->filter(fn($j) => !$j->moetUitPouleVerwijderd($tolerantie))
            ->count();

        $aantalWedstrijden = $actieveJudokas > 1 ? ($actieveJudokas * ($actieveJudokas - 1)) / 2 : 0;

        return response()->json([
            'success' => true,
            'poule' => [
                'id' => $poule->id,
                'aantal_judokas' => $actieveJudokas,
                'aantal_wedstrijden' => $aantalWedstrijden,
            ],
        ]);
    }

    /**
     * Determine new category for judoka based on actual weight
     */
    private function bepaalNieuweCategorie(Judoka $judoka): ?string
    {
        $gewicht = $judoka->gewicht_gewogen;
        if (!$gewicht) return null;

        // Build label to key mapping from preset config
        $toernooi = $judoka->toernooi;
        $gewichtsklassenConfig = $toernooi->getAlleGewichtsklassen();
        
        $labelToKey = [];
        foreach ($gewichtsklassenConfig as $key => $data) {
            $label = $data['label'] ?? $key;
            $labelToKey[$label] = $key;
        }

        $configKey = $labelToKey[$judoka->leeftijdsklasse] ?? null;
        if (!$configKey) return null;

        // Get weight classes from preset config (key is 'gewichten', values are strings like "-20", "+29")
        $gewichten = $gewichtsklassenConfig[$configKey]['gewichten'] ?? [];
        if (empty($gewichten)) return null;

        // Find matching weight class
        foreach ($gewichten as $klasse) {
            $isPlusKlasse = str_starts_with($klasse, '+');
            $limiet = (float) str_replace(['+', '-'], '', $klasse);

            if ($isPlusKlasse) {
                // +29 means minimum 29kg
                if ($gewicht >= $limiet) {
                    return $judoka->leeftijdsklasse . '|' . $klasse;
                }
            } else {
                // -20 means maximum 20kg
                if ($gewicht <= $limiet) {
                    return $judoka->leeftijdsklasse . '|' . $klasse;
                }
            }
        }

        return null;
    }

    /**
     * Convert elimination poule to regular poules (with or without kruisfinale)
     */
    public function zetOmNaarPoules(Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'poule_id' => 'required|exists:poules,id',
            'systeem' => 'required|in:poules,poules_kruisfinale',
        ]);

        $elimPoule = Poule::with('judokas')->findOrFail($validated['poule_id']);
        $judokas = $elimPoule->judokas;
        $aantalJudokas = $judokas->count();

        if ($aantalJudokas < 2) {
            return response()->json(['success' => false, 'message' => 'Te weinig judoka\'s'], 400);
        }

        // Determine optimal pool sizes based on tournament settings
        $voorkeur = $toernooi->getPouleGrootteVoorkeurOfDefault();
        $minPoule = $toernooi->min_judokas_poule ?? 3;
        $maxPoule = $toernooi->max_judokas_poule ?? 6;

        // Calculate how to split judokas into poules
        $pouleGroottes = $this->berekenPouleGroottes($aantalJudokas, $voorkeur, $minPoule, $maxPoule);
        $aantalPoules = count($pouleGroottes);

        // Sort judokas by club for spreading (simple alternating)
        $gesorteerd = $judokas->sortBy('club_id')->values();

        // Create new poules
        $nieuwePoules = [];
        $judokaIndex = 0;

        for ($i = 0; $i < $aantalPoules; $i++) {
            $poule = Poule::create([
                'toernooi_id' => $toernooi->id,
                'blok_id' => $elimPoule->blok_id,
                'leeftijdsklasse' => $elimPoule->leeftijdsklasse,
                'gewichtsklasse' => $elimPoule->gewichtsklasse,
                'nummer' => $i + 1,
                'titel' => $elimPoule->leeftijdsklasse . ' ' . $elimPoule->gewichtsklasse . ' Poule ' . ($i + 1),
                'type' => 'voorronde',
                'aantal_judokas' => $pouleGroottes[$i],
                'aantal_wedstrijden' => ($pouleGroottes[$i] * ($pouleGroottes[$i] - 1)) / 2,
            ]);

            // Attach judokas to this poule (alternating to spread clubs)
            for ($j = 0; $j < $pouleGroottes[$i]; $j++) {
                $idx = $judokaIndex + ($j * $aantalPoules);
                if ($idx < $aantalJudokas) {
                    $judoka = $gesorteerd[$idx % $aantalJudokas];
                    // Skip if already attached
                    if (!in_array($judoka->id, array_column($nieuwePoules, 'judokas_attached') ?: [])) {
                        $poule->judokas()->attach($judoka->id, ['positie' => $j + 1]);
                    }
                }
            }
            $judokaIndex++;

            $nieuwePoules[] = $poule;
        }

        // Redistribute judokas properly (the above is simplified, let's fix it)
        // Reset and distribute properly
        foreach ($nieuwePoules as $poule) {
            $poule->judokas()->detach();
        }

        $judokaArray = $gesorteerd->values()->all();
        $pouleIdx = 0;
        $positie = [];

        foreach ($judokaArray as $judoka) {
            $targetPoule = $nieuwePoules[$pouleIdx % $aantalPoules];
            $positie[$targetPoule->id] = ($positie[$targetPoule->id] ?? 0) + 1;
            $targetPoule->judokas()->attach($judoka->id, ['positie' => $positie[$targetPoule->id]]);
            $pouleIdx++;
        }

        // Update statistics
        foreach ($nieuwePoules as $poule) {
            $poule->updateStatistieken();
        }

        // Create kruisfinale if requested
        if ($validated['systeem'] === 'poules_kruisfinale' && $aantalPoules >= 2) {
            Poule::create([
                'toernooi_id' => $toernooi->id,
                'blok_id' => $elimPoule->blok_id,
                'leeftijdsklasse' => $elimPoule->leeftijdsklasse,
                'gewichtsklasse' => $elimPoule->gewichtsklasse,
                'nummer' => $aantalPoules + 1,
                'titel' => $elimPoule->leeftijdsklasse . ' ' . $elimPoule->gewichtsklasse . ' Kruisfinale',
                'type' => 'kruisfinale',
                'aantal_judokas' => min($aantalPoules * 2, $aantalJudokas), // Top 2 from each poule
                'aantal_wedstrijden' => 0,
            ]);
        }

        // Delete original elimination poule
        $elimPoule->judokas()->detach();
        $elimPoule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Eliminatie omgezet naar ' . ($validated['systeem'] === 'poules_kruisfinale' ? 'poules + kruisfinale' : 'poules'),
        ]);
    }

    /**
     * Calculate optimal poule sizes for given number of judokas
     */
    private function berekenPouleGroottes(int $totaal, array $voorkeur, int $min, int $max): array
    {
        // Try preferred size first
        foreach ($voorkeur as $grootte) {
            if ($grootte < $min || $grootte > $max) continue;

            if ($totaal <= $grootte) {
                return [$totaal]; // Single poule
            }

            $aantalPoules = ceil($totaal / $grootte);
            $basisGrootte = floor($totaal / $aantalPoules);
            $extra = $totaal % $aantalPoules;

            // Check if all poules would be valid
            if ($basisGrootte >= $min && ($basisGrootte + ($extra > 0 ? 1 : 0)) <= $max) {
                $groottes = array_fill(0, (int)$aantalPoules, (int)$basisGrootte);
                for ($i = 0; $i < $extra; $i++) {
                    $groottes[$i]++;
                }
                return $groottes;
            }
        }

        // Fallback: just split evenly
        $aantalPoules = max(1, ceil($totaal / $max));
        $basisGrootte = floor($totaal / $aantalPoules);
        $extra = $totaal % $aantalPoules;

        $groottes = array_fill(0, (int)$aantalPoules, (int)$basisGrootte);
        for ($i = 0; $i < $extra; $i++) {
            $groottes[$i]++;
        }

        return $groottes;
    }

    /**
     * Herbereken kruisfinale aantallen op basis van actueel aantal voorrondepoules
     */
    private function herberkenKruisfinales(Toernooi $toernooi): void
    {
        $kruisfinales = $toernooi->poules()->where('type', 'kruisfinale')->get();

        foreach ($kruisfinales as $kruisfinale) {
            $aantalVoorrondes = Poule::where('toernooi_id', $toernooi->id)
                ->where('leeftijdsklasse', $kruisfinale->leeftijdsklasse)
                ->where('gewichtsklasse', $kruisfinale->gewichtsklasse)
                ->where('type', 'voorronde')
                ->count();

            $plaatsen = $kruisfinale->kruisfinale_plaatsen ?? 2;
            $aantalJudokas = $aantalVoorrondes * $plaatsen;
            $aantalWedstrijden = $aantalJudokas <= 1 ? 0 : intval(($aantalJudokas * ($aantalJudokas - 1)) / 2);

            // Alleen updaten als gewijzigd
            if ($kruisfinale->aantal_judokas !== $aantalJudokas || $kruisfinale->aantal_wedstrijden !== $aantalWedstrijden) {
                $kruisfinale->update([
                    'aantal_judokas' => $aantalJudokas,
                    'aantal_wedstrijden' => $aantalWedstrijden,
                ]);
            }
        }
    }
}
