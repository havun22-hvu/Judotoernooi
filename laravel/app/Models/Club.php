<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Club extends Model
{
    use HasFactory;

    protected $fillable = [
        'organisator_id',
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
        'locale',
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

    /**
     * Get portal URL for a specific tournament (new URL structure).
     */
    public function getPortalUrl(Toernooi $toernooi): string
    {
        $pivot = $this->toernooien()->where('toernooien.id', $toernooi->id)->first()?->pivot;

        if (!$pivot || !$pivot->portal_code) {
            // Ensure pivot exists with code
            $toernooi->ensureClubPivot($this);
            $pivot = $this->toernooien()->where('toernooien.id', $toernooi->id)->first()->pivot;
        }

        return route('coach.portal.code', [
            'organisator' => $toernooi->organisator->slug,
            'toernooi' => $toernooi->slug,
            'code' => $pivot->portal_code,
        ]);
    }

    /**
     * Get portal code for a specific tournament.
     */
    public function getPortalCodeForToernooi(Toernooi $toernooi): ?string
    {
        return $this->toernooien()
            ->where('toernooien.id', $toernooi->id)
            ->first()
            ?->pivot
            ?->portal_code;
    }

    /**
     * Get pincode for a specific tournament.
     */
    public function getPincodeForToernooi(Toernooi $toernooi): ?string
    {
        return $this->toernooien()
            ->where('toernooien.id', $toernooi->id)
            ->first()
            ?->pivot
            ?->pincode;
    }

    /**
     * Check pincode for a specific tournament.
     */
    public function checkPincodeForToernooi(Toernooi $toernooi, string $pincode): bool
    {
        return $this->getPincodeForToernooi($toernooi) === $pincode;
    }

    /**
     * Regenerate pincode for a specific tournament.
     */
    public function regeneratePincodeForToernooi(Toernooi $toernooi): string
    {
        $newPincode = self::generatePincode();
        $this->toernooien()->updateExistingPivot($toernooi->id, ['pincode' => $newPincode]);
        return $newPincode;
    }

    /**
     * Legacy: Get portal URL using old portal_code on club (deprecated).
     * @deprecated Use getPortalUrl(Toernooi) instead
     */
    public function getLegacyPortalUrl(): string
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

    public function organisator(): BelongsTo
    {
        return $this->belongsTo(Organisator::class);
    }

    /**
     * Get all toernooien this club is linked to via pivot.
     */
    public function toernooien(): BelongsToMany
    {
        return $this->belongsToMany(Toernooi::class, 'club_toernooi')
            ->withPivot('portal_code', 'pincode')
            ->withTimestamps();
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

    /**
     * Find or create a club by name, with optional organisator scope
     * When organisator_id is provided, searches within that organisator's clubs first
     */
    public static function findOrCreateByName(string $naam, ?int $organisatorId = null): self
    {
        $naam = trim($naam);

        // Build base query - if organisator provided, search their clubs first
        $query = $organisatorId
            ? self::where('organisator_id', $organisatorId)
            : self::query();

        // 1. Exact match (within organisator scope if provided)
        $club = (clone $query)->where('naam', $naam)->first();
        if ($club) {
            return $club;
        }

        // 2. Case-insensitive match
        $club = (clone $query)->whereRaw('LOWER(naam) = ?', [strtolower($naam)])->first();
        if ($club) {
            return $club;
        }

        // 3. Fuzzy match - check if one name contains the other
        $naamLower = strtolower($naam);
        $clubs = (clone $query)->get();
        foreach ($clubs as $bestaandeClub) {
            $bestaandeLower = strtolower($bestaandeClub->naam);
            if (str_contains($naamLower, $bestaandeLower) || str_contains($bestaandeLower, $naamLower)) {
                return $bestaandeClub;
            }
        }

        // 4. If organisator provided but no match, also check global clubs (for backwards compatibility)
        if ($organisatorId) {
            $globalClub = self::whereNull('organisator_id')
                ->where(function ($q) use ($naam, $naamLower) {
                    $q->where('naam', $naam)
                      ->orWhereRaw('LOWER(naam) = ?', [strtolower($naam)]);
                })
                ->first();

            if ($globalClub) {
                // Claim this global club for the organisator
                $globalClub->update(['organisator_id' => $organisatorId]);
                return $globalClub;
            }

            // Fuzzy match on global clubs
            $globalClubs = self::whereNull('organisator_id')->get();
            foreach ($globalClubs as $bestaandeClub) {
                $bestaandeLower = strtolower($bestaandeClub->naam);
                if (str_contains($naamLower, $bestaandeLower) || str_contains($bestaandeLower, $naamLower)) {
                    $bestaandeClub->update(['organisator_id' => $organisatorId]);
                    return $bestaandeClub;
                }
            }
        }

        // 5. No match found - create new club
        return self::create([
            'organisator_id' => $organisatorId,
            'naam' => $naam,
            'afkorting' => substr($naam, 0, 10),
        ]);
    }
}
