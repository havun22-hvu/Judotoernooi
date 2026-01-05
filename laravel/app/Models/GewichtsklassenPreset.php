<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GewichtsklassenPreset extends Model
{
    protected $fillable = ['organisator_id', 'naam', 'configuratie'];

    protected $casts = [
        'configuratie' => 'array',
    ];

    public function organisator()
    {
        return $this->belongsTo(Organisator::class);
    }
}
