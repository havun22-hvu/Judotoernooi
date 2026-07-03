<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * API token for an external integration (HavunClub) scoped to one Organisator.
 *
 * The token IS the tenant identification — a request authenticated with it acts
 * on behalf of {@see static::organisator()} only.
 */
class ClubApiToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'organisator_id',
        'token',
        'label',
        'actief',
        'last_used_at',
    ];

    protected $casts = [
        'actief' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function organisator(): BelongsTo
    {
        return $this->belongsTo(Organisator::class);
    }

    /**
     * Generate a fresh, unguessable token string (not yet persisted).
     */
    public static function generateToken(): string
    {
        return 'jtc_' . Str::random(60);
    }

    public function markUsed(): void
    {
        $this->forceFill(['last_used_at' => now()])->saveQuietly();
    }
}
