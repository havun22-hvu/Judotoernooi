<?php

namespace App\Http\Controllers;

use App\Models\Mat;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Services\BlokMatVerdelingService;
use App\Services\WedstrijdSchemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Handles the spreker (announcer) interface — standings, announcements,
 * notities, poule/category moves and barrage creation. Split out of
 * BlokController to keep the main controller focused on voorbereiding.
 */
class BlokSprekerController extends Controller
{
    public function __construct(
        private BlokMatVerdelingService $verdelingService,
        private WedstrijdSchemaService $wedstrijdService
    ) {}

    public function sprekerInterface(Organisator $organisator, Toernooi $toernooi): View
    {
        $overzicht = $this->verdelingService->getZaalOverzicht($toernooi);

        // Get poules that are ready for spreker (with results) but not yet announced
        // EXCLUDE barrage poules - they are only used to determine standings in original poule
        $klarePoules = $toernooi->poules()
            ->whereNotNull('spreker_klaar')
            ->whereNull('afgeroepen_at')
            ->where('type', '!=', 'barrage')  // Don't show barrage poules separately
            ->with(['mat', 'blok', 'judokas.club', 'wedstrijden'])
            ->orderBy('spreker_klaar', 'asc')  // Oldest first (longest waiting at top)
            ->get()
            ->filter(function ($poule) {
                // PUNTENCOMPETITIE: geen uitslagen naar spreker (geen directe winnaar)
                return !$poule->isPuntenCompetitie();
            })
            ->map(function ($poule) use ($toernooi) {
                // ELIMINATIE: Haal medaille winnaars direct uit bracket
                if ($poule->type === 'eliminatie') {
                    $poule->standings = $this->getEliminatieStandings($poule);
                    $poule->is_eliminatie = true;
                    return $poule;
                }

                // Check if there's a completed barrage for this poule
                $barrage = $toernooi->poules()
                    ->where('barrage_van_poule_id', $poule->id)
                    ->whereNotNull('spreker_klaar')
                    ->with(['wedstrijden', 'judokas'])
                    ->first();

                // POULE: Calculate WP/JP/gewonnen from wedstrijden + barrage for each judoka
                // Filter out absent judokas (weging check happens at doorsturen, not here)
                $activeJudokas = $poule->judokas->filter(function ($judoka) {
                    return $judoka->aanwezigheid !== 'afwezig';
                });

                $isPuntenComp = false; // Puntencompetitie poules are already filtered out above
                $standings = $activeJudokas->map(function ($judoka) use ($poule, $barrage, $isPuntenComp) {
                    $wp = 0;
                    $jp = 0;
                    $gewonnen = 0;

                    // Points from original poule
                    foreach ($poule->wedstrijden as $w) {
                        if (!$w->is_gespeeld) continue;
                        $isInWedstrijd = $w->judoka_wit_id === $judoka->id || $w->judoka_blauw_id === $judoka->id;
                        if (!$isInWedstrijd) continue;

                        if ($w->winnaar_id === $judoka->id) $gewonnen++;

                        if (!$isPuntenComp) {
                            // JP
                            if ($w->judoka_wit_id === $judoka->id) {
                                $jp += (int) preg_replace('/[^0-9]/', '', $w->score_wit ?? '');
                            } else {
                                $jp += (int) preg_replace('/[^0-9]/', '', $w->score_blauw ?? '');
                            }

                            // WP: Win=2, Draw=1, Loss=0
                            if ($w->winnaar_id === $judoka->id) {
                                $wp += 2;
                            } elseif ($w->winnaar_id === null) {
                                $wp += 1; // Gelijkspel
                            }
                        }
                    }

                    // ADD barrage points if judoka participated in barrage
                    if ($barrage && $barrage->judokas->contains('id', $judoka->id)) {
                        foreach ($barrage->wedstrijden as $w) {
                            if (!$w->is_gespeeld) continue;
                            $isInWedstrijd = $w->judoka_wit_id === $judoka->id || $w->judoka_blauw_id === $judoka->id;
                            if (!$isInWedstrijd) continue;

                            if ($w->winnaar_id === $judoka->id) $gewonnen++;

                            if (!$isPuntenComp) {
                                // JP
                                if ($w->judoka_wit_id === $judoka->id) {
                                    $jp += (int) preg_replace('/[^0-9]/', '', $w->score_wit ?? '');
                                } else {
                                    $jp += (int) preg_replace('/[^0-9]/', '', $w->score_blauw ?? '');
                                }

                                // WP: Win=2, Draw=1, Loss=0
                                if ($w->winnaar_id === $judoka->id) {
                                    $wp += 2;
                                } elseif ($w->winnaar_id === null) {
                                    $wp += 1;
                                }
                            }
                        }
                    }

                    return ['judoka' => $judoka, 'wp' => (int) $wp, 'jp' => (int) $jp, 'gewonnen' => (int) $gewonnen];
                });

                // Sort: punten competitie by gewonnen, normal by WP/JP/h2h
                $wedstrijden = $poule->wedstrijden;
                if ($isPuntenComp) {
                    $poule->standings = $standings->sortByDesc('gewonnen')->values();
                } else {
                    $poule->standings = $standings->sort(function ($a, $b) use ($wedstrijden) {
                        if ($a['wp'] !== $b['wp']) return $b['wp'] - $a['wp'];
                        if ($a['jp'] !== $b['jp']) return $b['jp'] - $a['jp'];
                        foreach ($wedstrijden as $w) {
                            $isMatch = ($w->judoka_wit_id === $a['judoka']->id && $w->judoka_blauw_id === $b['judoka']->id)
                                    || ($w->judoka_wit_id === $b['judoka']->id && $w->judoka_blauw_id === $a['judoka']->id);
                            if ($isMatch && $w->winnaar_id) {
                                return $w->winnaar_id === $a['judoka']->id ? -1 : 1;
                            }
                        }
                        return 0;
                    })->values();
                }

                $poule->is_eliminatie = false;
                $poule->is_punten_competitie = $isPuntenComp;
                $poule->has_barrage = $barrage !== null;
                return $poule;
            });

        // Recent afgeroepen poules (laatste 30 minuten) - voor "Terug" functie
        $afgeroepen = $toernooi->poules()
            ->whereNotNull('afgeroepen_at')
            ->where('afgeroepen_at', '>=', now()->subMinutes(30))
            ->with(['mat', 'blok', 'judokas.club', 'wedstrijden'])
            ->orderBy('afgeroepen_at', 'desc')
            ->get()
            ->map(function ($poule) {
                if ($poule->type === 'eliminatie') {
                    $poule->standings = $this->getEliminatieStandings($poule);
                    $poule->is_eliminatie = true;
                }
                return $poule;
            });

        // Poules per blok/mat voor "Oproepen" tab (alleen doorgestuurde poules)
        $blokken = $toernooi->blokken()
            ->with(['poules' => function ($q) {
                $q->with(['mat', 'judokas.club'])
                    ->whereNotNull('mat_id')
                    ->whereNotNull('doorgestuurd_op')
                    ->orderBy('mat_id')
                    ->orderBy('nummer');
            }])
            ->orderBy('nummer')
            ->get();

        // Groepeer poules per blok per mat
        $poulesPerBlok = $blokken->mapWithKeys(function ($blok) {
            $poulesPerMat = $blok->poules->groupBy('mat_id')->map(function ($poules, $matId) {
                return [
                    'mat' => $poules->first()->mat,
                    'poules' => $poules,
                ];
            })->sortKeys();
            return [$blok->nummer => ['blok' => $blok, 'matten' => $poulesPerMat]];
        });

        // Wimpel milestone-uitreikingen voor spreker (puntencompetitie)
        $wimpelUitreikingen = \App\Models\WimpelUitreiking::where('uitgereikt', false)
            ->whereHas('stamJudoka', function ($q) use ($toernooi) {
                $q->where('organisator_id', $toernooi->organisator_id);
            })
            ->where('toernooi_id', $toernooi->id)
            ->with(['stamJudoka', 'milestone'])
            ->get()
            ->sortBy('milestone.punten')
            ->values();

        // Admin versie met layouts.app menu (zie docs: INTERFACES.md)
        return view('pages.spreker.interface-admin', compact('toernooi', 'klarePoules', 'afgeroepen', 'poulesPerBlok', 'wimpelUitreikingen'));
    }

