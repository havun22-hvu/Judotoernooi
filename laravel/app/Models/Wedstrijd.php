<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wedstrijd extends Model
{
    use HasFactory;

    protected $table = 'wedstrijden';

    protected $fillable = [
        'poule_id',
        'judoka_wit_id',
        'judoka_blauw_id',
        'volgorde',
        'winnaar_id',
        'score_wit',
        'score_blauw',
        'uitslag_type',
        'is_gespeeld',
        'gespeeld_op',
    ];

    protected $casts = [
        'is_gespeeld' => 'boolean',
        'gespeeld_op' => 'datetime',
    ];

    public function poule(): BelongsTo
    {
        return $this->belongsTo(Poule::class);
    }

    public function judokaWit(): BelongsTo
    {
        return $this->belongsTo(Judoka::class, 'judoka_wit_id');
    }

    public function judokaBlauw(): BelongsTo
    {
        return $this->belongsTo(Judoka::class, 'judoka_blauw_id');
    }

    public function winnaar(): BelongsTo
    {
        return $this->belongsTo(Judoka::class, 'winnaar_id');
    }

    public function registreerUitslag(int $winnaarId, string $scoreWinnaar, string $scoreVerliezer, string $type): void
    {
        $isWitWinnaar = $winnaarId === $this->judoka_wit_id;

        $this->update([
            'winnaar_id' => $winnaarId,
            'score_wit' => $isWitWinnaar ? $scoreWinnaar : $scoreVerliezer,
            'score_blauw' => $isWitWinnaar ? $scoreVerliezer : $scoreWinnaar,
            'uitslag_type' => $type,
            'is_gespeeld' => true,
            'gespeeld_op' => now(),
        ]);
    }

    public function isGelijk(): bool
    {
        return $this->is_gespeeld && $this->winnaar_id === null;
    }
}
