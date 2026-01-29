<?php

namespace App\Http\Controllers;

use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use App\Services\CategorieClassifier;
use App\Services\EliminatieService;
use App\Services\PouleIndelingService;
use App\Services\WedstrijdSchemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PouleController extends Controller
{
    public function __construct(
        private PouleIndelingService $pouleService,
        private WedstrijdSchemaService $wedstrijdService,
        private EliminatieService $eliminatieService
    ) {}

    public function index(Organisator $organisator, Toernooi $toernooi): View
    {
        // Get config and build dynamic ordering from preset
        $gewichtsklassenConfig = $toernooi->getAlleGewichtsklassen();

        // Build leeftijdsklasse volgorde from config (labels AND keys)
        $leeftijdsklasseVolgorde = [];
        $index = 0;
        foreach ($gewichtsklassenConfig as $key => $config) {
            $label = $config['label'] ?? $key;
            // Map both label and key to same index for flexible matching
            $leeftijdsklasseVolgorde[$label] = $index;
            $leeftijdsklasseVolgorde[$key] = $index;
            // Also map partial matches (e.g., "U7" matches "U7 Alles")
            if (preg_match('/^(U\d+|Mini|Pupil|Aspirant|Junior|Senior)/i', $label, $m)) {
                $leeftijdsklasseVolgorde[$m[1]] = $index;
            }
            $index++;
        }

        // Build labels mapping (for backwards compatibility in views)
        $leeftijdsklasseLabels = [];
        foreach ($gewichtsklassenConfig as $key => $config) {
            $label = $config['label'] ?? $key;
            $leeftijdsklasseLabels[$label] = $label;
        }

        // Filter out poules created on wedstrijddag (after weging_gesloten_op)
        // These should only appear on wedstrijddag interface, not voorbereiding
        $poules = $toernooi->poules()
            ->with(['blok', 'mat', 'judokas.club'])
            ->withCount('judokas')
            ->whereDoesntHave('blok', function ($q) {
                // Exclude poules where: blok has weging_gesloten_op AND poule was created after that
                $q->whereNotNull('weging_gesloten_op')
                  ->whereColumn('poules.created_at', '>', 'blokken.weging_gesloten_op');
            })
            ->get();

        // Sort by: age class (youngest first), then weight class (lightest first)
        $poules = $poules->sortBy([
            fn ($a, $b) => $this->getLeeftijdsklasseVolgorde($a->leeftijdsklasse, $leeftijdsklasseVolgorde)
                          <=> $this->getLeeftijdsklasseVolgorde($b->leeftijdsklasse, $leeftijdsklasseVolgorde),
            fn ($a, $b) => $this->parseGewicht($a->gewichtsklasse) <=> $this->parseGewicht($b->gewichtsklasse),
            fn ($a, $b) => $a->nummer <=> $b->nummer,
        ]);

        // Group by leeftijdsklasse (preserving sort order)
        $poulesPerKlasse = $poules->groupBy('leeftijdsklasse');

        return view('pages.poule.index', compact('toernooi', 'poules', 'poulesPerKlasse', 'leeftijdsklasseLabels'));
    }

    /**
     * Get sort order for a leeftijdsklasse, with fallback to numeric parsing
     */
    private function getLeeftijdsklasseVolgorde(string $leeftijdsklasse, array $volgorde): int
    {
        // Direct match
        if (isset($volgorde[$leeftijdsklasse])) {
            return $volgorde[$leeftijdsklasse];
        }

        // Try prefix match (e.g., "U7 Alles" -> try "U7")
        if (preg_match('/^(U\d+|Mini|Pupil|Aspirant|Junior|Senior)/i', $leeftijdsklasse, $m)) {
            if (isset($volgorde[$m[1]])) {
                return $volgorde[$m[1]];
            }
        }

        // Fallback: parse numeric value from name (U7=7, U11=11, etc)
        if (preg_match('/U(\d+)/i', $leeftijdsklasse, $m)) {
            return (int) $m[1];
        }

        // Ultimate fallback
        return 99;
    }

    /**
     * Parse weight class to numeric value for sorting
     * -50 = up to 50kg, +50 = over 50kg, so +50 should sort after -50
     */
    private function parseGewicht(string $gewichtsklasse): int
    {
        if (preg_match('/([+-]?)(\d+)/', $gewichtsklasse, $matches)) {
            $sign = $matches[1] ?? '';
            $num = (int) ($matches[2] ?? 999);
            return $sign === '+' ? $num + 1000 : $num;
        }
        return 999;
    }

    /**
     * Delete an empty poule (or poule with only absent judokas)
     */
    public function destroy(Organisator $organisator, Toernooi $toernooi, Poule $poule): JsonResponse
    {
        // Check for active judokas (not absent, and weighted if weging is closed)
        $blok = $poule->blok;
        $wegingGesloten = $blok ? $blok->weging_gesloten : false;

        $actieveJudokas = $poule->judokas->filter(function ($j) use ($wegingGesloten) {
            return $j->aanwezigheid !== 'afwezig' &&
                   !($wegingGesloten && $j->gewicht_gewogen === null);
        });

        if ($actieveJudokas->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Kan alleen lege poules verwijderen (poule heeft nog ' . $actieveJudokas->count() . ' actieve judoka\'s)',
            ], 400);
        }

        // Detach any remaining (absent) judokas
        $poule->judokas()->detach();

        $nummer = $poule->nummer;
        $poule->delete();

        return response()->json([
            'success' => true,
            'message' => "Poule #{$nummer} verwijderd",
        ]);
    }

    /**
     * Create a new empty poule
     */
    public function store(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'leeftijdsklasse' => 'required|string',
            'gewichtsklasse' => 'nullable|string',
        ]);

        // Get next nummer for this tournament
        $maxNummer = $toernooi->poules()->max('nummer') ?? 0;
        $nieuweNummer = $maxNummer + 1;

        // Find blok_id and categorie_key from existing poule with same leeftijdsklasse (category)
        $existingPoule = $toernooi->poules()
            ->where('leeftijdsklasse', $validated['leeftijdsklasse'])
            ->whereNotNull('blok_id')
            ->first();
        $blokId = $existingPoule?->blok_id;
        $categorieKey = $existingPoule?->categorie_key;

        // Create the poule
        $gewichtsklasse = $validated['gewichtsklasse'] ?? null;
        $titel = $gewichtsklasse
            ? $validated['leeftijdsklasse'] . ' ' . $gewichtsklasse
            : $validated['leeftijdsklasse'];

        $poule = $toernooi->poules()->create([
            'nummer' => $nieuweNummer,
            'blok_id' => $blokId,
            'categorie_key' => $categorieKey,
            'leeftijdsklasse' => $validated['leeftijdsklasse'],
            'gewichtsklasse' => $gewichtsklasse,
            'titel' => $titel,
            'aantal_judokas' => 0,
            'aantal_wedstrijden' => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Poule #{$nieuweNummer} aangemaakt",
            'poule' => $poule,
        ]);
    }

    public function genereer(Organisator $organisator, Toernooi $toernooi, Request $request): RedirectResponse
    {
        // Block if category problems exist
        $errors = [];

        // Check for uncategorized judokas
        $nietGecategoriseerd = $toernooi->countNietGecategoriseerd();
        if ($nietGecategoriseerd > 0) {
            $errors[] = "{$nietGecategoriseerd} judoka('s) zijn niet gecategoriseerd.";
        }

        // Check for overlapping categories
        $config = $toernooi->gewichtsklassen ?? [];
        if (!empty($config)) {
            $classifier = new CategorieClassifier($config);
            $overlaps = $classifier->detectOverlap();
            if (!empty($overlaps)) {
                $errors[] = 'Er zijn overlappende categorieën.';
            }
        }

        if (!empty($errors)) {
            return redirect()->route('toernooi.edit', $toernooi->routeParams())
                ->with('error', 'Kan geen poule-indeling genereren: ' . implode(' ', $errors) . ' Pas eerst de categorie-instellingen aan.');
        }

        // Generate pool division (herberkenKlassen() handles sort fields)
        $statistieken = $this->pouleService->genereerPouleIndeling($toernooi);

        $message = "Poule-indeling gegenereerd: {$statistieken['totaal_poules']} poules, " .
                   "{$statistieken['totaal_wedstrijden']} wedstrijden.";

        $redirect = redirect()->route('toernooi.poule.index', $toernooi->routeParams());

        // Check for warnings about elimination participant counts
        $waarschuwingen = $statistieken['waarschuwingen'] ?? [];
        if (!empty($waarschuwingen)) {
            $errorMessages = [];
            $warningMessages = [];

            foreach ($waarschuwingen as $w) {
                if ($w['type'] === 'error') {
                    $errorMessages[] = $w['bericht'];
                } else {
                    $warningMessages[] = $w['bericht'];
                }
            }

            if (!empty($errorMessages)) {
                return $redirect
                    ->with('success', $message)
                    ->with('error', implode(' ', $errorMessages));
            }

            if (!empty($warningMessages)) {
                return $redirect
                    ->with('success', $message)
                    ->with('warning', implode(' ', $warningMessages));
            }
        }

        return $redirect->with('success', $message);
    }

    /**
     * Verify all poules and recalculate match counts
     */
    public function verifieer(Organisator $organisator, Toernooi $toernooi): JsonResponse
    {
        $poules = $toernooi->poules()->withCount('judokas')->get();
        $problemen = [];
        $totaalWedstrijden = 0;
        $herberekend = 0;

        foreach ($poules as $poule) {
            $aantalJudokas = $poule->judokas_count;
            $verwachtWedstrijden = $aantalJudokas >= 2 ? ($aantalJudokas * ($aantalJudokas - 1)) / 2 : 0;

            // Check for problems (empty poules are ok)
            // Skip eliminatie and kruisfinale - they have different size requirements
            $isEliminatie = $poule->type === 'eliminatie';
            $isKruisfinale = $poule->isKruisfinale();

            if ($isEliminatie) {
                // Eliminatie needs at least 8 judokas
                if ($aantalJudokas > 0 && $aantalJudokas < 8) {
                    $problemen[] = [
                        'poule' => $poule->titel,
                        'type' => 'te_weinig',
                        'message' => "#{$poule->nummer} {$poule->leeftijdsklasse} / {$poule->gewichtsklasse} kg: {$aantalJudokas} judoka's (min. 8 voor eliminatie)",
                    ];
                }
            } elseif (!$isKruisfinale) {
                // Regular poules: 3-6 judokas
                if ($aantalJudokas > 0 && $aantalJudokas < 3) {
                    $problemen[] = [
                        'poule' => $poule->titel,
                        'type' => 'te_weinig',
                        'message' => "#{$poule->nummer} {$poule->leeftijdsklasse} / {$poule->gewichtsklasse} kg: {$aantalJudokas} judoka's (min. 3)",
                    ];
                } elseif ($aantalJudokas > 6) {
                    $problemen[] = [
                        'poule' => $poule->titel,
                        'type' => 'te_veel',
                        'message' => "#{$poule->nummer} {$poule->leeftijdsklasse} / {$poule->gewichtsklasse} kg: {$aantalJudokas} judoka's (max. 6)",
                    ];
                }
            }
            // Kruisfinale: no size restrictions

            // Check and fix match count
            $huidigWedstrijden = $poule->wedstrijden()->count();
            if ($huidigWedstrijden !== $verwachtWedstrijden) {
                // Regenerate matches
                $poule->wedstrijden()->delete();
                $this->wedstrijdService->genereerWedstrijdenVoorPoule($poule);
                $poule->updateStatistieken();
                $herberekend++;
            }

            $totaalWedstrijden += $verwachtWedstrijden;
        }

        return response()->json([
            'success' => true,
            'totaal_poules' => $poules->count(),
            'totaal_wedstrijden' => $totaalWedstrijden,
            'herberekend' => $herberekend,
            'problemen' => $problemen,
        ]);
    }

    /**
     * Zoek mogelijke matches voor een judoka (orphan handling)
     * Toont alleen relevante poules en ook poules uit volgende (oudere) categorie
     *
     * Query params:
     * - wedstrijddag=1: Filter op blok beschikbaarheid (weging status)
     * - from_poule_id: Huidige poule ID (voor wedstrijddag mode)
     */
    public function zoekMatch(Organisator $organisator, Request $request, Toernooi $toernooi, Judoka $judoka): JsonResponse
    {
        $huidigJaar = now()->year;
        $isWedstrijddag = $request->boolean('wedstrijddag', false);
        $fromPouleId = $request->input('from_poule_id');

        // Vind huidige poule van judoka
        $huidigePoule = $fromPouleId
            ? Poule::find($fromPouleId)
            : $judoka->poules()->where('toernooi_id', $toernooi->id)->first();

        // Bepaal huidige blok voor wedstrijddag filtering
        $huidigeBlok = $huidigePoule?->blok;

        // Haal config parameters op
        $config = $toernooi->getAlleGewichtsklassen();
        $categorieKey = $huidigePoule?->categorie_key;
        $categorieConfig = $categorieKey ? ($config[$categorieKey] ?? []) : [];
        $maxKgVerschil = (float) ($categorieConfig['max_kg_verschil'] ?? $toernooi->max_kg_verschil ?? 3.0);
        $maxLftVerschil = (int) ($categorieConfig['max_leeftijd_verschil'] ?? $toernooi->max_leeftijd_verschil ?? 2);

        // Judoka gegevens
        $judokaGewicht = $judoka->gewicht_gewogen ?? $judoka->gewicht ?? 0;
        $judokaLeeftijd = $judoka->geboortejaar ? $huidigJaar - $judoka->geboortejaar : 0;
        $judokaGeslacht = $judoka->geslacht; // M of V

        // Bepaal huidige leeftijdsklasse
        $huidigeLeeftijdsklasse = $huidigePoule?->leeftijdsklasse;

        // Haal ALLE poules op - we filteren later op basis van leeftijd/gewicht relevantie
        $poulesQuery = $toernooi->poules()->with(['judokas', 'blok']);
        if ($huidigePoule) {
            $poulesQuery->where('id', '!=', $huidigePoule->id);
        }
        $poules = $poulesQuery->get();

        // Laad alle blokken voor wedstrijddag filtering
        $blokken = $isWedstrijddag ? $toernooi->blokken()->orderBy('nummer')->get()->keyBy('id') : collect();

        $matches = [];

        foreach ($poules as $poule) {
            // Filter afwezige judoka's - alleen aanwezigen tellen mee voor ranges
            $judokasInPoule = $poule->judokas->filter(fn($j) => $j->aanwezigheid !== 'afwezig');

            if ($judokasInPoule->isEmpty()) {
                continue;
            }

            // Wedstrijddag: check blok beschikbaarheid
            $blokStatus = null;
            if ($isWedstrijddag && $poule->blok_id) {
                $pouleBlok = $blokken->get($poule->blok_id);

                if ($pouleBlok && $huidigeBlok) {
                    if ($pouleBlok->nummer < $huidigeBlok->nummer) {
                        // Eerder blok met gesloten weging
                        $blokStatus = 'earlier_closed';
                    } elseif ($pouleBlok->nummer === $huidigeBlok->nummer) {
                        $blokStatus = 'same';
                    } else {
                        // Later blok - zou niet moeten voorkomen want weging open
                        $blokStatus = 'later';
                    }
                }
            }

            // Check geslacht: M/V moet matchen
            // Bepaal het geslacht van de poule op basis van de judoka's erin
            $geslachtenInPoule = $judokasInPoule->pluck('geslacht')->unique()->filter();
            $isGemengdePoule = $geslachtenInPoule->count() > 1;
            $pouleGeslacht = $geslachtenInPoule->first();

            // Skip als geslacht niet matcht (tenzij gemengde poule)
            if (!$isGemengdePoule && $pouleGeslacht && $judokaGeslacht && $pouleGeslacht !== $judokaGeslacht) {
                continue;
            }

            // Huidige statistieken
            $huidigeGewichten = $judokasInPoule->map(fn($j) => $j->gewicht_gewogen ?? $j->gewicht ?? 0)->filter();
            $huidigeLeeftijden = $judokasInPoule->map(fn($j) => $j->geboortejaar ? $huidigJaar - $j->geboortejaar : 0)->filter();

            $huidigeMinKg = $huidigeGewichten->min();
            $huidigeMaxKg = $huidigeGewichten->max();
            $huidigeMinLft = $huidigeLeeftijden->min();
            $huidigeMaxLft = $huidigeLeeftijden->max();

            // Nieuwe statistieken na toevoegen judoka
            $nieuweMinKg = min($huidigeMinKg, $judokaGewicht);
            $nieuweMaxKg = max($huidigeMaxKg, $judokaGewicht);
            $nieuweMinLft = min($huidigeMinLft, $judokaLeeftijd);
            $nieuweMaxLft = max($huidigeMaxLft, $judokaLeeftijd);

            $nieuweKgRange = $nieuweMaxKg - $nieuweMinKg;
            $nieuweLftRange = $nieuweMaxLft - $nieuweMinLft;

            // Bereken overschrijding
            $kgOverschrijding = max(0, $nieuweKgRange - $maxKgVerschil);
            $lftOverschrijding = max(0, $nieuweLftRange - $maxLftVerschil);

            // Is dit een andere categorie?
            $isCategorieOverschrijding = $huidigeLeeftijdsklasse && $poule->leeftijdsklasse !== $huidigeLeeftijdsklasse;

            // ============================================================
            // BEPAAL STATUS - TWEE VERSCHILLENDE SYSTEMEN
            // ============================================================
            $isVasteKlassen = $maxKgVerschil == 0;

            if ($isVasteKlassen) {
                // --------------------------------------------------------
                // VASTE GEWICHTSKLASSEN (bijv. Jeugd -27 kg)
                // Judoka past in EXACT 1 categorie: leeftijdsklasse + gewichtsklasse
                // --------------------------------------------------------
                $tolerantie = $toernooi->gewicht_tolerantie ?? 0.5;

                // Stap 1: Bepaal de juiste gewichtsklasse voor dit gewicht
                $beschikbareKlassen = Judoka::where('leeftijdsklasse', $judoka->leeftijdsklasse)
                    ->where('toernooi_id', $toernooi->id)
                    ->whereNotNull('gewichtsklasse')
                    ->distinct()
                    ->pluck('gewichtsklasse')
                    ->toArray();

                usort($beschikbareKlassen, fn($a, $b) =>
                    floatval(preg_replace('/[^0-9.]/', '', $a)) - floatval(preg_replace('/[^0-9.]/', '', $b))
                );

                $juisteGewichtsklasse = null;
                foreach ($beschikbareKlassen as $klasse) {
                    $limiet = floatval(preg_replace('/[^0-9.]/', '', $klasse));
                    if (str_starts_with($klasse, '+') || $judokaGewicht <= $limiet + $tolerantie) {
                        $juisteGewichtsklasse = $klasse;
                        break;
                    }
                }

                // Stap 2: Check of poule exact matcht
                $leeftijdsklasseKlopt = $poule->leeftijdsklasse === $judoka->leeftijdsklasse;
                $gewichtsklasseKlopt = $poule->gewichtsklasse === $juisteGewichtsklasse;

                if ($leeftijdsklasseKlopt && $gewichtsklasseKlopt) {
                    $status = 'ok';
                    $kgOverschrijding = 0;
                } else {
                    $status = 'error';
                }

            } else {
                // --------------------------------------------------------
                // VARIABELE GEWICHTEN (max_kg_verschil > 0)
                // Check of gewicht/leeftijd spreiding binnen limieten blijft
                // --------------------------------------------------------
                $status = 'ok';
                if ($kgOverschrijding > 0 || $lftOverschrijding > 0) {
                    $status = ($kgOverschrijding <= 2 && $lftOverschrijding <= 1) ? 'warning' : 'error';
                }
            }

            $match = [
                'poule_id' => $poule->id,
                'poule_nummer' => $poule->nummer,
                'poule_titel' => $poule->titel ?? "Poule #{$poule->nummer}",
                'leeftijdsklasse' => $poule->leeftijdsklasse,
                'gewichtsklasse' => $poule->gewichtsklasse,
                'categorie_overschrijding' => $isCategorieOverschrijding,
                'huidige_judokas' => $judokasInPoule->count(),
                'huidige_leeftijd' => $huidigeMinLft == $huidigeMaxLft ? "{$huidigeMinLft}j" : "{$huidigeMinLft}-{$huidigeMaxLft}j",
                'huidige_gewicht' => round($huidigeMinKg, 1) == round($huidigeMaxKg, 1)
                    ? round($huidigeMinKg, 1) . "kg"
                    : round($huidigeMinKg, 1) . "-" . round($huidigeMaxKg, 1) . "kg",
                'nieuwe_judokas' => $judokasInPoule->count() + 1,
                'nieuwe_leeftijd' => $nieuweMinLft == $nieuweMaxLft ? "{$nieuweMinLft}j" : "{$nieuweMinLft}-{$nieuweMaxLft}j",
                'nieuwe_gewicht' => round($nieuweMinKg, 1) == round($nieuweMaxKg, 1)
                    ? round($nieuweMinKg, 1) . "kg"
                    : round($nieuweMinKg, 1) . "-" . round($nieuweMaxKg, 1) . "kg",
                'kg_overschrijding' => round($kgOverschrijding, 1),
                'lft_overschrijding' => $lftOverschrijding,
                'status' => $status,
            ];

            // Voeg blok info toe voor wedstrijddag
            if ($isWedstrijddag && $poule->blok) {
                $match['blok_nummer'] = $poule->blok->nummer;
                $match['blok_naam'] = $poule->blok->naam;
                $match['blok_status'] = $blokStatus; // same, later, earlier_open
            }

            $matches[] = $match;
        }

        // Split in eigen categorie en andere categorie
        $eigenCategorie = array_filter($matches, fn($m) => !$m['categorie_overschrijding']);
        $andereCategorie = array_filter($matches, fn($m) => $m['categorie_overschrijding']);

        // Sorteer op status en kg overschrijding
        // Voor wedstrijddag: ook op blok (same > later > earlier_open)
        $sortFn = function ($a, $b) use ($isWedstrijddag) {
            // Wedstrijddag: sorteer eerst op blok status
            if ($isWedstrijddag) {
                $blokOrder = ['same' => 0, 'later' => 1, 'earlier_open' => 2];
                $blokCmp = ($blokOrder[$a['blok_status'] ?? ''] ?? 3) <=> ($blokOrder[$b['blok_status'] ?? ''] ?? 3);
                if ($blokCmp !== 0) return $blokCmp;
            }

            $statusOrder = ['ok' => 0, 'warning' => 1, 'error' => 2];
            $statusCmp = ($statusOrder[$a['status']] ?? 3) <=> ($statusOrder[$b['status']] ?? 3);
            if ($statusCmp !== 0) return $statusCmp;
            return $a['kg_overschrijding'] <=> $b['kg_overschrijding'];
        };

        usort($eigenCategorie, $sortFn);
        usort($andereCategorie, $sortFn);

        // Neem max 7 uit eigen categorie + max 5 uit andere categorie
        $eigenCategorie = array_slice($eigenCategorie, 0, 7);
        $andereCategorie = array_slice($andereCategorie, 0, 5);

        // Combineer: eigen categorie eerst, dan andere
        $matches = array_merge($eigenCategorie, $andereCategorie);

        $response = [
            'success' => true,
            'judoka' => [
                'id' => $judoka->id,
                'naam' => $judoka->naam,
                'gewicht' => $judokaGewicht,
                'leeftijd' => $judokaLeeftijd,
            ],
            'huidige_poule_id' => $huidigePoule?->id,
            'huidige_leeftijdsklasse' => $huidigeLeeftijdsklasse,
            'gebruik_gewichtsklassen' => $toernooi->gebruik_gewichtsklassen ?? false,
            'max_kg_verschil' => $maxKgVerschil,
            'max_lft_verschil' => $maxLftVerschil,
            'matches' => $matches,
        ];

        // Voeg wedstrijddag info toe
        if ($isWedstrijddag && $huidigeBlok) {
            $response['wedstrijddag'] = true;
            $response['huidige_blok'] = [
                'nummer' => $huidigeBlok->nummer,
                'naam' => $huidigeBlok->naam,
            ];
        }

        return response()->json($response);
    }

    /**
     * API endpoint for drag-and-drop judoka move
     */
    public function verplaatsJudokaApi(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'judoka_id' => 'required|exists:judokas,id',
            'van_poule_id' => 'required|exists:poules,id',
            'naar_poule_id' => 'required|exists:poules,id',
        ]);

        $judoka = Judoka::findOrFail($validated['judoka_id']);
        $vanPoule = Poule::findOrFail($validated['van_poule_id']);
        $naarPoule = Poule::findOrFail($validated['naar_poule_id']);

        // Skip if same poule
        if ($vanPoule->id === $naarPoule->id) {
            return response()->json(['success' => true, 'message' => 'Geen wijziging']);
        }

        // Remove from current poule
        $vanPoule->judokas()->detach($judoka->id);

        // Add to new poule
        $nieuwePositie = $naarPoule->judokas()->count() + 1;
        $naarPoule->judokas()->attach($judoka->id, ['positie' => $nieuwePositie]);

        // Regenerate matches for both poules (need fresh judoka lists)
        $vanPoule->load('judokas');
        $naarPoule->load('judokas');
        $vanPoule->wedstrijden()->delete();
        $naarPoule->wedstrijden()->delete();
        $this->wedstrijdService->genereerWedstrijdenVoorPoule($vanPoule);
        $this->wedstrijdService->genereerWedstrijdenVoorPoule($naarPoule);

        // Update statistics and refresh to get final state
        $vanPoule->updateStatistieken();
        $naarPoule->updateStatistieken();
        $vanPoule->refresh();
        $naarPoule->refresh();

        // Calculate ranges and update titels
        $huidigJaar = now()->year;
        $vanRanges = $this->berekenPouleRanges($vanPoule, $huidigJaar);
        $naarRanges = $this->berekenPouleRanges($naarPoule, $huidigJaar);
        $vanTitel = $this->updateDynamischeTitel($vanPoule, $vanRanges);
        $naarTitel = $this->updateDynamischeTitel($naarPoule, $naarRanges);

        return response()->json([
            'success' => true,
            'message' => "{$judoka->naam} verplaatst naar {$naarTitel}",
            'van_poule' => [
                'id' => $vanPoule->id,
                'nummer' => $vanPoule->nummer,
                'judokas_count' => $vanPoule->aantal_judokas,
                'aantal_wedstrijden' => $vanPoule->aantal_wedstrijden,
                'titel' => $vanPoule->titel,
                ...$vanRanges,
            ],
            'naar_poule' => [
                'id' => $naarPoule->id,
                'nummer' => $naarPoule->nummer,
                'judokas_count' => $naarPoule->aantal_judokas,
                'aantal_wedstrijden' => $naarPoule->aantal_wedstrijden,
                'titel' => $naarPoule->titel,
                ...$naarRanges,
            ],
        ]);
    }

    /**
     * Update kruisfinale plaatsen (how many qualify from each voorronde)
     */
    public function updateKruisfinale(Organisator $organisator, Request $request, Toernooi $toernooi, Poule $poule): JsonResponse
    {
        if (!$poule->isKruisfinale()) {
            return response()->json(['success' => false, 'message' => 'Dit is geen kruisfinale poule'], 400);
        }

        $validated = $request->validate([
            'kruisfinale_plaatsen' => 'required|integer|min:1|max:3',
        ]);

        // Count how many voorrondepoules feed into this kruisfinale
        $aantalVoorrondepoules = Poule::where('toernooi_id', $toernooi->id)
            ->where('leeftijdsklasse', $poule->leeftijdsklasse)
            ->where('gewichtsklasse', $poule->gewichtsklasse)
            ->where('type', 'voorronde')
            ->count();

        $kruisfinalesPlaatsen = $validated['kruisfinale_plaatsen'];
        $aantalJudokas = $aantalVoorrondepoules * $kruisfinalesPlaatsen;

        // Calculate wedstrijden
        $aantalWedstrijden = $aantalJudokas <= 1 ? 0 : ($aantalJudokas === 3 ? 6 : intval(($aantalJudokas * ($aantalJudokas - 1)) / 2));

        $poule->update([
            'kruisfinale_plaatsen' => $kruisfinalesPlaatsen,
            'aantal_judokas' => $aantalJudokas,
            'aantal_wedstrijden' => $aantalWedstrijden,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Kruisfinale aangepast: top {$kruisfinalesPlaatsen} door ({$aantalJudokas} judoka's)",
            'aantal_judokas' => $aantalJudokas,
            'aantal_wedstrijden' => $aantalWedstrijden,
        ]);
    }

    /**
     * Show elimination bracket for a poule
     */
    public function eliminatie(Organisator $organisator, Toernooi $toernooi, Poule $poule): View
    {
        $poule->load(['judokas.club', 'wedstrijden.judokaWit', 'wedstrijden.judokaBlauw', 'wedstrijden.winnaar']);

        $bracket = $this->eliminatieService->getBracketStructuur($poule);
        $heeftEliminatie = $poule->wedstrijden()->where('groep', 'A')->exists();

        return view('pages.poule.eliminatie', compact('toernooi', 'poule', 'bracket', 'heeftEliminatie'));
    }

    /**
     * Generate elimination bracket for a poule
     */
    public function genereerEliminatie(Organisator $organisator, Toernooi $toernooi, Poule $poule): JsonResponse
    {
        $judokas = $poule->judokas;

        if ($judokas->count() < 2) {
            return response()->json([
                'success' => false,
                'message' => 'Minimaal 2 judoka\'s nodig voor eliminatie',
            ], 400);
        }

        // Alleen aanwezige judoka's (niet afwezig)
        $judokaIds = $judokas
            ->filter(fn($j) => $j->aanwezigheid !== 'afwezig')
            ->pluck('id')
            ->toArray();
        $eliminatieType = $toernooi->eliminatie_type ?? 'dubbel';
        $statistieken = $this->eliminatieService->genereerBracket($poule, $judokaIds, $eliminatieType);

        if (isset($statistieken['error'])) {
            return response()->json([
                'success' => false,
                'message' => $statistieken['error'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => "Eliminatie bracket gegenereerd: {$statistieken['totaal_wedstrijden']} wedstrijden",
            'statistieken' => $statistieken,
        ]);
    }

    /**
     * Save match result in elimination bracket
     */
    public function opslaanEliminatieUitslag(Organisator $organisator, Request $request, Toernooi $toernooi, Poule $poule): JsonResponse
    {
        $validated = $request->validate([
            'wedstrijd_id' => 'required|exists:wedstrijden,id',
            'winnaar_id' => 'required|exists:judokas,id',
            'uitslag_type' => 'nullable|string|in:ippon,wazari,yuko,beslissing,opgave',
        ]);

        $wedstrijd = Wedstrijd::findOrFail($validated['wedstrijd_id']);

        // Verify the winner is one of the participants
        if ($validated['winnaar_id'] != $wedstrijd->judoka_wit_id &&
            $validated['winnaar_id'] != $wedstrijd->judoka_blauw_id) {
            return response()->json([
                'success' => false,
                'message' => 'Winnaar moet een van de deelnemers zijn',
            ], 400);
        }

        // Update the match
        $wedstrijd->update([
            'winnaar_id' => $validated['winnaar_id'],
            'is_gespeeld' => true,
            'uitslag_type' => $validated['uitslag_type'] ?? 'ippon',
        ]);

        // Process advancement
        $eliminatieType = $toernooi->eliminatie_type ?? 'dubbel';
        $this->eliminatieService->verwerkUitslag($wedstrijd, $validated['winnaar_id'], null, $eliminatieType);

        return response()->json([
            'success' => true,
            'message' => 'Uitslag opgeslagen',
        ]);
    }

    /**
     * Verplaats judoka in B-groep (seeding)
     * Alleen toegestaan als de bracket nog in seeding fase is
     */
    public function seedingBGroep(Organisator $organisator, Request $request, Toernooi $toernooi, Poule $poule): JsonResponse
    {
        $validated = $request->validate([
            'judoka_id' => 'required|exists:judokas,id',
            'van_wedstrijd_id' => 'required|exists:wedstrijden,id',
            'naar_wedstrijd_id' => 'required|exists:wedstrijden,id',
            'naar_slot' => 'required|in:wit,blauw',
        ]);

        $vanWedstrijd = Wedstrijd::findOrFail($validated['van_wedstrijd_id']);
        $naarWedstrijd = Wedstrijd::findOrFail($validated['naar_wedstrijd_id']);

        // Validatie: beide wedstrijden moeten in B-groep zijn
        if ($vanWedstrijd->groep !== 'B' || $naarWedstrijd->groep !== 'B') {
            return response()->json([
                'success' => false,
                'message' => 'Seeding is alleen mogelijk binnen de B-groep',
            ], 400);
        }

        // Validatie: beide wedstrijden moeten bij dezelfde poule horen
        if ($vanWedstrijd->poule_id !== $poule->id || $naarWedstrijd->poule_id !== $poule->id) {
            return response()->json([
                'success' => false,
                'message' => 'Wedstrijden horen niet bij deze poule',
            ], 400);
        }

        // Validatie: check of bracket nog in seeding fase is (geen wedstrijden gespeeld in B-groep)
        $bWedstrijdenGespeeld = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'B')
            ->where('is_gespeeld', true)
            ->exists();

        if ($bWedstrijdenGespeeld) {
            return response()->json([
                'success' => false,
                'message' => 'Bracket is vergrendeld - er zijn al wedstrijden gespeeld in de B-groep',
            ], 400);
        }

        // Validatie: judoka moet in van_wedstrijd zitten
        $judokaId = $validated['judoka_id'];
        $vanSlot = null;
        if ($vanWedstrijd->judoka_wit_id == $judokaId) {
            $vanSlot = 'wit';
        } elseif ($vanWedstrijd->judoka_blauw_id == $judokaId) {
            $vanSlot = 'blauw';
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Judoka zit niet in de bron wedstrijd',
            ], 400);
        }

        // Validatie: doel slot moet leeg zijn
        $naarSlot = $validated['naar_slot'];
        if ($naarWedstrijd->{"judoka_{$naarSlot}_id"} !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Doel slot is niet leeg',
            ], 400);
        }

        // Check op potentiële rematch
        $potentieleTegenstander = $naarSlot === 'wit'
            ? $naarWedstrijd->judoka_blauw_id
            : $naarWedstrijd->judoka_wit_id;

        $waarschuwing = null;
        if ($potentieleTegenstander) {
            if ($this->eliminatieService->heeftAlGespeeld($poule->id, $judokaId, $potentieleTegenstander)) {
                $tegenstander = \App\Models\Judoka::find($potentieleTegenstander);
                $waarschuwing = "Let op: dit veroorzaakt een rematch met {$tegenstander->naam}";
            }
        }

        // Voer de verplaatsing uit
        $vanWedstrijd->update(["judoka_{$vanSlot}_id" => null]);
        $naarWedstrijd->update(["judoka_{$naarSlot}_id" => $judokaId]);

        $judoka = \App\Models\Judoka::find($judokaId);

        return response()->json([
            'success' => true,
            'message' => "{$judoka->naam} verplaatst naar {$naarWedstrijd->ronde}",
            'waarschuwing' => $waarschuwing,
        ]);
    }

    /**
     * Haal B-groep seeding informatie op
     */
    public function getBGroepSeeding(Organisator $organisator, Toernooi $toernooi, Poule $poule): JsonResponse
    {
        $poule->load(['wedstrijden.judokaWit', 'wedstrijden.judokaBlauw']);

        $bWedstrijden = $poule->wedstrijden
            ->where('groep', 'B')
            ->sortBy('bracket_positie')
            ->groupBy('ronde');

        $isLocked = $poule->wedstrijden
            ->where('groep', 'B')
            ->where('is_gespeeld', true)
            ->isNotEmpty();

        // Verzamel potentiële rematches
        $rematches = [];
        foreach ($bWedstrijden as $ronde => $wedstrijden) {
            foreach ($wedstrijden as $wed) {
                if ($wed->judoka_wit_id && $wed->judoka_blauw_id) {
                    if ($this->eliminatieService->heeftAlGespeeld($poule->id, $wed->judoka_wit_id, $wed->judoka_blauw_id)) {
                        $rematches[] = [
                            'wedstrijd_id' => $wed->id,
                            'ronde' => $ronde,
                            'judoka_wit' => $wed->judokaWit->naam,
                            'judoka_blauw' => $wed->judokaBlauw->naam,
                        ];
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'is_locked' => $isLocked,
            'rematches' => $rematches,
            'wedstrijden' => $bWedstrijden,
        ]);
    }

    /**
     * Haal A-groep seeding informatie op
     */
    public function getSeedingStatus(Organisator $organisator, Toernooi $toernooi, Poule $poule): JsonResponse
    {
        $poule->load(['wedstrijden.judokaWit.club', 'wedstrijden.judokaBlauw.club']);

        $eersteRonde = $poule->wedstrijden
            ->where('groep', 'A')
            ->whereIn('ronde', ['voorronde', 'achtste_finale', 'kwartfinale', 'zestiende_finale'])
            ->sortBy('bracket_positie');

        $isLocked = !$this->eliminatieService->isInSeedingFase($poule);

        // Groepeer clubgenoten die tegen elkaar moeten
        $clubConflicten = [];
        foreach ($eersteRonde as $wed) {
            if ($wed->judoka_wit_id && $wed->judoka_blauw_id) {
                $clubWit = $wed->judokaWit->club_id ?? null;
                $clubBlauw = $wed->judokaBlauw->club_id ?? null;
                if ($clubWit && $clubWit === $clubBlauw) {
                    $clubConflicten[] = [
                        'wedstrijd_id' => $wed->id,
                        'ronde' => $wed->ronde,
                        'judoka_wit' => $wed->judokaWit->naam,
                        'judoka_blauw' => $wed->judokaBlauw->naam,
                        'club' => $wed->judokaWit->club->naam ?? 'Onbekend',
                    ];
                }
            }
        }

        return response()->json([
            'success' => true,
            'is_locked' => $isLocked,
            'club_conflicten' => $clubConflicten,
            'wedstrijden' => $eersteRonde->values(),
        ]);
    }

    /**
     * Swap twee judoka's in de eerste ronde (A-groep seeding)
     * Alleen mogelijk in seeding-fase (voor eerste wedstrijd)
     */
    public function swapSeeding(Organisator $organisator, Request $request, Toernooi $toernooi, Poule $poule): JsonResponse
    {
        $validated = $request->validate([
            'judoka_a_id' => 'required|exists:judokas,id',
            'judoka_b_id' => 'required|exists:judokas,id',
        ]);

        $result = $this->eliminatieService->swapJudokas(
            $poule,
            $validated['judoka_a_id'],
            $validated['judoka_b_id']
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Verplaats judoka naar lege plek in eerste ronde (A-groep seeding)
     * Alleen mogelijk in seeding-fase (voor eerste wedstrijd)
     */
    public function moveSeeding(Organisator $organisator, Request $request, Toernooi $toernooi, Poule $poule): JsonResponse
    {
        $validated = $request->validate([
            'judoka_id' => 'required|exists:judokas,id',
            'naar_wedstrijd_id' => 'required|exists:wedstrijden,id',
            'naar_positie' => 'required|in:wit,blauw',
        ]);

        $result = $this->eliminatieService->moveJudokaNaarLegePlek(
            $poule,
            $validated['judoka_id'],
            $validated['naar_wedstrijd_id'],
            $validated['naar_positie']
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Herstel B-groep koppelingen voor bestaande bracket
     */
    public function herstelBKoppelingen(Organisator $organisator, Toernooi $toernooi, Poule $poule): \Illuminate\Http\JsonResponse
    {
        $hersteld = $this->eliminatieService->herstelBKoppelingen($poule->id);

        return response()->json([
            'success' => true,
            'message' => "{$hersteld} B-koppelingen hersteld",
            'hersteld' => $hersteld,
        ]);
    }

    /**
     * Diagnose B-koppelingen - toon huidige koppelingen zonder wijzigingen
     */
    public function diagnoseBKoppelingen(Organisator $organisator, Toernooi $toernooi, Poule $poule): \Illuminate\Http\JsonResponse
    {
        $wedstrijden = Wedstrijd::where('poule_id', $poule->id)
            ->where('groep', 'B')
            ->orderBy('volgorde')
            ->get(['id', 'ronde', 'bracket_positie', 'volgende_wedstrijd_id', 'winnaar_naar_slot', 'locatie_wit', 'locatie_blauw']);

        $perRonde = [];
        foreach ($wedstrijden as $wed) {
            $perRonde[$wed->ronde][] = [
                'id' => $wed->id,
                'pos' => $wed->bracket_positie,
                'volgende' => $wed->volgende_wedstrijd_id,
                'slot' => $wed->winnaar_naar_slot,
                'loc_wit' => $wed->locatie_wit,
                'loc_blauw' => $wed->locatie_blauw,
            ];
        }

        return response()->json([
            'poule' => $poule->naam,
            'rondes' => array_keys($perRonde),
            'koppelingen' => $perRonde,
        ]);
    }

    /**
     * Calculate min/max age and weight ranges for a poule
     */
    private function berekenPouleRanges(Poule $poule, int $huidigJaar): array
    {
        // Force fresh query to avoid SQLite caching issues
        // Filter afwezige judoka's - alleen aanwezigen tellen mee voor ranges
        $judokas = $poule->judokas()->get()->filter(fn($j) => $j->aanwezigheid !== 'afwezig');

        if ($judokas->isEmpty()) {
            return [
                'leeftijd_range' => '',
                'gewicht_range' => '',
            ];
        }

        $leeftijden = $judokas->map(fn($j) => $j->geboortejaar ? $huidigJaar - $j->geboortejaar : null)->filter();

        // Gewichten: gewogen > ingeschreven > gewichtsklasse
        $gewichten = $judokas->map(function($j) {
            if ($j->gewicht_gewogen !== null) return $j->gewicht_gewogen;
            if ($j->gewicht !== null) return $j->gewicht;
            // Gewichtsklasse is bijv. "-38" of "+73" - extract getal
            if ($j->gewichtsklasse && preg_match('/(\d+)/', $j->gewichtsklasse, $m)) {
                return (float) $m[1];
            }
            return null;
        })->filter();

        $leeftijdRange = '';
        $gewichtRange = '';

        if ($leeftijden->count() > 0) {
            $minL = $leeftijden->min();
            $maxL = $leeftijden->max();
            $leeftijdRange = $minL === $maxL ? "{$minL}j" : "{$minL}-{$maxL}j";
        }

        if ($gewichten->count() > 0) {
            $minG = $gewichten->min();
            $maxG = $gewichten->max();
            $gewichtRange = $minG === $maxG ? "{$minG}kg" : "{$minG}-{$maxG}kg";
        }

        return [
            'leeftijd_range' => $leeftijdRange,
            'gewicht_range' => $gewichtRange,
        ];
    }

    /**
     * Update poule titel als deze dynamisch is (variabele categorie)
     * Retourneert de (eventueel bijgewerkte) titel
     *
     * Variabele categorieën worden herkend aan:
     * - max_leeftijd_verschil > 0 of max_kg_verschil > 0 in categorie config
     */
    private function updateDynamischeTitel(Poule $poule, array $ranges): string
    {
        $titel = $poule->titel;
        $toernooi = $poule->toernooi;

        // Get category config for this pool's leeftijdsklasse
        $config = $toernooi->getAlleGewichtsklassen();
        $categorieConfig = null;

        // Find matching category config - try by label first, then by categorie_key
        foreach ($config as $key => $data) {
            if (($data['label'] ?? '') === $poule->leeftijdsklasse) {
                $categorieConfig = $data;
                break;
            }
        }

        // Fallback: search by categorie_key
        if (!$categorieConfig && $poule->categorie_key) {
            $categorieConfig = $config[$poule->categorie_key] ?? null;
        }

        // Check if this is a variable category
        $maxLftVerschil = (int) ($categorieConfig['max_leeftijd_verschil'] ?? 0);

        // Build new title based on config
        $parts = [];

        // 1. Label (optional)
        $toonLabel = $categorieConfig['toon_label_in_titel'] ?? true;
        if ($toonLabel && !empty($categorieConfig['label'])) {
            $parts[] = $categorieConfig['label'];
        }

        // 2. Gender (if not mixed)
        $geslacht = $poule->geslacht;
        if ($geslacht && $geslacht !== 'gemengd') {
            $parts[] = $geslacht;
        }

        // 3. Age range (if variable leeftijd)
        if ($maxLftVerschil > 0) {
            $leeftijdRange = $ranges['leeftijd_range'] ?? '';
            if ($leeftijdRange) {
                $parts[] = $leeftijdRange;
            }
        }

        // 4. Weight range - always show actual range from judokas
        $gewichtRange = $ranges['gewicht_range'] ?? '';
        if ($gewichtRange) {
            $parts[] = $gewichtRange;
        }

        $nieuweTitel = implode(' ', $parts) ?: $titel;

        // Update database if title changed
        if ($nieuweTitel !== $titel) {
            $poule->update(['titel' => $nieuweTitel]);
        }

        return $nieuweTitel;
    }
}