    /**
     * Calculate poule standings (WP/JP sorted)
     */
    private function berekenPouleStand($poule): \Illuminate\Support\Collection
    {
        // Filter out absent judokas (not weighed or marked afwezig)
        $activeJudokas = $poule->judokas->filter(function ($judoka) {
            return $judoka->gewicht_gewogen > 0 && $judoka->aanwezigheid !== 'afwezig';
        });

        $standings = $activeJudokas->map(function ($judoka) use ($poule) {
            $wp = 0;
            $jp = 0;

            foreach ($poule->wedstrijden as $w) {
                if (!$w->is_gespeeld) continue;
                $isInWedstrijd = $w->judoka_wit_id === $judoka->id || $w->judoka_blauw_id === $judoka->id;
                if (!$isInWedstrijd) continue;

                // JP
                if ($w->judoka_wit_id === $judoka->id) {
                    $jp += (int) preg_replace('/[^0-9]/', '', $w->score_wit ?? '');
                } else {
                    $jp += (int) preg_replace('/[^0-9]/', '', $w->score_blauw ?? '');
                }

                // WP: Win=2, Draw=1, Loss=0
                if ($w->winnaar_id === $judoka->id) {
                    $wp += 2;
                } elseif ($w->winnaar_id === null) {
                    $wp += 1; // Gelijkspel
                }
            }

            return [
                'judoka' => $judoka,
                'wp' => (int) $wp,
                'jp' => (int) $jp,
            ];
        });

        $wedstrijden = $poule->wedstrijden;
        return $standings->sort(function ($a, $b) use ($wedstrijden) {
            if ($a['wp'] !== $b['wp']) return $b['wp'] - $a['wp'];
            if ($a['jp'] !== $b['jp']) return $b['jp'] - $a['jp'];
            foreach ($wedstrijden as $w) {
                $isMatch = ($w->judoka_wit_id === $a['judoka']->id && $w->judoka_blauw_id === $b['judoka']->id)
                        || ($w->judoka_wit_id === $b['judoka']->id && $w->judoka_blauw_id === $a['judoka']->id);
                if ($isMatch && $w->winnaar_id) {
                    return $w->winnaar_id === $a['judoka']->id ? -1 : 1;
                }
            }
            return 0;
        })->values();
    }

