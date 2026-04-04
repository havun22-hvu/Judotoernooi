<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class SafeMigrateFresh extends Command
{
    protected $signature = 'migrate:safe-fresh {--seed : Run seeders after migration}';
    protected $description = 'Safe migrate:fresh with automatic backup and restore (staging/production)';

    public function handle(BackupService $backupService): int
    {
        if (!BackupService::isServerEnvironment()) {
            $this->info('Local environment — running migrate:fresh directly.');
            return $this->call('migrate:fresh', [
                '--seed' => $this->option('seed'),
                '--force' => true,
            ]);
        }

        $database = config('database.connections.mysql.database');
        $this->warn("Database: {$database}");
        $this->warn('This will wipe ALL data, then restore from backup.');

        if (!$this->confirm('Continue with safe migrate:fresh?')) {
            $this->info('Cancelled.');
            return 0;
        }

        // Step 1: Backup
        $this->info('Step 1/3: Creating backup...');
        $backupFile = $backupService->maakMilestoneBackup('voor-migrate-fresh');

        if (!$backupFile) {
            $this->error('Backup failed! Aborting migrate:fresh.');
            return 1;
        }

        $this->info("  Backup: {$backupFile}");

        // Step 2: migrate:fresh (mark as safe so AppServiceProvider allows it)
        $this->info('Step 2/3: Running migrate:fresh...');
        app()->instance('migrate:safe-fresh-running', true);
        $result = $this->call('migrate:fresh', [
            '--seed' => $this->option('seed'),
            '--force' => true,
        ]);

        if ($result !== 0) {
            $this->error('migrate:fresh failed! Restoring from backup...');
            $backupService->restoreFromBackup($backupFile);
            return 1;
        }

        // Step 3: Restore data
        $this->info('Step 3/3: Restoring data from backup...');
        $restored = $backupService->restoreFromBackup($backupFile);

        if ($restored) {
            $this->info('Done! Database has fresh schema WITH all data restored.');
        } else {
            $this->error("Restore failed! Manual restore needed:");
            $this->error("   gunzip -c {$backupFile} | mysql -u root {$database}");
        }

        return $restored ? 0 : 1;
    }
}
