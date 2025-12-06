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

        // Recalculate judoka code
        $judoka->update(['judoka_code' => $judoka->berekenJudokaCode()]);

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
}
