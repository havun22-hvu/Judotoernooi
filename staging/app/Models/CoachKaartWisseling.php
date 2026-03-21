<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachKaartWisseling extends Model
{
    protected $table = 'coach_kaart_wisselingen';

    protected $fillable = [
        'coach_kaart_id',
        'naam',
        'foto',
        'device_info',
        'geactiveerd_op',
        'overgedragen_op',
    ];

    protected $casts = [
        'geactiveerd_op' => 'datetime',
        'overgedragen_op' => 'datetime',
    ];

    public function coachKaart(): BelongsTo
    {
        return $this->belongsTo(CoachKaart::class);
    }

    public function getFotoUrl(): ?string
    {
        if (!$this->foto) {
            return null;
        }
        return asset('storage/' . $this->foto);
    }

    public function isHuidigeCoach(): bool
    {
        return is_null($this->overgedragen_op);
    }
}
