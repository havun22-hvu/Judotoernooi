<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Betaling extends Model
{
    protected $table = 'betalingen';

    protected $fillable = [
        'toernooi_id',
        'club_id',
        'mollie_payment_id',
        'bedrag',
        'aantal_judokas',
        'status',
        'betaald_op',
    ];

    protected $casts = [
        'bedrag' => 'decimal:2',
        'betaald_op' => 'datetime',
    ];

    // Statuses
    const STATUS_OPEN = 'open';
    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_FAILED = 'failed';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELED = 'canceled';

    public function toernooi(): BelongsTo
    {
        return $this->belongsTo(Toernooi::class);
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function judokas(): HasMany
    {
        return $this->hasMany(Judoka::class);
    }

    public function isBetaald(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_PENDING]);
    }

    /**
     * Markeer als betaald en update alle gekoppelde judoka's
     */
    public function markeerAlsBetaald(): void
    {
        $this->update([
            'status' => self::STATUS_PAID,
            'betaald_op' => now(),
        ]);

        // Update alle gekoppelde judoka's
        $this->judokas()->update(['betaald_op' => now()]);
    }
}
