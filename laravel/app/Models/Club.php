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
    ];

    public function judokas(): HasMany
    {
        return $this->hasMany(Judoka::class);
    }

    public static function findOrCreateByName(string $naam): self
    {
        return self::firstOrCreate(
            ['naam' => trim($naam)],
            ['afkorting' => substr(trim($naam), 0, 10)]
        );
    }
}
