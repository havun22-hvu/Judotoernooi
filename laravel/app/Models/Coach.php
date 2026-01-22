<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Coach extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'toernooi_id',
        'uuid',
        'naam',
        'email',
        'telefoon',
        'laatst_ingelogd_op',
    ];

    protected $casts = [
        'laatst_ingelogd_op' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Coach $coach) {
            if (empty($coach->uuid)) {
                $coach->uuid = (string) Str::uuid();
            }
        });
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function toernooi(): BelongsTo
    {
        return $this->belongsTo(Toernooi::class);
    }

    public function updateLaatstIngelogd(): void
    {
        $this->laatst_ingelogd_op = now();
        $this->save();
    }
}
