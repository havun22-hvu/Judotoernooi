<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WimpelMilestone extends Model
{
    protected $table = 'wimpel_milestones';

    protected $fillable = [
        'organisator_id',
        'punten',
        'omschrijving',
        'volgorde',
    ];

    protected $casts = [
        'punten' => 'integer',
        'volgorde' => 'integer',
    ];

    public function organisator(): BelongsTo
    {
        return $this->belongsTo(Organisator::class);
    }
}
