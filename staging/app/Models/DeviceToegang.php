<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DeviceToegang extends Model
{
    protected $table = 'device_toegangen';

    protected $fillable = [
        'toernooi_id',
        'naam',
        'telefoon',
        'email',
        'rol',
        'mat_nummer',
        'code',
        'pincode',
        'device_token',
        'device_info',
        'gebonden_op',
        'laatst_actief',
    ];

    protected $casts = [
        'gebonden_op' => 'datetime',
        'laatst_actief' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (DeviceToegang $toegang) {
            if (empty($toegang->code)) {
                $toegang->code = self::generateCode();
            }
            if (empty($toegang->pincode)) {
                $toegang->pincode = self::generatePincode();
            }
        });
    }

    public static function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(12));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    public static function generatePincode(): string
    {
        return str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    public function toernooi(): BelongsTo
    {
        return $this->belongsTo(Toernooi::class);
    }

    public function getUrl(): string
    {
        $toernooi = $this->toernooi;

        return route('toegang.show', [
            'organisator' => $toernooi->organisator->slug,
            'toernooi' => $toernooi->slug,
            'code' => $this->code,
        ]);
    }

    public function isGebonden(): bool
    {
        return !empty($this->device_token);
    }

    public function bind(string $token, string $deviceInfo): void
    {
        $this->update([
            'device_token' => $token,
            'device_info' => $deviceInfo,
            'gebonden_op' => now(),
            'laatst_actief' => now(),
        ]);
    }

    public function reset(): void
    {
        $this->update([
            'device_token' => null,
            'device_info' => null,
            'gebonden_op' => null,
        ]);
    }

    public function updateLaatstActief(): void
    {
        $this->update(['laatst_actief' => now()]);
    }

    public function getLabel(): string
    {
        if ($this->rol === 'mat' && $this->mat_nummer) {
            return "Mat {$this->mat_nummer}";
        }

        return ucfirst($this->rol);
    }

    public function getStatusText(): string
    {
        if ($this->isGebonden()) {
            return $this->device_info ?? 'Gebonden';
        }

        return 'Wacht op binding';
    }

    public static function generateDeviceToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
