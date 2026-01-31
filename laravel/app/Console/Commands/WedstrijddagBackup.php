<?php

namespace App\Console\Commands;

use App\Models\Toernooi;
use Illuminate\Console\Command;

class WedstrijddagBackup extends Command
{
    protected $signature = 'backup:wedstrijddag';
    protected $description = 'Backup database elke minuut tijdens actieve wedstrijddag';

    public function handle(): int
    {
        // Check of er een toernooi op wedstrijddag is
        $actiefToernooi = Toernooi::where('datum', today())
            ->get()
            ->first(fn($t) => $t->isWedstrijddagGestart());

        if (!$actiefToernooi) {
            return self::SUCCESS; // Geen wedstrijddag, skip
        }

        $timestamp = now()->format('Y-m-d_H-i');
        $backupDir = '/var/backups/havun/wedstrijddag';
        $database = config('database.connections.mysql.database');
        $file = "{$backupDir}/judo_toernooi_{$timestamp}.sql.gz";

        // Create backup directory
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        // Mysqldump
        $command = "mysqldump --single-transaction --quick {$database} 2>/dev/null | gzip > {$file}";
        exec($command, $output, $returnCode);

        if ($returnCode === 0) {
            $this->info("Wedstrijddag backup: {$file}");

            // Cleanup: keep last 60 minutes (60 files)
            exec("find {$backupDir} -name '*.sql.gz' -mmin +60 -delete 2>/dev/null");
        } else {
            $this->error("Backup failed!");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
