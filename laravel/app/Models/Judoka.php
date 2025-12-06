<?php

namespace App\Models;

use App\Enums\AanwezigheidsStatus;
use App\Enums\Band;
use App\Enums\Geslacht;
use App\Enums\Leeftijdsklasse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Judoka extends Model
{
    use HasFactory;

    protected $table = 'judokas';

    protected $fillable = [
        'toernooi_id',
        'club_id',
        'naam',
        'voornaam',
        'achternaam',
        'geboortejaar',
        'geslacht',
        'band',
        'gewicht',
        'leeftijdsklasse',
        'gewichtsklasse',
        'judoka_code',
        'aanwezigheid',
        'gewicht_gewogen',
        'opmerking',
        'qr_code',
    ];

    protected $casts = [
        'gewicht' => 'decimal:1',
        'gewicht_gewogen' => 'decimal:1',
    ];

    protected static function booted(): void
    {
        static::creating(function (Judoka $judoka) {
            if (empty($judoka->qr_code)) {
                $judoka->qr_code = Str::uuid()->toString();
            }
        });
    }

    public function toernooi(): BelongsTo
    {
        return $this->belongsTo(Toernooi::class);
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function poules(): BelongsToMany
    {
        return $this->belongsToMany(Poule::class, 'poule_judoka')
            ->withPivot(['positie', 'punten', 'gewonnen', 'verloren', 'gelijk', 'eindpositie'])
            ->withTimestamps();
    }

    public function wegingen(): HasMany
    {
        return $this->hasMany(Weging::class)->orderByDesc('created_at');
    }

    public function wedstrijdenAlsWit(): HasMany
    {
        return $this->hasMany(Wedstrijd::class, 'judoka_wit_id');
    }

    public function wedstrijdenAlsBlauw(): HasMany
    {
        return $this->hasMany(Wedstrijd::class, 'judoka_blauw_id');
    }

    public function getBandEnumAttribute(): ?Band
    {
        return Band::fromString($this->band);
    }

    public function getGeslachtEnumAttribute(): ?Geslacht
    {
        return Geslacht::fromString($this->geslacht);
    }

    public function getAanwezigheidEnumAttribute(): AanwezigheidsStatus
    {
        return AanwezigheidsStatus::tryFrom($this->aanwezigheid) ?? AanwezigheidsStatus::ONBEKEND;
    }

    public function getLeeftijdAttribute(): int
    {
        return date('Y') - $this->geboortejaar;
    }

    public function isAanwezig(): bool
    {
        return $this->aanwezigheid === AanwezigheidsStatus::AANWEZIG->value;
    }

    /**
     * Calculate the judoka code for pool assignment
     * Format: LLGGBG (Leeftijd-Gewicht-Band-Geslacht)
     */
    public function berekenJudokaCode(): string
    {
        $leeftijdsklasse = Leeftijdsklasse::fromLeeftijdEnGeslacht($this->leeftijd, $this->geslacht);
        $leeftijdCode = $leeftijdsklasse->code();

        // Weight class code (2 digits)
        $gewichtNum = abs(intval(str_replace(['-', '+', 'kg', ' '], '', $this->gewichtsklasse)));
        $gewichtCode = str_pad($gewichtNum, 2, '0', STR_PAD_LEFT);

        // Band code (1 digit)
        $bandEnum = $this->band_enum;
        $bandCode = $bandEnum ? $bandEnum->kyu() : 'X';

        // Gender code
        $geslachtCode = strtoupper($this->geslacht);

        return "{$leeftijdCode}{$gewichtCode}{$bandCode}{$geslachtCode}";
    }

    /**
     * Check if weight is within allowed range for weight class
     */
    public function isGewichtBinnenKlasse(?float $gewicht = null, float $tolerantie = 0.5): bool
    {
        $gewicht = $gewicht ?? $this->gewicht_gewogen ?? $this->gewicht;
        if (!$gewicht) return true;

        $klasse = $this->gewichtsklasse;
        if (!$klasse) return true;

        $isPlusKlasse = str_starts_with($klasse, '+');
        $limiet = floatval(preg_replace('/[^0-9.]/', '', $klasse));

        if ($isPlusKlasse) {
            // +70 means minimum 70kg
            return $gewicht >= ($limiet - $tolerantie);
        } else {
            // -36 means maximum 36kg
            return $gewicht <= ($limiet + $tolerantie);
        }
    }
}
