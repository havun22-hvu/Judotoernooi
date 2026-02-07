<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Creates labeled database backups at critical workflow milestones.
 * Only runs on production (MySQL). Skipped on local (SQLite).
 */
class BackupService
{
    private string $backupDir = '/var/backups/havun/milestones';

    /**
     * Create a labeled backup before a destructive operation.
     *
     * @param string $label  Short identifier, e.g. 'voor-verdeling-matten'
     * @return string|null   Path to backup file, or null if skipped
     */
    public function maakMilestoneBackup(string $label): ?string
    {
        // Only on production/staging (MySQL on Linux)
        if (config('database.default') !== 'mysql' || PHP_OS_FAMILY === 'Windows') {
            return null;
        }

        $database = config('database.connections.mysql.database');
        $timestamp = now()->format('Y-m-d_H-i-s');
        $safeLabel = preg_replace('/[^a-zA-Z0-9_-]/', '_', $label);
        $file = "{$this->backupDir}/{$database}_{$safeLabel}_{$timestamp}.sql.gz";

        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }

        $command = "mysqldump --single-transaction --quick {$database} 2>/dev/null | gzip > {$file}";
        exec($command, $output, $returnCode);

        if ($returnCode === 0) {
            Log::info("Milestone backup created: {$file}");

            // Cleanup: keep max 20 milestone backups
            $this->cleanupOudeBackups(20);

            return $file;
        }

        Log::error("Milestone backup failed for: {$label}");
        return null;
    }

    private function cleanupOudeBackups(int $keep): void
    {
        $files = glob("{$this->backupDir}/*.sql.gz");
        if (count($files) > $keep) {
            usort($files, fn($a, $b) => filemtime($a) - filemtime($b));
            $toDelete = array_slice($files, 0, count($files) - $keep);
            foreach ($toDelete as $file) {
                @unlink($file);
            }
        }
    }
}
