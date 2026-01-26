<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class Organisator extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'organisators';

    protected $fillable = [
        'naam',
        'slug',
        'email',
        'telefoon',
        'is_sitebeheerder',
        'password',
        'email_verified_at',
        'laatste_login',
    ];

    protected static function booted(): void
    {
        static::creating(function (Organisator $organisator) {
            if (empty($organisator->slug) && !empty($organisator->naam)) {
                $organisator->slug = static::generateUniqueSlug($organisator->naam);
            }
        });

        static::updating(function (Organisator $organisator) {
            if ($organisator->isDirty('naam') && !$organisator->isDirty('slug')) {
                $organisator->slug = static::generateUniqueSlug($organisator->naam, $organisator->id);
            }
        });
    }

    public static function generateUniqueSlug(string $naam, ?int $excludeId = null): string
    {
        $baseSlug = Str::slug($naam);
        $slug = $baseSlug;
        $counter = 1;

        $query = static::where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
            $query = static::where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

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

    /**
     * Get all clubs belonging to this organisator
     */
    public function clubs(): HasMany
    {
        return $this->hasMany(Club::class);
    }

    /**
     * Get all toernooi templates belonging to this organisator
     */
    public function toernooiTemplates(): HasMany
    {
        return $this->hasMany(ToernooiTemplate::class);
    }
}
