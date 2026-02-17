<?php

namespace App\Http\Controllers;

use App\Http\Requests\StamJudokaRequest;
use App\Models\Organisator;
use App\Models\StamJudoka;
use App\Services\ImportService;
use App\Services\StambestandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

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
            'csv_file' => 'required|file|mimes:csv,txt,xlsx,xls|max:2048',
        ]);

        $file = $request->file('csv_file');
        $data = Excel::toArray(null, $file)[0];

        if (count($data) < 2) {
            return response()->json(['error' => 'Bestand is leeg of heeft alleen een header.'], 422);
        }

        $header = array_shift($data);

        // Use ImportService column detection (same as tournament import)
        $importService = app(ImportService::class);
        $analyse = $importService->analyseerCsvData($header, $data, false);
        $detectie = $analyse['detectie'];

        // naam + geboortejaar are required
        if ($detectie['naam']['csv_index'] === null && $detectie['geboortejaar']['csv_index'] === null) {
            return response()->json([
                'error' => 'Bestand moet minimaal kolommen "naam" en "geboortejaar" bevatten.',
            ], 422);
        }

        $naamIdx = $detectie['naam']['csv_index'];
        $geboortejaarIdx = $detectie['geboortejaar']['csv_index'];
        $geslachtIdx = $detectie['geslacht']['csv_index'] ?? null;
        $bandIdx = $detectie['band']['csv_index'] ?? null;
        $gewichtIdx = $detectie['gewicht']['csv_index'] ?? null;

        $imported = 0;
        $skipped = 0;
        $fouten = [];

        foreach ($data as $index => $row) {
            // Skip empty rows
            $isEmpty = true;
            foreach ($row as $val) {
                if ($val !== null && trim((string)$val) !== '') { $isEmpty = false; break; }
            }
            if ($isEmpty) continue;

            $rijNummer = $index + 2;
            $naamRaw = $naamIdx !== null ? ($row[$naamIdx] ?? null) : null;

            if (empty($naamRaw) || trim((string)$naamRaw) === '') continue;

            try {
                $naam = ImportService::normaliseerNaam(trim((string)$naamRaw));

                $geboortejaar = null;
                if ($geboortejaarIdx !== null && !empty($row[$geboortejaarIdx])) {
                    $geboortejaar = ImportService::parseGeboortejaar($row[$geboortejaarIdx]);
                }

                if (!$geboortejaar) {
                    $fouten[] = "Rij {$rijNummer} ({$naam}): Geboortejaar ontbreekt of ongeldig";
                    continue;
                }

                // Check for duplicate
                $exists = StamJudoka::where('organisator_id', $organisator->id)
                    ->where('naam', $naam)
                    ->where('geboortejaar', $geboortejaar)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                $geslacht = ($geslachtIdx !== null && !empty($row[$geslachtIdx]))
                    ? ImportService::parseGeslacht($row[$geslachtIdx])
                    : 'M';

                $band = ($bandIdx !== null && !empty($row[$bandIdx]))
                    ? ImportService::parseBand($row[$bandIdx])
                    : 'wit';

                $gewicht = ($gewichtIdx !== null && !empty($row[$gewichtIdx]))
                    ? ImportService::parseGewicht($row[$gewichtIdx])
                    : null;

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
            } catch (\Exception $e) {
                $fouten[] = "Rij {$rijNummer} ({$naamRaw}): {$e->getMessage()}";
            }
        }

        $message = "{$imported} judoka's geimporteerd";
        if ($skipped > 0) $message .= ", {$skipped} overgeslagen (duplicaat)";
        if (count($fouten) > 0) $message .= ", " . count($fouten) . " fouten";

        return response()->json([
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped,
            'fouten' => $fouten,
            'message' => $message,
        ]);
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
