<?php

namespace App\Http\Controllers;

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
            ->orderBy('leeftijdsklasse')
            ->orderBy('naam')
            ->paginate(50);

        return view('pages.judoka.index', compact('toernooi', 'judokas'));
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

        $message = "Import voltooid: {$resultaat['geimporteerd']} geïmporteerd, {$resultaat['overgeslagen']} overgeslagen.";

        if (!empty($resultaat['fouten'])) {
            $message .= " " . count($resultaat['fouten']) . " fouten.";
        }

        return redirect()
            ->route('toernooi.judoka.index', $toernooi)
            ->with('success', $message);
    }

    public function zoek(Request $request, Toernooi $toernooi): JsonResponse
    {
        $zoekterm = $request->get('q', '');

        if (strlen($zoekterm) < 2) {
            return response()->json([]);
        }

        $judokas = $toernooi->judokas()
            ->where('naam', 'LIKE', "%{$zoekterm}%")
            ->with(['club', 'poules'])
            ->limit(20)
            ->get()
            ->map(fn($j) => [
                'id' => $j->id,
                'naam' => $j->naam,
                'club' => $j->club?->naam,
                'leeftijdsklasse' => $j->leeftijdsklasse,
                'gewichtsklasse' => $j->gewichtsklasse,
                'aanwezig' => $j->isAanwezig(),
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
            $naamNieuw = $this->formatNaam($naamOud);
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

    private function formatNaam(string $naam): string
    {
        // Dutch name prefixes that should stay lowercase
        $tussenvoegsels = ['van', 'de', 'den', 'der', 'het', 'ter', 'ten', 'te', 'op', 'in', "'t"];

        $delen = explode(' ', trim($naam));
        $result = [];

        foreach ($delen as $i => $deel) {
            $lower = mb_strtolower($deel);
            if (in_array($lower, $tussenvoegsels) && $i > 0) {
                $result[] = $lower;
            } else {
                $result[] = mb_convert_case($deel, MB_CASE_TITLE);
            }
        }

        return implode(' ', $result);
    }
}
