<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthDevice extends Model
{
    protected $fillable = [
        'organisator_id',
        'token',
        'pin_hash',
        'has_biometric',
        'device_fingerprint',
        'device_name',
        'browser',
        'os',
        'ip_address',
        'is_active',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'has_biometric' => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = ['token', 'pin_hash'];

    public function organisator(): BelongsTo
    {
        return $this->belongsTo(Organisator::class);
    }

    public static function createForOrganisator(Organisator $organisator, array $deviceInfo = []): self
    {
        return self::create([
            'organisator_id' => $organisator->id,
            'token' => Str::random(64),
            'device_name' => $deviceInfo['name'] ?? 'Onbekend apparaat',
            'browser' => $deviceInfo['browser'] ?? 'Unknown',
            'os' => $deviceInfo['os'] ?? 'Unknown',
            'ip_address' => $deviceInfo['ip'] ?? null,
            'is_active' => true,
            'last_used_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
    }

    public function isValid(): bool
    {
        return $this->is_active && $this->expires_at->isFuture();
    }

    public function touch($attribute = null): bool
    {
        $this->last_used_at = now();
        return parent::touch($attribute);
    }

    public static function findByToken(string $token): ?self
    {
        return self::where('token', $token)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->first();
    }

    public static function findByFingerprint(int $organisatorId, string $fingerprint): ?self
    {
        return self::where('organisator_id', $organisatorId)
            ->where('device_fingerprint', $fingerprint)
            ->where('is_active', true)
            ->first();
    }

    public static function findActiveByFingerprint(string $fingerprint): ?self
    {
        return self::where('device_fingerprint', $fingerprint)
            ->where('is_active', true)
            ->whereNotNull('pin_hash')
            ->first();
    }

    public static function findRegisteredByFingerprint(string $fingerprint): ?self
    {
        return self::where('device_fingerprint', $fingerprint)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNotNull('pin_hash')
                    ->orWhere('has_biometric', true);
            })
            ->first();
    }

    public function setPin(string $pin): bool
    {
        $this->pin_hash = Hash::make($pin . $this->id);
        return $this->save();
    }

    public function verifyPin(string $pin): bool
    {
        if (!$this->pin_hash) {
            return false;
        }
        return Hash::check($pin . $this->id, $this->pin_hash);
    }

    public function hasPin(): bool
    {
        return !empty($this->pin_hash);
    }

    public function enableBiometric(): bool
    {
        $this->has_biometric = true;
        return $this->save();
    }

    public static function findOrCreateForOrganisator(Organisator $organisator, string $fingerprint, array $deviceInfo = []): self
    {
        $device = self::findByFingerprint($organisator->id, $fingerprint);

        if ($device) {
            $device->touch();
            return $device;
        }

        return self::create([
            'organisator_id' => $organisator->id,
            'token' => Str::random(64),
            'device_fingerprint' => $fingerprint,
            'device_name' => $deviceInfo['name'] ?? 'Onbekend apparaat',
            'browser' => $deviceInfo['browser'] ?? 'Unknown',
            'os' => $deviceInfo['os'] ?? 'Unknown',
            'ip_address' => $deviceInfo['ip'] ?? null,
            'is_active' => true,
            'last_used_at' => now(),
            'expires_at' => now()->addDays(365),
        ]);
    }
}
