<?php

namespace App\Http\Controllers;

use App\Http\Requests\StamJudokaRequest;
use App\Models\Organisator;
use App\Models\StamJudoka;
use App\Services\ImportService;
use App\Services\StambestandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

    /**
     * Step 1: Upload file and show preview with column detection
     */
    public function importUpload(Request $request, Organisator $organisator): View
    {
        $this->authorizeAccess($organisator);

        $request->validate([
            'bestand' => 'required|file|mimes:csv,txt,xlsx,xls',
        ]);

        $file = $request->file('bestand');
        $data = Excel::toArray(null, $file)[0];

        $header = array_shift($data);

        $importService = app(ImportService::class);
        $analyse = $importService->analyseerCsvData($header, $data, false);

        // Store in session for step 2
        session(['stam_import_data' => $data, 'stam_import_header' => $header]);

        return view('organisator.stambestand.import-preview', [
            'organisator' => $organisator,
            'analyse' => $analyse,
        ]);
    }

    /**
     * Step 2: Confirm import with (adjusted) column mapping
     */
    public function importConfirm(Request $request, Organisator $organisator): RedirectResponse
    {
        $this->authorizeAccess($organisator);

        $mapping = $request->input('mapping', []);
        $data = session('stam_import_data');
        $header = session('stam_import_header');

        if (!$data || !$header) {
            return redirect()
                ->route('organisator.stambestand.index', $organisator)
                ->with('error', 'Geen import data gevonden. Upload opnieuw.');
        }

        $headerCount = count($header);

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

            // Pad row to match header length
            $row = array_pad($row, $headerCount, '');
            $rijNummer = $index + 2;

            try {
                // Get naam (supports multi-column via comma-separated indices)
                $naamRaw = $this->getMappedValue($row, $mapping['naam'] ?? '');
                if (empty($naamRaw)) continue;

                $naam = ImportService::normaliseerNaam(trim((string)$naamRaw));

                $geboortejaarRaw = $this->getMappedValue($row, $mapping['geboortejaar'] ?? '');
                if (empty($geboortejaarRaw)) {
                    $fouten[] = "Rij {$rijNummer} ({$naam}): Geboortejaar ontbreekt";
                    continue;
                }
                $geboortejaar = ImportService::parseGeboortejaar($geboortejaarRaw);

                // Check for duplicate
                $exists = StamJudoka::where('organisator_id', $organisator->id)
                    ->where('naam', $naam)
                    ->where('geboortejaar', $geboortejaar)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                $geslachtRaw = $this->getMappedValue($row, $mapping['geslacht'] ?? '');
                $geslacht = !empty($geslachtRaw) ? ImportService::parseGeslacht($geslachtRaw) : 'M';

                $bandRaw = $this->getMappedValue($row, $mapping['band'] ?? '');
                $band = !empty($bandRaw) ? ImportService::parseBand($bandRaw) : 'wit';

                $gewichtRaw = $this->getMappedValue($row, $mapping['gewicht'] ?? '');
                $gewicht = !empty($gewichtRaw) ? ImportService::parseGewicht($gewichtRaw) : null;

                $stamJudoka = StamJudoka::create([
                    'organisator_id' => $organisator->id,
                    'naam' => $naam,
                    'geboortejaar' => $geboortejaar,
                    'geslacht' => $geslacht,
                    'band' => $band,
                    'gewicht' => $gewicht,
                ]);

                $imported++;
            } catch (\Exception $e) {
                $fouten[] = "Rij {$rijNummer}: {$e->getMessage()}";
            }
        }

        session()->forget(['stam_import_data', 'stam_import_header']);

        $message = "Import voltooid: {$imported} geimporteerd";
        if ($skipped > 0) $message .= ", {$skipped} duplicaten overgeslagen";
        if (count($fouten) > 0) $message .= ", " . count($fouten) . " fouten";

        $redirect = redirect()->route('organisator.stambestand.index', $organisator)->with('success', $message);

        if (!empty($fouten)) {
            $redirect = $redirect->with('import_fouten', $fouten);
        }

        return $redirect;
    }

    /**
     * Get value from row using mapping index (supports comma-separated for multi-column).
     */
    private function getMappedValue(array $row, string $mappingValue): ?string
    {
        if ($mappingValue === '') return null;

        // Multi-column: comma-separated indices (e.g., "0,1,2" for voornaam+tussenvoegsel+achternaam)
        if (str_contains($mappingValue, ',')) {
            $indices = array_map('intval', explode(',', $mappingValue));
            $parts = [];
            foreach ($indices as $idx) {
                $val = $row[$idx] ?? null;
                if ($val !== null && trim((string)$val) !== '') {
                    $parts[] = trim((string)$val);
                }
            }
            return $parts ? implode(' ', $parts) : null;
        }

        // Single column index
        if (is_numeric($mappingValue)) {
            $val = $row[(int)$mappingValue] ?? null;
            return ($val !== null && trim((string)$val) !== '') ? trim((string)$val) : null;
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
