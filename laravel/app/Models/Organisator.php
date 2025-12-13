<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Organisator extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'organisators';

    protected $fillable = [
        'naam',
        'email',
        'telefoon',
        'is_sitebeheerder',
        'password',
        'email_verified_at',
        'laatste_login',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'laatste_login' => 'datetime',
            'password' => 'hashed',
            'is_sitebeheerder' => 'boolean',
        ];
    }

    /**
     * Check if this organisator is a sitebeheerder
     */
    public function isSitebeheerder(): bool
    {
        return $this->is_sitebeheerder === true;
    }

    /**
     * Get all toernooien this organisator has access to
     */
    public function toernooien(): BelongsToMany
    {
        return $this->belongsToMany(Toernooi::class, 'organisator_toernooi')
            ->withPivot('rol')
            ->withTimestamps();
    }

    /**
     * Check if organisator owns a specific toernooi
     */
    public function ownsToernooi(Toernooi $toernooi): bool
    {
        return $this->toernooien()
            ->wherePivot('toernooi_id', $toernooi->id)
            ->wherePivot('rol', 'eigenaar')
            ->exists();
    }

    /**
     * Check if organisator has access to a specific toernooi
     */
    public function hasAccessToToernooi(Toernooi $toernooi): bool
    {
        if ($this->isSitebeheerder()) {
            return true;
        }

        return $this->toernooien()
            ->wherePivot('toernooi_id', $toernooi->id)
            ->exists();
    }

    /**
     * Update last login timestamp
     */
    public function updateLaatsteLogin(): void
    {
        $this->laatste_login = now();
        $this->save();
    }
}
