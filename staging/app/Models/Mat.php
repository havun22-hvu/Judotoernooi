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
     * Reset wedstrijd selectie als de wedstrijd van een specifieke poule is
     * Inclusief doorschuiving: als geel reset → blauw wordt geel
     */
    public function resetWedstrijdSelectieVoorPoule(int $pouleId): void
    {
        $updates = [];
        $resetGroen = false;
        $resetGeel = false;
        $resetBlauw = false;

        // Check welke kleuren gereset moeten worden
        if ($this->actieve_wedstrijd_id) {
            $actieve = Wedstrijd::find($this->actieve_wedstrijd_id);
            if ($actieve && $actieve->poule_id === $pouleId) {
                $resetGroen = true;
            }
        }

        if ($this->volgende_wedstrijd_id) {
            $volgende = Wedstrijd::find($this->volgende_wedstrijd_id);
            if ($volgende && $volgende->poule_id === $pouleId) {
                $resetGeel = true;
            }
        }

        if ($this->gereedmaken_wedstrijd_id) {
            $gereedmaken = Wedstrijd::find($this->gereedmaken_wedstrijd_id);
            if ($gereedmaken && $gereedmaken->poule_id === $pouleId) {
                $resetBlauw = true;
            }
        }

        // Doorschuiving toepassen
        if ($resetGroen) {
            // Groen reset: geel → groen, blauw → geel
            $updates['actieve_wedstrijd_id'] = $this->volgende_wedstrijd_id;
            $updates['volgende_wedstrijd_id'] = $this->gereedmaken_wedstrijd_id;
            $updates['gereedmaken_wedstrijd_id'] = null;
        } elseif ($resetGeel) {
            // Geel reset: blauw → geel
            $updates['volgende_wedstrijd_id'] = $this->gereedmaken_wedstrijd_id;
            $updates['gereedmaken_wedstrijd_id'] = null;
        } elseif ($resetBlauw) {
            // Blauw reset: geen doorschuiving
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
