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
        'actieve_wedstrijd_id',
        'volgende_wedstrijd_id',
    ];

    public function toernooi(): BelongsTo
    {
        return $this->belongsTo(Toernooi::class);
    }

    public function poules(): HasMany
    {
        return $this->hasMany(Poule::class);
    }

    /**
     * De wedstrijd die nu speelt (groen)
     */
    public function actieveWedstrijd(): BelongsTo
    {
        return $this->belongsTo(Wedstrijd::class, 'actieve_wedstrijd_id');
    }

    /**
     * De wedstrijd die klaar moet maken (geel)
     */
    public function volgendeWedstrijd(): BelongsTo
    {
        return $this->belongsTo(Wedstrijd::class, 'volgende_wedstrijd_id');
    }

    /**
     * Reset wedstrijd selectie als de wedstrijd van een specifieke poule is
     */
    public function resetWedstrijdSelectieVoorPoule(int $pouleId): void
    {
        $updates = [];

        if ($this->actieve_wedstrijd_id) {
            $actieve = Wedstrijd::find($this->actieve_wedstrijd_id);
            if ($actieve && $actieve->poule_id === $pouleId) {
                $updates['actieve_wedstrijd_id'] = null;
            }
        }

        if ($this->volgende_wedstrijd_id) {
            $volgende = Wedstrijd::find($this->volgende_wedstrijd_id);
            if ($volgende && $volgende->poule_id === $pouleId) {
                $updates['volgende_wedstrijd_id'] = null;
            }
        }

        if (!empty($updates)) {
            $this->update($updates);
        }
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
