<?php

namespace App\Services;

use App\Models\Blok;
use App\Models\Club;
use App\Models\Judoka;
use App\Models\Mat;
use App\Models\Poule;
use App\Models\SyncQueueItem;
use App\Models\SyncStatus;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LocalSyncService
{
    private const CLOUD_URL = 'https://judotournament.org';
    private const TIMEOUT = 30;

    /**
     * Sync result object
     */
    public function createResult(): object
    {
        return (object) [
            'success' => true,
            'records_synced' => 0,
            'errors' => [],
            'details' => [],
        ];
    }

    /**
     * Cloud -> Local: Download all tournament data
     * Used: evening before + at startup
     */
    public function syncCloudToLocal(Toernooi $toernooi): object
    {
        $result = $this->createResult();
        $status = SyncStatus::getOrCreate($toernooi->id, 'cloud_to_local');
        $status->startSync();

        try {
            // Fetch data from cloud
            $response = Http::timeout(self::TIMEOUT)
                ->get(self::CLOUD_URL . "/api/sync/export/{$toernooi->id}");

            if (!$response->successful()) {
                throw new \Exception("Cloud server returned: {$response->status()}");
            }

            $data = $response->json();

            DB::transaction(function () use ($data, $toernooi, &$result) {
                // Import clubs
                if (isset($data['clubs'])) {
                    foreach ($data['clubs'] as $clubData) {
                        $this->importClub($clubData, $toernooi, $result);
                    }
                }

                // Import blokken
                if (isset($data['blokken'])) {
                    foreach ($data['blokken'] as $blokData) {
                        $this->importBlok($blokData, $toernooi, $result);
                    }
                }

                // Import matten
                if (isset($data['matten'])) {
                    foreach ($data['matten'] as $matData) {
                        $this->importMat($matData, $toernooi, $result);
                    }
                }

                // Import judokas
                if (isset($data['judokas'])) {
                    foreach ($data['judokas'] as $judokaData) {
                        $this->importJudoka($judokaData, $toernooi, $result);
                    }
                }

                // Import poules
                if (isset($data['poules'])) {
                    foreach ($data['poules'] as $pouleData) {
                        $this->importPoule($pouleData, $toernooi, $result);
                    }
                }

                // Import wedstrijden
                if (isset($data['wedstrijden'])) {
                    foreach ($data['wedstrijden'] as $wedstrijdData) {
                        $this->importWedstrijd($wedstrijdData, $toernooi, $result);
                    }
                }
            });

            $status->completeSync($result->records_synced);
        } catch (\Exception $e) {
            $result->success = false;
            $result->errors[] = $e->getMessage();
            $status->failSync($e->getMessage());
            Log::error("Cloud to Local sync failed", [
                'toernooi_id' => $toernooi->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Local -> Cloud: Push queued changes
     * Used: continuously during tournament day
     */
    public function syncLocalToCloud(Toernooi $toernooi): object
    {
        $result = $this->createResult();
        $status = SyncStatus::getOrCreate($toernooi->id, 'local_to_cloud');

        // Get unsynced items
        $queueItems = SyncQueueItem::unsynced()
            ->forToernooi($toernooi->id)
            ->orderBy('created_at')
            ->get();

        if ($queueItems->isEmpty()) {
            $result->details[] = 'Geen wijzigingen om te synchroniseren';
            return $result;
        }

        $status->startSync();

        try {
            // Prepare payload
            $payload = [
                'toernooi_id' => $toernooi->id,
                'items' => $queueItems->map(fn($item) => [
                    'id' => $item->id,
                    'table' => $item->table_name,
                    'record_id' => $item->record_id,
                    'action' => $item->action,
                    'payload' => $item->payload,
                ])->toArray(),
            ];

            // Send to cloud
            $response = Http::timeout(self::TIMEOUT)
                ->post(self::CLOUD_URL . '/api/sync/receive', $payload);

            if (!$response->successful()) {
                throw new \Exception("Cloud server returned: {$response->status()}");
            }

            $responseData = $response->json();

            // Mark items as synced based on response
            foreach ($queueItems as $item) {
                if (isset($responseData['synced']) && in_array($item->id, $responseData['synced'])) {
                    $item->markSynced();
                    $result->records_synced++;
                } elseif (isset($responseData['errors'][$item->id])) {
                    $item->markFailed($responseData['errors'][$item->id]);
                    $result->errors[] = "Item {$item->id}: {$responseData['errors'][$item->id]}";
                }
            }

            $status->completeSync($result->records_synced);
        } catch (\Exception $e) {
            $result->success = false;
            $result->errors[] = $e->getMessage();
            $status->failSync($e->getMessage());
            Log::error("Local to Cloud sync failed", [
                'toernooi_id' => $toernooi->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Process the sync queue for a toernooi
     * Returns number of successfully synced items
     */
    public function processQueue(Toernooi $toernooi): int
    {
        $result = $this->syncLocalToCloud($toernooi);
        return $result->records_synced;
    }

    /**
     * Get queue statistics for a toernooi
     */
    public function getQueueStats(int $toernooiId): array
    {
        return [
            'pending' => SyncQueueItem::unsynced()->forToernooi($toernooiId)->count(),
            'failed' => SyncQueueItem::failed()->forToernooi($toernooiId)->count(),
            'total_today' => SyncQueueItem::forToernooi($toernooiId)
                ->whereDate('created_at', today())
                ->count(),
        ];
    }

    // Import helpers

    private function importClub(array $data, Toernooi $toernooi, object &$result): void
    {
        Club::updateOrCreate(
            ['id' => $data['id']],
            collect($data)->except(['id'])->toArray()
        );
        $result->records_synced++;
        $result->details[] = "Club: {$data['naam']}";
    }

    private function importBlok(array $data, Toernooi $toernooi, object &$result): void
    {
        Blok::updateOrCreate(
            ['id' => $data['id']],
            collect($data)->except(['id'])->toArray()
        );
        $result->records_synced++;
        $result->details[] = "Blok: {$data['nummer']}";
    }

    private function importMat(array $data, Toernooi $toernooi, object &$result): void
    {
        Mat::updateOrCreate(
            ['id' => $data['id']],
            collect($data)->except(['id'])->toArray()
        );
        $result->records_synced++;
        $result->details[] = "Mat: {$data['nummer']}";
    }

    private function importJudoka(array $data, Toernooi $toernooi, object &$result): void
    {
        Judoka::updateOrCreate(
            ['id' => $data['id']],
            collect($data)->except(['id'])->toArray()
        );
        $result->records_synced++;
    }

    private function importPoule(array $data, Toernooi $toernooi, object &$result): void
    {
        $poule = Poule::updateOrCreate(
            ['id' => $data['id']],
            collect($data)->except(['id', 'judoka_ids'])->toArray()
        );

        // Sync judokas in poule
        if (isset($data['judoka_ids'])) {
            $poule->judokas()->sync($data['judoka_ids']);
        }

        $result->records_synced++;
        $result->details[] = "Poule: {$data['nummer']}";
    }

    private function importWedstrijd(array $data, Toernooi $toernooi, object &$result): void
    {
        Wedstrijd::updateOrCreate(
            ['id' => $data['id']],
            collect($data)->except(['id'])->toArray()
        );
        $result->records_synced++;
    }
}
