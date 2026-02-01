<?php

namespace App\Observers;

use App\Models\Judoka;
use App\Models\SyncQueueItem;
use App\Models\Wedstrijd;
use Illuminate\Database\Eloquent\Model;

class SyncQueueObserver
{
    /**
     * Fields to track for Wedstrijd changes
     * Only queue when these fields change
     */
    private array $wedstrijdSyncFields = [
        'score_wit',
        'score_blauw',
        'winnaar_id',
        'is_gespeeld',
    ];

    /**
     * Fields to track for Judoka changes
     * Only queue when these fields change
     */
    private array $judokaSyncFields = [
        'gewicht',
        'aanwezigheid',
    ];

    /**
     * Handle the "created" event.
     * Note: We don't queue creates - those come from cloud
     */
    public function created(Model $model): void
    {
        // Skip - creates come from cloud sync
    }

    /**
     * Handle the "updated" event.
     */
    public function updated(Model $model): void
    {
        // Only run on local server
        if (!$this->isLocalServer()) {
            return;
        }

        // Check if relevant fields changed
        if (!$this->hasRelevantChanges($model)) {
            return;
        }

        try {
            SyncQueueItem::queueChange($model, 'update');
        } catch (\Exception $e) {
            // Log but don't fail - queue is not critical
            \Log::warning("Failed to queue sync item", [
                'model' => get_class($model),
                'id' => $model->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the "deleted" event.
     */
    public function deleted(Model $model): void
    {
        // Only run on local server
        if (!$this->isLocalServer()) {
            return;
        }

        try {
            SyncQueueItem::queueChange($model, 'delete');
        } catch (\Exception $e) {
            \Log::warning("Failed to queue sync delete", [
                'model' => get_class($model),
                'id' => $model->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if this is a local server (not cloud)
     */
    private function isLocalServer(): bool
    {
        $role = config('local-server.role');
        return in_array($role, ['primary', 'standby']);
    }

    /**
     * Check if any sync-relevant fields changed
     */
    private function hasRelevantChanges(Model $model): bool
    {
        if ($model instanceof Wedstrijd) {
            return $model->wasChanged($this->wedstrijdSyncFields);
        }

        if ($model instanceof Judoka) {
            return $model->wasChanged($this->judokaSyncFields);
        }

        return false;
    }
}
