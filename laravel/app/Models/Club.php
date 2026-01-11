<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    ];

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
            return 0;
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

        // If no blokken assigned yet, fall back to total count
        if (empty($judokasPerBlok)) {
            return (int) ceil($judokas->count() / $perCoach);
        }

        // Use the largest block to determine number of coach cards needed
        $maxJudokasInBlok = max($judokasPerBlok);

        return (int) ceil($maxJudokasInBlok / $perCoach);
    }

    public static function findOrCreateByName(string $naam): self
    {
        return self::firstOrCreate(
            ['naam' => trim($naam)],
            ['afkorting' => substr(trim($naam), 0, 10)]
        );
    }
}
