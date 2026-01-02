<?php

namespace App\Exports;

use App\Models\Blok;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PouleBlokSheet implements FromArray, WithTitle, WithEvents
{
    public function __construct(
        protected Blok $blok
    ) {}

    public function title(): string
    {
        return "Blok {$this->blok->nummer}";
    }

    public function array(): array
    {
        $rows = [];
        $currentMat = null;

        // Group poules by mat
        $poules = $this->blok->poules->sortBy([
            ['mat_id', 'asc'],
            ['nummer', 'asc'],
        ]);

        // Version header
        $rows[] = ['VERSIE_4_' . now()->format('H:i:s')];
        $rows[] = [];

        foreach ($poules as $poule) {
            $matNummer = $poule->mat?->nummer;

            // Mat header when mat changes
            if ($currentMat !== $matNummer) {
                if ($currentMat !== null) {
                    // Lege rijen tussen matten
                    $rows[] = [];
                    $rows[] = [];
                }
                $rows[] = ['MAT_HEADER_' . ($matNummer ?? 'GEEN')];
                $rows[] = [];
                $currentMat = $matNummer;
            }

            // Poule header met marker
            $rows[] = [sprintf(
                'POULE_HEADER_%s | %s | Poule %d | (%d judoka\'s, %d wedstrijden)',
                $poule->leeftijdsklasse ?? 'Onbekend',
                $poule->gewichtsklasse ?? 'Onbekend',
                $poule->nummer,
                $poule->aantal_judokas,
                $poule->aantal_wedstrijden
            )];

            // Column headers met marker
            $rows[] = ['KOLOM_Naam', 'Band', 'Club', 'Gewicht', 'Geslacht', 'Geboortejaar'];

            // Judoka rows - geen marker, gewoon data
            foreach ($poule->judokas as $judoka) {
                $rows[] = [
                    $judoka->naam,
                    $judoka->band,
                    $judoka->club?->naam ?? '',
                    $judoka->gewichtsklasse,
                    $judoka->geslacht === 'M' ? 'Man' : 'Vrouw',
                    $judoka->geboortejaar,
                ];
            }

            // Lege rij na poule
            $rows[] = [];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                for ($row = 1; $row <= $highestRow; $row++) {
                    $cellValue = $sheet->getCell("A{$row}")->getValue();

                    if ($cellValue === null || $cellValue === '') {
                        continue;
                    }

                    $cellStr = (string) $cellValue;

                    // Mat header
                    if (str_starts_with($cellStr, 'MAT_HEADER_')) {
                        // Vervang marker met echte tekst
                        $matNummer = str_replace('MAT_HEADER_', '', $cellStr);
                        $sheet->setCellValue("A{$row}", $matNummer === 'GEEN' ? 'Geen mat toegewezen' : "Mat {$matNummer}");

                        $sheet->getStyle("A{$row}:F{$row}")->applyFromArray([
                            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => '4472C4'],
                            ],
                        ]);
                    }
                    // Poule header
                    elseif (str_starts_with($cellStr, 'POULE_HEADER_')) {
                        // Vervang marker
                        $sheet->setCellValue("A{$row}", str_replace('POULE_HEADER_', '', $cellStr));

                        $sheet->getStyle("A{$row}:F{$row}")->applyFromArray([
                            'font' => ['bold' => true, 'size' => 11],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'D6DCE4'],
                            ],
                        ]);
                    }
                    // Column header
                    elseif (str_starts_with($cellStr, 'KOLOM_')) {
                        // Vervang marker
                        $sheet->setCellValue("A{$row}", str_replace('KOLOM_', '', $cellStr));

                        $sheet->getStyle("A{$row}:F{$row}")->applyFromArray([
                            'font' => ['bold' => true],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'F2F2F2'],
                            ],
                        ]);
                    }
                    // Versie header
                    elseif (str_starts_with($cellStr, 'VERSIE_')) {
                        $sheet->setCellValue("A{$row}", 'Export ' . str_replace('_', ' ', $cellStr));
                    }
                    // Alles zonder marker = judoka data, geen styling
                }

                // Auto-size columns
                foreach (range('A', 'F') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }
}
