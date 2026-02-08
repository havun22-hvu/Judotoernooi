<?php

namespace App\Http\Controllers;

use App\Models\Organisator;
use App\Models\Blok;
use App\Models\Club;
use App\Models\Coach;
use App\Models\CoachKaart;
use App\Models\DeviceToegang;
use App\Models\Judoka;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use App\Exports\PouleExport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class NoodplanController extends Controller
{
    // Free tier limits for noodplan
    private const FREE_MAX_POULES = 2;
    private const FREE_MAX_WEEGLIJST = 10;
    private const FREE_WEDSTRIJDSCHEMA_JUDOKAS = 6; // Only show schemas with exactly 6 judokas

    /**
     * Noodplan index - overzicht met alle print opties
     */
    public function index(Organisator $organisator, Toernooi $toernooi): View
    {
        $blokken = $toernooi->blokken()->orderBy('nummer')->get();
        $clubs = Club::whereHas('judokas', fn($q) => $q->where('toernooi_id', $toernooi->id))
            ->orderBy('naam')
            ->get();

        // Actieve poules (momenteel bezig op een mat)
        $actievePoules = Poule::where('toernooi_id', $toernooi->id)
            ->whereNotNull('mat_id')
            ->whereNotNull('actieve_wedstrijd_id')
            ->with(['judokas', 'mat'])
            ->get();

        $isFreeTier = $toernooi->isFreeTier();
        $freeLimits = $isFreeTier ? [
            'max_poules' => self::FREE_MAX_POULES,
            'max_weeglijst' => self::FREE_MAX_WEEGLIJST,
            'wedstrijdschema_judokas' => self::FREE_WEDSTRIJDSCHEMA_JUDOKAS,
        ] : null;

        return view('pages.noodplan.index', compact('toernooi', 'blokken', 'clubs', 'actievePoules', 'isFreeTier', 'freeLimits'));
    }

    /**
     * Print poules - per blok, per mat
     */
    public function printPoules(Organisator $organisator, Toernooi $toernooi, ?int $blokNummer = null): View
    {
        $query = $toernooi->blokken()->with(['poules' => fn($q) => $q->with(['judokas.club', 'mat'])])->orderBy('nummer');

        if ($blokNummer) {
            $query->where('nummer', $blokNummer);
        }

        $blokken = $query->get();
        $matten = $toernooi->matten()->orderBy('nummer')->get();
        $blok = $blokNummer ? $blokken->first() : null;

        return view('pages.noodplan.poules-print', compact('toernooi', 'blokken', 'matten', 'blok'));
    }

    /**
     * Print weeglijst - alle judoka's gegroepeerd per blok, alfabetisch gesorteerd
     */
    public function printWeeglijst(Organisator $organisator, Toernooi $toernooi, ?int $blokNummer = null): View
    {
        $query = $toernooi->blokken()->with(['poules.judokas.club'])->orderBy('nummer');

        if ($blokNummer) {
            $query->where('nummer', $blokNummer);
        }

        $blokken = $query->get();
        $isFreeTier = $toernooi->isFreeTier();

        // Bouw lijst per blok met judoka's alfabetisch gesorteerd op voornaam
        $judokasPerBlok = $blokken->mapWithKeys(function ($blok) {
            $judokas = $blok->poules
                ->flatMap(fn($p) => $p->judokas)
                ->unique('id')
                ->sortBy(fn($j) => strtolower($j->naam))
                ->values();
            return [$blok->nummer => $judokas];
        });

        // Free tier: limit to 10 judokas total
        if ($isFreeTier) {
            $count = 0;
            $judokasPerBlok = $judokasPerBlok->map(function ($judokas) use (&$count) {
                $remaining = self::FREE_MAX_WEEGLIJST - $count;
                if ($remaining <= 0) {
                    return collect();
                }
                $limited = $judokas->take($remaining);
                $count += $limited->count();
                return $limited;
            });
        }

        return view('pages.noodplan.weeglijst', compact('toernooi', 'judokasPerBlok', 'isFreeTier'));
    }

    /**
     * Print zaaloverzicht
     */
    public function printZaaloverzicht(Organisator $organisator, Toernooi $toernooi): View
    {
        $blokken = $toernooi->blokken()
            ->with(['poules' => fn($q) => $q->whereNotNull('mat_id')->with('mat')->orderBy('mat_id')])
            ->orderBy('nummer')
            ->get();

        return view('pages.noodplan.zaaloverzicht', compact('toernooi', 'blokken'));
    }

    /**
     * Print alle weegkaarten
     */
    public function printWeegkaarten(Organisator $organisator, Toernooi $toernooi): View
    {
        $judokas = $toernooi->judokas()
            ->with(['club', 'poules.mat', 'poules.blok'])
            ->orderBy('club_id')
            ->orderBy('sort_categorie')
            ->orderBy('sort_gewicht')
            ->orderBy('sort_band')
            ->orderBy('naam')
            ->get();

        $enkelBlok = $toernooi->blokken()->count() === 1;

        return view('pages.noodplan.weegkaarten', compact('toernooi', 'judokas', 'enkelBlok'));
    }

    /**
     * Print weegkaarten per club
     */
    public function printWeegkaartenClub(Organisator $organisator, Toernooi $toernooi, Club $club): View
    {
        $judokas = $toernooi->judokas()
            ->where('club_id', $club->id)
            ->with(['club', 'poules.mat', 'poules.blok'])
            ->orderBy('sort_categorie')
            ->orderBy('sort_gewicht')
            ->orderBy('sort_band')
            ->orderBy('naam')
            ->get();

        $enkelBlok = $toernooi->blokken()->count() === 1;

        return view('pages.noodplan.weegkaarten', compact('toernooi', 'judokas', 'club', 'enkelBlok'));
    }

    /**
     * Print 1 weegkaart
     */
    public function printWeegkaart(Organisator $organisator, Toernooi $toernooi, Judoka $judoka): View
    {
        $judokas = collect([$judoka->load(['club', 'poules.mat', 'poules.blok'])]);
        $enkelBlok = $toernooi->blokken()->count() === 1;

        return view('pages.noodplan.weegkaarten', compact('toernooi', 'judokas', 'enkelBlok'));
    }

    /**
     * Print alle coachkaarten
     */
    public function printCoachkaarten(Organisator $organisator, Toernooi $toernooi): View
    {
        $coachkaarten = CoachKaart::where('toernooi_id', $toernooi->id)
            ->with(['club'])
            ->orderBy('club_id')
            ->get();

        return view('pages.noodplan.coachkaarten', compact('toernooi', 'coachkaarten'));
    }

    /**
     * Print coachkaarten per club
     */
    public function printCoachkaartenClub(Organisator $organisator, Toernooi $toernooi, Club $club): View
    {
        $coachkaarten = CoachKaart::where('toernooi_id', $toernooi->id)
            ->where('club_id', $club->id)
            ->with(['club'])
            ->get();

        return view('pages.noodplan.coachkaarten', compact('toernooi', 'coachkaarten', 'club'));
    }

    /**
     * Print 1 coachkaart
     */
    public function printCoachkaart(Organisator $organisator, Toernooi $toernooi, CoachKaart $coachKaart): View
    {
        $coachkaarten = collect([$coachKaart->load(['club', 'coach'])]);

        return view('pages.noodplan.coachkaarten', compact('toernooi', 'coachkaarten'));
    }

    /**
     * Print leeg wedstrijdschema template
     */
    public function printLeegSchema(Organisator $organisator, Toernooi $toernooi, int $aantal): View
    {
        if ($aantal < 2 || $aantal > 7) {
            abort(404, 'Aantal judoka\'s moet tussen 2 en 7 zijn');
        }

        // Free tier: only allow schema for 6 judokas
        if ($toernooi->isFreeTier() && $aantal !== self::FREE_WEDSTRIJDSCHEMA_JUDOKAS) {
            return view('pages.noodplan.upgrade-required', [
                'toernooi' => $toernooi,
                'feature' => "Leeg schema voor {$aantal} judoka's",
            ]);
        }

        // Haal wedstrijdvolgorde uit toernooi instellingen
        $schemas = $toernooi->wedstrijd_schemas ?? [];
        $bestOfThree = $toernooi->best_of_three_bij_2 ?? false;
        $schema = $schemas[$aantal] ?? $this->getStandaardSchema($aantal, $bestOfThree && $aantal === 2);

        $isFreeTier = $toernooi->isFreeTier();

        return view('pages.noodplan.leeg-schema', compact('toernooi', 'aantal', 'schema', 'isFreeTier'));
    }

    /**
     * Print instellingen samenvatting
     */
    public function printInstellingen(Organisator $organisator, Toernooi $toernooi): View
    {
        $blokken = $toernooi->blokken()->orderBy('nummer')->get();

        return view('pages.noodplan.instellingen', compact('toernooi', 'blokken'));
    }

    /**
     * Print contactlijst coaches
     */
    public function printContactlijst(Organisator $organisator, Toernooi $toernooi): View
    {
        $clubs = Club::whereHas('judokas', fn($q) => $q->where('toernooi_id', $toernooi->id))
            ->with(['coaches' => fn($q) => $q->where('toernooi_id', $toernooi->id)])
            ->orderBy('naam')
            ->get();

        return view('pages.noodplan.contactlijst', compact('toernooi', 'clubs'));
    }

    /**
     * Print ingevulde wedstrijdschema's per blok
     */
    public function printWedstrijdschemas(Organisator $organisator, Toernooi $toernooi, ?int $blokNummer = null): View
    {
        $blok = null;
        $isFreeTier = $toernooi->isFreeTier();

        if ($blokNummer) {
            $blok = $toernooi->blokken()->where('nummer', $blokNummer)->first();
            if (!$blok) {
                abort(404, 'Blok niet gevonden');
            }
            $poules = $blok->poules()
                ->with(['judokas', 'wedstrijden'])
                ->get();
            $titel = "Wedstrijdschema's Blok {$blok->nummer}";
        } else {
            $poules = Poule::where('toernooi_id', $toernooi->id)
                ->with(['judokas', 'wedstrijden', 'blok'])
                ->orderBy('blok_id')
                ->get();
            $titel = "Alle Wedstrijdschema's";
        }

        // Free tier: only show poules with exactly 6 judokas, max 2 poules
        if ($isFreeTier) {
            $poules = $poules->filter(fn($p) => $p->judokas->count() === self::FREE_WEDSTRIJDSCHEMA_JUDOKAS)
                ->take(self::FREE_MAX_POULES);
            $titel .= " (Voorbeeld - max " . self::FREE_MAX_POULES . " poules van " . self::FREE_WEDSTRIJDSCHEMA_JUDOKAS . " judoka's)";
        }

        return view('pages.noodplan.wedstrijdschemas', compact('toernooi', 'poules', 'titel', 'blok', 'isFreeTier'));
    }

    /**
     * Print huidige staat van 1 poule
     */
    public function printPouleSchema(Organisator $organisator, Toernooi $toernooi, Poule $poule): View
    {
        $poule->load(['judokas', 'wedstrijden']);

        return view('pages.noodplan.poule-schema', compact('toernooi', 'poule'));
    }

    /**
     * Print ingevulde wedstrijdschema's in matrix-formaat (zoals mat interface)
     * 1 poule per A4, landscape voor ≥6 judoka's
     * Met checkboxes om te selecteren welke schema's geprint worden
     */
    public function printIngevuldSchemas(Organisator $organisator, Toernooi $toernooi, ?int $blokNummer = null): View
    {
        // Free tier: not available
        if ($toernooi->isFreeTier()) {
            return view('pages.noodplan.upgrade-required', [
                'toernooi' => $toernooi,
                'feature' => 'Ingevulde wedstrijdschema\'s',
            ]);
        }

        $blok = null;
        // Filter afwezige judoka's uit de poules (NULL = nog niet geregistreerd, wel tonen)
        $judokasConstraint = fn($q) => $q->where(function($q) {
            $q->whereNull('aanwezigheid')->orWhere('aanwezigheid', '!=', 'afwezig');
        })->with('club');

        if ($blokNummer) {
            $blok = $toernooi->blokken()->where('nummer', $blokNummer)->first();
            if (!$blok) {
                abort(404, 'Blok niet gevonden');
            }
            $poules = $blok->poules()
                ->whereNotNull('mat_id')
                ->whereHas('wedstrijden')
                ->with(['judokas' => $judokasConstraint, 'wedstrijden', 'mat', 'blok'])
                ->get();
            $titel = "Wedstrijdschema's (Matrix) - Blok {$blok->nummer}";
        } else {
            $poules = Poule::where('toernooi_id', $toernooi->id)
                ->whereNotNull('mat_id')
                ->whereHas('wedstrijden')
                ->with(['judokas' => $judokasConstraint, 'wedstrijden', 'mat', 'blok'])
                ->orderBy('blok_id')
                ->get();
            $titel = "Alle Wedstrijdschema's (Matrix)";
        }

        // Build schema for each poule
        $schemas = $toernooi->wedstrijd_schemas ?? [];
        $bestOfThree = $toernooi->best_of_three_bij_2 ?? false;
        $poulesMetSchema = $poules->map(function ($poule) use ($schemas, $bestOfThree) {
            $aantal = $poule->judokas->count();
            $schema = $schemas[$aantal] ?? $this->getStandaardSchema($aantal, $bestOfThree && $aantal === 2);
            return [
                'poule' => $poule,
                'schema' => $schema,
                'aantal' => $aantal,
            ];
        });

        return view('pages.noodplan.ingevuld-schema', compact('toernooi', 'poulesMetSchema', 'titel', 'blok'));
    }

    /**
     * Print live wedstrijdschema's in matrix-formaat MET scores
     * Zelfde layout als ingevuld-schema, maar met actuele uitslagen
     */
    public function printLiveSchemas(Organisator $organisator, Toernooi $toernooi, ?int $blokNummer = null): View
    {
        // Free tier: not available
        if ($toernooi->isFreeTier()) {
            return view('pages.noodplan.upgrade-required', [
                'toernooi' => $toernooi,
                'feature' => 'Live wedstrijdschema\'s',
            ]);
        }

        $blok = null;
        // Filter afwezige judoka's uit de poules
        $judokasConstraint = fn($q) => $q->where('aanwezigheid', '!=', 'afwezig')->with('club');

        if ($blokNummer) {
            $blok = $toernooi->blokken()->where('nummer', $blokNummer)->first();
            if (!$blok) {
                abort(404, 'Blok niet gevonden');
            }
            $poules = $blok->poules()
                ->whereNotNull('mat_id')
                ->whereHas('wedstrijden')
                ->with(['judokas' => $judokasConstraint, 'wedstrijden', 'mat', 'blok'])
                ->get();
            $titel = "Live Schema's - Blok {$blok->nummer}";
        } else {
            $poules = Poule::where('toernooi_id', $toernooi->id)
                ->whereNotNull('mat_id')
                ->whereHas('wedstrijden')
                ->with(['judokas' => $judokasConstraint, 'wedstrijden', 'mat', 'blok'])
                ->orderBy('blok_id')
                ->get();
            $titel = "Alle Live Schema's";
        }

        // Build schema for each poule
        $schemas = $toernooi->wedstrijd_schemas ?? [];
        $bestOfThree = $toernooi->best_of_three_bij_2 ?? false;
        $poulesMetSchema = $poules->map(function ($poule) use ($schemas, $bestOfThree) {
            $aantal = $poule->judokas->count();
            $schema = $schemas[$aantal] ?? $this->getStandaardSchema($aantal, $bestOfThree && $aantal === 2);
            return [
                'poule' => $poule,
                'schema' => $schema,
                'aantal' => $aantal,
            ];
        });

        // Show scores = true for live version
        $showScores = true;

        return view('pages.noodplan.ingevuld-schema', compact('toernooi', 'poulesMetSchema', 'titel', 'blok', 'showScores'));
    }

    /**
     * Export poules naar Excel/CSV (1 sheet per blok)
     */
    public function exportPoules(Organisator $organisator, Toernooi $toernooi, string $format = 'xlsx')
    {
        $filename = sprintf('poules_%s_%s', $toernooi->slug, now()->format('Y-m-d'));

        return match($format) {
            'csv' => Excel::download(new PouleExport($toernooi), "{$filename}.csv", \Maatwebsite\Excel\Excel::CSV),
            default => Excel::download(new PouleExport($toernooi), "{$filename}.xlsx"),
        };
    }

    /**
     * Download offline pakket - standalone HTML bestand met alle toernooi data
     */
    public function downloadOfflinePakket(Organisator $organisator, Toernooi $toernooi)
    {
        // Free tier: not available
        if ($toernooi->isFreeTier()) {
            return view('pages.noodplan.upgrade-required', [
                'toernooi' => $toernooi,
                'feature' => 'Offline Pakket',
            ]);
        }

        // Collect all tournament data
        $data = [
            'toernooi' => [
                'id' => $toernooi->id,
                'naam' => $toernooi->naam,
                'datum' => $toernooi->datum?->format('d-m-Y'),
                'slug' => $toernooi->slug,
                'wedstrijd_schemas' => $toernooi->wedstrijd_schemas ?? [],
                'best_of_three_bij_2' => $toernooi->best_of_three_bij_2 ?? false,
            ],
            'clubs' => $toernooi->clubs()->orderBy('naam')->get(['clubs.id', 'clubs.naam'])->toArray(),
            'blokken' => $toernooi->blokken()->orderBy('nummer')->get(['id', 'nummer'])->toArray(),
            'matten' => $toernooi->matten()->orderBy('nummer')->get(['id', 'nummer'])->toArray(),
            'judokas' => $toernooi->judokas()->with('club:id,naam')->get()->map(fn($j) => [
                'id' => $j->id,
                'naam' => $j->naam,
                'club_id' => $j->club_id,
                'club_naam' => $j->club?->naam,
                'geboortejaar' => $j->geboortejaar,
                'geslacht' => $j->geslacht,
                'band' => $j->band,
                'gewicht' => $j->gewicht,
                'gewicht_gewogen' => $j->gewicht_gewogen,
                'leeftijdsklasse' => $j->leeftijdsklasse,
                'gewichtsklasse' => $j->gewichtsklasse,
                'aanwezigheid' => $j->aanwezigheid,
            ])->toArray(),
            'poules' => $toernooi->poules()
                ->with(['judokas:id', 'blok:id,nummer', 'mat:id,nummer'])
                ->get()
                ->map(fn($p) => [
                    'id' => $p->id,
                    'nummer' => $p->nummer,
                    'type' => $p->type,
                    'blok_id' => $p->blok_id,
                    'blok_nummer' => $p->blok?->nummer,
                    'mat_id' => $p->mat_id,
                    'mat_nummer' => $p->mat?->nummer,
                    'leeftijdsklasse' => $p->leeftijdsklasse,
                    'gewichtsklasse' => $p->gewichtsklasse,
                    'judoka_ids' => $p->judokas->pluck('id')->toArray(),
                ])->toArray(),
            'wedstrijden' => Wedstrijd::whereHas('poule', fn($q) => $q->where('toernooi_id', $toernooi->id))
                ->get()
                ->map(fn($w) => [
                    'id' => $w->id,
                    'poule_id' => $w->poule_id,
                    'volgorde' => $w->volgorde,
                    'judoka_wit_id' => $w->judoka_wit_id,
                    'judoka_blauw_id' => $w->judoka_blauw_id,
                    'is_gespeeld' => $w->is_gespeeld,
                    'winnaar_id' => $w->winnaar_id,
                    'score_wit' => $w->score_wit,
                    'score_blauw' => $w->score_blauw,
                    'groep' => $w->groep,
                    'ronde' => $w->ronde,
                ])->toArray(),
            'device_toegangen' => $toernooi->deviceToegangen()
                ->orderBy('rol')
                ->orderBy('mat_nummer')
                ->get()
                ->map(fn($d) => [
                    'id' => $d->id,
                    'naam' => $d->naam,
                    'telefoon' => $d->telefoon,
                    'rol' => $d->rol,
                    'mat_nummer' => $d->mat_nummer,
                    'label' => $d->getLabel(),
                    'code' => $d->code,
                    'pincode' => $d->pincode,
                    'url' => $d->getUrl(),
                    'is_gebonden' => $d->isGebonden(),
                    'device_info' => $d->device_info,
                ])->toArray(),
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ];

        $html = view('pages.noodplan.offline-pakket', ['jsonData' => json_encode($data, JSON_UNESCAPED_UNICODE)])->render();

        $filename = sprintf('%s_offline_%s.html',
            preg_replace('/[^a-zA-Z0-9_-]/', '_', $toernooi->naam),
            now()->format('Y-m-d_Hi')
        );

        return response($html)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Upload offline resultaten terug naar server
     */
    public function uploadOfflineResultaten(Organisator $organisator, Toernooi $toernooi, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'resultaten' => 'required|array',
            'resultaten.*.wedstrijd_id' => 'required|integer',
            'resultaten.*.winnaar_id' => 'required|integer',
            'resultaten.*.score_wit' => 'required|integer|min:0',
            'resultaten.*.score_blauw' => 'required|integer|min:0',
        ]);

        $synced = 0;
        $skipped = 0;

        DB::beginTransaction();
        try {
            foreach ($validated['resultaten'] as $resultaat) {
                $wedstrijd = Wedstrijd::whereHas('poule', fn($q) => $q->where('toernooi_id', $toernooi->id))
                    ->where('id', $resultaat['wedstrijd_id'])
                    ->first();

                if (!$wedstrijd) continue;

                // Only overwrite if not already played on server
                if ($wedstrijd->is_gespeeld) {
                    $skipped++;
                    continue;
                }

                $wedstrijd->update([
                    'winnaar_id' => $resultaat['winnaar_id'],
                    'score_wit' => $resultaat['score_wit'],
                    'score_blauw' => $resultaat['score_blauw'],
                    'is_gespeeld' => true,
                    'gespeeld_op' => now(),
                ]);
                $synced++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'synced' => $synced,
            'skipped' => $skipped,
        ]);
    }

    /**
     * Standaard wedstrijdschema's
     */
    private function getStandaardSchema(int $aantal, ?bool $bestOfThree = false): array
    {
        return match($aantal) {
            2 => $bestOfThree ? [[1,2], [2,1], [1,2]] : [[1,2], [2,1]],
            3 => [[1,2], [1,3], [2,3], [2,1], [3,2], [3,1]],
            4 => [[1,2], [3,4], [2,3], [1,4], [2,4], [1,3]],
            5 => [[1,2], [3,4], [1,5], [2,3], [4,5], [1,3], [2,4], [3,5], [1,4], [2,5]],
            6 => [[1,2], [3,4], [5,6], [1,3], [2,5], [4,6], [3,5], [2,4], [1,6], [2,3], [4,5], [3,6], [1,4], [2,6], [1,5]],
            7 => [[1,2], [3,4], [5,6], [1,7], [2,3], [4,5], [6,7], [1,3], [2,4], [5,7], [3,6], [1,4], [2,5], [3,7], [4,6], [1,5], [2,6], [4,7], [1,6], [3,5], [2,7]],
            default => []
        };
    }

    /**
     * SSE Stream voor live backup sync
     * Stuurt alle wedstrijduitslagen en sluit dan (client reconnect elke 30s)
     */
    public function stream(Organisator $organisator, Toernooi $toernooi)
    {
        // Disable output buffering
        while (ob_get_level()) ob_end_clean();

        return response()->stream(function () use ($toernooi) {
            // Send sync with all current data
            $data = $this->getAllePouleData($toernooi);
            echo "event: sync\n";
            echo "data: " . json_encode($data) . "\n\n";
            echo "retry: 30000\n\n"; // Tell client to reconnect after 30 seconds

            flush();
            // Connection closes after this - client will reconnect based on retry
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Get alle poule data voor sync (API endpoint)
     */
    public function syncData(Organisator $organisator, Toernooi $toernooi)
    {
        return response()->json($this->getAllePouleData($toernooi));
    }

    /**
     * Haal alle poule data op met wedstrijden en uitslagen
     */
    private function getAllePouleData(Toernooi $toernooi): array
    {
        $poules = Poule::where('toernooi_id', $toernooi->id)
            ->whereNotNull('mat_id')
            ->with(['judokas.club', 'wedstrijden', 'mat', 'blok'])
            ->get();

        return [
            'toernooi_id' => $toernooi->id,
            'toernooi_naam' => $toernooi->naam,
            'toernooi_datum' => $toernooi->datum->format('d-m-Y'),
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'poules' => $poules->map(function ($poule) {
                return [
                    'id' => $poule->id,
                    'nummer' => $poule->nummer,
                    'titel' => $poule->getDisplayTitel(),
                    'mat_nummer' => $poule->mat?->nummer,
                    'blok_nummer' => $poule->blok?->nummer,
                    'judokas' => $poule->judokas->map(fn($j) => [
                        'id' => $j->id,
                        'naam' => $j->naam,
                        'club' => $j->club?->naam,
                    ])->values(),
                    'wedstrijden' => $poule->wedstrijden->map(fn($w) => [
                        'id' => $w->id,
                        'volgorde' => $w->volgorde,
                        'judoka_wit_id' => $w->judoka_wit_id,
                        'judoka_blauw_id' => $w->judoka_blauw_id,
                        'is_gespeeld' => $w->is_gespeeld,
                        'winnaar_id' => $w->winnaar_id,
                        'score_wit' => $w->score_wit,
                        'score_blauw' => $w->score_blauw,
                    ])->values(),
                ];
            })->values(),
        ];
    }
}
