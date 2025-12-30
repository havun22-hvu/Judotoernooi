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
        'ronde',
        'groep',
        'bracket_positie',
        'locatie_wit',
        'locatie_blauw',
        'volgende_wedstrijd_id',
        'herkansing_wedstrijd_id',
        'winnaar_naar_slot',
        'verliezer_naar_slot',
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

    /**
     * Wedstrijd waar de winnaar naartoe gaat
     */
    public function volgendeWedstrijd(): BelongsTo
    {
        return $this->belongsTo(Wedstrijd::class, 'volgende_wedstrijd_id');
    }

    /**
     * Wedstrijd waar de verliezer naartoe gaat (herkansing)
     */
    public function herkansingWedstrijd(): BelongsTo
    {
        return $this->belongsTo(Wedstrijd::class, 'herkansing_wedstrijd_id');
    }

    /**
     * Check if this is an elimination match
     */
    public function isEliminatie(): bool
    {
        return $this->ronde !== null;
    }

    /**
     * Check if this is a main bracket match (Groep A)
     */
    public function isHoofdboom(): bool
    {
        return $this->groep === 'A';
    }

    /**
     * Check if this is a repechage match (Groep B)
     */
    public function isHerkansing(): bool
    {
        return $this->groep === 'B';
    }

    /**
     * Get the loser of this match
     */
    public function getVerliezerId(): ?int
    {
        if (!$this->is_gespeeld || !$this->winnaar_id) {
            return null;
        }

        return $this->winnaar_id === $this->judoka_wit_id
            ? $this->judoka_blauw_id
            : $this->judoka_wit_id;
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

    /**
     * Bereken winnaar doel-locatie op basis van locatie_wit
     * Locatie 1,2 → 1 | Locatie 3,4 → 2 | Locatie 5,6 → 3 | etc.
     */
    public function getWinnaarDoelLocatie(): ?int
    {
        if (!$this->locatie_wit) {
            return null;
        }
        return (int) ceil($this->locatie_wit / 2);
    }

    /**
     * Bereken of winnaar naar WIT of BLAUW slot gaat
     * Locatie 1,2 → wit | Locatie 3,4 → blauw | Locatie 5,6 → wit | etc.
     */
    public function getWinnaarDoelSlot(): ?string
    {
        if (!$this->locatie_wit) {
            return null;
        }
        // Locatie 1,2 (ceil=1) → wit, Locatie 3,4 (ceil=2) → blauw
        $doelLocatie = $this->getWinnaarDoelLocatie();
        return ($doelLocatie % 2 === 1) ? 'wit' : 'blauw';
    }
}
