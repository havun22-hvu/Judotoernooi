<?php

namespace App\Http\Controllers;

use App\Enums\Band;
use App\Http\Requests\JudokaStoreRequest;
use App\Http\Requests\JudokaUpdateRequest;
use App\Jobs\ImportJudokasJob;
use App\Mail\CorrectieVerzoekMail;
use App\Models\EmailLog;
use App\Models\Organisator;
use App\Models\Club;
use App\Models\Judoka;
use App\Models\Toernooi;
use App\Services\CategorieClassifier;
use App\Services\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class JudokaController extends Controller
{
    public function __construct(
        private ImportService $importService
    ) {}

    public function index(Organisator $organisator, Toernooi $toernooi): View
    {
        $alleJudokas = $toernooi->judokas()
            ->with('club')
            ->get();

        // Filter out judokas that don't fit in any category (from file import)
        // These are shown in the portal but not in the main list
        $judokas = $alleJudokas->filter(fn ($j) =>
            !empty($j->leeftijdsklasse) &&
            $j->leeftijdsklasse !== 'Onbekend' &&
            $j->import_status !== 'niet_in_categorie'
        );

        // Keep track of excluded judokas for warning display
        $nietInCategorie = $alleJudokas->filter(fn ($j) =>
            $j->import_status === 'niet_in_categorie' ||
            empty($j->leeftijdsklasse) ||
            $j->leeftijdsklasse === 'Onbekend'
        );

        // Sort by: age class (youngest first), weight class (lightest first), gender, name
        $judokas = $judokas->sortBy([
            fn ($a, $b) => $toernooi->getLeeftijdsklasseSortValue($a->leeftijdsklasse ?? '') <=> $toernooi->getLeeftijdsklasseSortValue($b->leeftijdsklasse ?? ''),
            fn ($a, $b) => $this->parseGewicht($a->gewichtsklasse) <=> $this->parseGewicht($b->gewichtsklasse),
            fn ($a, $b) => $a->geslacht <=> $b->geslacht,
            fn ($a, $b) => $a->naam <=> $b->naam,
        ]);

        // Build judokasPerKlasse with ALL categories from config (including empty ones)
        $gewichtsklassen = $toernooi->gewichtsklassen ?? [];
        $judokasPerKlasse = collect();

        // Add all configured categories (sorted by max_leeftijd)
        // Skip metadata keys (starting with _) and non-array entries
        foreach ($gewichtsklassen as $key => $config) {
            if (!is_array($config) || str_starts_with($key, '_')) {
                continue;
            }
            $label = !empty($config['label']) ? $config['label'] : ucfirst(str_replace('_', ' ', $key));
            $judokasPerKlasse[$label] = $judokas->filter(fn ($j) => $j->leeftijdsklasse === $label);
        }

        // Sort by config order (already sorted by max_leeftijd in gewichtsklassen)
        $judokasPerKlasse = $judokasPerKlasse->sortBy(fn ($group, $klasse) => $toernooi->getLeeftijdsklasseSortValue($klasse));

        // Get judokas with import warnings, grouped by club
        $importWarningsPerClub = $judokas
            ->filter(fn ($j) => !empty($j->import_warnings))
            ->groupBy(fn ($j) => $j->club?->naam ?? 'Geen club');

        return view('pages.judoka.index', compact('toernooi', 'judokas', 'judokasPerKlasse', 'importWarningsPerClub', 'nietInCategorie'));
    }

    /**
     * Parse weight class to numeric value for sorting
     * -50 = up to 50kg, +50 = over 50kg, so +50 should sort after -50
     */
    private function parseGewicht(string $gewichtsklasse): int
    {
        if (preg_match('/([+-]?)(\d+)/', $gewichtsklasse ?? '', $matches)) {
            $sign = $matches[1] ?? '';
            $num = (int) ($matches[2] ?? 999);
            return $sign === '+' ? $num + 1000 : $num;
        }
        return 999;
    }

    public function show(Organisator $organisator, Toernooi $toernooi, Judoka $judoka): View
    {
        $judoka->load(['club', 'poules.blok', 'poules.mat', 'wegingen']);

        return view('pages.judoka.show', compact('toernooi', 'judoka'));
    }

    public function edit(Organisator $organisator, Toernooi $toernooi, Judoka $judoka): View
    {
        return view('pages.judoka.edit', compact('toernooi', 'judoka'));
    }

    public function update(Organisator $organisator, JudokaUpdateRequest $request, Toernooi $toernooi, Judoka $judoka): RedirectResponse
    {
        $validated = $request->validated();

        // Free tier: naam cannot be changed after creation
        if ($toernooi->isFreeTier()) {
            unset($validated['naam']);
        }

        try {
            $judoka->update($validated);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Er bestaat al een judoka met deze naam en geboortejaar in dit toernooi.');
        }

        // Recalculate leeftijdsklasse from toernooi config (NOT hardcoded enum)
        $leeftijd = date('Y') - $judoka->geboortejaar;
        $nieuweLeeftijdsklasse = $toernooi->bepaalLeeftijdsklasse($leeftijd, $judoka->geslacht, $judoka->band);
        if ($nieuweLeeftijdsklasse) {
            $judoka->update(['leeftijdsklasse' => $nieuweLeeftijdsklasse]);
        }

        // Auto-calculate gewichtsklasse when gewicht is provided
        if (!empty($validated['gewicht'])) {
            $nieuweGewichtsklasse = $toernooi->bepaalGewichtsklasse($validated['gewicht'], $leeftijd, $judoka->geslacht, $judoka->band);
            if ($nieuweGewichtsklasse) {
                $judoka->update(['gewichtsklasse' => $nieuweGewichtsklasse]);
            }
        }

        // Re-check import warnings (e.g. gewicht was too low, now fixed)
        $judoka->hervalideerImportStatus();

        // Return to filtered list if came from filter
        $redirectRoute = $request->input('filter') === 'onvolledig'
            ? route('toernooi.judoka.index', $toernooi->routeParams()) . '?filter=onvolledig'
            : route('toernooi.judoka.show', $toernooi->routeParamsWith(['judoka' => $judoka]));

        return redirect($redirectRoute)->with('success', 'Judoka bijgewerkt');
    }

    public function store(Organisator $organisator, JudokaStoreRequest $request, Toernooi $toernooi): RedirectResponse
    {
        // Check freemium judoka limit
        if (!$toernooi->canAddMoreJudokas()) {
            return redirect()->route('toernooi.judoka.index', $toernooi->routeParams())
                ->with('error', 'Maximum aantal judoka\'s voor dit toernooi bereikt. Upgrade naar een betaald abonnement voor meer ruimte.');
        }

        $validated = $request->validated();

        // Calculate leeftijdsklasse and gewichtsklasse
        $leeftijdsklasse = null;
        $gewichtsklasse = null;

        if (!empty($validated['geboortejaar']) && !empty($validated['geslacht'])) {
            $toernooiJaar = $toernooi->datum ? $toernooi->datum->year : (int) date('Y');
            $leeftijd = $toernooiJaar - $validated['geboortejaar'];
            $leeftijdsklasse = $toernooi->bepaalLeeftijdsklasse($leeftijd, $validated['geslacht'], $validated['band'] ?? null);

            // Block if judoka doesn't fit in any category
            if (empty($leeftijdsklasse)) {
                $config = $toernooi->getAlleGewichtsklassen();
                $maxLeeftijd = 0;
                foreach ($config as $cat) {
                    $catMax = $cat['max_leeftijd'] ?? 99;
                    if ($catMax > $maxLeeftijd && $catMax < 99) $maxLeeftijd = $catMax;
                }

                $probleem = $leeftijd > $maxLeeftijd
                    ? "Te oud ({$leeftijd} jaar, max {$maxLeeftijd})"
                    : "Past niet in een categorie (leeftijd {$leeftijd})";

                return redirect()->back()
                    ->withInput()
                    ->with('error', "Judoka kan niet worden toegevoegd: {$probleem}");
            }

            if (!empty($validated['gewicht'])) {
                $gewichtsklasse = $toernooi->bepaalGewichtsklasse($validated['gewicht'], $leeftijd, $validated['geslacht'], $validated['band'] ?? null);
            }
        }

        // Fallback gewichtsklasse
        if (empty($gewichtsklasse) && !empty($validated['gewicht'])) {
            $gewichtsklasse = '-' . (int) $validated['gewicht'];
        }

        $judoka = Judoka::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $validated['club_id'] ?? null,
            'naam' => $validated['naam'],
            'geboortejaar' => $validated['geboortejaar'] ?? null,
            'geslacht' => $validated['geslacht'] ?? null,
            'band' => $validated['band'] ?? null,
            'gewicht' => $validated['gewicht'] ?? null,
            'leeftijdsklasse' => $leeftijdsklasse,
            'gewichtsklasse' => $gewichtsklasse ?? 'Onbekend',
        ]);

        return redirect()
            ->route('toernooi.judoka.index', $toernooi->routeParams())
            ->with('success', 'Judoka toegevoegd');
    }

    public function destroy(Organisator $organisator, Toernooi $toernooi, Judoka $judoka): RedirectResponse
    {
        // Free tier: judokas cannot be deleted
        if ($toernooi->isFreeTier()) {
            return redirect()
                ->route('toernooi.judoka.index', $toernooi->routeParams())
                ->with('error', 'In de gratis versie kunnen judoka\'s niet verwijderd worden.');
        }

        $judoka->delete();

        return redirect()
            ->route('toernooi.judoka.index', $toernooi->routeParams())
            ->with('success', 'Judoka verwijderd');
    }

    public function importForm(Organisator $organisator, Toernooi $toernooi): View
    {
        return view('pages.judoka.import', compact('toernooi'));
    }

    /**
     * Step 1: Upload file and show preview with column detection
     */
    public function import(Organisator $organisator, Request $request, Toernooi $toernooi): View
    {
        $request->validate([
            'bestand' => 'required|file|mimes:csv,txt,xlsx,xls',
        ]);

        $file = $request->file('bestand');
        $data = Excel::toArray(null, $file)[0];

        // Split header and data
        $header = array_shift($data);

        // Bepaal of toernooi vaste gewichtsklassen heeft (minstens 1 categorie met gevulde gewichten array)
        // Filter metadata keys (beginnen met _) - alleen echte categorieën tellen mee
        $config = $toernooi->gewichtsklassen ?? [];
        $heeftVasteGewichtsklassen = collect($config)
            ->filter(fn($cat, $key) => !str_starts_with($key, '_') && is_array($cat))
            ->contains(fn($cat) => !empty($cat['gewichten'] ?? []));

        // Analyse columns (alleen gewichtsklasse detecteren als toernooi vaste gewichtsklassen heeft)
        $analyse = $this->importService->analyseerCsvData($header, $data, $heeftVasteGewichtsklassen);

        // Store in session for step 2
        session(['import_data' => $data, 'import_header' => $header]);

        return view('pages.judoka.import-preview', [
            'toernooi' => $toernooi,
            'analyse' => $analyse,
        ]);
    }

    /**
     * Step 2: Confirm import with (adjusted) column mapping
     */
    public function importConfirm(Organisator $organisator, Request $request, Toernooi $toernooi): RedirectResponse
    {
        $mapping = $request->input('mapping', []);

        // Get data from session
        $data = session('import_data');
        $header = session('import_header');

        if (!$data || !$header) {
            return redirect()
                ->route('toernooi.judoka.import', $toernooi->routeParams())
                ->with('error', 'Geen import data gevonden. Upload opnieuw.');
        }

        // Check freemium judoka limit
        $aantalTeImporteren = count($data);
        if (!$toernooi->canAddMoreJudokas($aantalTeImporteren)) {
            $remaining = $toernooi->getRemainingJudokaSlots();
            return redirect()
                ->route('toernooi.judoka.import', $toernooi->routeParams())
                ->with('error', "Je probeert {$aantalTeImporteren} judoka's te importeren, maar er is alleen ruimte voor {$remaining}. Upgrade naar een betaald abonnement voor meer ruimte.");
        }

        // Build column mapping: field name => header column name OR numeric indices for multi-column
        $kolomMapping = [];
        foreach ($mapping as $veld => $kolomIndex) {
            if ($kolomIndex !== null && $kolomIndex !== '') {
                // Multi-column: keep as comma-separated indices (e.g., "0,1,2" for naam)
                if (str_contains((string)$kolomIndex, ',')) {
                    $kolomMapping[$veld] = $kolomIndex; // Pass indices directly
                } elseif (isset($header[$kolomIndex])) {
                    $kolomMapping[$veld] = $header[$kolomIndex];
                }
            }
        }

        // Convert to associative array (pad rows to match header length)
        // Also keep numeric indices for multi-column fields
        $headerCount = count($header);
        $rows = array_map(function($row) use ($header, $headerCount) {
            // Pad row with empty values if shorter than header
            $row = array_pad($row, $headerCount, '');
            // Truncate if longer than header
            $row = array_slice($row, 0, $headerCount);
            // Combine with headers BUT also keep numeric indices
            $assoc = array_combine($header, $row);
            // Merge: numeric indices + named keys (numeric first for multi-column access)
            return $row + $assoc;
        }, $data);

        $resultaat = $this->importService->importeerDeelnemers($toernooi, $rows, $kolomMapping);

        // Clear session data
        session()->forget(['import_data', 'import_header']);

        $message = "Import voltooid: {$resultaat['geimporteerd']} geïmporteerd";
        if ($resultaat['overgeslagen'] > 0) {
            $message .= ", {$resultaat['overgeslagen']} duplicaten bijgewerkt";
        }

        if (!empty($resultaat['fouten'])) {
            $message .= ", " . count($resultaat['fouten']) . " fouten";
        }
        $message .= ".";

        // Check for uncategorized judokas
        $nietGecategoriseerd = $toernooi->countNietGecategoriseerd();

        $redirect = redirect()->route('toernooi.judoka.index', $toernooi->routeParams())->with('success', $message);

        // Store import errors in session for display
        if (!empty($resultaat['fouten'])) {
            $redirect = $redirect->with('import_fouten', $resultaat['fouten']);
        }

        if ($nietGecategoriseerd > 0) {
            $redirect = $redirect->with('warning', "⚠️ {$nietGecategoriseerd} judoka('s) niet gecategoriseerd! Pas de categorie-instellingen aan.");
        }

        $zonderClub = $resultaat['zonder_club'] ?? 0;
        if ($zonderClub > 0) {
            $redirect = $redirect->with('zonder_club', "{$zonderClub} judoka('s) zonder club geïmporteerd. Je kunt de club alsnog invullen via de deelnemerslijst.");
        }

        // Send correction emails to clubs with judokas that need correction
        $correctieMailsVerstuurd = $this->verstuurCorrectieMails($toernooi);
        if ($correctieMailsVerstuurd > 0) {
            $redirect = $redirect->with('correctie_mails', "{$correctieMailsVerstuurd} correctie email(s) verstuurd naar clubs.");
        }

        return $redirect;
    }

    /**
     * Check import progress for async imports.
     */
    public function importProgress(Request $request): JsonResponse
    {
        $importId = $request->input('import_id');

        if (!$importId) {
            return response()->json(['error' => 'Import ID required'], 400);
        }

        $progress = ImportJudokasJob::getProgress($importId);

        if (!$progress) {
            return response()->json(['error' => 'Import not found'], 404);
        }

        return response()->json($progress);
    }

    /**
     * Send correction emails to clubs with judokas marked as 'te_corrigeren'
     */
    private function verstuurCorrectieMails(Toernooi $toernooi): int
    {
        // Get judokas that need correction, grouped by club
        $judokasTeCorrigeren = Judoka::where('toernooi_id', $toernooi->id)
            ->where('import_status', 'te_corrigeren')
            ->whereNotNull('club_id')
            ->with('club')
            ->get()
            ->groupBy('club_id');

        $verstuurd = 0;

        foreach ($judokasTeCorrigeren as $clubId => $judokas) {
            $club = Club::find($clubId);

            if (!$club || !$club->email) {
                continue;
            }

            $recipients = array_filter([$club->email, $club->email2]);
            $subject = "Correctie vereist - {$toernooi->naam}";
            $judokaCount = $judokas->count();

            try {
                Mail::to($recipients)->send(new CorrectieVerzoekMail($toernooi, $club, $judokas));

                EmailLog::logSent(
                    $toernooi->id,
                    'correctie',
                    $recipients,
                    $subject,
                    "{$judokaCount} judoka(s) vereisen correctie voor {$club->naam}",
                    $club->id
                );

                $verstuurd++;
            } catch (\Exception $e) {
                EmailLog::logFailed(
                    $toernooi->id,
                    'correctie',
                    $recipients,
                    $subject,
                    $e->getMessage(),
                    $club->id
                );

                \Log::error("Failed to send correction email to club {$club->naam}: " . $e->getMessage());
            }
        }

        return $verstuurd;
    }

    /**
     * API endpoint for inline judoka updates
     */
    public function updateApi(Organisator $organisator, Request $request, Toernooi $toernooi, Judoka $judoka): JsonResponse
    {
        $validated = $request->validate([
            'naam' => 'sometimes|string|max:255',
            'gewichtsklasse' => 'sometimes|nullable|string|max:20',
            'geslacht' => 'sometimes|in:M,V',
            'band' => 'sometimes|nullable|string|max:20',
            'gewicht' => 'sometimes|nullable|numeric|min:10|max:200',
            'geboortejaar' => 'sometimes|integer|min:1900|max:' . date('Y'),
        ]);

        // Free tier: naam cannot be changed after creation
        if ($toernooi->isFreeTier()) {
            unset($validated['naam']);
        }

        $judoka->update($validated);

        // Recalculate leeftijdsklasse if geboortejaar or geslacht changed (from toernooi config)
        if (isset($validated['geboortejaar']) || isset($validated['geslacht'])) {
            $leeftijd = date('Y') - $judoka->geboortejaar;
            $nieuweLeeftijdsklasse = $toernooi->bepaalLeeftijdsklasse($leeftijd, $judoka->geslacht, $judoka->band);
            if ($nieuweLeeftijdsklasse) {
                $judoka->update(['leeftijdsklasse' => $nieuweLeeftijdsklasse]);
            }
        }

        // Auto-calculate gewichtsklasse when gewicht is provided (from toernooi config)
        if (isset($validated['gewicht']) && $validated['gewicht']) {
            $leeftijd = date('Y') - $judoka->geboortejaar;
            $nieuweGewichtsklasse = $toernooi->bepaalGewichtsklasse($validated['gewicht'], $leeftijd, $judoka->geslacht, $judoka->band);
            if ($nieuweGewichtsklasse) {
                $judoka->update(['gewichtsklasse' => $nieuweGewichtsklasse]);
            }
        }

        // Re-check import warnings (e.g. gewicht was too low, now fixed)
        $judoka->hervalideerImportStatus();

        return response()->json([
            'success' => true,
            'judoka' => [
                'id' => $judoka->id,
                'naam' => $judoka->naam,
                'leeftijdsklasse' => $judoka->leeftijdsklasse,
                'gewichtsklasse' => $judoka->gewichtsklasse,
                'geslacht' => $judoka->geslacht,
                'band' => $judoka->band,
                'gewicht' => $judoka->gewicht,
            ]
        ]);
    }

    public function zoek(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        $zoekterm = $request->get('q', '');
        $blokFilter = $request->get('blok');

        if (strlen($zoekterm) < 2) {
            return response()->json([]);
        }

        $query = $toernooi->judokas()
            ->where(function ($q) use ($zoekterm) {
                $q->where('naam', 'LIKE', "%{$zoekterm}%")
                  ->orWhereHas('club', fn($q) => $q->where('naam', 'LIKE', "%{$zoekterm}%"));
            })
            ->with(['club', 'poules.blok']);

        // Filter by blok if specified
        if ($blokFilter) {
            $query->whereHas('poules.blok', fn($q) => $q->where('nummer', $blokFilter));
        }

        $judokas = $query->orderBy('naam')
            ->limit(30)
            ->get()
            ->map(fn($j) => [
                'id' => $j->id,
                'naam' => $j->naam,
                'club' => $j->club?->naam,
                'leeftijdsklasse' => $j->leeftijdsklasse,
                'gewichtsklasse' => $j->gewichtsklasse,
                'band' => Band::toKleur($j->band) ?: null,
                'aanwezig' => $j->isAanwezig(),
                'gewogen' => $j->gewicht_gewogen > 0,
                'gewicht_gewogen' => $j->gewicht_gewogen,
                'blok' => $j->poules->first()?->blok?->nummer,
                'aantal_wegingen' => $j->wegingen()->count(),
            ]);

        return response()->json($judokas);
    }

    public function valideer(Organisator $organisator, Toernooi $toernooi): RedirectResponse
    {
        $result = $this->voerValidatieUit($organisator, $toernooi);

        $message = "Validatie voltooid: {$result['gecorrigeerd']} judoka's gecorrigeerd.";
        if (!empty($result['fouten'])) {
            $message .= " " . count($result['fouten']) . " met ontbrekende gegevens.";
            session()->flash('validatie_fouten', $result['fouten']);
        }

        return redirect()
            ->route('toernooi.judoka.index', $toernooi->routeParams())
            ->with('success', $message);
    }

    /**
     * Voer validatie uit zonder redirect (voor gebruik vanuit andere controllers)
     */
    public function voerValidatieUit(Organisator $organisator, Toernooi $toernooi): array
    {
        $judokas = $toernooi->judokas()->get();
        $gecorrigeerd = 0;
        $fouten = [];

        // Use CategorieClassifier for correct key + label + sortCategorie
        $config = $toernooi->getAlleGewichtsklassen();
        $classifier = new CategorieClassifier($config, $toernooi->gewicht_tolerantie ?? 0.5);
        $toernooiJaar = $toernooi->datum?->year ?? (int) date('Y');

        // First pass: correct names and check required fields
        foreach ($judokas as $judoka) {
            $wijzigingen = [];

            // Correct name capitalization (Jan de Vries, Anna van den Berg)
            $naamOud = $judoka->naam;
            $naamNieuw = Judoka::formatNaam($naamOud);
            if ($naamOud !== $naamNieuw) {
                $wijzigingen['naam'] = $naamNieuw;
            }

            // Normalize band to lowercase base value (Geel (5e kyu) → geel, GROEN → groen)
            if (!empty($judoka->band)) {
                $bandEnum = Band::fromString($judoka->band);
                if ($bandEnum) {
                    $bandNieuw = strtolower($bandEnum->value);
                    if ($judoka->band !== $bandNieuw) {
                        $wijzigingen['band'] = $bandNieuw;
                    }
                }
            }

            // Recalculate classification using CategorieClassifier (key + label + sortCategorie)
            if (!empty($judoka->geboortejaar) && !empty($judoka->geslacht)) {
                $classificatie = $classifier->classificeer($judoka, $toernooiJaar);

                $nieuweLeeftijdsklasse = $classificatie['label'];
                $nieuweCategorieKey = $classificatie['key'];
                $nieuweSortCategorie = $classificatie['sortCategorie'];

                if ($nieuweLeeftijdsklasse && $nieuweLeeftijdsklasse !== $judoka->leeftijdsklasse) {
                    $wijzigingen['leeftijdsklasse'] = $nieuweLeeftijdsklasse;
                }
                if ($nieuweCategorieKey !== $judoka->categorie_key) {
                    $wijzigingen['categorie_key'] = $nieuweCategorieKey;
                }
                if ($nieuweSortCategorie !== $judoka->sort_categorie) {
                    $wijzigingen['sort_categorie'] = $nieuweSortCategorie;
                }

                // Update gewichtsklasse if classifier found one
                $nieuweGewichtsklasse = $classificatie['gewichtsklasse'];
                if ($nieuweGewichtsklasse !== null && $nieuweGewichtsklasse !== $judoka->gewichtsklasse) {
                    $wijzigingen['gewichtsklasse'] = $nieuweGewichtsklasse;
                }

                // Update import_status if judoka now fits in a category
                $huidigeLeeftijdsklasse = $wijzigingen['leeftijdsklasse'] ?? $judoka->leeftijdsklasse;
                if ($huidigeLeeftijdsklasse && $huidigeLeeftijdsklasse !== 'Onbekend' && $judoka->import_status === 'niet_in_categorie') {
                    $wijzigingen['import_status'] = 'compleet';
                    $wijzigingen['import_warnings'] = null;
                }
                // Also mark as niet_in_categorie if judoka no longer fits
                if ((!$huidigeLeeftijdsklasse || $huidigeLeeftijdsklasse === 'Onbekend') && $judoka->import_status !== 'niet_in_categorie') {
                    $wijzigingen['import_status'] = 'niet_in_categorie';
                }
            }

            // Check required fields
            $ontbreekt = [];
            if (empty($judoka->naam)) $ontbreekt[] = 'naam';
            if (empty($judoka->geboortejaar)) $ontbreekt[] = 'geboortejaar';
            if (empty($judoka->geslacht)) $ontbreekt[] = 'geslacht';
            if (empty($judoka->band)) $ontbreekt[] = 'band';

            if (!empty($ontbreekt)) {
                $fouten[] = "{$judoka->naam}: ontbreekt " . implode(', ', $ontbreekt);
            }

            // Apply name changes
            if (!empty($wijzigingen)) {
                $judoka->update($wijzigingen);
                $gecorrigeerd++;
            }
        }

        return [
            'gecorrigeerd' => $gecorrigeerd,
            'fouten' => $fouten,
        ];
    }

}
