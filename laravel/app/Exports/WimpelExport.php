<?php

namespace App\Exports;

use App\Models\Organisator;
use App\Models\Toernooi;
use App\Models\WimpelPuntenLog;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class WimpelExport implements FromArray, WithEvents
{
    public function __construct(
        protected Organisator $organisator
    ) {}

    public function array(): array
    {
        $judokas = $this->organisator->stamJudokas()
            ->metWimpel()
            ->orderByDesc('wimpel_punten_totaal')
            ->get();

        if ($judokas->isEmpty()) {
            return [['Geen wimpel judoka\'s gevonden']];
        }

        // Collect all unique toernooi IDs from punten log (ordered by toernooi date)
        $toernooiIds = WimpelPuntenLog::query()
            ->whereIn('stam_judoka_id', $judokas->pluck('id'))
            ->whereNotNull('toernooi_id')
            ->distinct()
            ->pluck('toernooi_id');

        $toernooien = Toernooi::whereIn('id', $toernooiIds)
            ->orderBy('datum')
            ->get()
            ->keyBy('id');

        // Check for handmatige punten
        $heeftHandmatig = WimpelPuntenLog::query()
            ->whereIn('stam_judoka_id', $judokas->pluck('id'))
            ->whereNull('toernooi_id')
            ->exists();

        // Build header row
        $header = ['Naam', 'Geboortejaar', 'Totaal'];
        $toernooiIdsSorted = $toernooien->pluck('id')->values()->all();

        foreach ($toernooiIdsSorted as $toernooiId) {
            $toernooi = $toernooien->get($toernooiId);
            $datum = $toernooi->datum?->format('d-m-Y') ?? '?';
            $header[] = "{$datum} {$toernooi->naam}";
        }

        if ($heeftHandmatig) {
            $header[] = 'Handmatig';
        }

        // Pre-load punten per judoka per toernooi
        $puntenPerJudoka = WimpelPuntenLog::query()
            ->whereIn('stam_judoka_id', $judokas->pluck('id'))
            ->selectRaw('stam_judoka_id, toernooi_id, SUM(punten) as totaal')
            ->groupBy('stam_judoka_id', 'toernooi_id')
            ->get()
            ->groupBy('stam_judoka_id');

        // Build data rows
        $rows = [$header];

        foreach ($judokas as $judoka) {
            $row = [
                $judoka->naam,
                $judoka->geboortejaar,
                $judoka->wimpel_punten_totaal,
            ];

            $judokaLogs = $puntenPerJudoka->get($judoka->id, collect());
            $logsByToernooi = $judokaLogs->keyBy('toernooi_id');

            foreach ($toernooiIdsSorted as $toernooiId) {
                $entry = $logsByToernooi->get($toernooiId);
                $row[] = $entry ? $entry->totaal : '';
            }

            if ($heeftHandmatig) {
                $entry = $logsByToernooi->get(null);
                $row[] = $entry ? $entry->totaal : '';
            }

            $rows[] = $row;
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestColumn = $sheet->getHighestColumn();

                // Header row styling
                $sheet->getStyle("A1:{$highestColumn}1")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'D6DCE4'],
                    ],
                ]);

                // Column widths
                $sheet->getColumnDimension('A')->setWidth(28); // Naam
                $sheet->getColumnDimension('B')->setWidth(14); // Geboortejaar
                $sheet->getColumnDimension('C')->setWidth(10); // Totaal
            },
        ];
    }
}
