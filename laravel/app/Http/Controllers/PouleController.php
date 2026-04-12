<?php

namespace App\Http\Controllers;

use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Services\ActivityLogger;
use App\Services\PouleIndelingService;
use App\Services\WedstrijdSchemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Handles poule CRUD and kruisfinale settings.
 * Generation and verification: PouleGeneratieController
 * Judoka matching and moves: PouleJudokaController
 * Elimination bracket operations: PouleEliminatieController
 */
class PouleController extends Controller
{
    public function __construct(
        private PouleIndelingService $pouleService,
        private WedstrijdSchemaService $wedstrijdService
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

        // Group by categorie_key (stable identifier from config), fallback to leeftijdsklasse
        $poulesPerKlasse = $poules->groupBy(fn($p) => $p->categorie_key ?: $p->leeftijdsklasse);

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
        $leeftijdsklasse = $poule->leeftijdsklasse;
        $gewichtsklasse = $poule->gewichtsklasse;
        $poule->delete();

        ActivityLogger::log($toernooi, 'verwijder_poule', "Poule #{$nummer} verwijderd ({$leeftijdsklasse} {$gewichtsklasse})", [
            'model_type' => 'Poule',
            'model_id' => null,
            'properties' => ['nummer' => $nummer, 'leeftijdsklasse' => $leeftijdsklasse, 'gewichtsklasse' => $gewichtsklasse],
        ]);

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
        $gewichtsklasse = $validated['gewichtsklasse'] ?? '';
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
            'type' => 'voorronde',
            'aantal_judokas' => 0,
            'aantal_wedstrijden' => 0,
        ]);

        ActivityLogger::log($toernooi, 'maak_poule', "Poule #{$nieuweNummer} aangemaakt: {$titel}", [
            'model' => $poule,
            'properties' => ['nummer' => $nieuweNummer, 'leeftijdsklasse' => $validated['leeftijdsklasse'], 'gewichtsklasse' => $gewichtsklasse],
        ]);

        return response()->json([
            'success' => true,
            'message' => "Poule #{$nieuweNummer} aangemaakt",
            'poule' => $poule,
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
}
