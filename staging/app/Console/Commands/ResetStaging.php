<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetStaging extends Command
{
    protected $signature = 'staging:reset {--force : Skip confirmation}';
    protected $description = 'Reset all data on staging environment (tournaments, judokas, etc.)';

    public function handle(): int
    {
        // Safety check: only allow on staging
        if (app()->environment('production')) {
            $this->error('This command cannot run on production!');
            return 1;
        }

        if (!$this->option('force')) {
            if (!$this->confirm('This will DELETE ALL DATA. Are you sure?')) {
                $this->info('Cancelled.');
                return 0;
            }
        }

        $this->info('Resetting staging database...');

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        $tables = [
            'wedstrijden',
            'poule_judoka',
            'poules',
            'wegingen',
            'coach_kaarten',
            'coaches',
            'club_uitnodigingen',
            'betalingen',
            'judokas',
            'blokken',
            'matten',
            'organisator_toernooi',
            'toernooien',
            'clubs',
        ];

        foreach ($tables as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                DB::table($table)->truncate();
                $this->line("  Truncated: {$table}");
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->info('Staging reset complete!');
        return 0;
    }
}
