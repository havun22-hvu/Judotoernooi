<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
    ];

    protected $casts = [
        'geboortejaar' => 'integer',
        'gewicht' => 'decimal:1',
        'actief' => 'boolean',
    ];

    public function organisator(): BelongsTo
    {
        return $this->belongsTo(Organisator::class);
    }

    public function judokas(): HasMany
    {
        return $this->hasMany(Judoka::class);
    }

    public function wimpelJudoka(): HasOne
    {
        return $this->hasOne(WimpelJudoka::class);
    }

    public function scopeActief(Builder $query): Builder
    {
        return $query->where('actief', true);
    }
}
