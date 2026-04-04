<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

/**
 * Blocks migrate:fresh on staging/production.
 * Use migrate:safe-fresh instead (backup → fresh → restore).
 */
class SafeMigrateFresh extends Command
{
    protected $signature = 'migrate:safe-fresh {--seed : Run seeders after migration}';
    protected $description = 'Safe migrate:fresh with automatic backup and restore (staging/production)';

    public function handle(BackupService $backupService): int
    {
        // On local (SQLite/Windows): just run migrate:fresh directly
        if (config('database.default') !== 'mysql' || PHP_OS_FAMILY === 'Windows') {
            $this->info('Local environment — running migrate:fresh directly.');
            return $this->call('migrate:fresh', [
                '--seed' => $this->option('seed'),
                '--force' => true,
            ]);
        }

        $database = config('database.connections.mysql.database');
        $this->warn("⚠️  Database: {$database}");
        $this->warn('⚠️  This will wipe ALL data, then restore from backup.');

        if (!$this->confirm('Continue with safe migrate:fresh?')) {
            $this->info('Cancelled.');
            return 0;
        }

        // Step 1: Backup
        $this->info('📦 Step 1/3: Creating backup...');
        $backupFile = $backupService->maakMilestoneBackup('voor-migrate-fresh');

        if (!$backupFile) {
            $this->error('Backup failed! Aborting migrate:fresh.');
            return 1;
        }

        $this->info("  ✓ Backup: {$backupFile}");

        // Step 2: migrate:fresh (mark as safe so AppServiceProvider allows it)
        $this->info('🔄 Step 2/3: Running migrate:fresh...');
        app()->instance('migrate:safe-fresh-running', true);
        $result = $this->call('migrate:fresh', [
            '--seed' => $this->option('seed'),
            '--force' => true,
        ]);

        if ($result !== 0) {
            $this->error('migrate:fresh failed! Restoring from backup...');
            $this->restoreBackup($backupFile, $database);
            return 1;
        }

        // Step 3: Restore data
        $this->info('📥 Step 3/3: Restoring data from backup...');
        $restored = $this->restoreBackup($backupFile, $database);

        if ($restored) {
            $this->info('✅ Done! Database has fresh schema WITH all data restored.');
        } else {
            $this->error("❌ Restore failed! Manual restore needed:");
            $this->error("   gunzip -c {$backupFile} | mysql -u root {$database}");
        }

        return $restored ? 0 : 1;
    }

    private function restoreBackup(string $backupFile, string $database): bool
    {
        $dbArg = escapeshellarg($database);
        $fileArg = escapeshellarg($backupFile);
        $command = "gunzip -c {$fileArg} | mysql -u root {$dbArg} 2>&1";
        exec($command, $output, $returnCode);

        return $returnCode === 0;
    }
}
