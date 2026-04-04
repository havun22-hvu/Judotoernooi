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
     * Check if running on a server with MySQL (staging/production).
     * Returns false on local dev (SQLite/Windows).
     */
    public static function isServerEnvironment(): bool
    {
        return config('database.default') === 'mysql' && PHP_OS_FAMILY !== 'Windows';
    }

    /**
     * Create a labeled backup before a destructive operation.
     *
     * @param string $label  Short identifier, e.g. 'voor-verdeling-matten'
     * @return string|null   Path to backup file, or null if skipped
     */
    public function maakMilestoneBackup(string $label): ?string
    {
        if (!static::isServerEnvironment()) {
            return null;
        }

        $database = config('database.connections.mysql.database');
        $timestamp = now()->format('Y-m-d_H-i-s');
        $safeLabel = preg_replace('/[^a-zA-Z0-9_-]/', '_', $label);
        $file = "{$this->backupDir}/{$database}_{$safeLabel}_{$timestamp}.sql.gz";

        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }

        $dbArg = escapeshellarg($database);
        $fileArg = escapeshellarg($file);
        $command = "mysqldump --single-transaction --quick {$dbArg} 2>/dev/null | gzip > {$fileArg}";
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

    /**
     * Restore database from a gzipped backup file.
     */
    public function restoreFromBackup(string $backupFile): bool
    {
        $database = config('database.connections.mysql.database');
        $dbArg = escapeshellarg($database);
        $fileArg = escapeshellarg($backupFile);
        $command = "gunzip -c {$fileArg} | mysql -u root {$dbArg} 2>&1";
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            Log::error("Backup restore failed: " . implode("\n", $output));
        }

        return $returnCode === 0;
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
