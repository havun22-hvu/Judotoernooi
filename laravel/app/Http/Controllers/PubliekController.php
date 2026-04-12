<?php

namespace App\Http\Controllers;

use App\Enums\Band;
use App\Models\Club;
use App\Models\ClubAanmelding;
use App\Models\Organisator;
use App\Models\Blok;
use App\Models\Judoka;
use App\Models\Poule;
use App\Models\Toernooi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class PubliekController extends Controller
{
    /**
     * Show public tournament page with all judokas
     */
    public function index(Organisator $organisator, Toernooi $toernooi): View
    {
        // Get blokken with times
        $blokken = Blok::where('toernooi_id', $toernooi->id)
            ->orderBy('nummer')
            ->get();

        // Get total judoka count (all judokas)
        $totaalJudokas = Judoka::where('toernooi_id', $toernooi->id)->count();

        // Get judokas with complete data for category display
        // Include poules.blok and poules.mat for showing blok/mat info after voorbereiding
        $judokas = Judoka::where('toernooi_id', $toernooi->id)
            ->whereNotNull('leeftijdsklasse')
            ->with(['club', 'poules.blok', 'poules.mat'])
            ->orderBy('leeftijdsklasse')
            ->orderBy('naam')
            ->get();

        // Get toernooi category settings
        $categorieSettings = $toernooi->getAlleGewichtsklassen();

        // Build mapping: label -> settings (for quick lookup)
        $settingsPerLabel = [];
        foreach ($categorieSettings as $key => $settings) {
            $label = $settings['label'] ?? $key;
            $settingsPerLabel[$label] = $settings;
        }

        // Group by leeftijdsklasse, then apply correct grouping based on category type
        $categorien = $judokas->groupBy('leeftijdsklasse')->map(function ($groep, $leeftijdsklasse) use ($settingsPerLabel) {
            $settings = $settingsPerLabel[$leeftijdsklasse] ?? null;
            $maxKgVerschil = $settings['max_kg_verschil'] ?? 0;
            $vasteGewichten = $settings['gewichten'] ?? [];

            // Dynamische indeling (max_kg_verschil > 0): geen groepering op gewicht
            if ($maxKgVerschil > 0 || empty($vasteGewichten)) {
                // Return all judokas under one key, sorted by age then weight
                return collect([
                    'Alle' => $groep->sortBy([
                        fn($a, $b) => $a->leeftijd <=> $b->leeftijd,
                        fn($a, $b) => ($a->gewicht_gewogen ?? $a->gewicht ?? 0) <=> ($b->gewicht_gewogen ?? $b->gewicht ?? 0),
                    ])->values(),
                ]);
            }

            // Vaste gewichtsklassen: groepeer judoka's op basis van hun gewicht in de vaste klassen
            $grouped = collect();
            foreach ($vasteGewichten as $gewichtsklasse) {
                $grouped[$gewichtsklasse] = collect();
            }

            foreach ($groep as $judoka) {
                $judokaGewicht = $judoka->gewicht_gewogen ?? $judoka->gewicht ?? 0;
                $geplaatst = false;

                // Find the correct weight class for this judoka
                foreach ($vasteGewichten as $gewichtsklasse) {
                    $isPlusKlasse = str_starts_with($gewichtsklasse, '+');
                    $limiet = (float) preg_replace('/[^0-9.]/', '', $gewichtsklasse);

                    if ($isPlusKlasse) {
                        // +XX: judoka moet >= limiet zijn
                        if ($judokaGewicht >= $limiet) {
                            $grouped[$gewichtsklasse]->push($judoka);
                            $geplaatst = true;
                            break;
                        }
                    } else {
                        // -XX of XX: judoka moet <= limiet zijn
                        if ($judokaGewicht <= $limiet) {
                            $grouped[$gewichtsklasse]->push($judoka);
                            $geplaatst = true;
                            break;
                        }
                    }
                }

                // Fallback: if not placed, put in last (heaviest) class
                if (!$geplaatst && $vasteGewichten) {
                    $lastClass = end($vasteGewichten);
                    $grouped[$lastClass]->push($judoka);
                }
            }

            // Sort each group by name and filter empty groups
            return $grouped->filter(fn($g) => $g->isNotEmpty())
                ->map(fn($g) => $g->sortBy('naam')->values());
        });

        // Sort leeftijdsklassen based on preset config
        $leeftijdVolgorde = $toernooi->getCategorieVolgorde();

        $categorien = $categorien->sortBy(function ($gewichten, $leeftijd) use ($leeftijdVolgorde) {
            return $leeftijdVolgorde[$leeftijd] ?? 99;
        });

        // Check if poules are generated (tournament started)
        $poulesGegenereerd = $toernooi->poules()->exists();

        // Get mat info with current poule, wedstrijden and standings.
        // Groen/geel/blauw komt van MAT niveau (niet poule niveau).
        // poules.toernooi is eager-loaded to avoid N+1 via Poule accessors.
        $matten = [];
        if ($poulesGegenereerd) {
            $matten = $toernooi->matten()
                ->with(['poules' => function ($q) {
                    $q->whereNull('afgeroepen_at')  // Not yet announced
                      ->with(['judokas.club', 'wedstrijden', 'toernooi'])
                      ->orderBy('nummer');
                }])
                ->orderBy('nummer')
                ->get()
                ->map(function ($mat) {
                    // Cleanup invalid selections (gespeelde wedstrijden)
                    $mat->cleanupGespeeldeSelecties();

                    // Get first unfinished poule for this mat
                    $poule = $mat->poules->first();

                    // Collect all wedstrijden from all poules on this mat (for finding groen/geel/blauw)
                    $alleWedstrijden = $mat->poules->flatMap(fn($p) => $p->wedstrijden);

                    // Groen/geel/blauw van MAT niveau
                    $groeneWedstrijd = null;
                    $geleWedstrijd = null;
                    $blauweWedstrijd = null;

                    if ($mat->actieve_wedstrijd_id) {
                        $groeneWedstrijd = $alleWedstrijden->first(fn($w) => $w->id === $mat->actieve_wedstrijd_id && $w->isNogTeSpelen());
                    }
                    if ($mat->volgende_wedstrijd_id) {
                        $geleWedstrijd = $alleWedstrijden->first(fn($w) => $w->id === $mat->volgende_wedstrijd_id && $w->isNogTeSpelen());
                    }
                    if ($mat->gereedmaken_wedstrijd_id) {
                        $blauweWedstrijd = $alleWedstrijden->first(fn($w) => $w->id === $mat->gereedmaken_wedstrijd_id && $w->isNogTeSpelen());
                    }

                    if ($poule) {
                        // Calculate standings for each judoka (exclude absent)
                        $activeJudokas = $poule->judokas->filter(fn($j) => $j->gewicht_gewogen > 0 && $j->aanwezigheid !== 'afwezig');
                        $poule->standings = $activeJudokas->map(function ($judoka) use ($poule) {
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
                            return ['judoka' => $judoka, 'wp' => (int) $wp, 'jp' => (int) $jp];
                        })->sortByDesc(fn($s) => (int) $s['wp'] * 1000 + (int) $s['jp'])->values();

                        // Add judoka info to wedstrijden (find from all poules)
                        if ($groeneWedstrijd) {
                            $groenePoule = $mat->poules->first(fn($p) => $p->wedstrijden->contains('id', $groeneWedstrijd->id));
                            if ($groenePoule) {
                                $groeneWedstrijd->wit = $groenePoule->judokas->firstWhere('id', $groeneWedstrijd->judoka_wit_id);
                                $groeneWedstrijd->blauw = $groenePoule->judokas->firstWhere('id', $groeneWedstrijd->judoka_blauw_id);
                            }
                        }
                        if ($geleWedstrijd) {
                            $gelePoule = $mat->poules->first(fn($p) => $p->wedstrijden->contains('id', $geleWedstrijd->id));
                            if ($gelePoule) {
                                $geleWedstrijd->wit = $gelePoule->judokas->firstWhere('id', $geleWedstrijd->judoka_wit_id);
                                $geleWedstrijd->blauw = $gelePoule->judokas->firstWhere('id', $geleWedstrijd->judoka_blauw_id);
                            }
                        }
                        if ($blauweWedstrijd) {
                            $blauwePoule = $mat->poules->first(fn($p) => $p->wedstrijden->contains('id', $blauweWedstrijd->id));
                            if ($blauwePoule) {
                                $blauweWedstrijd->wit = $blauwePoule->judokas->firstWhere('id', $blauweWedstrijd->judoka_wit_id);
                                $blauweWedstrijd->blauw = $blauwePoule->judokas->firstWhere('id', $blauweWedstrijd->judoka_blauw_id);
                            }
                        }

                        $poule->groeneWedstrijd = $groeneWedstrijd;
                        $poule->geleWedstrijd = $geleWedstrijd;
                        $poule->blauweWedstrijd = $blauweWedstrijd;
                    }
                    $mat->huidigePoule = $poule;
                    $mat->groeneWedstrijd = $groeneWedstrijd;
                    $mat->geleWedstrijd = $geleWedstrijd;
                    $mat->blauweWedstrijd = $blauweWedstrijd;
                    return $mat;
                });
        }

        // Cached 30s; invalidated by PublicTournamentCacheObserver when Poule/Wedstrijd changes.
        $uitslagen = Cache::remember(
            "public.toernooi.{$toernooi->id}.uitslagen",
            30,
            fn () => $this->getUitslagen($toernooi),
        );

        return view('pages.publiek.index', [
            'toernooi' => $toernooi,
            'categorien' => $categorien,
            'totaalJudokas' => $totaalJudokas,
            'poulesGegenereerd' => $poulesGegenereerd,
            'blokken' => $blokken,
            'matten' => $matten,
            'uitslagen' => $uitslagen,
        ]);
    }

    /**
     * Get results for completed poules, sorted by age class (young to old) and weight (light to heavy)
     */
    private function getUitslagen(Toernooi $toernooi): array
    {
        // Get sort order from preset config
        $leeftijdVolgorde = $toernooi->getCategorieVolgorde();

        $poules = $toernooi->poules()
            ->whereNotNull('afgeroepen_at')
            ->with(['judokas.club', 'wedstrijden', 'toernooi'])
            ->get();

        // Calculate standings for each poule (exclude absent judokas)
        $poules = $poules->map(function ($poule) {
            $isPuntenComp = $poule->isPuntenCompetitie();
            $activeJudokas = $poule->judokas->filter(fn($j) => $j->gewicht_gewogen > 0 && $j->aanwezigheid !== 'afwezig');
            $standings = $activeJudokas->map(function ($judoka) use ($poule, $isPuntenComp) {
                $gewonnen = 0;
                $wp = 0;
                $jp = 0;
                foreach ($poule->wedstrijden as $w) {
                    if (!$w->is_gespeeld) continue;
                    $isInWedstrijd = $w->judoka_wit_id === $judoka->id || $w->judoka_blauw_id === $judoka->id;
                    if (!$isInWedstrijd) continue;

                    // Count wins
                    if ($w->winnaar_id === $judoka->id) {
                        $gewonnen++;
                    }

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
                return ['judoka' => $judoka, 'wp' => (int) $wp, 'jp' => (int) $jp, 'gewonnen' => (int) $gewonnen];
            });

            // Sort: punten competitie by gewonnen, normal by WP/JP
            if ($isPuntenComp) {
                $poule->standings = $standings->sortByDesc('gewonnen')->values();
            } else {
                $poule->standings = $standings->sortByDesc('wp')->sortByDesc(function ($s) {
                    return (int) $s['wp'] * 1000 + (int) $s['jp'];
                })->values();
            }

            $poule->is_punten_competitie = $isPuntenComp;

            return $poule;
        });

        // Sort by leeftijdsklasse (young first), then gewichtsklasse (light first)
        $poules = $poules->sort(function ($a, $b) use ($leeftijdVolgorde) {
            $leeftijdA = $leeftijdVolgorde[$a->leeftijdsklasse] ?? 99;
            $leeftijdB = $leeftijdVolgorde[$b->leeftijdsklasse] ?? 99;
            if ($leeftijdA !== $leeftijdB) return $leeftijdA - $leeftijdB;

            $gewichtA = (float) preg_replace('/[^0-9.]/', '', explode('-', $a->gewichtsklasse)[0]);
            $gewichtB = (float) preg_replace('/[^0-9.]/', '', explode('-', $b->gewichtsklasse)[0]);
            if (str_starts_with($a->gewichtsklasse, '+')) $gewichtA += 1000;
            if (str_starts_with($b->gewichtsklasse, '+')) $gewichtB += 1000;

            return $gewichtA <=> $gewichtB;
        });

        // Group by leeftijdsklasse
        return $poules->groupBy('leeftijdsklasse')->all();
    }

    /**
     * Get live mat data (AJAX) - groen/geel/blauw wedstrijden per mat
     */
    public function matten(Organisator $organisator, Toernooi $toernooi): JsonResponse
    {
        $matten = $toernooi->matten()
            // Load ALL poules (including afgeroepen) for wedstrijd lookup
            ->with(['poules' => function ($q) {
                $q->with(['judokas.club', 'wedstrijden', 'toernooi'])
                  ->orderBy('nummer');
            }])
            ->orderBy('nummer')
            ->get()
            ->map(function ($mat) {
                // Cleanup invalid selections (gespeelde wedstrijden)
                $mat->cleanupGespeeldeSelecties();

                // Get first unfinished (not afgeroepen) poule for display title
                $poule = $mat->poules->whereNull('afgeroepen_at')->first();

                // Collect all wedstrijden from ALL poules on this mat (including afgeroepen)
                $alleWedstrijden = $mat->poules->flatMap(fn($p) => $p->wedstrijden);

                // Groen/geel/blauw van MAT niveau
                $groeneWedstrijd = null;
                $geleWedstrijd = null;
                $blauweWedstrijd = null;

                if ($mat->actieve_wedstrijd_id) {
                    $groeneWedstrijd = $alleWedstrijden->first(fn($w) => $w->id === $mat->actieve_wedstrijd_id && $w->isNogTeSpelen());
                }
                if ($mat->volgende_wedstrijd_id) {
                    $geleWedstrijd = $alleWedstrijden->first(fn($w) => $w->id === $mat->volgende_wedstrijd_id && $w->isNogTeSpelen());
                }
                if ($mat->gereedmaken_wedstrijd_id) {
                    $blauweWedstrijd = $alleWedstrijden->first(fn($w) => $w->id === $mat->gereedmaken_wedstrijd_id && $w->isNogTeSpelen());
                }

                // Add judoka info to wedstrijden
                $formatWedstrijd = function ($wedstrijd) use ($mat) {
                    if (!$wedstrijd) return null;
                    $wedstrijdPoule = $mat->poules->first(fn($p) => $p->wedstrijden->contains('id', $wedstrijd->id));
                    if (!$wedstrijdPoule) return null;

                    $wit = $wedstrijdPoule->judokas->firstWhere('id', $wedstrijd->judoka_wit_id);
                    $blauw = $wedstrijdPoule->judokas->firstWhere('id', $wedstrijd->judoka_blauw_id);

                    return [
                        'id' => $wedstrijd->id,
                        'poule_titel' => $wedstrijdPoule->getDisplayTitel(),
                        'wit' => $wit ? ['naam' => $wit->naam, 'club' => $wit->club?->naam] : null,
                        'blauw' => $blauw ? ['naam' => $blauw->naam, 'club' => $blauw->club?->naam] : null,
                    ];
                };

                return [
                    'id' => $mat->id,
                    'nummer' => $mat->nummer,
                    'naam' => $mat->naam,
                    'poule_titel' => $poule?->getDisplayTitel(),
                    'groen' => $formatWedstrijd($groeneWedstrijd),
                    'geel' => $formatWedstrijd($geleWedstrijd),
                    'blauw' => $formatWedstrijd($blauweWedstrijd),
                ];
            });

        return response()->json(['matten' => $matten]);
    }

    /**
     * Get poules for favorite judokas (AJAX)
     */
    public function favorieten(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $judokaIds = $request->input('judoka_ids', []);

        if (empty($judokaIds)) {
            return response()->json(['poules' => []]);
        }

        // Get poules containing these judokas, include mat for groen/geel/blauw lookup
        $poules = Poule::where('toernooi_id', $toernooi->id)
            ->whereHas('judokas', function ($q) use ($judokaIds) {
                $q->whereIn('judokas.id', $judokaIds);
            })
            ->with(['judokas.club', 'mat', 'blok', 'wedstrijden'])
            ->get()
            ->map(function ($poule) use ($judokaIds, $toernooi) {
                $tolerantie = $toernooi->gewicht_tolerantie ?? 0.5;
                $mat = $poule->mat;

                // Find current, next and preparing match - NOW ON MAT LEVEL
                $wedstrijden = $poule->wedstrijden->sortBy('volgorde')->values();

                $huidigeWedstrijd = null;
                $volgendeWedstrijd = null;
                $gereedmakenWedstrijd = null;

                // Groen/Geel/Blauw komen van MAT niveau (niet poule)
                // Wedstrijden kunnen van andere poules op dezelfde mat zijn,
                // dus zoek eerst in eigen poule, dan in DB als niet gevonden
                if ($mat && $mat->actieve_wedstrijd_id) {
                    $huidigeWedstrijd = $wedstrijden->first(fn($w) => $w->id === $mat->actieve_wedstrijd_id)
                        ?? \App\Models\Wedstrijd::find($mat->actieve_wedstrijd_id);
                }
                if ($mat && $mat->volgende_wedstrijd_id) {
                    $volgendeWedstrijd = $wedstrijden->first(fn($w) => $w->id === $mat->volgende_wedstrijd_id)
                        ?? \App\Models\Wedstrijd::find($mat->volgende_wedstrijd_id);
                }
                if ($mat && $mat->gereedmaken_wedstrijd_id) {
                    $gereedmakenWedstrijd = $wedstrijden->first(fn($w) => $w->id === $mat->gereedmaken_wedstrijd_id)
                        ?? \App\Models\Wedstrijd::find($mat->gereedmaken_wedstrijd_id);
                }

                // IDs of judokas in current/next/preparing match
                $huidigeJudokaIds = $huidigeWedstrijd ? [$huidigeWedstrijd->judoka_wit_id, $huidigeWedstrijd->judoka_blauw_id] : [];
                $volgendeJudokaIds = $volgendeWedstrijd ? [$volgendeWedstrijd->judoka_wit_id, $volgendeWedstrijd->judoka_blauw_id] : [];
                $gereedmakenJudokaIds = $gereedmakenWedstrijd ? [$gereedmakenWedstrijd->judoka_wit_id, $gereedmakenWedstrijd->judoka_blauw_id] : [];

                return [
                    'id' => $poule->id,
                    'nummer' => $poule->nummer,
                    'titel' => $poule->getDisplayTitel(),
                    'leeftijdsklasse' => $poule->leeftijdsklasse,
                    'gewichtsklasse' => $poule->gewichtsklasse,
                    'mat' => $mat?->nummer,
                    'blok' => $poule->blok?->nummer,
                    'type' => $poule->type,
                    'huidige_wedstrijd' => $huidigeWedstrijd ? [
                        'judoka1_id' => $huidigeWedstrijd->judoka_wit_id,
                        'judoka2_id' => $huidigeWedstrijd->judoka_blauw_id,
                    ] : null,
                    'volgende_wedstrijd' => $volgendeWedstrijd ? [
                        'judoka1_id' => $volgendeWedstrijd->judoka_wit_id,
                        'judoka2_id' => $volgendeWedstrijd->judoka_blauw_id,
                    ] : null,
                    'gereedmaken_wedstrijd' => $gereedmakenWedstrijd ? [
                        'judoka1_id' => $gereedmakenWedstrijd->judoka_wit_id,
                        'judoka2_id' => $gereedmakenWedstrijd->judoka_blauw_id,
                    ] : null,
                    'judokas' => $poule->judokas->map(function ($j) use ($poule, $judokaIds, $tolerantie, $huidigeJudokaIds, $volgendeJudokaIds, $gereedmakenJudokaIds) {
                        // Extract band color for colored dot display
                        $bandKleur = $this->getBandKleur($j->band);

                        // Calculate WP/JP live from wedstrijden (same as admin view)
                        $wp = 0;
                        $jp = 0;
                        foreach ($poule->wedstrijden as $w) {
                            if (!$w->is_gespeeld) continue;
                            if ($w->judoka_wit_id !== $j->id && $w->judoka_blauw_id !== $j->id) continue;

                            if ($w->judoka_wit_id === $j->id) {
                                $jp += (int) preg_replace('/[^0-9]/', '', $w->score_wit ?? '');
                            } else {
                                $jp += (int) preg_replace('/[^0-9]/', '', $w->score_blauw ?? '');
                            }

                            if ($w->winnaar_id === $j->id) {
                                $wp += 2;
                            } elseif ($w->winnaar_id === null) {
                                $wp += 1;
                            }
                        }

                        return [
                            'id' => $j->id,
                            'naam' => $j->naam,
                            'club' => $j->club?->naam,
                            'band' => Band::toKleur($j->band),
                            'band_kleur' => $bandKleur,
                            'leeftijd' => $j->geboortejaar ? (date('Y') - $j->geboortejaar) : null,
                            'gewicht' => $j->gewicht,
                            'is_favoriet' => in_array($j->id, $judokaIds),
                            'is_afwezig' => $j->aanwezigheid === 'afwezig',
                            'is_aan_de_beurt' => in_array($j->id, $huidigeJudokaIds),
                            'is_volgende' => in_array($j->id, $volgendeJudokaIds),
                            'is_gereedmaken' => in_array($j->id, $gereedmakenJudokaIds),
                            'positie' => $j->pivot->positie ?? null,
                            'wp' => $wp,
                            'jp' => $jp,
                            'eindpositie' => $j->pivot->eindpositie ?? null,
                        ];
                    })->sortBy('positie')->values(),
                ];
            });

        return response()->json(['poules' => $poules]);
    }

    /**
     * Dynamic manifest.json for PWA per tournament
     */
    public function manifest(Organisator $organisator, Toernooi $toernooi): JsonResponse
    {
        return response()->json([
            'name' => '📺 LIVE ' . $toernooi->naam,
            'short_name' => '📺 LIVE',
            'description' => 'Live uitslagen voor ' . $toernooi->naam,
            'start_url' => '/' . $toernooi->slug,
            'scope' => '/' . $toernooi->slug,
            'display' => 'standalone',
            'background_color' => '#2563eb',
            'theme_color' => '#2563eb',
            'icons' => [
                [
                    'src' => '/icon-192x192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                ],
                [
                    'src' => '/icon-512x512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                ],
            ],
        ])->header('Content-Type', 'application/manifest+json');
    }

    /**
     * Search judokas (AJAX for quick search)
     */
    public function zoeken(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return response()->json(['judokas' => []]);
        }

        $judokas = Judoka::where('toernooi_id', $toernooi->id)
            ->where(function ($q) use ($query) {
                $q->where('naam', 'like', "%{$query}%")
                  ->orWhereHas('club', function ($q2) use ($query) {
                      $q2->where('naam', 'like', "%{$query}%");
                  });
            })
            ->with('club')
            ->orderBy('naam')
            ->limit(20)
            ->get()
            ->map(function ($j) {
                return [
                    'id' => $j->id,
                    'naam' => $j->naam,
                    'club' => $j->club?->naam,
                    'leeftijd' => $j->leeftijd,
                    'gewicht' => $j->gewicht_gewogen ?? $j->gewicht,
                    'leeftijdsklasse' => $j->leeftijdsklasse ?? '-',
                    'band' => Band::toKleur($j->band) ?: '-',
                ];
            });

        return response()->json(['judokas' => $judokas]);
    }

    /**
     * Store a club registration request from the public page
     */
    public function clubAanmelding(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'club_naam' => 'required|string|max:255',
            'contact_naam' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefoon' => 'nullable|string|max:20',
        ]);

        if (empty($validated['email']) && empty($validated['telefoon'])) {
            return response()->json(['error' => __('Vul een e-mailadres of telefoonnummer in.')], 422);
        }

        // Check for duplicate club name at this organisator
        $organisatorId = $toernooi->organisator_id;
        $bestaandeClub = Club::where('organisator_id', $organisatorId)
            ->whereRaw('LOWER(naam) = ?', [strtolower($validated['club_naam'])])
            ->first();

        if ($bestaandeClub) {
            // Check if already linked to this toernooi
            if ($toernooi->clubs()->where('club_id', $bestaandeClub->id)->exists()) {
                return response()->json(['error' => __('Deze club is al aangemeld voor dit toernooi.')], 422);
            }
        }

        // Create club if not exists, update contact info
        $club = Club::findOrCreateByName($validated['club_naam'], $organisatorId);
        if (!empty($validated['contact_naam'])) $club->update(['contact_naam' => $validated['contact_naam']]);
        if (!empty($validated['email'])) $club->update(['email' => $validated['email']]);
        if (!empty($validated['telefoon'])) $club->update(['telefoon' => $validated['telefoon']]);

        // Save aanmelding for tracking
        ClubAanmelding::create([
            'toernooi_id' => $toernooi->id,
            ...$validated,
        ]);

        return response()->json(['success' => true, 'message' => __('Aanmelding ontvangen! De organisator neemt contact met u op.')]);
    }

    /**
     * Extract band color from band string for colored dot display.
     * Returns CSS color value.
     */
    private function getBandKleur(?string $band): ?string
    {
        if (empty($band)) {
            return null;
        }

        $bandLower = strtolower($band);

        $kleuren = [
            'wit' => '#ffffff',
            'geel' => '#fbbf24',
            'oranje' => '#f97316',
            'groen' => '#22c55e',
            'blauw' => '#3b82f6',
            'bruin' => '#92400e',
            'zwart' => '#1f2937',
        ];

        foreach ($kleuren as $kleur => $hex) {
            if (str_contains($bandLower, $kleur)) {
                return $hex;
            }
        }

        return null;
    }
}
