<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WimpelPuntenLog extends Model
{
    protected $table = 'wimpel_punten_log';

    public $timestamps = false;

    protected $fillable = [
        'wimpel_judoka_id',
        'toernooi_id',
        'poule_id',
        'punten',
        'type',
        'notitie',
    ];

    protected $casts = [
        'punten' => 'integer',
        'created_at' => 'datetime',
    ];

    public function wimpelJudoka(): BelongsTo
    {
        return $this->belongsTo(WimpelJudoka::class);
    }

    public function toernooi(): BelongsTo
    {
        return $this->belongsTo(Toernooi::class);
    }
}
