<?php

namespace App\Http\Controllers;

use App\Models\Blok;
use App\Models\Judoka;
use App\Models\Poule;
use App\Models\Toernooi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PubliekController extends Controller
{
    /**
     * Show public tournament page with all judokas
     */
    public function index(Toernooi $toernooi): View
    {
        // Get blokken with times
        $blokken = Blok::where('toernooi_id', $toernooi->id)
            ->orderBy('nummer')
            ->get();

        // Get all complete judokas grouped by age class and weight class
        $judokas = Judoka::where('toernooi_id', $toernooi->id)
            ->whereNotNull('geboortejaar')
            ->whereNotNull('geslacht')
            ->whereNotNull('band')
            ->where(function ($q) {
                $q->whereNotNull('gewicht')->where('gewicht', '>', 0);
            })
            ->with('club')
            ->orderBy('leeftijdsklasse')
            ->orderBy('gewichtsklasse')
            ->orderBy('naam')
            ->get();

        // Group by leeftijdsklasse, then by gewichtsklasse
        $categorien = $judokas->groupBy('leeftijdsklasse')->map(function ($groep) {
            return $groep->groupBy('gewichtsklasse')
                ->sortBy(function ($judokas, $gewichtsklasse) {
                    // Sort weight classes numerically (light to heavy)
                    // Handle formats like "-27", "+55", "27"
                    $numericValue = (float) preg_replace('/[^0-9.]/', '', $gewichtsklasse);
                    // Put "+" classes at the end
                    if (str_starts_with($gewichtsklasse, '+')) {
                        $numericValue += 1000;
                    }
                    return $numericValue;
                })
                ->map(function ($judokas) {
                    // Sort judokas alphabetically within each weight class
                    return $judokas->sortBy('naam')->values();
                });
        });

        // Sort leeftijdsklassen
        $leeftijdVolgorde = [
            "Mini's" => 1,
            'A-pupillen' => 2,
            'B-pupillen' => 3,
            'C-pupillen' => 4,
            'Dames -15' => 5,
            'Heren -15' => 6,
            'Dames -18' => 7,
            'Heren -18' => 8,
            'Dames -21' => 9,
            'Heren -21' => 10,
            'Dames' => 11,
            'Heren' => 12,
        ];

        $categorien = $categorien->sortBy(function ($gewichten, $leeftijd) use ($leeftijdVolgorde) {
            return $leeftijdVolgorde[$leeftijd] ?? 99;
        });

        // Check if poules are generated (tournament started)
        $poulesGegenereerd = $toernooi->poules()->exists();

        // Get mat info with first unfinished poule per mat
        $matten = [];
        if ($poulesGegenereerd) {
            $matten = $toernooi->matten()
                ->with(['poules' => function ($q) {
                    $q->with('judokas.club')
                      ->orderBy('nummer')
                      ->limit(1);
                }])
                ->orderBy('nummer')
                ->get()
                ->map(function ($mat) {
                    $mat->huidigePoule = $mat->poules->first();
                    return $mat;
                });
        }

        // Get completed poules (afgeroepen) with standings for results
        $uitslagen = $this->getUitslagen($toernooi);

        return view('pages.publiek.index', [
            'toernooi' => $toernooi,
            'categorien' => $categorien,
            'totaalJudokas' => $judokas->count(),
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
        $leeftijdVolgorde = [
            "Mini's" => 1, 'A-pupillen' => 2, 'B-pupillen' => 3, 'C-pupillen' => 4,
            'Dames -15' => 5, 'Heren -15' => 6, 'Dames -18' => 7, 'Heren -18' => 8,
            'Dames -21' => 9, 'Heren -21' => 10, 'Dames' => 11, 'Heren' => 12,
        ];

        $poules = $toernooi->poules()
            ->whereNotNull('afgeroepen_at')
            ->with(['judokas.club', 'wedstrijden'])
            ->get();

        // Calculate standings for each poule
        $poules = $poules->map(function ($poule) {
            $standings = $poule->judokas->map(function ($judoka) use ($poule) {
                $wp = 0;
                $jp = 0;
                foreach ($poule->wedstrijden as $w) {
                    if ($w->judoka_wit_id === $judoka->id) {
                        $wp += $w->winnaar_id === $judoka->id ? 2 : 0;
                        $jp += (int) $w->score_wit;
                    } elseif ($w->judoka_blauw_id === $judoka->id) {
                        $wp += $w->winnaar_id === $judoka->id ? 2 : 0;
                        $jp += (int) $w->score_blauw;
                    }
                }
                return ['judoka' => $judoka, 'wp' => $wp, 'jp' => $jp];
            });

            // Sort by WP desc, JP desc
            $poule->standings = $standings->sortByDesc('wp')->sortByDesc(function ($s) {
                return $s['wp'] * 1000 + $s['jp'];
            })->values();

            return $poule;
        });

        // Sort by leeftijdsklasse (young first), then gewichtsklasse (light first)
        $poules = $poules->sort(function ($a, $b) use ($leeftijdVolgorde) {
            $leeftijdA = $leeftijdVolgorde[$a->leeftijdsklasse] ?? 99;
            $leeftijdB = $leeftijdVolgorde[$b->leeftijdsklasse] ?? 99;
            if ($leeftijdA !== $leeftijdB) return $leeftijdA - $leeftijdB;

            $gewichtA = (float) preg_replace('/[^0-9.]/', '', $a->gewichtsklasse);
            $gewichtB = (float) preg_replace('/[^0-9.]/', '', $b->gewichtsklasse);
            if (str_starts_with($a->gewichtsklasse, '+')) $gewichtA += 1000;
            if (str_starts_with($b->gewichtsklasse, '+')) $gewichtB += 1000;

            return $gewichtA <=> $gewichtB;
        });

        // Group by leeftijdsklasse
        return $poules->groupBy('leeftijdsklasse')->toArray();
    }

    /**
     * Get poules for favorite judokas (AJAX)
     */
    public function favorieten(Request $request, Toernooi $toernooi): JsonResponse
    {
        $judokaIds = $request->input('judoka_ids', []);

        if (empty($judokaIds)) {
            return response()->json(['poules' => []]);
        }

        // Get poules containing these judokas
        $poules = Poule::where('toernooi_id', $toernooi->id)
            ->whereHas('judokas', function ($q) use ($judokaIds) {
                $q->whereIn('judokas.id', $judokaIds);
            })
            ->with(['judokas.club', 'mat', 'blok'])
            ->get()
            ->map(function ($poule) use ($judokaIds, $toernooi) {
                $tolerantie = $toernooi->gewicht_tolerantie ?? 0.5;

                return [
                    'id' => $poule->id,
                    'nummer' => $poule->nummer,
                    'titel' => $poule->titel,
                    'leeftijdsklasse' => $poule->leeftijdsklasse,
                    'gewichtsklasse' => $poule->gewichtsklasse,
                    'mat' => $poule->mat?->nummer,
                    'blok' => $poule->blok?->nummer,
                    'type' => $poule->type,
                    'judokas' => $poule->judokas->map(function ($j) use ($judokaIds, $tolerantie) {
                        return [
                            'id' => $j->id,
                            'naam' => $j->naam,
                            'club' => $j->club?->naam,
                            'band' => $j->band,
                            'gewicht' => $j->gewicht,
                            'is_favoriet' => in_array($j->id, $judokaIds),
                            'is_afwezig' => $j->aanwezigheid === 'afwezig',
                            'is_doorgestreept' => $j->moetUitPouleVerwijderd($tolerantie),
                            'positie' => $j->pivot->positie ?? null,
                            'punten' => $j->pivot->punten ?? 0,
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
    public function manifest(Toernooi $toernooi): JsonResponse
    {
        return response()->json([
            'name' => $toernooi->naam,
            'short_name' => substr($toernooi->naam, 0, 12),
            'description' => 'Live informatie voor ' . $toernooi->naam,
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
    public function zoeken(Request $request, Toernooi $toernooi): JsonResponse
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
                    'leeftijdsklasse' => $j->leeftijdsklasse ?? '-',
                    'gewichtsklasse' => $j->gewichtsklasse ?? '-',
                    'band' => $j->band ?? '-',
                ];
            });

        return response()->json(['judokas' => $judokas]);
    }

    /**
     * Export results as CSV for organizer
     * Sorted by age class (young to old) and weight (light to heavy)
     */
    public function exportUitslagen(Toernooi $toernooi): Response
    {
        $uitslagen = $this->getUitslagen($toernooi);

        $csv = "Leeftijdsklasse;Gewichtsklasse;Poule;Plaats;Naam;Club;WP;JP\n";

        foreach ($uitslagen as $leeftijdsklasse => $poules) {
            foreach ($poules as $poule) {
                $plaats = 1;
                foreach ($poule->standings as $standing) {
                    $csv .= sprintf(
                        "%s;%s;%d;%d;%s;%s;%d;%d\n",
                        $leeftijdsklasse,
                        $poule->gewichtsklasse,
                        $poule->nummer,
                        $plaats,
                        $standing['judoka']->naam,
                        $standing['judoka']->club?->naam ?? '-',
                        $standing['wp'],
                        $standing['jp']
                    );
                    $plaats++;
                }
            }
        }

        $filename = sprintf('uitslagen_%s_%s.csv',
            \Illuminate\Support\Str::slug($toernooi->naam),
            now()->format('Y-m-d_His')
        );

        return response($csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }
}
