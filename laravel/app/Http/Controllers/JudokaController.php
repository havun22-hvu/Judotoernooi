<?php

namespace App\Http\Controllers;

use App\Enums\Leeftijdsklasse;
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
        // Build age class order from tournament config (youngest first)
        $gewichtsklassenConfig = $toernooi->getAlleGewichtsklassen();
        $leeftijdsklasseVolgorde = [];
        $index = 0;
        foreach ($gewichtsklassenConfig as $key => $config) {
            $label = $config['label'] ?? $key;
            $leeftijdsklasseVolgorde[$label] = $index++;
        }

        $judokas = $toernooi->judokas()
            ->with('club')
            ->get();

        // Sort by: age class (youngest first), weight class (lightest first), gender, name
        $judokas = $judokas->sortBy([
            fn ($a, $b) => ($leeftijdsklasseVolgorde[$a->leeftijdsklasse] ?? 99) <=> ($leeftijdsklasseVolgorde[$b->leeftijdsklasse] ?? 99),
            fn ($a, $b) => $this->parseGewicht($a->gewichtsklasse) <=> $this->parseGewicht($b->gewichtsklasse),
            fn ($a, $b) => $a->geslacht <=> $b->geslacht,
            fn ($a, $b) => $a->naam <=> $b->naam,
        ]);

        // Group by leeftijdsklasse and sort groups by config order
        $judokasPerKlasse = $judokas->groupBy('leeftijdsklasse')
            ->sortBy(fn ($group, $klasse) => $leeftijdsklasseVolgorde[$klasse] ?? 999);

        return view('pages.judoka.index', compact('toernooi', 'judokas', 'judokasPerKlasse', 'leeftijdsklasseVolgorde'));
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

        // Recalculate leeftijdsklasse
        $leeftijd = date('Y') - $judoka->geboortejaar;
        $leeftijdsklasseEnum = Leeftijdsklasse::fromLeeftijdEnGeslacht($leeftijd, $judoka->geslacht);
        $judoka->update(['leeftijdsklasse' => $leeftijdsklasseEnum->label()]);

        // Auto-calculate gewichtsklasse when gewicht is provided
        if (!empty($validated['gewicht'])) {
            $tolerantie = $toernooi->gewicht_tolerantie ?? 0.5;
            $nieuweGewichtsklasse = $this->bepaalGewichtsklasse($validated['gewicht'], $leeftijdsklasseEnum, $tolerantie);
            $judoka->update(['gewichtsklasse' => $nieuweGewichtsklasse]);
        }

        // Recalculate judoka code (keep existing volgnummer if possible)
        $bestaandeCode = $judoka->judoka_code;
        $volgnummer = 1;
        if ($bestaandeCode && strlen($bestaandeCode) >= 8) {
            $volgnummer = intval(substr($bestaandeCode, -2)) ?: 1;
        }
        $judoka->update(['judoka_code' => $judoka->berekenJudokaCode($volgnummer)]);

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

    public function import(Request $request, Toernooi $toernooi): RedirectResponse
    {
        $request->validate([
            'bestand' => 'required|file|mimes:csv,xlsx,xls',
        ]);

        $file = $request->file('bestand');
        $data = Excel::toArray(null, $file)[0];

        // Skip header row
        $header = array_shift($data);

        // Convert to associative array
        $rows = array_map(fn($row) => array_combine($header, $row), $data);

        $resultaat = $this->importService->importeerDeelnemers($toernooi, $rows);

        $message = "Import voltooid: {$resultaat['geimporteerd']} geïmporteerd";
        if ($resultaat['overgeslagen'] > 0) {
            $message .= ", {$resultaat['overgeslagen']} duplicaten bijgewerkt";
        }

        if (!empty($resultaat['fouten'])) {
            $message .= ", " . count($resultaat['fouten']) . " fouten";
        }
        $message .= ".";

        return redirect()
            ->route('toernooi.judoka.index', $toernooi)
            ->with('success', $message);
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

        // Recalculate leeftijdsklasse if geboortejaar or geslacht changed
        if (isset($validated['geboortejaar']) || isset($validated['geslacht'])) {
            $leeftijd = date('Y') - $judoka->geboortejaar;
            $leeftijdsklasse = Leeftijdsklasse::fromLeeftijdEnGeslacht($leeftijd, $judoka->geslacht);
            $judoka->update(['leeftijdsklasse' => $leeftijdsklasse->label()]);
        }

        // Auto-calculate gewichtsklasse when gewicht is provided
        // Weight takes precedence over manually set gewichtsklasse
        if (isset($validated['gewicht']) && $validated['gewicht']) {
            $leeftijd = date('Y') - $judoka->geboortejaar;
            $leeftijdsklasseEnum = Leeftijdsklasse::fromLeeftijdEnGeslacht($leeftijd, $judoka->geslacht);
            $tolerantie = $toernooi->gewicht_tolerantie ?? 0.5;
            $nieuweGewichtsklasse = $this->bepaalGewichtsklasse($validated['gewicht'], $leeftijdsklasseEnum, $tolerantie);
            $judoka->update(['gewichtsklasse' => $nieuweGewichtsklasse]);
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

    /**
     * Determine weight class based on weight, age category and tolerance
     */
    private function bepaalGewichtsklasse(float $gewicht, Leeftijdsklasse $leeftijdsklasse, float $tolerantie = 0.5): string
    {
        $klassen = $leeftijdsklasse->gewichtsklassen();

        foreach ($klassen as $klasse) {
            if ($klasse > 0) {
                // Plus category (minimum weight) - last one
                return "+{$klasse}";
            } else {
                // Minus category: limit + tolerance
                $limiet = abs($klasse);
                if ($gewicht <= $limiet + $tolerantie) {
                    return "{$klasse}";
                }
            }
        }

        // Fallback to plus category
        $laatsteKlasse = end($klassen);
        return $laatsteKlasse > 0 ? "+{$laatsteKlasse}" : "+" . abs($laatsteKlasse);
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

        // Second pass: generate judoka codes with volgnummers per category
        // Format: LLGGBGVV (Leeftijd-Gewicht-Band-Geslacht-Volgnummer)
        $categorieVolgnummers = [];

        // Sort judokas by name for consistent numbering
        $judokas = $judokas->sortBy('naam');

        foreach ($judokas as $judoka) {
            if (!$judoka->leeftijdsklasse || !$judoka->gewichtsklasse) {
                continue;
            }

            // Get base code (without volgnummer)
            $basisCode = $judoka->berekenBasisCode();

            // Track volgnummer per category
            if (!isset($categorieVolgnummers[$basisCode])) {
                $categorieVolgnummers[$basisCode] = 0;
            }
            $categorieVolgnummers[$basisCode]++;
            $volgnummer = $categorieVolgnummers[$basisCode];

            // Generate full code with volgnummer
            $nieuweCode = $judoka->berekenJudokaCode($volgnummer);

            if ($judoka->judoka_code !== $nieuweCode) {
                $judoka->update(['judoka_code' => $nieuweCode]);
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
