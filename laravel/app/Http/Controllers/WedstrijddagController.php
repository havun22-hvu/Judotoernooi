<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Organisator;
use App\Models\Blok;
use App\Models\Judoka;
use App\Models\Poule;
use App\Models\Toernooi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WedstrijddagController extends Controller
{
    public function poules(Organisator $organisator, Toernooi $toernooi): View
    {
        // Herbereken kruisfinale aantallen op basis van actueel aantal voorrondepoules
        $this->herberkenKruisfinales($toernooi);

        // Get all poules with blok info
        $poules = $toernooi->poules()
            ->with(['judokas.club', 'judokas.toernooi', 'blok', 'mat'])
            ->get();

        // Wachtruimte: judoka's die gewogen zijn maar nog GEEN poule hebben
        // (niet afwezig, afwijkend gewicht blijft nu in poule - org kiest wie eruit gaat)
        $judokasNaarWachtruimte = Judoka::where('toernooi_id', $toernooi->id)
            ->whereNotNull('gewicht_gewogen')
            ->where(function ($q) {
                $q->whereNull('aanwezigheid')
                  ->orWhere('aanwezigheid', '!=', 'afwezig');
            })
            ->whereDoesntHave('poules') // Geen poule = naar wachtruimte
            ->with('club')
            ->get();

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

            // Get sort order from preset config - use max_leeftijd for proper young-to-old sorting
            $leeftijdVolgorde = [];
            if ($gewichtsklassenConfig) {
                foreach ($gewichtsklassenConfig as $key => $data) {
                    $label = $data['label'] ?? $key;
                    $leeftijdVolgorde[$label] = $data['max_leeftijd'] ?? 99;
                }
            }

            $categories = $blokPoules
                // Filter alleen automatisch aangemaakte lege poules bij variabel gewicht
                // Handmatig aangemaakte poules (nummer > 100 of recent aangemaakt) WEL tonen
                ->filter(function ($poule) use ($gewichtsklassenConfig, $leeftijdsklasseToKey, $tolerantie) {
                    // Altijd tonen als poule judoka's heeft
                    $actief = $poule->judokas->filter(fn($j) => !$j->moetUitPouleVerwijderd($tolerantie))->count();
                    if ($actief > 0) return true;

                    // Handmatig aangemaakte poules altijd tonen (created recent = binnen 24h)
                    if ($poule->created_at && $poule->created_at->gt(now()->subDay())) {
                        return true;
                    }

                    // Check of dit een dynamische categorie is
                    $configKey = $leeftijdsklasseToKey[$poule->leeftijdsklasse] ?? null;
                    $maxKgVerschil = 0;
                    if ($configKey && isset($gewichtsklassenConfig[$configKey]['max_kg_verschil'])) {
                        $maxKgVerschil = $gewichtsklassenConfig[$configKey]['max_kg_verschil'];
                    }

                    // Dynamisch (max_kg_verschil > 0): lege automatische poules NIET tonen
                    // Vast (max_kg_verschil = 0): lege poules WEL tonen (wachtruimte)
                    return $maxKgVerschil == 0;
                })
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
                ['leeftijd_sort', 'asc'], // Eerst leeftijdscategorie (mini's → jeugd → dames/heren)
                ['gewicht_sort', 'asc'],  // Dan gewicht (licht naar zwaar)
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
                'weging_gesloten' => $blok->weging_gesloten,
                'categories' => $categories,
            ];
        });

        // Get sent-to-zaaloverzicht status per categorie
        $sentToZaaloverzicht = $toernooi->poules()
            ->whereNotNull('doorgestuurd_op')
            ->get()
            ->groupBy(fn($p) => $p->leeftijdsklasse . '|' . $p->gewichtsklasse)
            ->map(fn() => true)
            ->toArray();

        // Detecteer problematische poules na weging (gewichtsrange > max_kg_verschil)
        $problematischeGewichtsPoules = collect();
        foreach ($toernooi->blokken()->where('weging_gesloten', true)->get() as $blok) {
            foreach ($blok->getProblematischePoules() as $poule) {
                $problematischeGewichtsPoules->put($poule->id, $poule->probleem);
            }
        }

        // Check of poules al op matten staan (vanuit voorbereiding)
        $poulesZonderMat = $poules->filter(fn($p) => $p->type !== 'kruisfinale' && $p->mat_id === null);
        $matWaarschuwing = $poulesZonderMat->isNotEmpty();

        // Clubs for quick-add judoka modal
        $clubs = Club::where('organisator_id', $organisator->id)->orderBy('naam')->get();

        return view('pages.wedstrijddag.poules', compact(
            'toernooi',
            'blokken',
            'sentToZaaloverzicht',
            'problematischeGewichtsPoules',
            'clubs',
            'matWaarschuwing'
        ));
    }

    public function verplaatsJudoka(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
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
        $nieuwePoule = Poule::with('blok')->findOrFail($validated['poule_id']);

        // Validatie: bij verplaatsen naar eerder blok, check of weging nog open is
        if (!empty($validated['from_poule_id'])) {
            $oudePoule = Poule::with('blok')->findOrFail($validated['from_poule_id']);

            if ($oudePoule->blok && $nieuwePoule->blok) {
                if ($nieuwePoule->blok->nummer < $oudePoule->blok->nummer) {
                    if ($nieuwePoule->blok->weging_gesloten) {
                        return response()->json([
                            'success' => false,
                            'error' => "Kan niet verplaatsen naar {$nieuwePoule->blok->naam}: weging is al gesloten"
                        ], 422);
                    }
                }
            }
        }

        $tolerantie = $toernooi->gewicht_tolerantie ?? 0.5;

        // Wrap entire operation in transaction to prevent partial state
        $result = DB::transaction(function () use ($validated, $judoka, $nieuwePoule, $toernooi, $tolerantie) {
            $oudePouleData = null;

            // Remove from old poule(s)
            if (!empty($validated['from_poule_id'])) {
                $oudePoule = Poule::findOrFail($validated['from_poule_id']);
                $oudePoule->judokas()->detach($judoka->id);
                $oudePoule->updateStatistieken();
                $oudePoule->load('judokas');

                $actieveJudokasOud = $oudePoule->judokas->filter(fn($j) => $j->aanwezigheid !== 'afwezig')->count();

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

            // Update judoka's gewichtsklasse to match new poule (removes strikethrough)
            $judoka->update([
                'gewichtsklasse' => $nieuwePoule->gewichtsklasse,
                'opmerking' => 'Overgepouled',
            ]);

            // Check if judoka already in target poule (reordering within same poule)
            $alreadyInPoule = $nieuwePoule->judokas()->where('judoka_id', $judoka->id)->exists();

            if (!$alreadyInPoule) {
                $nieuwePoule->judokas()->attach($judoka->id, ['positie' => $nieuwePoule->judokas()->count() + 1]);
            }

            // Update positions if provided
            if (!empty($validated['positions'])) {
                foreach ($validated['positions'] as $pos) {
                    $nieuwePoule->judokas()->updateExistingPivot($pos['id'], ['positie' => $pos['positie']]);
                }
            }

            $nieuwePoule->updateStatistieken();
            $nieuwePoule->load('judokas');

            $actieveJudokasNieuw = $nieuwePoule->judokas->filter(fn($j) => $j->aanwezigheid !== 'afwezig')->count();

            $maxKgVerschil = $toernooi->max_kg_verschil ?? 0;

            $nieuweGewichtsRange = $nieuwePoule->getGewichtsRange();
            $nieuweRangeVerschil = ($nieuweGewichtsRange['max_kg'] ?? 0) - ($nieuweGewichtsRange['min_kg'] ?? 0);
            $nieuweIsProblematisch = $maxKgVerschil > 0 && $nieuweRangeVerschil > $maxKgVerschil;

            if ($oudePouleData && isset($oudePoule)) {
                $oudeGewichtsRange = $oudePoule->getGewichtsRange();
                $oudeRangeVerschil = ($oudeGewichtsRange['max_kg'] ?? 0) - ($oudeGewichtsRange['min_kg'] ?? 0);
                $oudeIsProblematisch = $maxKgVerschil > 0 && $oudeRangeVerschil > $maxKgVerschil;
                $oudePouleData['gewichts_range'] = $oudeGewichtsRange;
                $oudePouleData['is_gewicht_problematisch'] = $oudeIsProblematisch;
                $oudePouleData['titel'] = $oudePoule->titel;
            }

            $judokaPastInPoule = $judoka->isGewichtBinnenKlasse(null, $tolerantie, $nieuwePoule->gewichtsklasse);

            return [
                'oudePouleData' => $oudePouleData,
                'nieuwePoule' => $nieuwePoule,
                'actieveJudokasNieuw' => $actieveJudokasNieuw,
                'nieuweGewichtsRange' => $nieuweGewichtsRange,
                'nieuweIsProblematisch' => $nieuweIsProblematisch,
                'judokaPastInPoule' => $judokaPastInPoule,
            ];
        });

        ActivityLogger::log($toernooi, 'verplaats_judoka', "{$judoka->naam} verplaatst naar {$result['nieuwePoule']->getDisplayTitel()}", [
            'model' => $judoka,
            'properties' => [
                'van_poule_id' => $validated['from_poule_id'] ?? null,
                'naar_poule_id' => $nieuwePoule->id,
            ],
            'interface' => 'hoofdjury',
        ]);

        return response()->json([
            'success' => true,
            'van_poule' => $result['oudePouleData'],
            'naar_poule' => [
                'id' => $result['nieuwePoule']->id,
                'titel' => $result['nieuwePoule']->getDisplayTitel(),
                'aantal_judokas' => $result['actieveJudokasNieuw'],
                'aantal_wedstrijden' => $result['nieuwePoule']->berekenAantalWedstrijden($result['actieveJudokasNieuw']),
                'gewichts_range' => $result['nieuweGewichtsRange'],
                'is_gewicht_problematisch' => $result['nieuweIsProblematisch'],
            ],
            'judoka_id' => $judoka->id,
            'judoka_past_in_poule' => $result['judokaPastInPoule'],
        ]);
    }

    public function naarZaaloverzicht(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'category' => 'required|string',
        ]);

        // Parse category key (leeftijdsklasse|gewichtsklasse)
        [$leeftijdsklasse, $gewichtsklasse] = explode('|', $validated['category']);

        // Zorg dat niet-voorronde poules (kruisfinale, eliminatie) blok_id en mat_id hebben
        $voorrondePoule = $toernooi->poules()
            ->where('leeftijdsklasse', $leeftijdsklasse)
            ->where('gewichtsklasse', $gewichtsklasse)
            ->where('type', 'voorronde')
            ->whereNotNull('mat_id')
            ->first(['blok_id', 'mat_id']);

        if ($voorrondePoule) {
            $toernooi->poules()
                ->where('leeftijdsklasse', $leeftijdsklasse)
                ->where('gewichtsklasse', $gewichtsklasse)
                ->where('type', '!=', 'voorronde')
                ->where(function ($q) {
                    $q->whereNull('blok_id')->orWhereNull('mat_id');
                })
                ->update([
                    'blok_id' => $voorrondePoule->blok_id,
                    'mat_id' => $voorrondePoule->mat_id,
                ]);
        }

        // Weging check: bij verplichte weging moet weging gesloten zijn OF alle judoka's gewogen
        if ($toernooi->weging_verplicht) {
            $poules = $toernooi->poules()
                ->where('leeftijdsklasse', $leeftijdsklasse)
                ->where('gewichtsklasse', $gewichtsklasse)
                ->with(['blok', 'judokas'])
                ->get();

            foreach ($poules as $poule) {
                if ($error = $this->checkWegingVoorDoorsturen($toernooi, $poule)) {
                    return response()->json(['success' => false, 'message' => $error], 422);
                }
            }
        }

        // Update all poules for this category with doorgestuurd_op timestamp
        $updated = $toernooi->poules()
            ->where('leeftijdsklasse', $leeftijdsklasse)
            ->where('gewichtsklasse', $gewichtsklasse)
            ->update(['doorgestuurd_op' => now()]);

        return response()->json(['success' => true, 'updated' => $updated]);
    }

    public function naarZaaloverzichtPoule(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'poule_id' => 'required|integer|exists:poules,id',
        ]);

        $poule = Poule::findOrFail($validated['poule_id']);

        // Verify poule belongs to this tournament
        if ($poule->toernooi_id !== $toernooi->id) {
            return response()->json(['success' => false, 'message' => 'Poule niet gevonden'], 404);
        }

        // Weging check: bij verplichte weging moet weging gesloten zijn OF alle judoka's gewogen
        if ($error = $this->checkWegingVoorDoorsturen($toernooi, $poule)) {
            return response()->json(['success' => false, 'message' => $error], 422);
        }

        // Als blok_id of mat_id ontbreekt, kopieer van voorronde poule
        if (!$poule->blok_id || !$poule->mat_id) {
            $voorrondePoule = $toernooi->poules()
                ->where('leeftijdsklasse', $poule->leeftijdsklasse)
                ->where('gewichtsklasse', $poule->gewichtsklasse)
                ->where('type', 'voorronde')
                ->whereNotNull('mat_id')
                ->first(['blok_id', 'mat_id']);

            if ($voorrondePoule) {
                $poule->blok_id = $poule->blok_id ?: $voorrondePoule->blok_id;
                $poule->mat_id = $poule->mat_id ?: $voorrondePoule->mat_id;
            }
        }

        // Update titel met dynamisch berekende display titel (incl. gewichtsrange)
        $poule->update([
            'doorgestuurd_op' => now(),
            'titel' => $poule->getDisplayTitel(),
            'blok_id' => $poule->blok_id,
            'mat_id' => $poule->mat_id,
        ]);

        return response()->json(['success' => true, 'poule_id' => $poule->id]);
    }

    public function nieuwePoule(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'leeftijdsklasse' => 'required|string',
            'gewichtsklasse' => 'nullable|string',
            'blok_nummer' => 'nullable|integer',
        ]);

        // Find max nummer across entire tournament (nummer must be unique per toernooi)
        $maxNummer = Poule::where('toernooi_id', $toernooi->id)->max('nummer') ?? 0;

        // Determine blok_id: use provided blok_nummer or find from existing poule
        $blokId = null;
        if (!empty($validated['blok_nummer'])) {
            $blok = $toernooi->blokken()->where('nummer', $validated['blok_nummer'])->first();
            $blokId = $blok?->id;
        }

        if (!$blokId) {
            // Find the blok for this category (same leeftijdsklasse)
            $existingPoule = Poule::where('toernooi_id', $toernooi->id)
                ->where('leeftijdsklasse', $validated['leeftijdsklasse'])
                ->whereNotNull('blok_id')
                ->first();
            $blokId = $existingPoule?->blok_id;
        }

        // New poules always go to mat 1 (will be redistributed in zaaloverzicht)
        $mat1 = $toernooi->matten()->orderBy('nummer')->first();

        $gewichtsklasse = $validated['gewichtsklasse'] ?? '';
        $poule = Poule::create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blokId,
            'mat_id' => $mat1?->id,
            'leeftijdsklasse' => $validated['leeftijdsklasse'],
            'gewichtsklasse' => $gewichtsklasse,
            'nummer' => $maxNummer + 1,
            'titel' => $validated['leeftijdsklasse'] . ($gewichtsklasse ? ' ' . $gewichtsklasse : '') . ' Poule ' . ($maxNummer + 1),
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
     * Verplaats judoka naar wachtruimte (uit poule halen)
     * Judoka kan later naar andere poule gesleept worden of via Zoek Match
     */
    public function naarWachtruimte(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'judoka_id' => 'required|exists:judokas,id',
            'from_poule_id' => 'required|exists:poules,id',
        ]);

        return DB::transaction(function () use ($validated, $toernooi) {
            $judoka = Judoka::findOrFail($validated['judoka_id']);
            $oudePoule = Poule::findOrFail($validated['from_poule_id']);

            // Verwijder uit oude poule
            $oudePoule->judokas()->detach($judoka->id);
            $oudePoule->updateStatistieken();
            $oudePoule->refresh();
            $oudePoule->load('judokas');

            // Bereken actieve judoka's voor response
            $actieveJudokas = $oudePoule->judokas->filter(fn($j) => $j->aanwezigheid !== 'afwezig')->count();

            // Hervalideer of poule nog problematisch is
            $probleem = $oudePoule->isProblematischNaWeging();

            // Bereken nieuwe gewichtsrange voor titel update
            $gewichtsRange = $oudePoule->getGewichtsRange();

            ActivityLogger::log($toernooi, 'naar_wachtruimte', "{$judoka->naam} naar wachtruimte vanuit poule {$oudePoule->nummer}", [
                'model' => $judoka,
                'properties' => ['van_poule_id' => $oudePoule->id],
                'interface' => 'hoofdjury',
            ]);

            return response()->json([
                'success' => true,
                'message' => "{$judoka->naam} verplaatst naar wachtruimte",
                'van_poule' => [
                    'id' => $oudePoule->id,
                    'titel' => $oudePoule->titel,
                    'aantal_judokas' => $actieveJudokas,
                    'aantal_wedstrijden' => $oudePoule->berekenAantalWedstrijden($actieveJudokas),
                    'is_problematisch' => $probleem !== null,
                    'probleem' => $probleem,
                    'gewichts_range' => $gewichtsRange,
                ],
            ]);
        });
    }

    /**
     * Verwijder judoka definitief uit poule (voor afwezige/verplaatste judoka's)
     */
    public function verwijderUitPoule(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'judoka_id' => 'required|exists:judokas,id',
            'poule_id' => 'required|exists:poules,id',
        ]);

        return DB::transaction(function () use ($validated, $toernooi) {
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

            ActivityLogger::log($toernooi, 'verwijder_uit_poule', "{$judoka->naam} verwijderd uit poule {$poule->nummer}", [
                'model' => $judoka,
                'properties' => ['poule_id' => $poule->id],
                'interface' => 'hoofdjury',
            ]);

            return response()->json([
                'success' => true,
                'poule' => [
                    'id' => $poule->id,
                    'aantal_judokas' => $actieveJudokas,
                    'aantal_wedstrijden' => $aantalWedstrijden,
                ],
            ]);
        });
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
    public function zetOmNaarPoules(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
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

        return DB::transaction(function () use ($validated, $elimPoule, $judokas, $aantalJudokas, $toernooi) {
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

        // Get highest existing poule nummer to avoid UNIQUE constraint conflicts
        $maxNummer = $toernooi->poules()->max('nummer') ?? 0;

        for ($i = 0; $i < $aantalPoules; $i++) {
            $nieuweNummer = $maxNummer + $i + 1;
            $poule = Poule::create([
                'toernooi_id' => $toernooi->id,
                'blok_id' => $elimPoule->blok_id,
                'leeftijdsklasse' => $elimPoule->leeftijdsklasse,
                'gewichtsklasse' => $elimPoule->gewichtsklasse,
                'nummer' => $nieuweNummer,
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
        });
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

    /**
     * Wijzig poule type (poule ↔ eliminatie ↔ kruisfinale)
     */
    public function wijzigPouleType(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'poule_id' => 'required|exists:poules,id',
            'type' => 'required|in:voorronde,poules,eliminatie,kruisfinale,poules_kruisfinale',
        ]);

        $poule = Poule::findOrFail($validated['poule_id']);
        $nieuwType = $validated['type'];
        $oudType = $poule->type;

        // Map 'poules' to 'voorronde' (same thing)
        if ($nieuwType === 'poules') {
            $nieuwType = 'voorronde';
        }

        // poules_kruisfinale: maak kruisfinale aan voor deze categorie
        if ($nieuwType === 'poules_kruisfinale') {
            // Check of er al een kruisfinale bestaat
            $bestaandeKruisfinale = Poule::where('toernooi_id', $toernooi->id)
                ->where('leeftijdsklasse', $poule->leeftijdsklasse)
                ->where('type', 'kruisfinale')
                ->first();

            if ($bestaandeKruisfinale) {
                return response()->json(['success' => false, 'message' => 'Er bestaat al een kruisfinale voor deze categorie'], 400);
            }

            // Bepaal volgende nummer
            $maxNummer = Poule::where('toernooi_id', $toernooi->id)->max('nummer') ?? 0;

            // Maak kruisfinale aan
            $kruisfinale = Poule::create([
                'toernooi_id' => $toernooi->id,
                'nummer' => $maxNummer + 1,
                'leeftijdsklasse' => $poule->leeftijdsklasse,
                'gewichtsklasse' => $poule->gewichtsklasse,
                'titel' => 'Kruisfinale ' . $poule->leeftijdsklasse . ' ' . $poule->gewichtsklasse,
                'type' => 'kruisfinale',
                'kruisfinale_plaatsen' => 2,
                'categorie_key' => $poule->categorie_key,
                'blok_id' => $poule->blok_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Kruisfinale aangemaakt',
            ]);
        }

        if ($oudType === $nieuwType) {
            return response()->json(['success' => false, 'message' => 'Type is al ' . $nieuwType], 400);
        }

        // Update type FIRST (needed for correct wedstrijden calculation)
        $poule->type = $nieuwType;

        // Bij kruisfinale → eliminatie: bereken aantal judokas (kruisfinale_plaatsen × voorronde poules)
        $skipUpdateStatistieken = false;
        if ($oudType === 'kruisfinale' && $nieuwType === 'eliminatie') {
            $aantalVoorrondes = Poule::where('toernooi_id', $toernooi->id)
                ->where('leeftijdsklasse', $poule->leeftijdsklasse)
                ->where('gewichtsklasse', $poule->gewichtsklasse)
                ->where('type', 'voorronde')
                ->count();
            $plaatsen = $poule->kruisfinale_plaatsen ?? 2;
            $aantalJudokas = $aantalVoorrondes * $plaatsen;

            $poule->aantal_judokas = $aantalJudokas;
            // type is already 'eliminatie', so berekenAantalWedstrijden uses elimination formula
            $poule->aantal_wedstrijden = $poule->berekenAantalWedstrijden($aantalJudokas);
            $skipUpdateStatistieken = true;
        }

        // Update titel
        $basisTitel = $poule->leeftijdsklasse . ' ' . $poule->gewichtsklasse;
        if ($nieuwType === 'eliminatie') {
            $poule->titel = $basisTitel . ' - Eliminatie';
        } elseif ($nieuwType === 'kruisfinale') {
            $poule->titel = 'Kruisfinale ' . $basisTitel;
            $poule->kruisfinale_plaatsen = 2; // Default
        } else {
            $poule->titel = $basisTitel . ' Poule';
        }

        $poule->save();

        if (!$skipUpdateStatistieken) {
            $poule->updateStatistieken();
        }

        return response()->json([
            'success' => true,
            'message' => 'Gewijzigd naar ' . $nieuwType,
        ]);
    }

    /**
     * Meld judoka af (kan niet deelnemen)
     * Zet aanwezigheid op 'afwezig' en verwijdert uit poule
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
            // Houd bij in welke poules de judoka zat (voor statistieken update)
            $pouleIds = $judoka->poules()->pluck('poules.id');

            // Markeer als afwezig
            $judoka->update(['aanwezigheid' => 'afwezig']);

            // Verwijder uit alle poules
            $judoka->poules()->detach();

            // Update statistieken van de poules
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

        // Check freemium limit
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
            // Calculate classification based on age/gender
            $leeftijdsklasse = $poule->leeftijdsklasse;
            $gewichtsklasse = $poule->gewichtsklasse;

            if (!empty($validated['geboortejaar'])) {
                $toernooiJaar = $toernooi->datum ? $toernooi->datum->year : (int) date('Y');
                $leeftijd = $toernooiJaar - $validated['geboortejaar'];

                // Try to determine gewichtsklasse from weight
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

            // Attach to poule at last position
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

        // Herstel aanwezigheid (null = normaal aanwezig)
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
     * Check if weging allows doorsturen for a poule.
     * Returns error message if blocked, null if OK.
     */
    private function checkWegingVoorDoorsturen(Toernooi $toernooi, Poule $poule): ?string
    {
        if (!$toernooi->weging_verplicht) {
            return null;
        }

        $blok = $poule->blok;
        if ($blok && $blok->weging_gesloten) {
            return null; // Weging gesloten → niet-gewogen zijn al afwezig
        }

        // Weging open: check of alle judoka's (niet-afwezig) gewogen zijn
        $judokas = $poule->relationLoaded('judokas') ? $poule->judokas : $poule->judokas()->get();
        $nietGewogen = $judokas
            ->filter(fn($j) => $j->aanwezigheid !== 'afwezig')
            ->filter(fn($j) => !($j->gewicht_gewogen > 0));

        if ($nietGewogen->isNotEmpty()) {
            $namen = $nietGewogen->pluck('naam')->implode(', ');
            return "Poule {$poule->nummer}: niet alle judoka's zijn gewogen ({$namen}). Sluit eerst de weging of weeg alle judoka's.";
        }

        return null; // Alle judoka's gewogen → mag door
    }
}
