<?php

namespace App\Http\Controllers;

use App\Models\Blok;
use App\Models\Club;
use App\Models\Coach;
use App\Models\CoachKaart;
use App\Models\Judoka;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Exports\PouleExport;
use Illuminate\Http\Request;
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
    public function index(Toernooi $toernooi): View
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
     * Print poules - redirect naar reguliere poules pagina
     */
    public function printPoules(Toernooi $toernooi, ?int $blokNummer = null)
    {
        // Redirect naar de reguliere poules pagina (heeft print CSS)
        return redirect()->route('toernooi.poule.index', $toernooi);
    }

    /**
     * Print weeglijst - alle judoka's gegroepeerd per blok, alfabetisch gesorteerd
     */
    public function printWeeglijst(Toernooi $toernooi, ?int $blokNummer = null): View
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
    public function printZaaloverzicht(Toernooi $toernooi): View
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
    public function printWeegkaarten(Toernooi $toernooi): View
    {
        $judokas = $toernooi->judokas()
            ->with(['club', 'poules.mat', 'poules.blok'])
            ->orderBy('club_id')
            ->orderBy('sort_categorie')
            ->orderBy('sort_gewicht')
            ->orderBy('sort_band')
            ->orderBy('naam')
            ->get();

        return view('pages.noodplan.weegkaarten', compact('toernooi', 'judokas'));
    }

    /**
     * Print weegkaarten per club
     */
    public function printWeegkaartenClub(Toernooi $toernooi, Club $club): View
    {
        $judokas = $toernooi->judokas()
            ->where('club_id', $club->id)
            ->with(['club', 'poules.mat', 'poules.blok'])
            ->orderBy('sort_categorie')
            ->orderBy('sort_gewicht')
            ->orderBy('sort_band')
            ->orderBy('naam')
            ->get();

        return view('pages.noodplan.weegkaarten', compact('toernooi', 'judokas', 'club'));
    }

    /**
     * Print 1 weegkaart
     */
    public function printWeegkaart(Toernooi $toernooi, Judoka $judoka): View
    {
        $judokas = collect([$judoka->load(['club', 'poules.mat', 'poules.blok'])]);

        return view('pages.noodplan.weegkaarten', compact('toernooi', 'judokas'));
    }

    /**
     * Print alle coachkaarten
     */
    public function printCoachkaarten(Toernooi $toernooi): View
    {
        $coachkaarten = CoachKaart::where('toernooi_id', $toernooi->id)
            ->with(['club', 'coach'])
            ->orderBy('club_id')
            ->get();

        return view('pages.noodplan.coachkaarten', compact('toernooi', 'coachkaarten'));
    }

    /**
     * Print coachkaarten per club
     */
    public function printCoachkaartenClub(Toernooi $toernooi, Club $club): View
    {
        $coachkaarten = CoachKaart::where('toernooi_id', $toernooi->id)
            ->where('club_id', $club->id)
            ->with(['club', 'coach'])
            ->get();

        return view('pages.noodplan.coachkaarten', compact('toernooi', 'coachkaarten', 'club'));
    }

    /**
     * Print 1 coachkaart
     */
    public function printCoachkaart(Toernooi $toernooi, CoachKaart $coachKaart): View
    {
        $coachkaarten = collect([$coachKaart->load(['club', 'coach'])]);

        return view('pages.noodplan.coachkaarten', compact('toernooi', 'coachkaarten'));
    }

    /**
     * Print leeg wedstrijdschema template
     */
    public function printLeegSchema(Toernooi $toernooi, int $aantal): View
    {
        if ($aantal < 2 || $aantal > 7) {
            abort(404, 'Aantal judoka\'s moet tussen 2 en 7 zijn');
        }

        // Haal wedstrijdvolgorde uit toernooi instellingen
        $schemas = $toernooi->wedstrijd_schemas ?? [];
        $schema = $schemas[$aantal] ?? $this->getStandaardSchema($aantal);

        return view('pages.noodplan.leeg-schema', compact('toernooi', 'aantal', 'schema'));
    }

    /**
     * Print instellingen samenvatting
     */
    public function printInstellingen(Toernooi $toernooi): View
    {
        $blokken = $toernooi->blokken()->orderBy('nummer')->get();

        return view('pages.noodplan.instellingen', compact('toernooi', 'blokken'));
    }

    /**
     * Print contactlijst coaches
     */
    public function printContactlijst(Toernooi $toernooi): View
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
    public function printWedstrijdschemas(Toernooi $toernooi, ?int $blokNummer = null): View
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
    public function printPouleSchema(Toernooi $toernooi, Poule $poule): View
    {
        $poule->load(['judokas', 'wedstrijden']);

        return view('pages.noodplan.poule-schema', compact('toernooi', 'poule'));
    }

    /**
     * Print ingevulde wedstrijdschema's in matrix-formaat (zoals mat interface)
     * 1 poule per A4, landscape voor ≥6 judoka's
     */
    public function printIngevuldSchemas(Toernooi $toernooi, ?int $blokNummer = null): View
    {
        // Free tier: not available
        if ($toernooi->isFreeTier()) {
            return view('pages.noodplan.upgrade-required', [
                'toernooi' => $toernooi,
                'feature' => 'Ingevulde wedstrijdschema\'s',
            ]);
        }

        $blok = null;
        if ($blokNummer) {
            $blok = $toernooi->blokken()->where('nummer', $blokNummer)->first();
            if (!$blok) {
                abort(404, 'Blok niet gevonden');
            }
            $poules = $blok->poules()
                ->whereNotNull('mat_id')
                ->whereHas('wedstrijden')
                ->with(['judokas.club', 'wedstrijden', 'mat', 'blok'])
                ->get();
            $titel = "Wedstrijdschema's (Matrix) - Blok {$blok->nummer}";
        } else {
            $poules = Poule::where('toernooi_id', $toernooi->id)
                ->whereNotNull('mat_id')
                ->whereHas('wedstrijden')
                ->with(['judokas.club', 'wedstrijden', 'mat', 'blok'])
                ->orderBy('blok_id')
                ->get();
            $titel = "Alle Wedstrijdschema's (Matrix)";
        }

        // Build schema for each poule
        $schemas = $toernooi->wedstrijd_schemas ?? [];
        $poulesMetSchema = $poules->map(function ($poule) use ($schemas) {
            $aantal = $poule->judokas->count();
            $schema = $schemas[$aantal] ?? $this->getStandaardSchema($aantal);
            return [
                'poule' => $poule,
                'schema' => $schema,
                'aantal' => $aantal,
            ];
        });

        return view('pages.noodplan.ingevuld-schema', compact('toernooi', 'poulesMetSchema', 'titel', 'blok'));
    }

    /**
     * Export poules naar Excel/CSV (1 sheet per blok)
     */
    public function exportPoules(Toernooi $toernooi, string $format = 'xlsx')
    {
        $filename = sprintf('poules_%s_%s', $toernooi->slug, now()->format('Y-m-d'));

        return match($format) {
            'csv' => Excel::download(new PouleExport($toernooi), "{$filename}.csv", \Maatwebsite\Excel\Excel::CSV),
            default => Excel::download(new PouleExport($toernooi), "{$filename}.xlsx"),
        };
    }

    /**
     * Standaard wedstrijdschema's
     */
    private function getStandaardSchema(int $aantal): array
    {
        return match($aantal) {
            2 => [[1,2], [2,1]],
            3 => [[1,2], [1,3], [2,3], [2,1], [3,2], [3,1]],
            4 => [[1,2], [3,4], [2,3], [1,4], [2,4], [1,3]],
            5 => [[1,2], [3,4], [1,5], [2,3], [4,5], [1,3], [2,4], [3,5], [1,4], [2,5]],
            6 => [[1,2], [3,4], [5,6], [1,3], [2,5], [4,6], [3,5], [2,4], [1,6], [2,3], [4,5], [3,6], [1,4], [2,6], [1,5]],
            7 => [[1,2], [3,4], [5,6], [1,7], [2,3], [4,5], [6,7], [1,3], [2,4], [5,7], [3,6], [1,4], [2,5], [3,7], [4,6], [1,5], [2,6], [4,7], [1,6], [3,5], [2,7]],
            default => []
        };
    }
}
