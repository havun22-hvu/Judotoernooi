<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Blok extends Model
{
    use HasFactory;

    protected $table = 'blokken';

    protected $fillable = [
        'toernooi_id',
        'nummer',
        'gewenst_wedstrijden',
        'blok_label',
        'weging_start',
        'weging_einde',
        'starttijd',
        'eindtijd',
        'weging_gesloten',
        'weging_gesloten_op',
    ];

    protected $casts = [
        'weging_start' => 'datetime:H:i',
        'weging_einde' => 'datetime:H:i',
        'starttijd' => 'datetime:H:i',
        'eindtijd' => 'datetime:H:i',
        'weging_gesloten' => 'boolean',
        'weging_gesloten_op' => 'datetime',
    ];

    public function toernooi(): BelongsTo
    {
        return $this->belongsTo(Toernooi::class);
    }

    public function poules(): HasMany
    {
        return $this->hasMany(Poule::class);
    }

    public function getNaamAttribute(): string
    {
        return "Blok {$this->nummer}";
    }

    public function getTotaalWedstrijdenAttribute(): int
    {
        return $this->poules()->sum('aantal_wedstrijden');
    }

    public function sluitWeging(): void
    {
        $this->update([
            'weging_gesloten' => true,
            'weging_gesloten_op' => now(),
        ]);

        // Markeer alle niet-gewogen judoka's in dit blok als afwezig
        $this->markeerNietGewogenAlsAfwezig();

        // Herbereken statistieken voor alle poules in dit blok
        $this->herberekenPouleStatistieken();
    }

    /**
     * Herbereken statistieken voor alle poules na weging sluiting
     * Voor eliminatie poules: verwijder afwezige judoka's
     */
    public function herberekenPouleStatistieken(): void
    {
        foreach ($this->poules as $poule) {
            // Voor eliminatie poules: verwijder afwezige judoka's uit de groep
            if ($poule->type === 'eliminatie') {
                $afwezigeIds = $poule->judokas()
                    ->where('aanwezigheid', 'afwezig')
                    ->pluck('judokas.id');

                if ($afwezigeIds->isNotEmpty()) {
                    $poule->judokas()->detach($afwezigeIds);
                }
            }

            // Herbereken statistieken (aantal judoka's en wedstrijden)
            $poule->updateStatistieken();
        }
    }

    /**
     * Markeer alle judoka's in dit blok die niet gewogen zijn als afwezig
     */
    public function markeerNietGewogenAlsAfwezig(): void
    {
        // Haal alle judoka's in poules van dit blok
        $judokaIds = $this->poules()
            ->with('judokas')
            ->get()
            ->flatMap(fn($poule) => $poule->judokas->pluck('id'))
            ->unique();

        // Update alle judoka's die niet gewogen zijn naar afwezig
        Judoka::whereIn('id', $judokaIds)
            ->whereNull('gewicht_gewogen')
            ->where('aanwezigheid', '!=', 'afwezig')
            ->update(['aanwezigheid' => 'afwezig']);
    }

    /**
     * Get alle judoka's in dit blok (via poules)
     */
    public function getJudokas()
    {
        return Judoka::whereHas('poules', function ($q) {
            $q->where('blok_id', $this->id);
        });
    }

    /**
     * Get matten die dit blok bedienen (via poules)
     * Geen pivot tabel nodig - matten zijn gekoppeld via poules
     */
    public function getMattenAttribute()
    {
        return Mat::whereIn('id', $this->poules()->whereNotNull('mat_id')->pluck('mat_id')->unique())->get();
    }

    /**
     * Get poules met gewichtsrange probleem na weging
     * Alleen relevant voor dynamische categorieën waar range > max_kg_verschil
     */
    public function getProblematischePoules(): \Illuminate\Support\Collection
    {
        if (!$this->weging_gesloten) {
            return collect();
        }

        return $this->poules()
            ->with(['judokas', 'toernooi'])
            ->get()
            ->filter(function ($poule) {
                return $poule->isProblematischNaWeging() !== null;
            })
            ->map(function ($poule) {
                $probleem = $poule->isProblematischNaWeging();
                $poule->probleem = $probleem;
                return $poule;
            });
    }

}
