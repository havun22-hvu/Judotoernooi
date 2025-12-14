<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CoachKaart extends Model
{
    protected $table = 'coach_kaarten';

    protected $fillable = [
        'toernooi_id',
        'club_id',
        'naam',
        'foto',
        'qr_code',
        'activatie_token',
        'is_geactiveerd',
        'geactiveerd_op',
        'is_gescand',
        'gescand_op',
    ];

    protected $casts = [
        'is_geactiveerd' => 'boolean',
        'geactiveerd_op' => 'datetime',
        'is_gescand' => 'boolean',
        'gescand_op' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CoachKaart $kaart) {
            if (empty($kaart->qr_code)) {
                $kaart->qr_code = self::generateQrCode();
            }
        });
    }

    public static function generateQrCode(): string
    {
        do {
            $code = 'CK' . strtoupper(Str::random(10));
        } while (self::where('qr_code', $code)->exists());

        return $code;
    }

    public function toernooi(): BelongsTo
    {
        return $this->belongsTo(Toernooi::class);
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function markeerGescand(): void
    {
        $this->update([
            'is_gescand' => true,
            'gescand_op' => now(),
        ]);
    }

    public function getQrUrl(): string
    {
        return route('coach-kaart.scan', $this->qr_code);
    }

    public function getShowUrl(): string
    {
        return route('coach-kaart.show', $this->qr_code);
    }

    public function getFotoUrl(): ?string
    {
        if (!$this->foto) {
            return null;
        }
        return asset('storage/' . $this->foto);
    }

    public function isGeldig(): bool
    {
        return $this->is_geactiveerd && $this->foto;
    }

    public static function generateActivatieToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
