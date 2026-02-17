<?php

namespace App\Models;

use App\Enums\AanwezigheidsStatus;
use App\Enums\Band;
use App\Enums\Geslacht;
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
        'jbn_lidnummer',
        'gewicht',
        'leeftijdsklasse',
        'gewichtsklasse',
        'sort_categorie',
        'sort_gewicht',
        'sort_band',
        'categorie_key',
        'aanwezigheid',
        'gewicht_gewogen',
        'opmerking',
        'qr_code',
        'synced_at',
        'betaling_id',
        'betaald_op',
        'is_onvolledig',
        'import_warnings',
        'import_status',
        'telefoon',
        'overpouled_van_poule_id',
        'stam_judoka_id',
    ];

    protected $casts = [
        'gewicht' => 'decimal:1',
        'gewicht_gewogen' => 'decimal:1',
        'synced_at' => 'datetime',
        'betaald_op' => 'datetime',
        'is_onvolledig' => 'boolean',
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

    public function stamJudoka(): BelongsTo
    {
        return $this->belongsTo(StamJudoka::class);
    }

    public function betaling(): BelongsTo
    {
        return $this->belongsTo(Betaling::class);
    }

    /**
     * Check of voor deze judoka betaald is
     */
    public function isBetaald(): bool
    {
        return $this->betaald_op !== null;
    }

    /**
     * Check of deze judoka klaar is om af te rekenen (volledig en nog niet betaald)
     */
    public function isKlaarVoorBetaling(): bool
    {
        return $this->isVolledig() && !$this->isBetaald();
    }

    public function poules(): BelongsToMany
    {
        return $this->belongsToMany(Poule::class, 'poule_judoka')
            ->withPivot(['positie', 'punten', 'gewonnen', 'verloren', 'gelijk', 'eindpositie'])
            ->withTimestamps();
    }

    /**
     * Poule waar judoka uit overpouled is (bij vaste gewichtsklassen)
     */
    public function overpouledVanPoule(): BelongsTo
    {
        return $this->belongsTo(Poule::class, 'overpouled_van_poule_id');
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
     * Check if judoka is active for tournament (not absent, and weighed if required)
     * Used for counting active judokas in poules
     */
    public function isActief(bool $wegingGesloten = false): bool
    {
        // Expliciet afwezig
        if ($this->aanwezigheid === 'afwezig') {
            return false;
        }

        // Weging gesloten en niet gewogen = niet actief
        if ($wegingGesloten && $this->gewicht_gewogen === null) {
            return false;
        }

        return true;
    }

    /**
     * Check of dit een vaste gewichtsklasse is (max_kg_verschil = 0)
     * Gebruikt poule categorie config als beschikbaar, anders string fallback
     */
    public function isVasteGewichtsklasse(): bool
    {
        // Probeer via poule's categorie config
        $poule = $this->poules()->first();
        if ($poule && $poule->categorie_key) {
            // isDynamisch = max_kg_verschil > 0, dus vaste klasse = !isDynamisch
            return !$poule->isDynamisch();
        }

        // Fallback: string prefix check (voor edge cases zonder poule)
        $klasse = $this->gewichtsklasse ?? '';
        return str_starts_with($klasse, '-') || str_starts_with($klasse, '+');
    }

    /**
     * Check if weight is within allowed range for weight class
     * Only applies to fixed weight classes (max_kg_verschil = 0)
     * Variable weight classes always return true
     *
     * @param float|null $gewicht - gewicht to check (default: gewicht_gewogen ?? gewicht)
     * @param float $tolerantie - tolerance in kg (default: 0.5)
     * @param string|null $pouleGewichtsklasse - use poule's weight class instead of judoka's
     */
    public function isGewichtBinnenKlasse(?float $gewicht = null, float $tolerantie = 0.5, ?string $pouleGewichtsklasse = null): bool
    {
        $gewicht = $gewicht ?? $this->gewicht_gewogen ?? $this->gewicht;
        if (!$gewicht) return true;

        // Use poule's weight class if provided, otherwise judoka's
        $klasse = $pouleGewichtsklasse ?? $this->gewichtsklasse;
        if (!$klasse) return true;

        // Only check fixed weight classes (format: -XX or +XX)
        // Variable weight classes (no +/- prefix) always pass
        if (!str_starts_with($klasse, '-') && !str_starts_with($klasse, '+')) {
            return true;
        }

        // Voor vaste klassen: check of gewicht binnen limiet valt
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
     * ALLEEN afwezig = verwijderen uit poule
     * Afwijkend gewicht NIET verwijderen - organisator kiest wie eruit gaat
     */
    public function moetUitPouleVerwijderd(?float $tolerantie = null): bool
    {
        // Alleen afwezig = verwijderen
        return $this->aanwezigheid === 'afwezig';
    }

    /**
     * Check of judoka afwijkend gewicht heeft (gewogen maar buiten gewichtsklasse)
     */
    public function heeftAfwijkendGewicht(?float $tolerantie = null): bool
    {
        if ($this->gewicht_gewogen === null) {
            return false;
        }

        $tol = $tolerantie ?? $this->toernooi?->gewicht_tolerantie ?? 0.5;
        return !$this->isGewichtBinnenKlasse(null, $tol);
    }

    /**
     * Haal het effectieve gewicht op (voor sortering en indeling)
     * Prioriteit: gewicht_gewogen > gewicht > gewichtsklasse limiet
     */
    public function getEffectiefGewicht(): ?float
    {
        if ($this->gewicht_gewogen > 0) {
            return (float) $this->gewicht_gewogen;
        }

        if ($this->gewicht !== null) {
            return (float) $this->gewicht;
        }

        // Fallback: haal getal uit gewichtsklasse (-30, +70, etc.)
        if ($this->gewichtsklasse && preg_match('/(\d+)/', $this->gewichtsklasse, $m)) {
            return (float) $m[1];
        }

        return null;
    }

    /**
     * Verwijder judoka uit alle poules als afwezig en update statistieken
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
     * Check of judoka in een categorie past (niet te oud/jong)
     */
    public function pastInCategorie(): bool
    {
        // Als niet volledig, kunnen we niet bepalen
        if (!$this->isVolledig()) {
            return false;
        }

        // Als leeftijdsklasse is ingevuld, past de judoka
        return !empty($this->leeftijdsklasse);
    }

    /**
     * Get reden waarom judoka niet in categorie past
     */
    public function getCategorieProbleem(): ?string
    {
        if (!$this->isVolledig()) {
            return null; // Eerst gegevens invullen
        }

        if (!empty($this->leeftijdsklasse)) {
            return null; // Past in categorie
        }

        // Bereken leeftijd
        $toernooi = $this->toernooi;
        if (!$toernooi) {
            return 'Geen toernooi gekoppeld';
        }

        $toernooiJaar = $toernooi->datum ? $toernooi->datum->year : (int) date('Y');
        $leeftijd = $toernooiJaar - $this->geboortejaar;

        // Check of te oud of te jong
        $config = $toernooi->getAlleGewichtsklassen();
        $minLeeftijd = 99;
        $maxLeeftijd = 0;

        foreach ($config as $cat) {
            $catMax = $cat['max_leeftijd'] ?? 99;
            if ($catMax < $minLeeftijd) $minLeeftijd = $catMax;
            if ($catMax > $maxLeeftijd) $maxLeeftijd = $catMax;
        }

        if ($leeftijd > $maxLeeftijd) {
            return "Te oud ({$leeftijd} jaar, max {$maxLeeftijd})";
        }

        // Kan ook te jong zijn of andere reden
        return "Past niet in categorie (leeftijd {$leeftijd})";
    }

    /**
     * Check of judoka klaar is voor sync (volledig EN past in categorie)
     */
    public function isKlaarVoorSync(): bool
    {
        return $this->isVolledig() && $this->pastInCategorie();
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

    /**
     * Check of judoka correctie nodig heeft (import problemen)
     */
    public function isTeCorrigeren(): bool
    {
        return $this->import_status === 'te_corrigeren';
    }

    /**
     * Hervalideer import status na wijziging
     * Controleert of alle velden nu correct zijn en update status
     */
    public function hervalideerImportStatus(): void
    {
        // Alleen hervalideren als status 'te_corrigeren' was
        if ($this->import_status !== 'te_corrigeren') {
            return;
        }

        $problemen = $this->detecteerImportProblemen();

        if (empty($problemen)) {
            // Alles is nu correct
            $this->update([
                'import_status' => 'compleet',
                'import_warnings' => null,
            ]);
        } else {
            // Nog steeds problemen, update warnings
            $this->update([
                'import_warnings' => implode(' | ', $problemen),
            ]);
        }
    }

    /**
     * Detecteer problemen met judoka data
     * Gebruikt bij import en bij hervalidatie
     */
    public function detecteerImportProblemen(): array
    {
        $problemen = [];
        $huidigJaar = (int) date('Y');

        // Geboortejaar check
        if (empty($this->geboortejaar)) {
            $problemen[] = 'Geboortejaar ontbreekt';
        } elseif ($this->geboortejaar < 1950 || $this->geboortejaar > $huidigJaar) {
            $problemen[] = "Geboortejaar {$this->geboortejaar} lijkt ongeldig";
        } else {
            $leeftijd = $huidigJaar - $this->geboortejaar;
            if ($leeftijd < 4) {
                $problemen[] = "Leeftijd {$leeftijd} jaar erg jong";
            } elseif ($leeftijd > 50) {
                $problemen[] = "Leeftijd {$leeftijd} jaar erg hoog";
            }
        }

        // Gewicht check (rekening houdend met leeftijd)
        if ($this->gewicht === null) {
            $problemen[] = 'Gewicht ontbreekt';
        } else {
            // Minimum gewicht per leeftijd (ruwe inschatting)
            // 4-6 jaar: 15kg, 7-9 jaar: 20kg, 10-12 jaar: 25kg, 13-15 jaar: 35kg, 16+ jaar: 45kg
            $minGewicht = match (true) {
                $leeftijd <= 6 => 12,
                $leeftijd <= 9 => 18,
                $leeftijd <= 12 => 22,
                $leeftijd <= 15 => 30,
                default => 40,
            };

            if ($this->gewicht < $minGewicht) {
                $problemen[] = "Gewicht {$this->gewicht} kg lijkt laag";
            } elseif ($this->gewicht > 150) {
                $problemen[] = "Gewicht {$this->gewicht} kg lijkt hoog";
            }
        }

        // Geslacht check
        if (empty($this->geslacht)) {
            $problemen[] = 'Geslacht ontbreekt';
        }

        return $problemen;
    }
}
