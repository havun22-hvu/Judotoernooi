<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WimpelJudoka extends Model
{
    protected $table = 'wimpel_judokas';

    protected $fillable = [
        'organisator_id',
        'stam_judoka_id',
        'naam',
        'geboortejaar',
        'punten_totaal',
        'is_nieuw',
    ];

    protected $casts = [
        'geboortejaar' => 'integer',
        'punten_totaal' => 'integer',
        'is_nieuw' => 'boolean',
    ];

    public function organisator(): BelongsTo
    {
        return $this->belongsTo(Organisator::class);
    }

    public function stamJudoka(): BelongsTo
    {
        return $this->belongsTo(StamJudoka::class);
    }

    public function puntenLog(): HasMany
    {
        return $this->hasMany(WimpelPuntenLog::class)->orderByDesc('created_at');
    }

    public function uitreikingen(): HasMany
    {
        return $this->hasMany(WimpelUitreiking::class);
    }

    public function openUitreikingen(): HasMany
    {
        return $this->hasMany(WimpelUitreiking::class)->where('uitgereikt', false);
    }

    /**
     * Herbereken punten_totaal uit alle log entries
     */
    public function herbereken(): void
    {
        $this->punten_totaal = $this->puntenLog()->sum('punten');
        $this->save();
    }

    /**
     * Eerstvolgende milestone die nog niet bereikt is
     */
    public function getEerstvolgeneMilestone(): ?WimpelMilestone
    {
        return WimpelMilestone::where('organisator_id', $this->organisator_id)
            ->where('punten', '>', $this->punten_totaal)
            ->orderBy('punten')
            ->first();
    }

    /**
     * Alle bereikte milestones
     */
    public function getBereikteMilestones()
    {
        return WimpelMilestone::where('organisator_id', $this->organisator_id)
            ->where('punten', '<=', $this->punten_totaal)
            ->orderBy('punten')
            ->get();
    }
}
