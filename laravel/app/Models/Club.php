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
     *
     * DURING INSCHRIJVING: Always returns 1 (one card per club)
     * AFTER VOORBEREIDING: Calculate based on largest block
     *
     * @param Toernooi $toernooi
     * @param bool $forceCalculate Force calculation based on blokken (used after "Einde Voorbereiding")
     */
    public function berekenAantalCoachKaarten(Toernooi $toernooi, bool $forceCalculate = false): int
    {
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

        // During inschrijving (no blokken assigned yet): always 1 card per club
        // Only calculate more cards after "Einde Voorbereiding" when forceCalculate is true
        if (empty($judokasPerBlok)) {
            return 1;
        }

        // If blokken ARE assigned but forceCalculate is false, still return 1
        // This ensures portal always shows 1 card until organisator runs "Genereer Coachkaarten"
        if (!$forceCalculate) {
            return 1;
        }

        // After voorbereiding: calculate based on largest block
        $perCoach = $toernooi->judokas_per_coach ?? 5;
        $maxJudokasInBlok = max($judokasPerBlok);

        return max(1, (int) ceil($maxJudokasInBlok / $perCoach));
    }

    public static function findOrCreateByName(string $naam): self
    {
        $naam = trim($naam);

        // 1. Exact match
        $club = self::where('naam', $naam)->first();
        if ($club) {
            return $club;
        }

        // 2. Case-insensitive match
        $club = self::whereRaw('LOWER(naam) = ?', [strtolower($naam)])->first();
        if ($club) {
            return $club;
        }

        // 3. Fuzzy match - check if one name contains the other (handles "Cees Veen" vs "Judoschool Cees Veen")
        $naamLower = strtolower($naam);
        $clubs = self::all();
        foreach ($clubs as $bestaandeClub) {
            $bestaandeLower = strtolower($bestaandeClub->naam);
            // Check if one contains the other completely
            if (str_contains($naamLower, $bestaandeLower) || str_contains($bestaandeLower, $naamLower)) {
                return $bestaandeClub;
            }
        }

        // 4. No match found - create new club
        return self::create([
            'naam' => $naam,
            'afkorting' => substr($naam, 0, 10),
        ]);
    }
}
