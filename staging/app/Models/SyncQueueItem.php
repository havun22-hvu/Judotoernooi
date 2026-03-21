<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncQueueItem extends Model
{
    protected $table = 'sync_queue';

    protected $fillable = [
        'toernooi_id',
        'table_name',
        'record_id',
        'action',
        'payload',
        'synced_at',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'synced_at' => 'datetime',
    ];

    public function toernooi(): BelongsTo
    {
        return $this->belongsTo(Toernooi::class);
    }

    /**
     * Scope: only unsynced items
     */
    public function scopeUnsynced($query)
    {
        return $query->whereNull('synced_at');
    }

    /**
     * Scope: failed items (have error message but not synced)
     */
    public function scopeFailed($query)
    {
        return $query->whereNull('synced_at')
            ->whereNotNull('error_message');
    }

    /**
     * Scope: for a specific toernooi
     */
    public function scopeForToernooi($query, $toernooiId)
    {
        return $query->where('toernooi_id', $toernooiId);
    }

    /**
     * Mark this item as synced
     */
    public function markSynced(): void
    {
        $this->update([
            'synced_at' => now(),
            'error_message' => null,
        ]);
    }

    /**
     * Mark this item as failed
     */
    public function markFailed(string $message): void
    {
        $this->update([
            'error_message' => $message,
        ]);
    }

    /**
     * Create a queue item for a model change
     */
    public static function queueChange(Model $model, string $action): self
    {
        $tableName = $model->getTable();
        $toernooiId = $model->toernooi_id ?? $model->poule?->toernooi_id ?? null;

        if (!$toernooiId) {
            throw new \Exception("Cannot determine toernooi_id for {$tableName}");
        }

        return self::create([
            'toernooi_id' => $toernooiId,
            'table_name' => $tableName,
            'record_id' => $model->id,
            'action' => $action,
            'payload' => $model->toArray(),
        ]);
    }
}
