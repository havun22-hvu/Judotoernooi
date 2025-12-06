<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Toernooi extends Model
{
    use HasFactory;

    protected $table = 'toernooien';

    protected $fillable = [
        'naam',
        'organisatie',
        'datum',
        'locatie',
        'aantal_matten',
        'aantal_blokken',
        'min_judokas_poule',
        'optimal_judokas_poule',
        'max_judokas_poule',
        'gewicht_tolerantie',
        'is_actief',
        'poules_gegenereerd_op',
        'blokken_verdeeld_op',
    ];

    protected $casts = [
        'datum' => 'date',
        'is_actief' => 'boolean',
        'poules_gegenereerd_op' => 'datetime',
        'blokken_verdeeld_op' => 'datetime',
        'gewicht_tolerantie' => 'decimal:1',
    ];

    public function judokas(): HasMany
    {
        return $this->hasMany(Judoka::class);
    }

    public function blokken(): HasMany
    {
        return $this->hasMany(Blok::class)->orderBy('nummer');
    }

    public function matten(): HasMany
    {
        return $this->hasMany(Mat::class)->orderBy('nummer');
    }

    public function poules(): HasMany
    {
        return $this->hasMany(Poule::class)->orderBy('nummer');
    }

    public function scopeActief($query)
    {
        return $query->where('is_actief', true);
    }

    public function getTotaalWedstrijdenAttribute(): int
    {
        return $this->poules()->sum('aantal_wedstrijden');
    }

    public function getTotaalJudokasAttribute(): int
    {
        return $this->judokas()->count();
    }
}
