<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TvKoppeling extends Model
{
    protected $table = 'tv_koppelingen';

    protected $fillable = ['code', 'toernooi_id', 'mat_nummer', 'expires_at', 'linked_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'linked_at' => 'datetime',
    ];

    public function toernooi(): BelongsTo
    {
        return $this->belongsTo(Toernooi::class);
    }

    public static function generateCode(): string
    {
        do {
            $code = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (self::where('code', $code)->where('expires_at', '>', now())->exists());

        return $code;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isLinked(): bool
    {
        return $this->linked_at !== null;
    }
}
