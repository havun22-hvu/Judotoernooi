<?php

namespace App\Console\Commands;

use App\Models\Poule;
use App\Models\Toernooi;
use Illuminate\Console\Command;

class HerberekePouleStatistieken extends Command
{
    protected $signature = 'poules:herbereken {toernooi?} {--check : Alleen controleren, niet wijzigen}';
    protected $description = 'Herbereken poule statistieken (aantal_judokas en aantal_wedstrijden)';

    public function handle(): int
    {
        $toernooiId = $this->argument('toernooi');
        $checkOnly = $this->option('check');

        $query = Poule::query()->with('judokas');
        if ($toernooiId) {
            $query->where('toernooi_id', $toernooiId);
        }

        $poules = $query->get();
        $this->info(($checkOnly ? 'Controleren' : 'Herberekenen') . " van {$poules->count()} poules...");

        $fouten = [];
        $gecorrigeerd = 0;

        foreach ($poules as $poule) {
            $werkelijkJudokas = $poule->judokas->count();
            $werkelijkWedstrijden = $poule->berekenAantalWedstrijden($werkelijkJudokas);

            if ($poule->aantal_judokas != $werkelijkJudokas || $poule->aantal_wedstrijden != $werkelijkWedstrijden) {
                $fouten[] = [
                    'poule' => $poule->nummer,
                    'titel' => $poule->titel,
                    'db_judokas' => $poule->aantal_judokas,
                    'werkelijk_judokas' => $werkelijkJudokas,
                    'db_wed' => $poule->aantal_wedstrijden,
                    'werkelijk_wed' => $werkelijkWedstrijden,
                ];

                if (!$checkOnly) {
                    $poule->updateStatistieken();
                    $gecorrigeerd++;
                }
            }
        }

        if (empty($fouten)) {
            $this->info('✓ Alle poule statistieken zijn correct!');
        } else {
            $this->warn(count($fouten) . ' poules met afwijkende statistieken:');
            $this->table(
                ['Poule', 'Titel', 'DB Jud', 'Werkelijk', 'DB Wed', 'Werkelijk'],
                array_map(fn($f) => [$f['poule'], $f['titel'], $f['db_judokas'], $f['werkelijk_judokas'], $f['db_wed'], $f['werkelijk_wed']], $fouten)
            );

            if ($checkOnly) {
                $this->info("Gebruik 'php artisan poules:herbereken' zonder --check om te corrigeren.");
            } else {
                $this->info("✓ {$gecorrigeerd} poules gecorrigeerd.");
            }
        }

        return Command::SUCCESS;
    }
}
