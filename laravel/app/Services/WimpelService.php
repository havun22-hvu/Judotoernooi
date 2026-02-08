<?php

namespace App\Services;

use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\WimpelJudoka;
use App\Models\WimpelMilestone;
use App\Models\WimpelPuntenLog;
use Illuminate\Support\Facades\DB;

class WimpelService
{
    /**
     * Verwerk een heel toernooi: alle onverwerkte puntencompetitie-poules.
     * Used for bulk processing (manual or on tournament close).
     */
    public function verwerkToernooi(Toernooi $toernooi): array
    {
        $milestoneWarnings = [];

        // Haal alle puntencompetitie poules op
        $poules = $toernooi->poules()
            ->with('wedstrijden')
            ->get()
            ->filter(fn($p) => $p->isPuntenCompetitie());

        foreach ($poules as $poule) {
            $result = $this->verwerkPoule($poule);
            if (!empty($result['milestones'])) {
                $milestoneWarnings = array_merge($milestoneWarnings, $result['milestones']);
            }
        }

        return $milestoneWarnings;
    }

    /**
     * Verwerk een enkele poule: tel gewonnen wedstrijden en schrijf bij.
     * Called when poule is marked as spreker_klaar on the mat interface.
     * Returns array with milestone warnings and new judoka info.
     */
    public function verwerkPoule(Poule $poule): array
    {
        if (!$poule->isPuntenCompetitie()) {
            return [];
        }

        // Already processed?
        if ($this->isPouleAlVerwerkt($poule)) {
            return [];
        }

        $toernooi = $poule->toernooi;
        $organisator = $toernooi->organisator;

        $poule->loadMissing('wedstrijden');

        // Count wins per judoka
        $winsPerJudoka = [];
        foreach ($poule->wedstrijden as $wedstrijd) {
            if (!$wedstrijd->is_gespeeld || !$wedstrijd->winnaar_id) {
                continue;
            }
            $winsPerJudoka[$wedstrijd->winnaar_id] =
                ($winsPerJudoka[$wedstrijd->winnaar_id] ?? 0) + 1;
        }

        if (empty($winsPerJudoka)) {
            return [];
        }

        $judokas = Judoka::whereIn('id', array_keys($winsPerJudoka))->get()->keyBy('id');

        $result = ['milestones' => [], 'nieuwe_judokas' => []];

        DB::transaction(function () use ($organisator, $toernooi, $poule, $winsPerJudoka, $judokas, &$result) {
            foreach ($winsPerJudoka as $judokaId => $aantalWins) {
                $judoka = $judokas->get($judokaId);
                if (!$judoka) {
                    continue;
                }

                $wimpelJudoka = $this->matchJudoka($organisator, $judoka);
                $isNieuw = $wimpelJudoka->wasRecentlyCreated;
                $oudePunten = $wimpelJudoka->punten_totaal;

                WimpelPuntenLog::create([
                    'wimpel_judoka_id' => $wimpelJudoka->id,
                    'toernooi_id' => $toernooi->id,
                    'poule_id' => $poule->id,
                    'punten' => $aantalWins,
                    'type' => 'automatisch',
                ]);

                $wimpelJudoka->increment('punten_totaal', $aantalWins);

                if ($isNieuw) {
                    $result['nieuwe_judokas'][] = [
                        'naam' => $wimpelJudoka->naam,
                        'geboortejaar' => $wimpelJudoka->geboortejaar,
                        'punten' => $aantalWins,
                    ];
                }

                $bereikt = $this->checkMilestones($wimpelJudoka, $oudePunten);
                if (!empty($bereikt)) {
                    $result['milestones'][] = [
                        'judoka' => $wimpelJudoka->naam,
                        'punten' => $wimpelJudoka->punten_totaal,
                        'milestones' => $bereikt,
                    ];
                }
            }
        });

        return $result;
    }

    /**
     * Check of een poule al verwerkt is voor wimpel
     */
    public function isPouleAlVerwerkt(Poule $poule): bool
    {
        return WimpelPuntenLog::where('poule_id', $poule->id)
            ->where('type', 'automatisch')
            ->exists();
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
     * Check of toernooi al volledig verwerkt is
     */
    public function isAlVerwerkt(Toernooi $toernooi): bool
    {
        $pcPoules = $toernooi->poules()->get()->filter(fn($p) => $p->isPuntenCompetitie());
        if ($pcPoules->isEmpty()) {
            return true;
        }

        // Alle poules verwerkt?
        return $pcPoules->every(fn($p) => $this->isPouleAlVerwerkt($p));
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
