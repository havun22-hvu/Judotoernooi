<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Weging extends Model
{
    use HasFactory;

    protected $table = 'wegingen';

    protected $fillable = [
        'judoka_id',
        'gewicht',
        'binnen_klasse',
        'alternatieve_poule',
        'opmerking',
        'geregistreerd_door',
    ];

    protected $casts = [
        'gewicht' => 'decimal:1',
        'binnen_klasse' => 'boolean',
    ];

    public function judoka(): BelongsTo
    {
        return $this->belongsTo(Judoka::class);
    }
}
