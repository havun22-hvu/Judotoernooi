<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Audit record for a single sync conflict.
 *
 * A conflict is created whenever an incoming sync item targets a record
 * that has ALSO changed on the cloud side since the last successful sync.
 * Both versions are stored verbatim so an admin can review later.
 */
class SyncConflict extends Model
{
    protected $table = 'sync_conflicts';

    public const WINNER_LOCAL = 'local';
    public const WINNER_CLOUD = 'cloud';

    /**
     * Tables where the LOCAL (mat) side is the source of truth during a
     * tournament. Live scoring data must never be overwritten by a stale
     * cloud copy.
     */
    public const LOCAL_AUTHORITY_TABLES = [
        'wedstrijden',
        'scores',
    ];

    protected $fillable = [
        'table_name',
        'record_id',
        'local_data',
        'cloud_data',
        'applied_winner',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'local_data' => 'array',
        'cloud_data' => 'array',
        'resolved_at' => 'datetime',
    ];

    /**
     * Decide which side wins for the given table.
     */
    public static function winnerFor(string $table): string
    {
        return in_array($table, self::LOCAL_AUTHORITY_TABLES, true)
            ? self::WINNER_LOCAL
            : self::WINNER_CLOUD;
    }

    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }
}
