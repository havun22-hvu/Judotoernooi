<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class QrLoginToken extends Model
{
    protected $fillable = [
        'token',
        'organisator_id',
        'status',
        'device_info',
        'expires_at',
        'approved_at',
        'approved_by_organisator_id',
    ];

    protected $casts = [
        'device_info' => 'array',
        'expires_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function organisator(): BelongsTo
    {
        return $this->belongsTo(Organisator::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Organisator::class, 'approved_by_organisator_id');
    }

    public static function generate(array $deviceInfo = []): self
    {
        return self::create([
            'token' => Str::random(64),
            'status' => 'pending',
            'device_info' => $deviceInfo,
            'expires_at' => now()->addMinutes(5),
        ]);
    }

    public static function findByToken(string $token): ?self
    {
        return self::where('token', $token)
            ->where('expires_at', '>', now())
            ->first();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && $this->expires_at->isFuture();
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isValid(): bool
    {
        return $this->status === 'pending' && $this->expires_at->isFuture();
    }

    public function approve(Organisator $approver): bool
    {
        $this->status = 'approved';
        $this->approved_at = now();
        $this->approved_by_organisator_id = $approver->id;
        $this->organisator_id = $approver->id;
        return $this->save();
    }

    public function markUsed(): bool
    {
        $this->status = 'used';
        return $this->save();
    }

    public function markExpired(): bool
    {
        $this->status = 'expired';
        return $this->save();
    }
}
