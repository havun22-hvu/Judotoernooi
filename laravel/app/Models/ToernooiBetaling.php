<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToernooiBetaling extends Model
{
    protected $table = 'toernooi_betalingen';

    public const STATUS_OPEN = 'open';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELED = 'canceled';

    protected $fillable = [
        'toernooi_id',
        'organisator_id',
        'mollie_payment_id',
        'bedrag',
        'tier',
        'max_judokas',
        'status',
        'betaald_op',
    ];

    protected $casts = [
        'bedrag' => 'decimal:2',
        'max_judokas' => 'integer',
        'betaald_op' => 'datetime',
    ];

    public function toernooi(): BelongsTo
    {
        return $this->belongsTo(Toernooi::class);
    }

    public function organisator(): BelongsTo
    {
        return $this->belongsTo(Organisator::class);
    }

    public function isBetaald(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function markeerAlsBetaald(): void
    {
        $this->update([
            'status' => self::STATUS_PAID,
            'betaald_op' => now(),
        ]);

        // Activate paid plan on the tournament
        $this->toernooi->update([
            'plan_type' => 'paid',
            'paid_tier' => $this->tier,
            'paid_max_judokas' => $this->max_judokas,
            'max_judokas' => $this->max_judokas, // Also set the visible max_judokas field
            'paid_at' => now(),
            'toernooi_betaling_id' => $this->id,
        ]);
    }
}
