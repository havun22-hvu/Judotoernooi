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
     */
    public function berekenAantalCoachKaarten(Toernooi $toernooi): int
    {
        $aantalJudokas = $this->judokas()->where('toernooi_id', $toernooi->id)->count();
        $perCoach = $toernooi->judokas_per_coach ?? 5;

        if ($aantalJudokas === 0) {
            return 0;
        }

        return (int) ceil($aantalJudokas / $perCoach);
    }

    public static function findOrCreateByName(string $naam): self
    {
        return self::firstOrCreate(
            ['naam' => trim($naam)],
            ['afkorting' => substr(trim($naam), 0, 10)]
        );
    }
}
