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

        // Check if any poule has no mat assigned
        $heeftGeenMat = $poules->contains(fn($p) => $p->mat_id === null);
        if ($heeftGeenMat) {
            $rows[] = ['⚠️ LET OP: Niet alle poules zijn toegewezen aan een mat!'];
            $rows[] = [];
            $rows[] = [];
        }

        foreach ($poules as $poule) {
            $matNummer = $poule->mat?->nummer;
            $matLabel = $matNummer ? "Mat {$matNummer}" : "Geen mat toegewezen";

            // Mat header when mat changes
            if ($currentMat !== $matNummer) {
                if ($currentMat !== null) {
                    // Extra lege rijen tussen matten
                    $rows[] = [];
                    $rows[] = [];
                    $rows[] = [];
                }
                $rows[] = [$matLabel];
                $rows[] = [];
                $currentMat = $matNummer;
            }

            // Poule header met alle info
            $pouleInfo = [];
            if ($poule->leeftijdsklasse) {
                $pouleInfo[] = $poule->leeftijdsklasse;
            }
            if ($poule->gewichtsklasse) {
                $pouleInfo[] = $poule->gewichtsklasse;
            }

            // Gebruik titel als fallback als leeftijds/gewichtsklasse ontbreken
            if (empty($pouleInfo) && $poule->titel) {
                $pouleInfo[] = $poule->titel;
            }

            $pouleInfo[] = "Poule {$poule->nummer}";
            $pouleInfo[] = "({$poule->aantal_judokas} judoka's, {$poule->aantal_wedstrijden} wedstrijden)";

            $rows[] = [implode(' | ', $pouleInfo)];

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

            // Lege rijen na elke poule
            $rows[] = [];
            $rows[] = [];
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
                if (str_starts_with($value, '⚠️')) {
                    // Warning - red background
                    $styles[$row] = [
                        'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'CC0000']],
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FFEEEE'],
                        ],
                    ];
                } elseif (str_starts_with($value, 'Mat ') || str_starts_with($value, 'Geen mat')) {
                    // Mat header - bold and larger, dark background
                    $styles[$row] = [
                        'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => '4472C4'],
                        ],
                    ];
                } elseif (str_contains($value, 'Poule')) {
                    // Poule header - bold, light blue background
                    $styles[$row] = [
                        'font' => ['bold' => true, 'size' => 11],
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'D6DCE4'],
                        ],
                    ];
                }
            } elseif (!empty($data) && ($data[0] ?? '') === 'Naam') {
                // Column headers - bold, light gray
                $styles[$row] = [
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F2F2F2'],
                    ],
                ];
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