    /**
     * Get standings for elimination bracket (medal winners only)
     * Returns: 1=Goud (finale winnaar), 2=Zilver (finale verliezer), 3=Brons (1 of 2)
     */
    private function getEliminatieStandings($poule): \Illuminate\Support\Collection
    {
        $standings = collect();

        // 1. GOUD = Finale winnaar (A-groep)
        $finale = $poule->wedstrijden->first(fn($w) => $w->groep === 'A' && $w->ronde === 'finale');
        if ($finale && $finale->is_gespeeld && $finale->winnaar_id) {
            $goud = $finale->winnaar_id === $finale->judoka_wit_id
                ? $poule->judokas->firstWhere('id', $finale->judoka_wit_id)
                : $poule->judokas->firstWhere('id', $finale->judoka_blauw_id);
            if ($goud) {
                $standings->push(['judoka' => $goud, 'wp' => null, 'jp' => null, 'plaats' => 1]);
            }

            // 2. ZILVER = Finale verliezer
            $zilver = $finale->winnaar_id === $finale->judoka_wit_id
                ? $poule->judokas->firstWhere('id', $finale->judoka_blauw_id)
                : $poule->judokas->firstWhere('id', $finale->judoka_wit_id);
            if ($zilver) {
                $standings->push(['judoka' => $zilver, 'wp' => null, 'jp' => null, 'plaats' => 2]);
            }
        }

        // 3. BRONS = Winnaars van b_halve_finale_2 of b_brons of b_finale
        $bronsWedstrijden = $poule->wedstrijden->filter(fn($w) =>
            in_array($w->ronde, ['b_halve_finale_2', 'b_brons', 'b_finale']) && $w->is_gespeeld && $w->winnaar_id
        );

        foreach ($bronsWedstrijden as $bronsWed) {
            $brons = $bronsWed->winnaar_id === $bronsWed->judoka_wit_id
                ? $poule->judokas->firstWhere('id', $bronsWed->judoka_wit_id)
                : $poule->judokas->firstWhere('id', $bronsWed->judoka_blauw_id);
            if ($brons && !$standings->contains(fn($s) => $s['judoka']?->id === $brons->id)) {
                $standings->push(['judoka' => $brons, 'wp' => null, 'jp' => null, 'plaats' => 3]);
            }
        }

        return $standings;
    }

