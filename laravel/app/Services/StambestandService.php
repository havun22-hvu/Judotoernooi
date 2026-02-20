<?php

namespace App\Services;

use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\StamJudoka;
use App\Models\Toernooi;

class StambestandService
{
    /**
     * Import selected stam-judokas into a tournament as toernooi-judokas.
     */
    public function importNaarToernooi(array $stamJudokaIds, Toernooi $toernooi): int
    {
        $organisator = $toernooi->eigenaar();
        $club = $organisator?->clubs()->first();

        $stamJudokas = StamJudoka::whereIn('id', $stamJudokaIds)
            ->where('organisator_id', $organisator->id)
            ->actief()
            ->get();

        $count = 0;

        foreach ($stamJudokas as $stam) {
            // Skip if already imported (same stam_judoka_id in this tournament)
            $exists = Judoka::where('toernooi_id', $toernooi->id)
                ->where('stam_judoka_id', $stam->id)
                ->exists();

            if ($exists) {
                continue;
            }

            Judoka::create([
                'toernooi_id' => $toernooi->id,
                'club_id' => $club?->id,
                'stam_judoka_id' => $stam->id,
                'naam' => $stam->naam,
                'geboortejaar' => $stam->geboortejaar,
                'geslacht' => $stam->geslacht,
                'band' => $stam->band,
                'gewicht' => $stam->gewicht,
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * After CSV import: match or create a stam-judoka for the imported judoka.
     */
    public function syncVanuitImport(Judoka $judoka, Organisator $organisator): void
    {
        if ($judoka->stam_judoka_id) {
            return;
        }

        $stamJudoka = StamJudoka::where('organisator_id', $organisator->id)
            ->where('naam', $judoka->naam)
            ->where('geboortejaar', $judoka->geboortejaar)
            ->first();

        if (!$stamJudoka) {
            $stamJudoka = StamJudoka::create([
                'organisator_id' => $organisator->id,
                'naam' => $judoka->naam,
                'geboortejaar' => $judoka->geboortejaar,
                'geslacht' => $judoka->geslacht ?? 'M',
                'band' => $judoka->band ?? 'wit',
                'gewicht' => $judoka->gewicht,
            ]);
        }

        $judoka->update(['stam_judoka_id' => $stamJudoka->id]);
    }
}
