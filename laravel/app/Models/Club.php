<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Club extends Model
{
    use HasFactory;

    protected $fillable = [
        'naam',
        'afkorting',
        'plaats',
        'email',
        'email2',
        'contact_naam',
        'telefoon',
        'website',
        'portal_code',
        'pincode',
    ];

    protected $hidden = [
        'pincode',
    ];

    protected static function booted(): void
    {
        static::creating(function (Club $club) {
            if (empty($club->portal_code)) {
                $club->portal_code = self::generatePortalCode();
            }
            if (empty($club->pincode)) {
                $club->pincode = self::generatePincode();
            }
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

    public static function generatePincode(): string
    {
        return str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    }

    public function getPortalUrl(): string
    {
        return url('/school/' . $this->portal_code);
    }

    public function checkPincode(string $pincode): bool
    {
        return $this->pincode === $pincode;
    }

    public function regeneratePincode(): string
    {
        $this->pincode = self::generatePincode();
        $this->save();
        return $this->pincode;
    }

    public function judokas(): HasMany
    {
        return $this->hasMany(Judoka::class);
    }

    public function coaches(): HasMany
    {
        return $this->hasMany(Coach::class);
    }

    public function coachesVoorToernooi(int $toernooiId): HasMany
    {
        return $this->coaches()->where('toernooi_id', $toernooiId);
    }

    public function coachKaarten(): HasMany
    {
        return $this->hasMany(CoachKaart::class);
    }

    public function coachKaartenVoorToernooi(int $toernooiId): HasMany
    {
        return $this->coachKaarten()->where('toernooi_id', $toernooiId);
    }

    /**
     * Calculate number of coach cards for this club in a tournament
     * Based on the largest block (not total judokas) since coaches only need
     * to be present for their judokas in that block
     */
    public function berekenAantalCoachKaarten(Toernooi $toernooi): int
    {
        $perCoach = $toernooi->judokas_per_coach ?? 5;

        // Get all judokas for this club in this tournament
        $judokas = $this->judokas()
            ->where('toernooi_id', $toernooi->id)
            ->with('poules.blok')
            ->get();

        if ($judokas->isEmpty()) {
            return 0; // No judokas = no coach cards
        }

        // Count judokas per blok
        $judokasPerBlok = [];
        foreach ($judokas as $judoka) {
            foreach ($judoka->poules as $poule) {
                if ($poule->blok_id) {
                    $blokId = $poule->blok_id;
                    $judokasPerBlok[$blokId] = ($judokasPerBlok[$blokId] ?? 0) + 1;
                }
            }
        }

        // If no blokken assigned yet, return 1 (minimum)
        // Correct number will be calculated after poule-indeling
        if (empty($judokasPerBlok)) {
            return 1;
        }

        // Use the largest block to determine number of coach cards needed
        $maxJudokasInBlok = max($judokasPerBlok);

        return max(1, (int) ceil($maxJudokasInBlok / $perCoach));
    }

    public static function findOrCreateByName(string $naam): self
    {
        return self::firstOrCreate(
            ['naam' => trim($naam)],
            ['afkorting' => substr(trim($naam), 0, 10)]
        );
    }
}
