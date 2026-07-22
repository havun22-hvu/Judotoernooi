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
        'gereedmaken_wedstrijd_id',
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
     * De wedstrijd die klaar staat (geel)
     */
    public function volgendeWedstrijd(): BelongsTo
    {
        return $this->belongsTo(Wedstrijd::class, 'volgende_wedstrijd_id');
    }

    /**
     * De wedstrijd die gereed moet maken (blauw)
     */
    public function gereedmakenWedstrijd(): BelongsTo
    {
        return $this->belongsTo(Wedstrijd::class, 'gereedmaken_wedstrijd_id');
    }

    /**
     * Is deze wedstrijd de groene (nu-spelende) wedstrijd op deze mat?
     *
     * De scoreboard-app mag via /api/scoreboard/result alléén de groene wedstrijd scoren.
     * Elke andere wedstrijd (verschoven beurt, late offline-flush) hoort geweigerd te worden —
     * scoren ervan zou de eliminatie-doorschuif corrumperen. Gedeeld door green-check en de
     * result()-gate. Zie .claude/plan-scoreboard-groen-gate.md.
     */
    public function isGroen(Wedstrijd $wedstrijd): bool
    {
        // `null === $wedstrijd->id` is altijd false (geen juggling bij ===), dus een aparte
        // null-guard voegt niets toe; beide callers laden de wedstrijd via findOrFail.
        return $this->actieve_wedstrijd_id === $wedstrijd->id;
    }

    /**
     * Pas de kleurbeurten aan wanneer een poule/groep naar een ANDERE mat wordt
     * verplaatst.
     *
     * GROEN (actieve wedstrijd) blijft altijd staan: een lopende — of door de
     * jury geselecteerde — partij maakt af op deze mat, en scorebord + LCD
     * blijven 'm tonen. Was de partij nog niet gestart, dan zet de jury groen
     * handmatig uit (die knop vraagt bevestiging én notificeert app + LCD).
     * Alleen GEEL/BLAUW van de vertrokken poule/groep vervallen, met doorschuiving
     * (blauw → geel).
     *
     * Groep-bewust: bij een eliminatie A/B-split raakt het verplaatsen van groep B
     * de kleurbeurt van groep A (zelfde poule_id) niet.
     */
    public function resetWedstrijdSelectieVoorPoule(int $pouleId, ?string $groep = null): void
    {
        $hoortBijVerplaatsing = function (?int $wedstrijdId) use ($pouleId, $groep): bool {
            if (!$wedstrijdId) {
                return false;
            }
            $wedstrijd = Wedstrijd::find($wedstrijdId);
            if (!$wedstrijd || $wedstrijd->poule_id !== $pouleId) {
                return false;
            }
            // Groep null = reguliere poule (geen A/B) → alleen op poule matchen.
            return $groep === null || $wedstrijd->groep === $groep;
        };

        $resetGeel = $hoortBijVerplaatsing($this->volgende_wedstrijd_id);
        $resetBlauw = $hoortBijVerplaatsing($this->gereedmaken_wedstrijd_id);

        $updates = [];
        if ($resetGeel && $resetBlauw) {
            // Beide horen bij de vertrokken groep → beide vervallen.
            $updates['volgende_wedstrijd_id'] = null;
            $updates['gereedmaken_wedstrijd_id'] = null;
        } elseif ($resetGeel) {
            // Geel weg → blauw (blijft bij een andere groep) schuift door naar geel.
            $updates['volgende_wedstrijd_id'] = $this->gereedmaken_wedstrijd_id;
            $updates['gereedmaken_wedstrijd_id'] = null;
        } elseif ($resetBlauw) {
            // Blauw weg → geen doorschuiving.
            $updates['gereedmaken_wedstrijd_id'] = null;
        }

        if (!empty($updates)) {
            $this->update($updates);
        }
    }

    /**
     * Clean up invalid selections (wedstrijden that are already played WITH a winner)
     * Handles cases where auto-advance didn't happen (browser closed, etc.)
     * Only cleans up if wedstrijd has a winnaar_id (not just is_gespeeld flag)
     *
     * ROBUUST: Alleen doorschuiven als wedstrijd ECHT gespeeld is (met winnaar).
     * Als wedstrijd niet gevonden wordt, NIET automatisch doorschuiven (kan data-issue zijn).
     */
    public function cleanupGespeeldeSelecties(): void
    {
        $updates = [];

        // Check groen: alleen doorschuiven als wedstrijd BESTAAT en ECHT gespeeld is
        if ($this->actieve_wedstrijd_id) {
            $actieve = Wedstrijd::find($this->actieve_wedstrijd_id);
            if ($actieve && $actieve->isEchtGespeeld()) {
                // Actieve wedstrijd is echt gespeeld (met winnaar), doorschuiven
                $updates['actieve_wedstrijd_id'] = $this->volgende_wedstrijd_id;
                $updates['volgende_wedstrijd_id'] = $this->gereedmaken_wedstrijd_id;
                $updates['gereedmaken_wedstrijd_id'] = null;
            }
            // Als wedstrijd niet gevonden: NIET doorschuiven (behoud huidige selectie)
        }

        // Check geel: alleen als groen niet doorgeschoven is
        if (empty($updates) && $this->volgende_wedstrijd_id) {
            $volgende = Wedstrijd::find($this->volgende_wedstrijd_id);
            if ($volgende && $volgende->isEchtGespeeld()) {
                // Volgende wedstrijd is echt gespeeld, doorschuiven
                $updates['volgende_wedstrijd_id'] = $this->gereedmaken_wedstrijd_id;
                $updates['gereedmaken_wedstrijd_id'] = null;
            }
        }

        // Check blauw: alleen als groen en geel niet doorgeschoven zijn
        if (empty($updates) && $this->gereedmaken_wedstrijd_id) {
            $gereedmaken = Wedstrijd::find($this->gereedmaken_wedstrijd_id);
            if ($gereedmaken && $gereedmaken->isEchtGespeeld()) {
                // Gereedmaken wedstrijd is echt gespeeld, verwijderen
                $updates['gereedmaken_wedstrijd_id'] = null;
            }
        }

        if (!empty($updates)) {
            $this->update($updates);
            $this->refresh();
        }
    }

    /**
     * Verwijder selecties die naar niet-bestaande wedstrijden verwijzen
     * Dit is een aparte functie om data-integriteit te herstellen
     */
    public function cleanupOngeldigeSelecties(): void
    {
        $updates = [];

        if ($this->actieve_wedstrijd_id && !Wedstrijd::find($this->actieve_wedstrijd_id)) {
            \Log::warning("Mat {$this->id}: actieve_wedstrijd_id {$this->actieve_wedstrijd_id} bestaat niet, reset naar null");
            $updates['actieve_wedstrijd_id'] = null;
        }

        if ($this->volgende_wedstrijd_id && !Wedstrijd::find($this->volgende_wedstrijd_id)) {
            \Log::warning("Mat {$this->id}: volgende_wedstrijd_id {$this->volgende_wedstrijd_id} bestaat niet, reset naar null");
            $updates['volgende_wedstrijd_id'] = null;
        }

        if ($this->gereedmaken_wedstrijd_id && !Wedstrijd::find($this->gereedmaken_wedstrijd_id)) {
            \Log::warning("Mat {$this->id}: gereedmaken_wedstrijd_id {$this->gereedmaken_wedstrijd_id} bestaat niet, reset naar null");
            $updates['gereedmaken_wedstrijd_id'] = null;
        }

        if (!empty($updates)) {
            $this->update($updates);
            $this->refresh();
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
