<?php

namespace App\Exports;

use App\Models\Blok;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PouleBlokSheet implements FromArray, WithTitle, WithStyles
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

        foreach ($poules as $poule) {
            $matNummer = $poule->mat?->nummer ?? 'Geen mat';

            // Mat header when mat changes
            if ($currentMat !== $matNummer) {
                if ($currentMat !== null) {
                    $rows[] = []; // Empty row between mats
                }
                $rows[] = ["Mat {$matNummer}"];
                $rows[] = []; // Empty row after mat header
                $currentMat = $matNummer;
            }

            // Poule header: Leeftijdsklasse - Gewichtsklasse - Poule X (Y judoka's, Z wedstrijden)
            $pouleHeader = sprintf(
                '%s %s Poule %d (%d judoka\'s, %d wedstrijden)',
                $poule->leeftijdsklasse ?? '',
                $poule->gewichtsklasse ?? '',
                $poule->nummer,
                $poule->aantal_judokas,
                $poule->aantal_wedstrijden
            );
            $rows[] = [$pouleHeader];

            // Column headers
            $rows[] = ['Naam', 'Band', 'Club', 'Gewicht', 'Geslacht', 'Geboortejaar'];

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
            }

            $rows[] = []; // Empty row after each poule
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [];

        // Find mat headers and poule headers to style them
        $row = 1;
        foreach ($this->array() as $data) {
            if (!empty($data) && count($data) === 1) {
                $value = $data[0] ?? '';
                if (str_starts_with($value, 'Mat ')) {
                    // Mat header - bold and larger
                    $styles[$row] = [
                        'font' => ['bold' => true, 'size' => 14],
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'DDDDDD'],
                        ],
                    ];
                } elseif (str_contains($value, 'Poule')) {
                    // Poule header - bold
                    $styles[$row] = [
                        'font' => ['bold' => true, 'size' => 12],
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'EEEEEE'],
                        ],
                    ];
                }
            } elseif (!empty($data) && ($data[0] ?? '') === 'Naam') {
                // Column headers - bold
                $styles[$row] = ['font' => ['bold' => true]];
            }
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $styles;
    }
}
