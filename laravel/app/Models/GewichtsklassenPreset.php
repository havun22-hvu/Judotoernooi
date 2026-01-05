<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GewichtsklassenPreset extends Model
{
    protected $fillable = ['user_id', 'naam', 'configuratie'];

    protected $casts = [
        'configuratie' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
