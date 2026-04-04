<?php

namespace App\Console\Commands;

use App\Models\Toernooi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Long-running heartbeat command that broadcasts mat state every second
 * for active tournaments via Reverb. Auto-stops when no activity for 15 min.
 *
 * Usage:
 *   php artisan toernooi:heartbeat          — run heartbeat process (supervisor)
 *   php artisan toernooi:heartbeat:on {id}  — manually activate for a tournament
 *   php artisan toernooi:heartbeat:off {id} — manually deactivate
 */
class ToernooiHeartbeat extends Command
{
    protected $signature = 'toernooi:heartbeat';
    protected $description = 'Broadcast mat state every second for active tournaments via Reverb';

    public function handle(): int
    {
        $this->info('Heartbeat process started. Watching for active tournaments...');

        while (true) {
            $activeToernooien = $this->getActiveToernooien();

            foreach ($activeToernooien as $toernooi) {
                $this->broadcastMatState($toernooi);
            }

            usleep(1_000_000); // 1 second
        }

        return self::SUCCESS;
    }

    private function getActiveToernooien(): array
    {
        $toernooien = Toernooi::whereHas('matten')->whereHas('poules')->get();
        $active = [];

        foreach ($toernooien as $toernooi) {
            if (Cache::has("toernooi:{$toernooi->id}:heartbeat_active")) {
                $active[] = $toernooi;
            }
        }

        return $active;
    }

    private function broadcastMatState(Toernooi $toernooi): void
    {
        try {
            $matten = $toernooi->matten()
                ->with(['poules' => function ($q) {
                    $q->with(['judokas.club', 'wedstrijden'])
                      ->orderBy('nummer');
                }])
                ->orderBy('nummer')
                ->get()
                ->map(function ($mat) {
                    $mat->cleanupGespeeldeSelecties();

                    $poule = $mat->poules->whereNull('afgeroepen_at')->first();
                    $alleWedstrijden = $mat->poules->flatMap(fn($p) => $p->wedstrijden);

                    $groeneWedstrijd = $mat->actieve_wedstrijd_id
                        ? $alleWedstrijden->first(fn($w) => $w->id === $mat->actieve_wedstrijd_id && $w->isNogTeSpelen())
                        : null;
                    $geleWedstrijd = $mat->volgende_wedstrijd_id
                        ? $alleWedstrijden->first(fn($w) => $w->id === $mat->volgende_wedstrijd_id && $w->isNogTeSpelen())
                        : null;
                    $blauweWedstrijd = $mat->gereedmaken_wedstrijd_id
                        ? $alleWedstrijden->first(fn($w) => $w->id === $mat->gereedmaken_wedstrijd_id && $w->isNogTeSpelen())
                        : null;

                    $formatWedstrijd = function ($wedstrijd) use ($mat) {
                        if (!$wedstrijd) return null;
                        $wedstrijdPoule = $mat->poules->first(fn($p) => $p->wedstrijden->contains('id', $wedstrijd->id));
                        if (!$wedstrijdPoule) return null;

                        $wit = $wedstrijdPoule->judokas->firstWhere('id', $wedstrijd->judoka_wit_id);
                        $blauw = $wedstrijdPoule->judokas->firstWhere('id', $wedstrijd->judoka_blauw_id);

                        return [
                            'id' => $wedstrijd->id,
                            'poule_titel' => $wedstrijdPoule->getDisplayTitel(),
                            'wit' => $wit ? ['naam' => $wit->naam, 'club' => $wit->club?->naam] : null,
                            'blauw' => $blauw ? ['naam' => $blauw->naam, 'club' => $blauw->club?->naam] : null,
                        ];
                    };

                    return [
                        'id' => $mat->id,
                        'nummer' => $mat->nummer,
                        'naam' => $mat->naam,
                        'poule_titel' => $poule?->getDisplayTitel(),
                        'groen' => $formatWedstrijd($groeneWedstrijd),
                        'geel' => $formatWedstrijd($geleWedstrijd),
                        'blauw' => $formatWedstrijd($blauweWedstrijd),
                    ];
                });

            $breaker = new \App\Support\CircuitBreaker('reverb', 3, 30);
            if ($breaker->isAvailable()) {
                $breaker->call(fn () => broadcast(new \App\Events\MatHeartbeat($toernooi->id, $matten->toArray())));
            }
        } catch (\Exception $e) {
            // Heartbeat is best-effort — circuit breaker handles repeated failures
        }
    }
}
