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
        'portal_code',
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
            // Portal access is now on Club model, not Coach
        });
    }

    public static function generatePortalCode(): string
    {
        $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz';
        $code = '';
        for ($i = 0; $i < 12; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $code;
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
        return str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
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
        return route('coach.portal.code', $this->portal_code);
    }
}
