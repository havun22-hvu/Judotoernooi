<?php

namespace App\Services;

use App\Events\ScoreboardAssignment;
use App\Events\ScoreboardEvent;
use App\Models\Mat;
use App\Models\Poule;
use App\Models\Wedstrijd;

/**
 * Notifies the scoreboard app (ScoreboardAssignment) and the LCD display
 * (ScoreboardEvent) when a mat's active ("groen") match changes.
 *
 * Shared by both the manual flow (MatUitslagController::setHuidigeWedstrijd —
 * organisator zet groen) and the automatic flow (ScoreboardController::result —
 * na een uitslag schuift de groene beurt door). Eén plek zodat de payload niet
 * uit elkaar loopt.
 */
class ScoreboardNotifier
{
    /**
     * Her-broadcast de scoreboard-toewijzing voor elke mat waarvan de actieve wedstrijd
     * in deze poule zit. Aanroepen nadat de DEELNEMERS van poule-wedstrijden zijn gewijzigd
     * (drag-plaatsing, uitslagcorrectie, winnaar-doorschuiven) — zonder dit blijft de Android
     * scoreboard-app de oude judoka's tonen. Idempotent: geen actieve match in deze poule → niets.
     */
    public function notifyForPoule(int $toernooiId, Poule $poule): void
    {
        $wedstrijdIds = $poule->wedstrijden()->pluck('id');
        if ($wedstrijdIds->isEmpty()) {
            return;
        }

        Mat::whereIn('actieve_wedstrijd_id', $wedstrijdIds)
            ->get()
            ->each(fn (Mat $mat) => $this->notifyActiveMatchChanged($toernooiId, $mat));
    }

    /**
     * Roep aan NADAT $mat->actieve_wedstrijd_id is bijgewerkt.
     */
    public function notifyActiveMatchChanged(int $toernooiId, Mat $mat): void
    {
        if ($mat->actieve_wedstrijd_id) {
            $wedstrijd = Wedstrijd::with(['judokaWit.club', 'judokaBlauw.club', 'poule.toernooi'])
                ->find($mat->actieve_wedstrijd_id);

            if ($wedstrijd) {
                $matchData = [
                    'id' => $wedstrijd->id,
                    'judoka_wit' => [
                        'id' => $wedstrijd->judokaWit?->id,
                        'naam' => $wedstrijd->judokaWit?->naam ?? 'WIT',
                        'club' => $wedstrijd->judokaWit?->club?->naam ?? '',
                    ],
                    'judoka_blauw' => [
                        'id' => $wedstrijd->judokaBlauw?->id,
                        'naam' => $wedstrijd->judokaBlauw?->naam ?? 'BLAUW',
                        'club' => $wedstrijd->judokaBlauw?->club?->naam ?? '',
                    ],
                    'poule_naam' => $wedstrijd->poule?->titel ?? "Poule {$wedstrijd->poule?->nummer}",
                    'ronde' => $wedstrijd->ronde,
                    'groep' => $wedstrijd->groep,
                    'match_duration' => $wedstrijd->poule?->toernooi?->getMatchDurationForCategorie($wedstrijd->poule?->categorie_key) ?? 180,
                    ...($wedstrijd->poule?->toernooi?->getMatchRulesForCategorie($wedstrijd->poule?->categorie_key) ?? []),
                    'updated_at' => $wedstrijd->updated_at?->toISOString(),
                ];

                ScoreboardAssignment::dispatch($toernooiId, $mat->id, $matchData);
                ScoreboardEvent::dispatch($toernooiId, $mat->id, [
                    'event' => 'match.assign',
                    ...$matchData,
                ]);

                return;
            }
        }

        // Geen actieve wedstrijd meer — app + LCD resetten.
        ScoreboardAssignment::dispatch($toernooiId, $mat->id, []);
        ScoreboardEvent::dispatch($toernooiId, $mat->id, [
            'event' => 'match.unassign',
        ]);
    }
}
