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
        'synced_at',
    ];

    protected $casts = [
        'gewicht' => 'decimal:1',
        'gewicht_gewogen' => 'decimal:1',
        'synced_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Judoka $judoka) {
            if (empty($judoka->qr_code)) {
                $judoka->qr_code = Str::uuid()->toString();
            }
            // Auto-format naam bij aanmaken
            if (!empty($judoka->naam)) {
                $judoka->naam = self::formatNaam($judoka->naam);
            }
        });

        static::updating(function (Judoka $judoka) {
            // Auto-format naam bij wijzigen
            if ($judoka->isDirty('naam') && !empty($judoka->naam)) {
                $judoka->naam = self::formatNaam($judoka->naam);
            }
        });
    }

    /**
     * Format naam met correcte hoofdletters
     * Tussenvoegsels klein (van, de, etc.), namen met hoofdletter
     */
    public static function formatNaam(string $naam): string
    {
        $tussenvoegsels = ['van', 'de', 'den', 'der', 'het', 'ter', 'ten', 'te', 'op', 'in', "'t"];

        $delen = explode(' ', trim($naam));
        $result = [];

        foreach ($delen as $i => $deel) {
            $lower = mb_strtolower($deel);
            if (in_array($lower, $tussenvoegsels) && $i > 0) {
                $result[] = $lower;
            } else {
                $result[] = mb_convert_case($deel, MB_CASE_TITLE);
            }
        }

        return implode(' ', $result);
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
     * Calculate the judoka base code for pool assignment (without volgnummer)
     * Format depends on toernooi setting:
     * - gewicht_band: LLGGBG (Leeftijd-Gewicht-Band-Geslacht) - default
     * - band_gewicht: LLBGGG (Leeftijd-Band-Gewicht-Geslacht)
     */
    public function berekenBasisCode(): string
    {
        $leeftijdsklasse = Leeftijdsklasse::fromLeeftijdEnGeslacht($this->leeftijd, $this->geslacht);
        $leeftijdCode = $leeftijdsklasse->code();

        // Weight class code (2 digits)
        $gewichtNum = abs(intval(str_replace(['-', '+', 'kg', ' '], '', $this->gewichtsklasse)));
        $gewichtCode = str_pad($gewichtNum, 2, '0', STR_PAD_LEFT);

        // Band code (1 digit): wit=6, geel=5, oranje=4, groen=3, blauw=2, bruin=1, zwart=0
        $bandEnum = $this->band_enum;
        $bandCode = $bandEnum ? $bandEnum->kyu() : 'X';

        // Gender code
        $geslachtCode = strtoupper($this->geslacht);

        // Check toernooi setting for code order
        $volgorde = $this->toernooi?->judoka_code_volgorde ?? 'gewicht_band';

        if ($volgorde === 'band_gewicht') {
            // Leeftijd - Band - Gewicht - Geslacht
            return "{$leeftijdCode}{$bandCode}{$gewichtCode}{$geslachtCode}";
        }

        // Default: Leeftijd - Gewicht - Band - Geslacht
        return "{$leeftijdCode}{$gewichtCode}{$bandCode}{$geslachtCode}";
    }

    /**
     * Calculate full judoka code with volgnummer
     * Format: LLGGBGVV (Leeftijd-Gewicht-Band-Geslacht-Volgnummer)
     * Note: volgnummer must be provided externally per category
     */
    public function berekenJudokaCode(int $volgnummer = 1): string
    {
        $basisCode = $this->berekenBasisCode();
        $volgnummerCode = str_pad($volgnummer, 2, '0', STR_PAD_LEFT);

        return "{$basisCode}{$volgnummerCode}";
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

    /**
     * Check of judoka uit poules moet worden verwijderd
     * Doorgestreept = afwezig OF gewogen maar buiten gewichtsklasse
     */
    public function moetUitPouleVerwijderd(?float $tolerantie = null): bool
    {
        // Afwezig
        if ($this->aanwezigheid === 'afwezig') {
            return true;
        }

        // Gewogen maar buiten gewichtsklasse
        if ($this->gewicht_gewogen !== null) {
            // Gebruik toernooi tolerantie als beschikbaar
            $tol = $tolerantie ?? $this->toernooi?->gewicht_tolerantie ?? 0.5;
            if (!$this->isGewichtBinnenKlasse(null, $tol)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check of judoka doorgestreept moet worden weergegeven
     * Alias voor moetUitPouleVerwijderd
     */
    public function isDoorgestreept(?float $tolerantie = null): bool
    {
        return $this->moetUitPouleVerwijderd($tolerantie);
    }

    /**
     * Verwijder judoka uit alle poules als doorgestreept en update statistieken
     * Aanroepen bij elke mutatie (weging, aanwezigheid)
     */
    public function verwijderUitPoulesIndienNodig(): void
    {
        if (!$this->moetUitPouleVerwijderd()) {
            return;
        }

        $poules = $this->poules;

        foreach ($poules as $poule) {
            $poule->judokas()->detach($this->id);
            $poule->updateStatistieken();
        }
    }

    /**
     * Check of judoka volledig is ingevuld
     * Vereist: naam, geboortejaar, geslacht, band, gewicht
     */
    public function isVolledig(): bool
    {
        return !empty($this->naam)
            && !empty($this->geboortejaar)
            && !empty($this->geslacht)
            && !empty($this->band)
            && $this->gewicht !== null && $this->gewicht > 0;
    }

    /**
     * Check of judoka is gesynced (definitief ingeschreven)
     */
    public function isSynced(): bool
    {
        return $this->synced_at !== null;
    }

    /**
     * Check of judoka is gewijzigd na sync (needs re-sync)
     */
    public function isGewijzigdNaSync(): bool
    {
        return $this->synced_at !== null && $this->updated_at > $this->synced_at;
    }

    /**
     * Get array of missing fields for incomplete judoka
     */
    public function getOntbrekendeVelden(): array
    {
        $ontbrekend = [];

        if (empty($this->naam)) $ontbrekend[] = 'naam';
        if (empty($this->geboortejaar)) $ontbrekend[] = 'geboortejaar';
        if (empty($this->geslacht)) $ontbrekend[] = 'geslacht';
        if (empty($this->band)) $ontbrekend[] = 'band';
        if ($this->gewicht === null || $this->gewicht <= 0) $ontbrekend[] = 'gewicht';

        return $ontbrekend;
    }

    /**
     * Bepaal gewichtsklasse op basis van gewicht en leeftijdsklasse
     * Returns bijv. "-34" voor 32.5 kg in categorie met [-30, -34, -38]
     */
    public static function bepaalGewichtsklasse(float $gewicht, array $gewichtsklassen): ?string
    {
        if (empty($gewichtsklassen)) {
            return null;
        }

        // Sort weight classes by numeric value
        usort($gewichtsklassen, function ($a, $b) {
            $aNum = floatval(preg_replace('/[^0-9.]/', '', $a));
            $bNum = floatval(preg_replace('/[^0-9.]/', '', $b));
            $aPlus = str_starts_with($a, '+');
            $bPlus = str_starts_with($b, '+');

            if ($aPlus && !$bPlus) return 1;
            if (!$aPlus && $bPlus) return -1;
            return $aNum - $bNum;
        });

        // Find matching weight class
        foreach ($gewichtsklassen as $klasse) {
            $isPlusKlasse = str_starts_with($klasse, '+');
            $limiet = floatval(preg_replace('/[^0-9.]/', '', $klasse));

            if ($isPlusKlasse) {
                // +XX means minimum weight, this is the last option
                return $klasse;
            } else {
                // -XX means maximum weight
                if ($gewicht <= $limiet) {
                    return $klasse;
                }
            }
        }

        // If weight exceeds all classes, return the highest (+ class)
        return end($gewichtsklassen);
    }
}
