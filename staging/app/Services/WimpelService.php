<?php

namespace App\Services;

use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\Poule;
use App\Models\StamJudoka;
use App\Models\Toernooi;
use App\Models\WimpelMilestone;
use App\Models\WimpelPuntenLog;
use App\Models\WimpelUitreiking;
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

                $stamJudoka = $this->matchJudoka($organisator, $judoka);
                $isNieuw = $stamJudoka->wasRecentlyCreated;
                $oudePunten = $stamJudoka->wimpel_punten_totaal;

                WimpelPuntenLog::create([
                    'stam_judoka_id' => $stamJudoka->id,
                    'toernooi_id' => $toernooi->id,
                    'poule_id' => $poule->id,
                    'punten' => $aantalWins,
                    'type' => 'automatisch',
                ]);

                $stamJudoka->increment('wimpel_punten_totaal', $aantalWins);

                if ($isNieuw) {
                    $result['nieuwe_judokas'][] = [
                        'naam' => $stamJudoka->naam,
                        'geboortejaar' => $stamJudoka->geboortejaar,
                        'punten' => $aantalWins,
                    ];
                }

                $bereikt = $this->checkMilestones($stamJudoka, $oudePunten, $toernooi->id);
                if (!empty($bereikt)) {
                    $result['milestones'][] = [
                        'judoka' => $stamJudoka->naam,
                        'punten' => $stamJudoka->wimpel_punten_totaal,
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
     * Zoek of maak StamJudoka op basis van naam + geboortejaar.
     * New stam_judokas created here get wimpel_is_nieuw = true.
     */
    public function matchJudoka(Organisator $organisator, Judoka $judoka): StamJudoka
    {
        $naam = Judoka::formatNaam($judoka->naam);

        $stamJudoka = StamJudoka::firstOrCreate(
            [
                'organisator_id' => $organisator->id,
                'naam' => $naam,
                'geboortejaar' => $judoka->geboortejaar,
            ],
            [
                'geslacht' => $judoka->geslacht ?? 'M',
                'band' => $judoka->band ?? 'wit',
                'wimpel_punten_totaal' => 0,
                'wimpel_is_nieuw' => true,
            ]
        );

        // If existing stam_judoka was found but has no wimpel data yet, mark as new for wimpel
        if (!$stamJudoka->wasRecentlyCreated && $stamJudoka->wimpel_punten_totaal == 0 && !$stamJudoka->wimpel_is_nieuw) {
            $stamJudoka->update(['wimpel_is_nieuw' => true]);
        }

        return $stamJudoka;
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
    public function handmatigAanpassen(StamJudoka $stamJudoka, int $punten, ?string $notitie = null): array
    {
        $oudePunten = $stamJudoka->wimpel_punten_totaal;

        DB::transaction(function () use ($stamJudoka, $punten, $notitie) {
            WimpelPuntenLog::create([
                'stam_judoka_id' => $stamJudoka->id,
                'toernooi_id' => null,
                'punten' => $punten,
                'type' => 'handmatig',
                'notitie' => $notitie,
            ]);

            $stamJudoka->increment('wimpel_punten_totaal', $punten);
        });

        return $this->checkMilestones($stamJudoka->fresh(), $oudePunten, null);
    }

    /**
     * Check welke milestones gepasseerd zijn tussen oud en nieuw puntentotaal.
     * Maakt wimpel_uitreikingen records aan voor de spreker queue.
     */
    public function checkMilestones(StamJudoka $stamJudoka, int $oudePunten, ?int $toernooiId = null): array
    {
        $milestones = WimpelMilestone::where('organisator_id', $stamJudoka->organisator_id)
            ->where('punten', '>', $oudePunten)
            ->where('punten', '<=', $stamJudoka->wimpel_punten_totaal)
            ->orderBy('punten')
            ->get();

        // Create uitreiking records for spreker queue
        foreach ($milestones as $milestone) {
            WimpelUitreiking::firstOrCreate(
                [
                    'stam_judoka_id' => $stamJudoka->id,
                    'wimpel_milestone_id' => $milestone->id,
                ],
                [
                    'toernooi_id' => $toernooiId,
                    'uitgereikt' => false,
                ]
            );
        }

        return $milestones->toArray();
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
