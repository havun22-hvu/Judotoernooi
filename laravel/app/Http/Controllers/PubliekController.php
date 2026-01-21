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

        // Get total judoka count (all judokas)
        $totaalJudokas = Judoka::where('toernooi_id', $toernooi->id)->count();

        // Get judokas with complete data for category display
        $judokas = Judoka::where('toernooi_id', $toernooi->id)
            ->whereNotNull('leeftijdsklasse')
            ->whereNotNull('gewichtsklasse')
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

        // Sort leeftijdsklassen based on preset config
        $leeftijdVolgorde = $toernooi->getCategorieVolgorde();

        $categorien = $categorien->sortBy(function ($gewichten, $leeftijd) use ($leeftijdVolgorde) {
            return $leeftijdVolgorde[$leeftijd] ?? 99;
        });

        // Check if poules are generated (tournament started)
        $poulesGegenereerd = $toernooi->poules()->exists();

        // Get mat info with current poule, wedstrijden and standings
        $matten = [];
        if ($poulesGegenereerd) {
            $matten = $toernooi->matten()
                ->with(['poules' => function ($q) {
                    $q->whereNull('afgeroepen_at')  // Not yet announced
                      ->with(['judokas.club', 'wedstrijden'])
                      ->orderBy('nummer');
                }])
                ->orderBy('nummer')
                ->get()
                ->map(function ($mat) {
                    // Get first unfinished poule for this mat
                    $poule = $mat->poules->first();
                    if ($poule) {
                        // Calculate standings for each judoka
                        $poule->standings = $poule->judokas->map(function ($judoka) use ($poule) {
                            $wp = 0;
                            $jp = 0;
                            foreach ($poule->wedstrijden as $w) {
                                if ($w->judoka_wit_id === $judoka->id) {
                                    $wp += $w->winnaar_id === $judoka->id ? 2 : ($w->is_gespeeld ? 0 : 0);
                                    $jp += (int) $w->score_wit;
                                } elseif ($w->judoka_blauw_id === $judoka->id) {
                                    $wp += $w->winnaar_id === $judoka->id ? 2 : ($w->is_gespeeld ? 0 : 0);
                                    $jp += (int) $w->score_blauw;
                                }
                            }
                            return ['judoka' => $judoka, 'wp' => $wp, 'jp' => $jp];
                        })->sortByDesc(fn($s) => $s['wp'] * 1000 + $s['jp'])->values();

                        // Determine groen (speelt nu) en geel (klaar maken) wedstrijden
                        $wedstrijden = $poule->wedstrijden->sortBy('volgorde')->values();

                        // Groen: actieve_wedstrijd_id of auto-fallback (eerste niet-gespeelde)
                        $groeneWedstrijd = null;
                        if ($poule->actieve_wedstrijd_id) {
                            $groeneWedstrijd = $wedstrijden->first(fn($w) => $w->id === $poule->actieve_wedstrijd_id && !$w->is_gespeeld);
                        }
                        if (!$groeneWedstrijd) {
                            $groeneWedstrijd = $wedstrijden->first(fn($w) => !$w->is_gespeeld);
                        }

                        // Geel: huidige_wedstrijd_id of auto-fallback (tweede niet-gespeelde)
                        $geleWedstrijd = null;
                        if ($poule->huidige_wedstrijd_id) {
                            $geleWedstrijd = $wedstrijden->first(fn($w) => $w->id === $poule->huidige_wedstrijd_id && !$w->is_gespeeld);
                        }
                        if (!$geleWedstrijd && $groeneWedstrijd) {
                            $geleWedstrijd = $wedstrijden->first(fn($w) => !$w->is_gespeeld && $w->id !== $groeneWedstrijd->id);
                        }

                        // Add judoka info to wedstrijden
                        if ($groeneWedstrijd) {
                            $groeneWedstrijd->wit = $poule->judokas->firstWhere('id', $groeneWedstrijd->judoka_wit_id);
                            $groeneWedstrijd->blauw = $poule->judokas->firstWhere('id', $groeneWedstrijd->judoka_blauw_id);
                        }
                        if ($geleWedstrijd) {
                            $geleWedstrijd->wit = $poule->judokas->firstWhere('id', $geleWedstrijd->judoka_wit_id);
                            $geleWedstrijd->blauw = $poule->judokas->firstWhere('id', $geleWedstrijd->judoka_blauw_id);
                        }

                        $poule->groeneWedstrijd = $groeneWedstrijd;
                        $poule->geleWedstrijd = $geleWedstrijd;
                    }
                    $mat->huidigePoule = $poule;
                    return $mat;
                });
        }

        // Get completed poules (afgeroepen) with standings for results
        $uitslagen = $this->getUitslagen($toernooi);

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
        return $poules->groupBy('leeftijdsklasse')->all();
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
            ->with(['judokas.club', 'mat', 'blok', 'wedstrijden'])
            ->get()
            ->map(function ($poule) use ($judokaIds, $toernooi) {
                $tolerantie = $toernooi->gewicht_tolerantie ?? 0.5;

                // Find current and next match
                // Huidige (groen) = manual override (actieve_wedstrijd_id) OR auto-fallback
                // Volgende (geel) = manual override (huidige_wedstrijd_id) OR auto-fallback
                $wedstrijden = $poule->wedstrijden->sortBy('volgorde')->values();

                $huidigeWedstrijd = null;
                $volgendeWedstrijd = null;

                // Groen (speelt nu): check manual override first
                if ($poule->actieve_wedstrijd_id) {
                    $huidigeWedstrijd = $wedstrijden->first(fn($w) => $w->id === $poule->actieve_wedstrijd_id && $w->status !== 'gespeeld');
                }

                // Auto-fallback for groen: first unplayed
                if (!$huidigeWedstrijd) {
                    $ongespeeld = $wedstrijden->filter(fn($w) => $w->status !== 'gespeeld')->values();
                    $huidigeWedstrijd = $ongespeeld->first();
                }

                // Geel (klaar maken): check manual override first
                if ($poule->huidige_wedstrijd_id) {
                    $volgendeWedstrijd = $wedstrijden->first(fn($w) => $w->id === $poule->huidige_wedstrijd_id && $w->status !== 'gespeeld');
                }

                // Auto-fallback for geel: second unplayed (after huidige)
                if (!$volgendeWedstrijd && $huidigeWedstrijd) {
                    $volgendeWedstrijd = $wedstrijden->first(fn($w) => $w->status !== 'gespeeld' && $w->id !== $huidigeWedstrijd->id);
                }

                // IDs of judokas in current/next match
                $huidigeJudokaIds = $huidigeWedstrijd ? [$huidigeWedstrijd->judoka1_id, $huidigeWedstrijd->judoka2_id] : [];
                $volgendeJudokaIds = $volgendeWedstrijd ? [$volgendeWedstrijd->judoka1_id, $volgendeWedstrijd->judoka2_id] : [];

                return [
                    'id' => $poule->id,
                    'nummer' => $poule->nummer,
                    'titel' => $poule->titel,
                    'leeftijdsklasse' => $poule->leeftijdsklasse,
                    'gewichtsklasse' => $poule->gewichtsklasse,
                    'mat' => $poule->mat?->nummer,
                    'blok' => $poule->blok?->nummer,
                    'type' => $poule->type,
                    'huidige_wedstrijd' => $huidigeWedstrijd ? [
                        'judoka1_id' => $huidigeWedstrijd->judoka1_id,
                        'judoka2_id' => $huidigeWedstrijd->judoka2_id,
                    ] : null,
                    'volgende_wedstrijd' => $volgendeWedstrijd ? [
                        'judoka1_id' => $volgendeWedstrijd->judoka1_id,
                        'judoka2_id' => $volgendeWedstrijd->judoka2_id,
                    ] : null,
                    'judokas' => $poule->judokas->map(function ($j) use ($judokaIds, $tolerantie, $huidigeJudokaIds, $volgendeJudokaIds) {
                        return [
                            'id' => $j->id,
                            'naam' => $j->naam,
                            'club' => $j->club?->naam,
                            'band' => $j->band,
                            'gewicht' => $j->gewicht,
                            'is_favoriet' => in_array($j->id, $judokaIds),
                            'is_afwezig' => $j->aanwezigheid === 'afwezig',
                            'is_aan_de_beurt' => in_array($j->id, $huidigeJudokaIds),
                            'is_volgende' => in_array($j->id, $volgendeJudokaIds),
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
     * Scan QR code and return judoka info (public, read-only)
     */
    public function scanQR(Request $request, Toernooi $toernooi): JsonResponse
    {
        $qrCode = $request->input('qr_code', '');

        if (empty($qrCode)) {
            return response()->json(['success' => false, 'message' => 'Geen QR code']);
        }

        // Extract qr_code from URL if full URL is provided
        if (str_contains($qrCode, '/weegkaart/')) {
            $parts = explode('/weegkaart/', $qrCode);
            $qrCode = end($parts);
            $qrCode = strtok($qrCode, '?');
            $qrCode = strtok($qrCode, '#');
            $qrCode = rtrim($qrCode, '/');
        }

        $judoka = Judoka::where('toernooi_id', $toernooi->id)
            ->where('qr_code', $qrCode)
            ->with(['club', 'poules.blok'])
            ->first();

        if (!$judoka) {
            return response()->json(['success' => false, 'message' => 'Judoka niet gevonden']);
        }

        return response()->json([
            'success' => true,
            'judoka' => [
                'id' => $judoka->id,
                'naam' => $judoka->naam,
                'club' => $judoka->club?->naam,
                'leeftijdsklasse' => $judoka->leeftijdsklasse,
                'gewichtsklasse' => $judoka->gewichtsklasse,
                'blok' => $judoka->poules->first()?->blok?->nummer,
                'gewogen' => $judoka->gewicht_gewogen !== null,
                'gewicht_gewogen' => $judoka->gewicht_gewogen,
            ],
        ]);
    }

    /**
     * Register weight for judoka (public route for PWA)
     */
    public function registreerGewicht(Request $request, Toernooi $toernooi, Judoka $judoka): JsonResponse
    {
        // Verify judoka belongs to this tournament
        if ($judoka->toernooi_id !== $toernooi->id) {
            return response()->json(['success' => false, 'message' => 'Judoka niet gevonden'], 404);
        }

        $validated = $request->validate([
            'gewicht' => 'required|numeric|min:10|max:200',
        ]);

        $gewicht = $validated['gewicht'];
        $tolerantie = config('toernooi.gewicht_tolerantie', 0.5);

        // Get weight class limits
        $gewichtsklasse = $judoka->gewichtsklasse;
        $binnenKlasse = true;
        $opmerking = null;

        if ($gewichtsklasse) {
            // Parse weight class (e.g., "-30" or "30" for +30)
            if (str_starts_with($gewichtsklasse, '-')) {
                $maxGewicht = abs((float) $gewichtsklasse) + $tolerantie;
                if ($gewicht > $maxGewicht) {
                    $binnenKlasse = false;
                    $opmerking = "Te zwaar voor klasse {$gewichtsklasse} kg (max {$maxGewicht} kg)";
                }
            } else {
                $minGewicht = (float) $gewichtsklasse - $tolerantie;
                if ($gewicht < $minGewicht) {
                    $binnenKlasse = false;
                    $opmerking = "Te licht voor klasse +{$gewichtsklasse} kg (min {$minGewicht} kg)";
                }
            }
        }

        // Save weight
        $judoka->update([
            'gewicht_gewogen' => $gewicht,
            'gewogen_op' => now(),
        ]);

        return response()->json([
            'success' => true,
            'binnen_klasse' => $binnenKlasse,
            'opmerking' => $opmerking,
        ]);
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

    /**
     * Organisator resultaten pagina - alle uitslagen + club ranking
     */
    public function organisatorResultaten(Toernooi $toernooi): View
    {
        $uitslagen = $this->getUitslagen($toernooi);
        $clubRanking = $this->getClubRanking($toernooi);

        return view('pages.resultaten.organisator', [
            'toernooi' => $toernooi,
            'uitslagen' => $uitslagen,
            'clubRanking' => $clubRanking,
        ]);
    }

    /**
     * Calculate club ranking with medals (absolute and relative)
     */
    public function getClubRanking(Toernooi $toernooi): array
    {
        $uitslagen = $this->getUitslagen($toernooi);
        $clubs = [];

        // Count medals per club
        foreach ($uitslagen as $leeftijdsklasse => $poules) {
            foreach ($poules as $poule) {
                foreach ($poule->standings as $index => $standing) {
                    $plaats = $index + 1;
                    $clubNaam = $standing['judoka']->club?->naam ?? 'Geen club';
                    $clubId = $standing['judoka']->club_id ?? 0;

                    if (!isset($clubs[$clubId])) {
                        $clubs[$clubId] = [
                            'naam' => $clubNaam,
                            'goud' => 0,
                            'zilver' => 0,
                            'brons' => 0,
                            'totaal_medailles' => 0,
                            'totaal_judokas' => 0,
                        ];
                    }

                    if ($plaats === 1) $clubs[$clubId]['goud']++;
                    if ($plaats === 2) $clubs[$clubId]['zilver']++;
                    if ($plaats === 3) $clubs[$clubId]['brons']++;
                }
            }
        }

        // Get total judokas per club (for relative ranking)
        $judokasPerClub = Judoka::where('toernooi_id', $toernooi->id)
            ->whereNotNull('club_id')
            ->selectRaw('club_id, COUNT(*) as aantal')
            ->groupBy('club_id')
            ->pluck('aantal', 'club_id');

        foreach ($clubs as $clubId => &$club) {
            $club['totaal_medailles'] = $club['goud'] + $club['zilver'] + $club['brons'];
            $club['totaal_judokas'] = $judokasPerClub[$clubId] ?? 1;
            // Weighted score (gold=3, silver=2, bronze=1)
            $club['punten'] = ($club['goud'] * 3) + ($club['zilver'] * 2) + ($club['brons'] * 1);
            // Relative score: weighted points per judoka
            $club['relatief'] = $club['totaal_judokas'] > 0
                ? round($club['punten'] / $club['totaal_judokas'], 2)
                : 0;
        }

        // Sort by weighted points (descending)
        uasort($clubs, fn($a, $b) => $b['punten'] <=> $a['punten']);

        // Create relative ranking (sorted by relative score)
        $clubsRelatief = $clubs;
        uasort($clubsRelatief, fn($a, $b) => $b['relatief'] <=> $a['relatief']);

        return [
            'absoluut' => array_values($clubs),
            'relatief' => array_values($clubsRelatief),
        ];
    }

    /**
     * Get results for a specific club (for coach portal)
     */
    public function getClubResultaten(Toernooi $toernooi, int $clubId): array
    {
        $uitslagen = $this->getUitslagen($toernooi);
        $resultaten = [];

        foreach ($uitslagen as $leeftijdsklasse => $poules) {
            foreach ($poules as $poule) {
                foreach ($poule->standings as $index => $standing) {
                    if ($standing['judoka']->club_id === $clubId) {
                        $resultaten[] = [
                            'judoka' => $standing['judoka'],
                            'plaats' => $index + 1,
                            'wp' => $standing['wp'],
                            'jp' => $standing['jp'],
                            'leeftijdsklasse' => $leeftijdsklasse,
                            'gewichtsklasse' => $poule->gewichtsklasse,
                            'poule_nummer' => $poule->nummer,
                        ];
                    }
                }
            }
        }

        // Sort by plaats (best first)
        usort($resultaten, fn($a, $b) => $a['plaats'] <=> $b['plaats']);

        return $resultaten;
    }
}
