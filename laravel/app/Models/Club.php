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

    public static function findOrCreateByName(string $naam): self
    {
        return self::firstOrCreate(
            ['naam' => trim($naam)],
            ['afkorting' => substr(trim($naam), 0, 10)]
        );
    }
}
