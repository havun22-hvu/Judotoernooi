<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ClubUitnodiging extends Model
{
    use HasFactory;

    protected $table = 'club_uitnodigingen';

    protected $fillable = [
        'toernooi_id',
        'club_id',
        'token',
        'wachtwoord_hash',
        'uitgenodigd_op',
        'geregistreerd_op',
        'laatst_ingelogd_op',
    ];

    protected $casts = [
        'uitgenodigd_op' => 'datetime',
        'geregistreerd_op' => 'datetime',
        'laatst_ingelogd_op' => 'datetime',
    ];

    protected $hidden = [
        'wachtwoord_hash',
    ];

    protected static function booted(): void
    {
        static::creating(function (ClubUitnodiging $uitnodiging) {
            if (empty($uitnodiging->token)) {
                $uitnodiging->token = Str::random(64);
            }
        });
    }

    public function toernooi(): BelongsTo
    {
        return $this->belongsTo(Toernooi::class);
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function isGeregistreerd(): bool
    {
        return $this->geregistreerd_op !== null;
    }

    public function setWachtwoord(string $wachtwoord): void
    {
        $this->wachtwoord_hash = bcrypt($wachtwoord);
        $this->geregistreerd_op = now();
        $this->save();
    }

    public function checkWachtwoord(string $wachtwoord): bool
    {
        return password_verify($wachtwoord, $this->wachtwoord_hash);
    }

    public function updateLaatstIngelogd(): void
    {
        $this->laatst_ingelogd_op = now();
        $this->save();
    }
}
