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
    }
}
