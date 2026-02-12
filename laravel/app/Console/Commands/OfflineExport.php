<?php

namespace App\Console\Commands;

use App\Models\Toernooi;
use App\Services\OfflineExportService;
use Illuminate\Console\Command;

class OfflineExport extends Command
{
    protected $signature = 'offline:export {--toernooi= : Toernooi ID} {--license : Also generate license.json}';
    protected $description = 'Export tournament data to a standalone SQLite database for offline use';

    public function handle(OfflineExportService $exportService): int
    {
        $toernooiId = $this->option('toernooi');

        if (!$toernooiId) {
            $toernooi = Toernooi::where('is_actief', true)->first();
            if (!$toernooi) {
                $this->error('Geen actief toernooi gevonden. Gebruik --toernooi=ID');
                return self::FAILURE;
            }
        } else {
            $toernooi = Toernooi::find($toernooiId);
            if (!$toernooi) {
                $this->error("Toernooi {$toernooiId} niet gevonden.");
                return self::FAILURE;
            }
        }

        $this->info("Exporteren: {$toernooi->naam} (ID: {$toernooi->id})");

        try {
            $dbPath = $exportService->export($toernooi);
            $this->info("Database: {$dbPath}");
            $this->info("Grootte: " . round(filesize($dbPath) / 1024) . " KB");

            if ($this->option('license')) {
                $license = $exportService->generateLicense($toernooi);
                $licensePath = storage_path('app/offline_license.json');
                file_put_contents($licensePath, json_encode($license, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $this->info("License: {$licensePath}");
                $this->info("Geldig tot: {$license['expires_at']}");
            }

            $stats = $this->getStats($dbPath);
            $this->table(['Tabel', 'Rijen'], $stats);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Export mislukt: {$e->getMessage()}");
            $this->error("In: {$e->getFile()}:{$e->getLine()}");
            return self::FAILURE;
        }
    }

    private function getStats(string $dbPath): array
    {
        $pdo = new \PDO('sqlite:' . $dbPath);
        $tables = ['toernooien', 'clubs', 'judokas', 'blokken', 'matten', 'poules', 'poule_judoka', 'wedstrijden', 'device_toegangen', 'coach_kaarten'];

        $stats = [];
        foreach ($tables as $table) {
            try {
                $count = $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
                $stats[] = [$table, $count];
            } catch (\Exception $e) {
                $stats[] = [$table, 'ERROR'];
            }
        }

        return $stats;
    }
}
