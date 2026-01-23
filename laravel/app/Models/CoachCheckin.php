<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class CoachCheckin extends Model
{
    protected $fillable = [
        'coach_kaart_id',
        'toernooi_id',
        'naam',
        'club_naam',
        'foto',
        'actie',
        'geforceerd_door',
    ];

    public function coachKaart(): BelongsTo
    {
        return $this->belongsTo(CoachKaart::class);
    }

    public function toernooi(): BelongsTo
    {
        return $this->belongsTo(Toernooi::class);
    }

    /**
     * Filter op vandaag
     */
    public function scopeVandaag(Builder $query): Builder
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Filter op club
     */
    public function scopeVoorClub(Builder $query, int $clubId): Builder
    {
        return $query->whereHas('coachKaart', fn($q) => $q->where('club_id', $clubId));
    }

    /**
     * Check of dit een in-actie is
     */
    public function isIn(): bool
    {
        return $this->actie === 'in';
    }

    /**
     * Check of dit een uit-actie is (normaal of geforceerd)
     */
    public function isUit(): bool
    {
        return in_array($this->actie, ['uit', 'uit_geforceerd']);
    }

    /**
     * Check of dit een geforceerd uitcheck is
     */
    public function isGeforceerd(): bool
    {
        return $this->actie === 'uit_geforceerd';
    }

    /**
     * Get foto URL
     */
    public function getFotoUrl(): ?string
    {
        if (!$this->foto) {
            return null;
        }
        return asset('storage/' . $this->foto);
    }
}
