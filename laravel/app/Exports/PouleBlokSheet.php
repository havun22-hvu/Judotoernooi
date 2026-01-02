<?php

namespace App\Exports;

use App\Models\Blok;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PouleBlokSheet implements FromArray, WithTitle, WithStyles
{
    protected ?array $cachedData = null;
    protected array $matRows = [];
    protected array $pouleRows = [];
    protected array $headerRows = [];
    protected array $warningRows = [];

    public function __construct(
        protected Blok $blok
    ) {}

    public function title(): string
    {
        return "Blok {$this->blok->nummer}";
    }

    /**
     * Build and cache the data array
     */
    protected function buildData(): array
    {
        if ($this->cachedData !== null) {
            return $this->cachedData;
        }

        $this->matRows = [];
        $this->pouleRows = [];
        $this->headerRows = [];
        $this->warningRows = [];

        $rows = [];
        $rowNum = 1;
        $currentMat = null;

        // Group poules by mat
        $poules = $this->blok->poules->sortBy([
            ['mat_id', 'asc'],
            ['nummer', 'asc'],
        ]);

        // Check if any poule has no mat assigned
        $heeftGeenMat = $poules->contains(fn($p) => $p->mat_id === null);
        if ($heeftGeenMat) {
            $rows[] = ['LET OP: Niet alle poules zijn toegewezen aan een mat!'];
            $this->warningRows[] = $rowNum++;
            $rows[] = [];
            $rowNum++;
        }

        foreach ($poules as $poule) {
            $matNummer = $poule->mat?->nummer;
            $matLabel = $matNummer ? "Mat {$matNummer}" : "Geen mat toegewezen";

            // Mat header when mat changes
            if ($currentMat !== $matNummer) {
                if ($currentMat !== null) {
                    // Lege rijen tussen matten
                    $rows[] = [];
                    $rowNum++;
                    $rows[] = [];
                    $rowNum++;
                }
                $rows[] = [$matLabel];
                $this->matRows[] = $rowNum++;
                $currentMat = $matNummer;
            }

            // Lege rij voor poule header
            $rows[] = [];
            $rowNum++;

            // Poule header
            $pouleHeader = sprintf(
                '%s | %s | Poule %d | (%d judoka\'s, %d wedstrijden)',
                $poule->leeftijdsklasse ?? 'Onbekend',
                $poule->gewichtsklasse ?? 'Onbekend',
                $poule->nummer,
                $poule->aantal_judokas,
                $poule->aantal_wedstrijden
            );
            $rows[] = [$pouleHeader];
            $this->pouleRows[] = $rowNum++;

            // Column headers
            $rows[] = ['Naam', 'Band', 'Club', 'Gewicht', 'Geslacht', 'Geboortejaar'];
            $this->headerRows[] = $rowNum++;

            // Judoka rows
            foreach ($poule->judokas as $judoka) {
                $rows[] = [
                    $judoka->naam,
                    $judoka->band,
                    $judoka->club?->naam ?? '',
                    $judoka->gewichtsklasse,
                    $judoka->geslacht === 'M' ? 'Man' : 'Vrouw',
                    $judoka->geboortejaar,
                ];
                $rowNum++;
            }
        }

        $this->cachedData = $rows;
        return $rows;
    }

    public function array(): array
    {
        return $this->buildData();
    }

    public function styles(Worksheet $sheet): array
    {
        // Ensure data is built first
        $this->buildData();

        $styles = [];

        // Warning rows - red
        foreach ($this->warningRows as $row) {
            $styles[$row] = [
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'CC0000']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FFEEEE'],
                ],
            ];
        }

        // Mat headers - blue background, white text
        foreach ($this->matRows as $row) {
            $styles[$row] = [
                'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
            ];
        }

        // Poule headers - light blue background
        foreach ($this->pouleRows as $row) {
            $styles[$row] = [
                'font' => ['bold' => true, 'size' => 11],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'D6DCE4'],
                ],
            ];
        }

        // Column headers - light gray
        foreach ($this->headerRows as $row) {
            $styles[$row] = [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F2F2F2'],
                ],
            ];
        }

        // Auto-size columns
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $styles;
    }
}
