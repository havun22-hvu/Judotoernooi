<?php

namespace App\Http\Controllers;

use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PubliekResultatenController extends Controller
{
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
            Str::slug($toernooi->naam),
            now()->format('Y-m-d_His')
        );

        return response($csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * Export danpunten CSV voor JBN - gewonnen wedstrijden voor bruine banden
     */
    public function exportDanpunten(Organisator $organisator, Toernooi $toernooi): Response
    {
        if (!$toernooi->danpunten_actief) {
            abort(404);
        }

        // Get all brown belt judokas with at least 1 won match
        $bruineBanden = Judoka::where('toernooi_id', $toernooi->id)
            ->where('band', 'bruin')
            ->with('club')
            ->get();

        $csv = "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
        $csv .= "Naam;JBN Lidnummer;Judoschool;Toernooi;Toernooi datum;Aantal gewonnen wedstrijden\n";

        foreach ($bruineBanden as $judoka) {
            // Count won matches (poule + eliminatie, exclude byes)
            $gewonnen = \App\Models\Wedstrijd::where('winnaar_id', $judoka->id)
                ->whereNotNull('judoka_wit_id')
                ->whereNotNull('judoka_blauw_id')
                ->whereHas('poule', fn ($q) => $q->where('toernooi_id', $toernooi->id))
                ->count();

            if ($gewonnen === 0) {
                continue;
            }

            $csv .= sprintf(
                "%s;%s;%s;%s;%s;%d\n",
                $judoka->naam,
                $judoka->jbn_lidnummer ?? '',
                $judoka->club?->naam ?? '-',
                $toernooi->naam,
                $toernooi->datum?->format('d-m-Y') ?? '',
                $gewonnen
            );
        }

        $filename = sprintf('danpunten_%s_%s.csv',
            Str::slug($toernooi->naam),
            now()->format('Y-m-d')
        );

        return response($csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
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
}
