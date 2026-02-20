<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StamJudoka extends Model
{
    protected $table = 'stam_judokas';

    protected $fillable = [
        'organisator_id',
        'naam',
        'geboortejaar',
        'geslacht',
        'band',
        'gewicht',
        'notities',
        'actief',
        'wimpel_punten_totaal',
        'wimpel_is_nieuw',
    ];

    protected $casts = [
        'geboortejaar' => 'integer',
        'gewicht' => 'decimal:1',
        'actief' => 'boolean',
        'wimpel_punten_totaal' => 'integer',
        'wimpel_is_nieuw' => 'boolean',
    ];

    public function organisator(): BelongsTo
    {
        return $this->belongsTo(Organisator::class);
    }

    public function judokas(): HasMany
    {
        return $this->hasMany(Judoka::class);
    }

    public function wimpelPuntenLog(): HasMany
    {
        return $this->hasMany(WimpelPuntenLog::class)->orderByDesc('created_at');
    }

    public function wimpelUitreikingen(): HasMany
    {
        return $this->hasMany(WimpelUitreiking::class);
    }

    public function wimpelOpenUitreikingen(): HasMany
    {
        return $this->hasMany(WimpelUitreiking::class)->where('uitgereikt', false);
    }

    public function scopeActief(Builder $query): Builder
    {
        return $query->where('actief', true);
    }

    public function scopeMetWimpel(Builder $query): Builder
    {
        return $query->where('wimpel_punten_totaal', '>', 0)
            ->orWhere('wimpel_is_nieuw', true);
    }

    /**
     * Herbereken wimpel_punten_totaal uit alle log entries
     */
    public function herberekenWimpelPunten(): void
    {
        $this->wimpel_punten_totaal = $this->wimpelPuntenLog()->sum('punten');
        $this->save();
    }

    /**
     * Eerstvolgende milestone die nog niet bereikt is
     */
    public function getEerstvolgendeWimpelMilestone(): ?WimpelMilestone
    {
        return WimpelMilestone::where('organisator_id', $this->organisator_id)
            ->where('punten', '>', $this->wimpel_punten_totaal)
            ->orderBy('punten')
            ->first();
    }

    /**
     * Alle bereikte milestones
     */
    public function getBereikteWimpelMilestones()
    {
        return WimpelMilestone::where('organisator_id', $this->organisator_id)
            ->where('punten', '<=', $this->wimpel_punten_totaal)
            ->orderBy('punten')
            ->get();
    }
}
