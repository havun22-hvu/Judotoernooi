<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncStatus extends Model
{
    protected $table = 'sync_status';

    protected $fillable = [
        'toernooi_id',
        'direction',
        'last_sync_at',
        'records_synced',
        'status',
        'error_message',
    ];

    protected $casts = [
        'last_sync_at' => 'datetime',
    ];

    public function toernooi(): BelongsTo
    {
        return $this->belongsTo(Toernooi::class);
    }

    /**
     * Get or create sync status for a toernooi/direction
     */
    public static function getOrCreate(int $toernooiId, string $direction): self
    {
        return self::firstOrCreate(
            [
                'toernooi_id' => $toernooiId,
                'direction' => $direction,
            ],
            [
                'status' => 'idle',
                'records_synced' => 0,
            ]
        );
    }

    /**
     * Check if sync is healthy (last sync < 5 minutes ago and status is success)
     */
    public function isHealthy(): bool
    {
        if ($this->status !== 'success') {
            return false;
        }

        if (!$this->last_sync_at) {
            return false;
        }

        return $this->last_sync_at->diffInMinutes(now()) < 5;
    }

    /**
     * Check if currently syncing
     */
    public function isSyncing(): bool
    {
        return $this->status === 'syncing';
    }

    /**
     * Mark as syncing
     */
    public function startSync(): void
    {
        $this->update([
            'status' => 'syncing',
            'error_message' => null,
        ]);
    }

    /**
     * Mark sync as complete
     */
    public function completeSync(int $recordsSynced): void
    {
        $this->update([
            'status' => 'success',
            'last_sync_at' => now(),
            'records_synced' => $recordsSynced,
            'error_message' => null,
        ]);
    }

    /**
     * Mark sync as failed
     */
    public function failSync(string $message): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $message,
        ]);
    }

    /**
     * Get human-readable status
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'idle' => 'Wachtend',
            'syncing' => 'Bezig...',
            'success' => 'Geslaagd',
            'failed' => 'Mislukt',
            default => 'Onbekend',
        };
    }

    /**
     * Get time since last sync in human readable format
     */
    public function getTimeSinceSync(): ?string
    {
        if (!$this->last_sync_at) {
            return null;
        }

        return $this->last_sync_at->diffForHumans();
    }
}
