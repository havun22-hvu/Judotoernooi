<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
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
        'pincode',
        'activatie_token',
        'is_geactiveerd',
        'geactiveerd_op',
        'is_gescand',
        'gescand_op',
        'device_token',
        'device_info',
        'gebonden_op',
        'ingecheckt_op',
    ];

    protected $casts = [
        'is_geactiveerd' => 'boolean',
        'geactiveerd_op' => 'datetime',
        'is_gescand' => 'boolean',
        'gescand_op' => 'datetime',
        'gebonden_op' => 'datetime',
        'ingecheckt_op' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CoachKaart $kaart) {
            if (empty($kaart->qr_code)) {
                $kaart->qr_code = self::generateQrCode();
            }
            if (empty($kaart->pincode)) {
                $kaart->pincode = self::generatePincode();
            }
        });
    }

    public static function generatePincode(): string
    {
        return str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
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

    public function wisselingen(): HasMany
    {
        return $this->hasMany(CoachKaartWisseling::class)->orderBy('geactiveerd_op', 'desc');
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(CoachCheckin::class)->orderBy('created_at', 'desc');
    }

    public function checkinsVandaag(): HasMany
    {
        return $this->checkins()->whereDate('created_at', today());
    }

    public function huidigeWisseling(): ?CoachKaartWisseling
    {
        return $this->wisselingen()->whereNull('overgedragen_op')->first();
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

    public function isDeviceGebonden(): bool
    {
        return !empty($this->device_token);
    }

    public function bindDevice(string $token, string $deviceInfo): void
    {
        $this->update([
            'device_token' => $token,
            'device_info' => $deviceInfo,
            'gebonden_op' => now(),
        ]);
    }

    public function resetDevice(): void
    {
        $this->update([
            'device_token' => null,
            'device_info' => null,
            'gebonden_op' => null,
        ]);
    }

    /**
     * Transfer the coach card to a new coach.
     * Marks current coach as transferred and creates new wisseling record.
     */
    public function overdragen(string $naam, string $foto, string $deviceToken, string $deviceInfo): void
    {
        // Mark current wisseling as transferred
        $huidige = $this->huidigeWisseling();
        if ($huidige) {
            $huidige->update(['overgedragen_op' => now()]);
        }

        // Create new wisseling record
        $this->wisselingen()->create([
            'naam' => $naam,
            'foto' => $foto,
            'device_info' => $deviceInfo,
            'geactiveerd_op' => now(),
            'overgedragen_op' => null,
        ]);

        // Update the coach card itself
        $this->update([
            'naam' => $naam,
            'foto' => $foto,
            'is_geactiveerd' => true,
            'geactiveerd_op' => now(),
            'device_token' => $deviceToken,
            'device_info' => $deviceInfo,
            'gebonden_op' => now(),
        ]);
    }

    /**
     * First-time activation of the coach card.
     */
    public function activeer(string $naam, string $foto, string $deviceToken, string $deviceInfo): void
    {
        // Create first wisseling record
        $this->wisselingen()->create([
            'naam' => $naam,
            'foto' => $foto,
            'device_info' => $deviceInfo,
            'geactiveerd_op' => now(),
            'overgedragen_op' => null,
        ]);

        // Update the coach card
        $this->update([
            'naam' => $naam,
            'foto' => $foto,
            'is_geactiveerd' => true,
            'geactiveerd_op' => now(),
            'device_token' => $deviceToken,
            'device_info' => $deviceInfo,
            'gebonden_op' => now(),
        ]);
    }

    public function kanQrTonen(string $deviceToken): bool
    {
        // QR only visible if: device is bound, this is the bound device, and has photo
        return $this->isDeviceGebonden()
            && $this->device_token === $deviceToken
            && $this->foto;
    }

    public static function generateDeviceToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Check of coach is ingecheckt in de dojo
     */
    public function isIngecheckt(): bool
    {
        return $this->ingecheckt_op !== null;
    }

    /**
     * Check coach in bij dojo scanner
     */
    public function checkin(): void
    {
        $this->update(['ingecheckt_op' => now()]);

        // Log in history
        $this->checkins()->create([
            'toernooi_id' => $this->toernooi_id,
            'naam' => $this->naam,
            'club_naam' => $this->club->naam ?? 'Onbekend',
            'foto' => $this->foto,
            'actie' => 'in',
        ]);
    }

    /**
     * Check coach uit bij dojo scanner
     */
    public function checkout(): void
    {
        $this->update(['ingecheckt_op' => null]);

        // Log in history
        $this->checkins()->create([
            'toernooi_id' => $this->toernooi_id,
            'naam' => $this->naam,
            'club_naam' => $this->club->naam ?? 'Onbekend',
            'foto' => $this->foto,
            'actie' => 'uit',
        ]);
    }

    /**
     * Geforceerde uitcheck door hoofdjury
     */
    public function forceCheckout(): void
    {
        $this->update(['ingecheckt_op' => null]);

        // Log in history met geforceerd
        $this->checkins()->create([
            'toernooi_id' => $this->toernooi_id,
            'naam' => $this->naam,
            'club_naam' => $this->club->naam ?? 'Onbekend',
            'foto' => $this->foto,
            'actie' => 'uit_geforceerd',
            'geforceerd_door' => 'hoofdjury',
        ]);
    }

    /**
     * Check of overdracht mogelijk is (niet ingecheckt of incheck niet actief)
     */
    public function kanOverdragen(): bool
    {
        // Als incheck systeem niet actief is, altijd mogelijk
        if (!$this->toernooi?->coach_incheck_actief) {
            return true;
        }

        // Niet ingecheckt = kan overdragen
        return !$this->isIngecheckt();
    }
}
