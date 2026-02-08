<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class MilestoneBackup extends Command
{
    protected $signature = 'backup:milestone {label=handmatig}';
    protected $description = 'Create a labeled database backup (staging/production only)';

    public function handle(BackupService $backupService): int
    {
        $label = $this->argument('label');
        $file = $backupService->maakMilestoneBackup($label);

        if ($file === null) {
            $this->warn('Backup skipped (only runs on MySQL/Linux).');
            return self::SUCCESS;
        }

        $this->info("Backup created: {$file}");
        return self::SUCCESS;
    }
}
