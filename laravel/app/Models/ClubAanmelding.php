<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubAanmelding extends Model
{
    protected $table = 'club_aanmeldingen';

    protected $fillable = [
        'toernooi_id',
        'club_naam',
        'contact_naam',
        'email',
        'telefoon',
        'status',
    ];

    public function toernooi(): BelongsTo
    {
        return $this->belongsTo(Toernooi::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
