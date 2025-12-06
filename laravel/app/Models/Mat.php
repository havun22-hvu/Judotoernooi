<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mat extends Model
{
    use HasFactory;

    protected $table = 'matten';

    protected $fillable = [
        'toernooi_id',
        'nummer',
        'naam',
        'kleur',
    ];

    public function toernooi(): BelongsTo
    {
        return $this->belongsTo(Toernooi::class);
    }

    public function poules(): HasMany
    {
        return $this->hasMany(Poule::class);
    }

    public function getLabelAttribute(): string
    {
        return $this->naam ?? "Mat {$this->nummer}";
    }

    public function getPoulesVoorBlok(Blok $blok): \Illuminate\Database\Eloquent\Collection
    {
        return $this->poules()->where('blok_id', $blok->id)->get();
    }
}
