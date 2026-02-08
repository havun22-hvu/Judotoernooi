<?php

namespace App\Services;

use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Toernooi;
use App\Models\WimpelJudoka;
use App\Models\WimpelMilestone;
use App\Models\WimpelPuntenLog;
use Illuminate\Support\Facades\DB;

class WimpelService
{
    /**
     * Verwerk een toernooi: tel gewonnen wedstrijden per judoka in puntencompetitie-poules.
     * Returns array van milestone-waarschuwingen.
     */
    public function verwerkToernooi(Toernooi $toernooi): array
    {
        if ($this->isAlVerwerkt($toernooi)) {
            return [];
        }

        $organisator = $toernooi->organisator;
        $milestoneWarnings = [];

        // Haal alle puntencompetitie poules op
        $poules = $toernooi->poules()
            ->with('wedstrijden')
            ->get()
            ->filter(fn($p) => $p->isPuntenCompetitie());

        if ($poules->isEmpty()) {
            return [];
        }

        // Tel gewonnen wedstrijden per judoka (over alle poules)
        $winsTellingPerJudoka = [];

        foreach ($poules as $poule) {
            foreach ($poule->wedstrijden as $wedstrijd) {
                if (!$wedstrijd->is_gespeeld || !$wedstrijd->winnaar_id) {
                    continue;
                }
                $winsTellingPerJudoka[$wedstrijd->winnaar_id] =
                    ($winsTellingPerJudoka[$wedstrijd->winnaar_id] ?? 0) + 1;
            }
        }

        if (empty($winsTellingPerJudoka)) {
            return [];
        }

        // Laad alle judoka's in één query
        $judokaIds = array_keys($winsTellingPerJudoka);
        $judokas = Judoka::whereIn('id', $judokaIds)->get()->keyBy('id');

        DB::transaction(function () use ($organisator, $toernooi, $winsTellingPerJudoka, $judokas, &$milestoneWarnings) {
            foreach ($winsTellingPerJudoka as $judokaId => $aantalWins) {
                $judoka = $judokas->get($judokaId);
                if (!$judoka) {
                    continue;
                }

                $wimpelJudoka = $this->matchJudoka($organisator, $judoka);
                $oudePunten = $wimpelJudoka->punten_totaal;

                // Log entry aanmaken
                WimpelPuntenLog::create([
                    'wimpel_judoka_id' => $wimpelJudoka->id,
                    'toernooi_id' => $toernooi->id,
                    'punten' => $aantalWins,
                    'type' => 'automatisch',
                ]);

                // Totaal bijwerken
                $wimpelJudoka->increment('punten_totaal', $aantalWins);

                // Check milestones
                $bereikt = $this->checkMilestones($wimpelJudoka, $oudePunten);
                if (!empty($bereikt)) {
                    $milestoneWarnings[] = [
                        'judoka' => $wimpelJudoka->naam,
                        'punten' => $wimpelJudoka->punten_totaal,
                        'milestones' => $bereikt,
                    ];
                }
            }
        });

        return $milestoneWarnings;
    }

    /**
     * Zoek of maak WimpelJudoka op basis van naam + geboortejaar
     */
    public function matchJudoka(Organisator $organisator, Judoka $judoka): WimpelJudoka
    {
        $naam = Judoka::formatNaam($judoka->naam);

        return WimpelJudoka::firstOrCreate(
            [
                'organisator_id' => $organisator->id,
                'naam' => $naam,
                'geboortejaar' => $judoka->geboortejaar,
            ],
            ['punten_totaal' => 0]
        );
    }

    /**
     * Check of toernooi al verwerkt is (voorkom dubbel bijschrijven)
     */
    public function isAlVerwerkt(Toernooi $toernooi): bool
    {
        return WimpelPuntenLog::where('toernooi_id', $toernooi->id)
            ->where('type', 'automatisch')
            ->exists();
    }

    /**
     * Handmatige punten aanpassing (+/-)
     */
    public function handmatigAanpassen(WimpelJudoka $wimpelJudoka, int $punten, ?string $notitie = null): array
    {
        $oudePunten = $wimpelJudoka->punten_totaal;

        DB::transaction(function () use ($wimpelJudoka, $punten, $notitie) {
            WimpelPuntenLog::create([
                'wimpel_judoka_id' => $wimpelJudoka->id,
                'toernooi_id' => null,
                'punten' => $punten,
                'type' => 'handmatig',
                'notitie' => $notitie,
            ]);

            $wimpelJudoka->increment('punten_totaal', $punten);
        });

        return $this->checkMilestones($wimpelJudoka->fresh(), $oudePunten);
    }

    /**
     * Check welke milestones gepasseerd zijn tussen oud en nieuw puntentotaal
     */
    public function checkMilestones(WimpelJudoka $wimpelJudoka, int $oudePunten): array
    {
        return WimpelMilestone::where('organisator_id', $wimpelJudoka->organisator_id)
            ->where('punten', '>', $oudePunten)
            ->where('punten', '<=', $wimpelJudoka->punten_totaal)
            ->orderBy('punten')
            ->get()
            ->toArray();
    }

    /**
     * Haal onverwerkte puntencompetitie toernooien op voor een organisator
     */
    public function getOnverwerkteToernooien(Organisator $organisator)
    {
        $verwerkteIds = WimpelPuntenLog::where('type', 'automatisch')
            ->whereNotNull('toernooi_id')
            ->pluck('toernooi_id')
            ->unique();

        return $organisator->toernooien()
            ->whereNotIn('id', $verwerkteIds)
            ->orderByDesc('datum')
            ->get()
            ->filter(function ($toernooi) {
                // Alleen toernooien met tenminste 1 gespeelde puntencompetitie wedstrijd
                return $toernooi->poules()
                    ->get()
                    ->filter(fn($p) => $p->isPuntenCompetitie())
                    ->flatMap(fn($p) => $p->wedstrijden()->where('is_gespeeld', true)->whereNotNull('winnaar_id')->get())
                    ->isNotEmpty();
            });
    }
}
