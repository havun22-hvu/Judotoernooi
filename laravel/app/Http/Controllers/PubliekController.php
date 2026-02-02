<?php

namespace App\Http\Controllers;

use App\Models\Organisator;
use App\Models\Blok;
use App\Models\Judoka;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Services\WegingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PubliekController extends Controller
{
    public function __construct(
        private WegingService $wegingService
    ) {}

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

        // Get mat info with current poule, wedstrijden and standings
        // Groen/geel komt nu van MAT niveau (niet poule niveau)
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

                    // Collect all wedstrijden from all poules on this mat (for finding groen/geel)
                    $alleWedstrijden = $mat->poules->flatMap(fn($p) => $p->wedstrijden);

                    // Groen/geel van MAT niveau
                    $groeneWedstrijd = null;
                    $geleWedstrijd = null;

                    if ($mat->actieve_wedstrijd_id) {
                        $groeneWedstrijd = $alleWedstrijden->first(fn($w) => $w->id === $mat->actieve_wedstrijd_id && !$w->is_gespeeld);
                    }
                    if ($mat->volgende_wedstrijd_id) {
                        $geleWedstrijd = $alleWedstrijden->first(fn($w) => $w->id === $mat->volgende_wedstrijd_id && !$w->is_gespeeld);
                    }

                    if ($poule) {
                        // Calculate standings for each judoka
                        $poule->standings = $poule->judokas->map(function ($judoka) use ($poule) {
                            $wp = 0;
                            $jp = 0;
                            foreach ($poule->wedstrijden as $w) {
                                if ($w->judoka_wit_id === $judoka->id) {
                                    $wp += $w->winnaar_id === $judoka->id ? 2 : ($w->is_gespeeld ? 0 : 0);
                                    $jp += (int) preg_replace('/[^0-9]/', '', $w->score_wit ?? '');
                                } elseif ($w->judoka_blauw_id === $judoka->id) {
                                    $wp += $w->winnaar_id === $judoka->id ? 2 : ($w->is_gespeeld ? 0 : 0);
                                    $jp += (int) preg_replace('/[^0-9]/', '', $w->score_blauw ?? '');
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

                        $poule->groeneWedstrijd = $groeneWedstrijd;
                        $poule->geleWedstrijd = $geleWedstrijd;
                    }
                    $mat->huidigePoule = $poule;
                    $mat->groeneWedstrijd = $groeneWedstrijd;
                    $mat->geleWedstrijd = $geleWedstrijd;
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
                        $jp += (int) preg_replace('/[^0-9]/', '', $w->score_wit ?? '');
                    } elseif ($w->judoka_blauw_id === $judoka->id) {
                        $wp += $w->winnaar_id === $judoka->id ? 2 : 0;
                        $jp += (int) preg_replace('/[^0-9]/', '', $w->score_blauw ?? '');
                    }
                }
                return ['judoka' => $judoka, 'wp' => (int) $wp, 'jp' => (int) $jp];
            });

            // Sort by WP desc, JP desc
            $poule->standings = $standings->sortByDesc('wp')->sortByDesc(function ($s) {
                return (int) $s['wp'] * 1000 + (int) $s['jp'];
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
    public function favorieten(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $judokaIds = $request->input('judoka_ids', []);

        if (empty($judokaIds)) {
            return response()->json(['poules' => []]);
        }

        // Get poules containing these judokas, include mat for groen/geel lookup
        $poules = Poule::where('toernooi_id', $toernooi->id)
            ->whereHas('judokas', function ($q) use ($judokaIds) {
                $q->whereIn('judokas.id', $judokaIds);
            })
            ->with(['judokas.club', 'mat', 'blok', 'wedstrijden'])
            ->get()
            ->map(function ($poule) use ($judokaIds, $toernooi) {
                $tolerantie = $toernooi->gewicht_tolerantie ?? 0.5;
                $mat = $poule->mat;

                // Find current and next match - NOW ON MAT LEVEL
                $wedstrijden = $poule->wedstrijden->sortBy('volgorde')->values();

                $huidigeWedstrijd = null;
                $volgendeWedstrijd = null;

                // Groen/Geel komen van MAT niveau (niet poule)
                if ($mat && $mat->actieve_wedstrijd_id) {
                    $huidigeWedstrijd = $wedstrijden->first(fn($w) => $w->id === $mat->actieve_wedstrijd_id && !$w->is_gespeeld);
                }
                if ($mat && $mat->volgende_wedstrijd_id) {
                    $volgendeWedstrijd = $wedstrijden->first(fn($w) => $w->id === $mat->volgende_wedstrijd_id && !$w->is_gespeeld);
                }

                // IDs of judokas in current/next match (use correct column names)
                $huidigeJudokaIds = $huidigeWedstrijd ? [$huidigeWedstrijd->judoka_wit_id, $huidigeWedstrijd->judoka_blauw_id] : [];
                $volgendeJudokaIds = $volgendeWedstrijd ? [$volgendeWedstrijd->judoka_wit_id, $volgendeWedstrijd->judoka_blauw_id] : [];

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
                    'judokas' => $poule->judokas->map(function ($j) use ($judokaIds, $tolerantie, $huidigeJudokaIds, $volgendeJudokaIds) {
                        // Extract band color for colored dot display
                        $bandKleur = $this->getBandKleur($j->band);

                        return [
                            'id' => $j->id,
                            'naam' => $j->naam,
                            'club' => $j->club?->naam,
                            'band' => $j->band,
                            'band_kleur' => $bandKleur,
                            'leeftijd' => $j->geboortejaar ? (date('Y') - $j->geboortejaar) : null,
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
    public function manifest(Organisator $organisator, Toernooi $toernooi): JsonResponse
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
                    'band' => $j->band ?? '-',
                ];
            });

        return response()->json(['judokas' => $judokas]);
    }

    /**
     * Scan QR code and return judoka info (public, read-only)
     */
    public function scanQR(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
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
            ->with(['club', 'poules.blok', 'wegingen'])
            ->first();

        if (!$judoka) {
            return response()->json(['success' => false, 'message' => 'Judoka niet gevonden']);
        }

        $maxWegingen = $toernooi->max_wegingen;
        $aantalWegingen = $judoka->wegingen->count();

        return response()->json([
            'success' => true,
            'judoka' => [
                'id' => $judoka->id,
                'naam' => $judoka->naam,
                'club' => $judoka->club?->naam,
                'leeftijdsklasse' => $judoka->leeftijdsklasse,
                'gewichtsklasse' => $judoka->gewichtsklasse,
                'gewicht' => $judoka->gewicht, // opgegeven gewicht bij aanmelding
                'blok' => $judoka->poules->first()?->blok?->nummer,
                'gewogen' => $judoka->gewicht_gewogen !== null,
                'gewicht_gewogen' => $judoka->gewicht_gewogen,
                'vorige_wegingen' => $judoka->wegingen->take(5)->map(fn($w) => [
                    'gewicht' => $w->gewicht,
                    'tijd' => $w->created_at->format('H:i'),
                ])->toArray(),
                'aantal_wegingen' => $aantalWegingen,
                'max_wegingen' => $maxWegingen,
                'max_bereikt' => $maxWegingen && $aantalWegingen >= $maxWegingen,
            ],
        ]);
    }

    /**
     * Register weight for judoka (public route for PWA)
     * Uses WegingService to properly save weging records
     */
    public function registreerGewicht(Organisator $organisator, Request $request, Toernooi $toernooi, Judoka $judoka): JsonResponse
    {
        // Verify judoka belongs to this tournament
        if ($judoka->toernooi_id !== $toernooi->id) {
            return response()->json(['success' => false, 'message' => 'Judoka niet gevonden'], 404);
        }

        $validated = $request->validate([
            'gewicht' => 'required|numeric|min:10|max:200',
        ]);

        // Use WegingService to register weight (creates Weging record + updates judoka)
        $resultaat = $this->wegingService->registreerGewicht(
            $judoka,
            $validated['gewicht'],
            $request->user()?->name ?? 'PWA'
        );

        if (!($resultaat['success'] ?? true)) {
            return response()->json([
                'success' => false,
                'message' => $resultaat['error'] ?? 'Weging niet toegestaan',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'binnen_klasse' => $resultaat['binnen_klasse'],
            'alternatieve_poule' => $resultaat['alternatieve_poule'],
            'opmerking' => $resultaat['opmerking'],
        ]);
    }

    /**
     * Export results as CSV for organizer
     * Sorted by age class (young to old) and weight (light to heavy)
     */
    public function exportUitslagen(Organisator $organisator, Toernooi $toernooi): Response
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
    public function organisatorResultaten(Organisator $organisator, Toernooi $toernooi): View
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
     * Calculate club ranking based on average WP and JP per judoka
     * Sort by: avg WP desc, then avg JP desc (tiebreaker)
     *
     * WP/JP wordt direct berekend uit wedstrijden tabel (niet via poule standings)
     * om dubbeltelling te voorkomen bij judoka's in meerdere poules.
     */
    public function getClubRanking(Toernooi $toernooi): array
    {
        // Get all judokas with their clubs
        $judokas = Judoka::where('toernooi_id', $toernooi->id)
            ->whereNotNull('club_id')
            ->with('club')
            ->get()
            ->keyBy('id');

        // Get all wedstrijden for this toernooi
        $wedstrijden = \App\Models\Wedstrijd::whereHas('poule', function ($q) use ($toernooi) {
            $q->where('toernooi_id', $toernooi->id);
        })->get();

        // Calculate WP and JP per judoka directly from wedstrijden
        $wpPerJudoka = [];
        $jpPerJudoka = [];

        foreach ($wedstrijden as $w) {
            // WP: 2 punten voor winnaar
            if ($w->winnaar_id) {
                $wpPerJudoka[$w->winnaar_id] = ($wpPerJudoka[$w->winnaar_id] ?? 0) + 2;
            }

            // JP: uit scores
            if ($w->judoka_wit_id) {
                $jpWit = (int) preg_replace('/[^0-9]/', '', $w->score_wit ?? '');
                $jpPerJudoka[$w->judoka_wit_id] = ($jpPerJudoka[$w->judoka_wit_id] ?? 0) + $jpWit;
            }
            if ($w->judoka_blauw_id) {
                $jpBlauw = (int) preg_replace('/[^0-9]/', '', $w->score_blauw ?? '');
                $jpPerJudoka[$w->judoka_blauw_id] = ($jpPerJudoka[$w->judoka_blauw_id] ?? 0) + $jpBlauw;
            }
        }

        // Aggregate per club
        $clubs = [];
        foreach ($judokas as $judoka) {
            $clubId = $judoka->club_id;
            $clubNaam = $judoka->club?->naam ?? 'Geen club';

            if (!isset($clubs[$clubId])) {
                $clubs[$clubId] = [
                    'naam' => $clubNaam,
                    'goud' => 0,
                    'zilver' => 0,
                    'brons' => 0,
                    'totaal_wp' => 0,
                    'totaal_jp' => 0,
                    'totaal_judokas' => 0,
                ];
            }

            $clubs[$clubId]['totaal_wp'] += $wpPerJudoka[$judoka->id] ?? 0;
            $clubs[$clubId]['totaal_jp'] += $jpPerJudoka[$judoka->id] ?? 0;
            $clubs[$clubId]['totaal_judokas']++;
        }

        // Count medals from uitslagen (plaats 1/2/3 in afgesloten poules)
        $uitslagen = $this->getUitslagen($toernooi);
        foreach ($uitslagen as $leeftijdsklasse => $poules) {
            foreach ($poules as $poule) {
                foreach ($poule->standings as $index => $standing) {
                    $plaats = $index + 1;
                    $clubId = $standing['judoka']->club_id ?? 0;

                    if (isset($clubs[$clubId])) {
                        if ($plaats === 1) $clubs[$clubId]['goud']++;
                        if ($plaats === 2) $clubs[$clubId]['zilver']++;
                        if ($plaats === 3) $clubs[$clubId]['brons']++;
                    }
                }
            }
        }

        // Calculate averages per ingeschreven judoka
        foreach ($clubs as $clubId => &$club) {
            $club['totaal_medailles'] = $club['goud'] + $club['zilver'] + $club['brons'];
            $aantalJudokas = $club['totaal_judokas'] ?: 1;

            $club['gem_wp'] = round($club['totaal_wp'] / $aantalJudokas, 2);
            $club['gem_jp'] = round($club['totaal_jp'] / $aantalJudokas, 2);
        }

        // Sort by average WP desc, then average JP desc (tiebreaker)
        uasort($clubs, function ($a, $b) {
            if ($a['gem_wp'] !== $b['gem_wp']) {
                return $b['gem_wp'] <=> $a['gem_wp'];
            }
            return $b['gem_jp'] <=> $a['gem_jp'];
        });

        $rankedClubs = array_values($clubs);
        return [
            'absoluut' => $rankedClubs,
            'relatief' => $rankedClubs,
        ];
    }

    /**
     * Get results for a specific club (for coach portal)
     */
    public function getClubResultaten(Organisator $organisator, Toernooi $toernooi, int $clubId): array
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