    /**
     * Wimpel milestone uitgereikt markeren (spreker vinkt af)
     */
    public function wimpelUitgereikt(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'uitreiking_id' => 'required|exists:wimpel_uitreikingen,id',
        ]);

        $uitreiking = \App\Models\WimpelUitreiking::findOrFail($validated['uitreiking_id']);
        $uitreiking->update([
            'uitgereikt' => true,
            'uitgereikt_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Uitreiking geregistreerd',
        ]);
    }

    public function markeerAfgeroepen(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'poule_id' => 'required|exists:poules,id',
        ]);

        $poule = Poule::findOrFail($validated['poule_id']);
        $poule->update(['afgeroepen_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => "Poule {$poule->nummer} afgeroepen",
        ]);
    }

    /**
     * Zet afgeroepen poule terug naar klaar (undo)
     */
    public function zetAfgeroepenTerug(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'poule_id' => 'required|exists:poules,id',
        ]);

        $poule = Poule::findOrFail($validated['poule_id']);
        $poule->update(['afgeroepen_at' => null]);

        return response()->json([
            'success' => true,
            'message' => "Poule {$poule->nummer} teruggezet",
        ]);
    }

    /**
     * Get poule standings for speaker interface (view previously announced)
     */
    public function getPouleStandings(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'poule_id' => 'required|exists:poules,id',
        ]);

        $poule = Poule::with(['judokas.club', 'wedstrijden'])->findOrFail($validated['poule_id']);
        $isEliminatie = $poule->type === 'eliminatie';

        if ($isEliminatie) {
            $standings = $this->getEliminatieStandings($poule);
        } else {
            $standings = $this->berekenPouleStand($poule);
        }

        return response()->json([
            'success' => true,
            'poule' => [
                'id' => $poule->id,
                'nummer' => $poule->nummer,
                'leeftijdsklasse' => $poule->leeftijdsklasse,
                'gewichtsklasse' => $poule->gewichtsklasse,
                'type' => $poule->type,
                'is_eliminatie' => $isEliminatie,
            ],
            'standings' => $standings->map(fn($s) => [
                'naam' => $s['judoka']->naam,
                'club' => $s['judoka']->club?->naam ?? '-',
                'wp' => $s['wp'],
                'jp' => $s['jp'],
                'plaats' => $s['plaats'] ?? null,
            ])->toArray(),
        ]);
    }

    /**
     * Save speaker notes to tournament (persisted for next year)
     */
    public function saveNotities(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'notities' => 'nullable|string|max:10000',
        ]);

        $toernooi->update(['spreker_notities' => $validated['notities']]);

        return response()->json([
            'success' => true,
            'message' => 'Notities opgeslagen',
        ]);
    }

    /**
     * Get speaker notes from tournament
     */
    public function getNotities(Organisator $organisator, Toernooi $toernooi): JsonResponse
    {
        return response()->json([
            'success' => true,
            'notities' => $toernooi->spreker_notities ?? '',
        ]);
    }

    public function verplaatsPoule(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'poule_id' => 'required|exists:poules,id',
            'mat_id' => 'required|exists:matten,id',
            'groep' => 'nullable|in:A,B',
        ]);

        $poule = Poule::findOrFail($validated['poule_id']);
        $groep = $validated['groep'] ?? null;
        $nieuweMatId = $validated['mat_id'];

        // Determine which mat field to update
        if ($groep === 'B') {
            $oudeMatId = $poule->b_mat_id;
            $updateField = 'b_mat_id';
        } else {
            $oudeMatId = $poule->mat_id;
            $updateField = 'mat_id';
        }

        // Reset geel (volgende_wedstrijd) op oude mat als het een wedstrijd van deze poule was
        // Groen blijft staan - mat-jury moet handmatig stoppen
        if ($oudeMatId && $oudeMatId != $nieuweMatId) {
            $oudeMat = Mat::find($oudeMatId);
            if ($oudeMat) {
                $oudeMat->resetWedstrijdSelectieVoorPoule($poule->id);
            }
        }

        // Mat_id of b_mat_id wijzigen - wedstrijden en scores blijven intact
        $poule->update([$updateField => $nieuweMatId]);

        $suffix = $groep ? " ({$groep})" : '';
        return response()->json([
            'success' => true,
            'message' => "Poule {$poule->nummer}{$suffix} verplaatst",
        ]);
    }

    /**
     * Verplaats een categorie naar een blok (drag & drop)
     * vast parameter determines if category is pinned
     */
    public function verplaatsCategorie(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'key' => 'required|string',
            'blok' => 'required|integer|min:0',
            'vast' => 'nullable|boolean',
        ]);

        $key = $validated['key'];
        $blokNummer = $validated['blok'];

        // Blok 0 = niet verdeeld (null), also unpin
        $blokId = null;
        $blokVast = false;

        if ($blokNummer > 0) {
            $blok = $toernooi->blokken()->where('nummer', $blokNummer)->first();
            if ($blok) {
                $blokId = $blok->id;
                // Use vast from request, default false (drag = not pinned)
                $blokVast = $validated['vast'] ?? false;
            }
        }

        // Check key format: "poule_123" (single poule) or "leeftijd|gewicht" (category)
        if (str_starts_with($key, 'poule_')) {
            // Single poule by ID
            $pouleId = (int) substr($key, 6);
            $updated = $toernooi->poules()
                ->where('id', $pouleId)
                ->update(['blok_id' => $blokId, 'blok_vast' => $blokVast]);
        } else {
            // Category: "leeftijdsklasse|gewichtsklasse"
            $parts = explode('|', $key);
            if (count($parts) !== 2) {
                return response()->json(['success' => false, 'error' => 'Invalid key'], 400);
            }

            $leeftijdsklasse = $parts[0];
            $gewichtsklasse = $parts[1];

            // Update alle poules met deze categorie
            $updated = $toernooi->poules()
                ->where('leeftijdsklasse', $leeftijdsklasse)
                ->where('gewichtsklasse', $gewichtsklasse)
                ->update(['blok_id' => $blokId, 'blok_vast' => $blokVast]);
        }

        return response()->json(['success' => true, 'updated' => $updated, 'vast' => $blokVast]);
    }

    /**
     * Maak een barrage poule voor judoka's met gelijke stand (3-weg gelijkspel)
     * Judoka's blijven in originele poule, worden TOEGEVOEGD aan barrage (niet verplaatst)
     */
    public function maakBarrage(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'poule_id' => 'required|exists:poules,id',
            'judoka_ids' => 'required|array|min:2',
            'judoka_ids.*' => 'exists:judokas,id',
        ]);

        $originelePoule = Poule::with(['mat', 'blok', 'judokas'])->findOrFail($validated['poule_id']);

        // Verify poule belongs to this toernooi
        if ($originelePoule->toernooi_id !== $toernooi->id) {
            return response()->json(['success' => false, 'error' => 'Poule hoort niet bij dit toernooi'], 403);
        }

        // Get hoogste poule nummer voor nummering
        $maxNummer = $toernooi->poules()->max('nummer') ?? 0;

        // Maak barrage poule
        $barragePoule = Poule::create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $originelePoule->blok_id,
            'mat_id' => $originelePoule->mat_id,
            'nummer' => $maxNummer + 1,
            'leeftijdsklasse' => $originelePoule->leeftijdsklasse,
            'gewichtsklasse' => $originelePoule->gewichtsklasse,
            'type' => 'barrage', // Speciaal type
            'titel' => 'Barrage ' . $originelePoule->leeftijdsklasse . ' ' . $originelePoule->gewichtsklasse,
            'categorie_key' => $originelePoule->categorie_key,
            'barrage_van_poule_id' => $originelePoule->id, // Link naar originele poule
        ]);

        // Voeg judoka's toe aan barrage (NIET detach uit originele!)
        $positie = 1;
        foreach ($validated['judoka_ids'] as $judokaId) {
            $barragePoule->judokas()->attach($judokaId, ['positie' => $positie++]);
        }

        // Update statistieken
        $barragePoule->updateStatistieken();

        // Genereer wedstrijdschema
        $wedstrijden = $this->wedstrijdService->genereerWedstrijdenVoorPoule($barragePoule);

        // Doorsturen naar zaaloverzicht (zelfde mat als origineel)
        $barragePoule->update(['doorgestuurd_op' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Barrage poule aangemaakt',
            'barrage_poule' => [
                'id' => $barragePoule->id,
                'nummer' => $barragePoule->nummer,
                'titel' => $barragePoule->titel,
                'mat_id' => $barragePoule->mat_id,
                'aantal_judokas' => count($validated['judoka_ids']),
                'aantal_wedstrijden' => count($wedstrijden),
            ],
        ]);
    }
}
