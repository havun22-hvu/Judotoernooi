<?php

namespace App\Console\Commands;

use App\Models\Poule;
use App\Models\Toernooi;
use Illuminate\Console\Command;

class HerberekePouleStatistieken extends Command
{
    protected $signature = 'poules:herbereken {toernooi?}';
    protected $description = 'Herbereken poule statistieken (aantal_judokas en aantal_wedstrijden)';

    public function handle(): int
    {
        $toernooiId = $this->argument('toernooi');

        $query = Poule::query();
        if ($toernooiId) {
            $query->where('toernooi_id', $toernooiId);
        }

        $poules = $query->get();
        $this->info("Herberekenen van {$poules->count()} poules...");

        $bar = $this->output->createProgressBar($poules->count());

        foreach ($poules as $poule) {
            $poule->updateStatistieken();
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Klaar!');

        return Command::SUCCESS;
    }
}
