<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Coach extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'toernooi_id',
        'uuid',
        'naam',
        'email',
        'telefoon',
        'pincode',
        'laatst_ingelogd_op',
    ];

    protected $casts = [
        'laatst_ingelogd_op' => 'datetime',
    ];

    protected $hidden = [
        'pincode',
    ];

    protected static function booted(): void
    {
        static::creating(function (Coach $coach) {
            if (empty($coach->uuid)) {
                $coach->uuid = (string) Str::uuid();
            }
            if (empty($coach->pincode)) {
                $coach->pincode = self::generatePincode();
            }
        });
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function toernooi(): BelongsTo
    {
        return $this->belongsTo(Toernooi::class);
    }

    public static function generatePincode(): string
    {
        return str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    public function checkPincode(string $pincode): bool
    {
        return $this->pincode === $pincode;
    }

    public function updateLaatstIngelogd(): void
    {
        $this->laatst_ingelogd_op = now();
        $this->save();
    }

    public function regeneratePincode(): string
    {
        $this->pincode = self::generatePincode();
        $this->save();
        return $this->pincode;
    }

    public function getPortalUrl(): string
    {
        return route('coach.portal.uuid', $this->uuid);
    }
}
