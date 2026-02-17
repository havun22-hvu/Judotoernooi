<?php

namespace App\Http\Controllers;

use App\Http\Requests\StamJudokaRequest;
use App\Models\Organisator;
use App\Models\StamJudoka;
use App\Services\StambestandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StamJudokaController extends Controller
{
    public function __construct(private StambestandService $stambestandService)
    {
    }

    public function index(Organisator $organisator): View
    {
        $this->authorizeAccess($organisator);

        $judokas = $organisator->stamJudokas()
            ->orderBy('naam')
            ->get();

        return view('organisator.stambestand.index', compact('organisator', 'judokas'));
    }

    public function store(StamJudokaRequest $request, Organisator $organisator): JsonResponse
    {
        $this->authorizeAccess($organisator);

        $stamJudoka = StamJudoka::create([
            'organisator_id' => $organisator->id,
            ...$request->validated(),
        ]);

        // Try to link existing wimpel-judoka
        $this->stambestandService->koppelWimpelJudoka($stamJudoka);

        return response()->json([
            'success' => true,
            'judoka' => $stamJudoka,
        ]);
    }

    public function update(StamJudokaRequest $request, Organisator $organisator, StamJudoka $stamJudoka): JsonResponse
    {
        $this->authorizeAccess($organisator);
        $this->authorizeJudoka($organisator, $stamJudoka);

        $stamJudoka->update($request->validated());

        return response()->json([
            'success' => true,
            'judoka' => $stamJudoka->fresh(),
        ]);
    }

    public function destroy(Organisator $organisator, StamJudoka $stamJudoka): JsonResponse
    {
        $this->authorizeAccess($organisator);
        $this->authorizeJudoka($organisator, $stamJudoka);

        $stamJudoka->delete();

        return response()->json(['success' => true]);
    }

    public function toggleActief(Organisator $organisator, StamJudoka $stamJudoka): JsonResponse
    {
        $this->authorizeAccess($organisator);
        $this->authorizeJudoka($organisator, $stamJudoka);

        $stamJudoka->update(['actief' => !$stamJudoka->actief]);

        return response()->json([
            'success' => true,
            'actief' => $stamJudoka->actief,
        ]);
    }

    public function importCsv(Request $request, Organisator $organisator): JsonResponse
    {
        $this->authorizeAccess($organisator);

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $file = $request->file('csv_file');
        $rows = array_map('str_getcsv', file($file->getRealPath()));

        if (count($rows) < 2) {
            return response()->json(['error' => 'CSV bestand is leeg of heeft alleen een header.'], 422);
        }

        $header = array_map('strtolower', array_map('trim', $rows[0]));
        $naamIdx = $this->findColumn($header, ['naam', 'name', 'judoka']);
        $geboortejaarIdx = $this->findColumn($header, ['geboortejaar', 'jaar', 'birth_year', 'geb.jaar']);
        $geslachtIdx = $this->findColumn($header, ['geslacht', 'gender', 'sex', 'm/v']);
        $bandIdx = $this->findColumn($header, ['band', 'belt', 'gordel']);
        $gewichtIdx = $this->findColumn($header, ['gewicht', 'weight', 'kg']);

        if ($naamIdx === null || $geboortejaarIdx === null) {
            return response()->json([
                'error' => 'CSV moet minimaal kolommen "naam" en "geboortejaar" bevatten.',
            ], 422);
        }

        $imported = 0;
        $skipped = 0;

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (count($row) < 2) continue;

            $naam = trim($row[$naamIdx] ?? '');
            $geboortejaar = (int) trim($row[$geboortejaarIdx] ?? '');

            if (empty($naam) || $geboortejaar < 1950) continue;

            // Check for duplicate
            $exists = StamJudoka::where('organisator_id', $organisator->id)
                ->where('naam', $naam)
                ->where('geboortejaar', $geboortejaar)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $geslacht = $geslachtIdx !== null ? strtoupper(trim($row[$geslachtIdx] ?? 'M')) : 'M';
            if (!in_array($geslacht, ['M', 'V'])) $geslacht = 'M';

            $band = $bandIdx !== null ? strtolower(trim($row[$bandIdx] ?? 'wit')) : 'wit';
            $validBands = ['wit', 'geel', 'oranje', 'groen', 'blauw', 'bruin', 'zwart'];
            if (!in_array($band, $validBands)) {
                $parsed = \App\Enums\Band::fromString($band);
                $band = $parsed ? strtolower($parsed->label()) : 'wit';
            }

            $gewicht = $gewichtIdx !== null ? floatval($row[$gewichtIdx] ?? 0) : null;
            if ($gewicht !== null && ($gewicht < 10 || $gewicht > 200)) $gewicht = null;

            $stamJudoka = StamJudoka::create([
                'organisator_id' => $organisator->id,
                'naam' => $naam,
                'geboortejaar' => $geboortejaar,
                'geslacht' => $geslacht,
                'band' => $band,
                'gewicht' => $gewicht,
            ]);

            $this->stambestandService->koppelWimpelJudoka($stamJudoka);
            $imported++;
        }

        return response()->json([
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped,
            'message' => "{$imported} judoka's geimporteerd" . ($skipped > 0 ? ", {$skipped} overgeslagen (duplicaat)" : ''),
        ]);
    }

    private function findColumn(array $header, array $aliases): ?int
    {
        foreach ($aliases as $alias) {
            $idx = array_search($alias, $header);
            if ($idx !== false) return $idx;
        }
        return null;
    }

    private function authorizeAccess(Organisator $organisator): void
    {
        $loggedIn = auth('organisator')->user();
        if ($loggedIn->id !== $organisator->id && !$loggedIn->isSitebeheerder()) {
            abort(403);
        }
    }

    private function authorizeJudoka(Organisator $organisator, StamJudoka $stamJudoka): void
    {
        if ($stamJudoka->organisator_id !== $organisator->id) {
            abort(403);
        }
    }
}
