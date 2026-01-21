<?php

namespace App\Http\Controllers;

use App\Enums\Band;
use App\Models\Judoka;
use App\Models\Toernooi;
use App\Services\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class JudokaController extends Controller
{
    public function __construct(
        private ImportService $importService
    ) {}

    public function index(Toernooi $toernooi): View
    {
        $judokas = $toernooi->judokas()
            ->with('club')
            ->get();

        // Sort by: age class (youngest first), weight class (lightest first), gender, name
        $judokas = $judokas->sortBy([
            fn ($a, $b) => $toernooi->getLeeftijdsklasseSortValue($a->leeftijdsklasse ?? '') <=> $toernooi->getLeeftijdsklasseSortValue($b->leeftijdsklasse ?? ''),
            fn ($a, $b) => $this->parseGewicht($a->gewichtsklasse) <=> $this->parseGewicht($b->gewichtsklasse),
            fn ($a, $b) => $a->geslacht <=> $b->geslacht,
            fn ($a, $b) => $a->naam <=> $b->naam,
        ]);

        // Group by leeftijdsklasse and sort groups (youngest first using config)
        $judokasPerKlasse = $judokas->groupBy('leeftijdsklasse')
            ->sortBy(fn ($group, $klasse) => $toernooi->getLeeftijdsklasseSortValue($klasse));

        return view('pages.judoka.index', compact('toernooi', 'judokas', 'judokasPerKlasse'));
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

    public function show(Toernooi $toernooi, Judoka $judoka): View
    {
        $judoka->load(['club', 'poules.blok', 'poules.mat', 'wegingen']);

        return view('pages.judoka.show', compact('toernooi', 'judoka'));
    }

    public function edit(Toernooi $toernooi, Judoka $judoka): View
    {
        return view('pages.judoka.edit', compact('toernooi', 'judoka'));
    }

    public function update(Request $request, Toernooi $toernooi, Judoka $judoka): RedirectResponse
    {
        $validated = $request->validate([
            'naam' => 'required|string|max:255',
            'geboortejaar' => 'required|integer|min:1900|max:' . date('Y'),
            'geslacht' => 'required|in:M,V',
            'band' => 'required|string|max:20',
            'gewicht' => 'nullable|numeric|min:10|max:200',
        ]);

        $judoka->update($validated);

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

        return redirect()
            ->route('toernooi.judoka.show', [$toernooi, $judoka])
            ->with('success', 'Judoka bijgewerkt');
    }

    public function destroy(Toernooi $toernooi, Judoka $judoka): RedirectResponse
    {
        $judoka->delete();

        return redirect()
            ->route('toernooi.judoka.index', $toernooi)
            ->with('success', 'Judoka verwijderd');
    }

    public function importForm(Toernooi $toernooi): View
    {
        return view('pages.judoka.import', compact('toernooi'));
    }

    /**
     * Step 1: Upload file and show preview with column detection
     */
    public function import(Request $request, Toernooi $toernooi): View
    {
        $request->validate([
            'bestand' => 'required|file|mimes:csv,txt,xlsx,xls',
        ]);

        $file = $request->file('bestand');
        $data = Excel::toArray(null, $file)[0];

        // Split header and data
        $header = array_shift($data);

        // Analyse columns
        $analyse = $this->importService->analyseerCsvData($header, $data);

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
    public function importConfirm(Request $request, Toernooi $toernooi): RedirectResponse
    {
        $mapping = $request->input('mapping', []);

        // Get data from session
        $data = session('import_data');
        $header = session('import_header');

        if (!$data || !$header) {
            return redirect()
                ->route('toernooi.judoka.import', $toernooi)
                ->with('error', 'Geen import data gevonden. Upload opnieuw.');
        }

        // Build column mapping: field name => header column name
        $kolomMapping = [];
        foreach ($mapping as $veld => $kolomIndex) {
            if ($kolomIndex !== null && $kolomIndex !== '' && isset($header[$kolomIndex])) {
                $kolomMapping[$veld] = $header[$kolomIndex];
            }
        }

        // Convert to associative array (pad rows to match header length)
        $headerCount = count($header);
        $rows = array_map(function($row) use ($header, $headerCount) {
            // Pad row with empty values if shorter than header
            $row = array_pad($row, $headerCount, '');
            // Truncate if longer than header
            $row = array_slice($row, 0, $headerCount);
            return array_combine($header, $row);
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

        $redirect = redirect()->route('toernooi.judoka.index', $toernooi)->with('success', $message);

        // Store import errors in session for display
        if (!empty($resultaat['fouten'])) {
            $redirect = $redirect->with('import_fouten', $resultaat['fouten']);
        }

        if ($nietGecategoriseerd > 0) {
            $redirect = $redirect->with('warning', "⚠️ {$nietGecategoriseerd} judoka('s) niet gecategoriseerd! Pas de categorie-instellingen aan.");
        }

        return $redirect;
    }

    /**
     * API endpoint for inline judoka updates
     */
    public function updateApi(Request $request, Toernooi $toernooi, Judoka $judoka): JsonResponse
    {
        $validated = $request->validate([
            'naam' => 'sometimes|string|max:255',
            'gewichtsklasse' => 'sometimes|nullable|string|max:20',
            'geslacht' => 'sometimes|in:M,V',
            'band' => 'sometimes|nullable|string|max:20',
            'gewicht' => 'sometimes|nullable|numeric|min:10|max:200',
            'geboortejaar' => 'sometimes|integer|min:1900|max:' . date('Y'),
        ]);

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

    public function zoek(Request $request, Toernooi $toernooi): JsonResponse
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
                'band' => ucfirst($j->band),
                'aanwezig' => $j->isAanwezig(),
                'gewogen' => $j->gewicht_gewogen !== null,
                'gewicht_gewogen' => $j->gewicht_gewogen,
                'blok' => $j->poules->first()?->blok?->nummer,
                'aantal_wegingen' => $j->wegingen()->count(),
            ]);

        return response()->json($judokas);
    }

    public function valideer(Toernooi $toernooi): RedirectResponse
    {
        $judokas = $toernooi->judokas()->get();
        $gecorrigeerd = 0;
        $fouten = [];

        // First pass: correct names and check required fields
        foreach ($judokas as $judoka) {
            $wijzigingen = [];

            // Correct name capitalization (Jan de Vries, Anna van den Berg)
            $naamOud = $judoka->naam;
            $naamNieuw = Judoka::formatNaam($naamOud);
            if ($naamOud !== $naamNieuw) {
                $wijzigingen['naam'] = $naamNieuw;
            }

            // Correct band to kyu notation (groen → Groen (3e kyu))
            if (!empty($judoka->band)) {
                $bandEnum = Band::fromString($judoka->band);
                if ($bandEnum) {
                    $bandNieuw = $bandEnum->labelMetKyu();
                    if ($judoka->band !== $bandNieuw) {
                        $wijzigingen['band'] = $bandNieuw;
                    }
                }
            }

            // Recalculate leeftijdsklasse from toernooi config
            if (!empty($judoka->geboortejaar) && !empty($judoka->geslacht)) {
                $leeftijd = date('Y') - $judoka->geboortejaar;
                $nieuweLeeftijdsklasse = $toernooi->bepaalLeeftijdsklasse($leeftijd, $judoka->geslacht, $wijzigingen['band'] ?? $judoka->band);
                if ($nieuweLeeftijdsklasse && $nieuweLeeftijdsklasse !== $judoka->leeftijdsklasse) {
                    $wijzigingen['leeftijdsklasse'] = $nieuweLeeftijdsklasse;
                }

                // Recalculate gewichtsklasse from toernooi config (only for fixed weight classes)
                if (!empty($judoka->gewicht) && $judoka->gewicht > 0) {
                    $nieuweGewichtsklasse = $toernooi->bepaalGewichtsklasse(
                        $judoka->gewicht,
                        $leeftijd,
                        $judoka->geslacht,
                        $wijzigingen['band'] ?? $judoka->band
                    );
                    // Update if changed (null means dynamic category - keep existing or set null)
                    if ($nieuweGewichtsklasse !== $judoka->gewichtsklasse) {
                        $wijzigingen['gewichtsklasse'] = $nieuweGewichtsklasse;
                    }
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

        $message = "Validatie voltooid: {$gecorrigeerd} judoka's gecorrigeerd.";
        if (!empty($fouten)) {
            $message .= " " . count($fouten) . " met ontbrekende gegevens.";
            session()->flash('validatie_fouten', $fouten);
        }

        return redirect()
            ->route('toernooi.judoka.index', $toernooi)
            ->with('success', $message);
    }

}
