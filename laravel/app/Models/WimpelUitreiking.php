<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WimpelUitreiking extends Model
{
    protected $table = 'wimpel_uitreikingen';

    protected $fillable = [
        'stam_judoka_id',
        'wimpel_milestone_id',
        'toernooi_id',
        'uitgereikt',
        'uitgereikt_at',
    ];

    protected $casts = [
        'uitgereikt' => 'boolean',
        'uitgereikt_at' => 'datetime',
    ];

    public function stamJudoka(): BelongsTo
    {
        return $this->belongsTo(StamJudoka::class);
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(WimpelMilestone::class, 'wimpel_milestone_id');
    }

    public function toernooi(): BelongsTo
    {
        return $this->belongsTo(Toernooi::class);
    }
}
