<?php

namespace App\Exports;

use App\Models\Toernooi;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PouleExport implements WithMultipleSheets
{
    public function __construct(
        protected Toernooi $toernooi
    ) {}

    public function sheets(): array
    {
        $sheets = [];

        $blokken = $this->toernooi->blokken()
            ->with(['poules' => fn($q) => $q->with(['judokas.club', 'mat'])->orderBy('mat_id')->orderBy('nummer')])
            ->orderBy('nummer')
            ->get();

        foreach ($blokken as $blok) {
            $sheets[] = new PouleBlokSheet($blok);
        }

        return $sheets;
    }
}
