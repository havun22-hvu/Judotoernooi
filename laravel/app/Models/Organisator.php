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
        'is_test',
        'herdenkingsportaal',
        'kortingsregeling',
        'password',
        'email_verified_at',
        'laatste_login',
        // KYC / Facturatiegegevens
        'organisatie_naam',
        'kvk_nummer',
        'btw_nummer',
        'straat',
        'postcode',
        'plaats',
        'land',
        'contactpersoon',
        'factuur_email',
        'website',
        'kyc_compleet',
        'kyc_ingevuld_op',
        'is_premium',
    ];

    protected $casts = [
        'is_premium' => 'boolean',
        'is_sitebeheerder' => 'boolean',
        'is_test' => 'boolean',
        'herdenkingsportaal' => 'boolean',
        'kortingsregeling' => 'boolean',
        'kyc_compleet' => 'boolean',
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
            'kyc_compleet' => 'boolean',
            'kyc_ingevuld_op' => 'datetime',
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
     * Check if this is a test organisator (bypasses payments)
     */
    public function isTest(): bool
    {
        return $this->is_test === true;
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

    /**
     * Get all gewichtsklassen presets belonging to this organisator
     */
    public function gewichtsklassenPresets(): HasMany
    {
        return $this->hasMany(GewichtsklassenPreset::class);
    }

    /**
     * Get all toernooi betalingen for this organisator
     */
    public function toernooiBetalingen(): HasMany
    {
        return $this->hasMany(ToernooiBetaling::class);
    }

    /**
     * Check if organisator can add more presets (freemium limit)
     */
    public function canAddMorePresets(): bool
    {
        // Premium users have no limit
        if ($this->is_premium) {
            return true;
        }
        // Free tier: max 2 presets
        return $this->gewichtsklassenPresets()->count() < 2;
    }

    /**
     * Get the maximum number of presets allowed
     */
    public function getMaxPresets(): int
    {
        return $this->is_premium ? PHP_INT_MAX : 2;
    }

    /**
     * Check if KYC is complete for invoicing
     */
    public function isKycCompleet(): bool
    {
        return $this->kyc_compleet === true;
    }

    /**
     * Check if all required KYC fields are filled
     */
    public function hasRequiredKycFields(): bool
    {
        return !empty($this->organisatie_naam)
            && !empty($this->straat)
            && !empty($this->postcode)
            && !empty($this->plaats)
            && !empty($this->contactpersoon)
            && !empty($this->factuur_email);
    }

    /**
     * Mark KYC as complete
     */
    public function markKycCompleet(): void
    {
        $this->update([
            'kyc_compleet' => true,
            'kyc_ingevuld_op' => now(),
        ]);
    }

    /**
     * Get formatted address for invoicing
     */
    public function getFactuurAdres(): string
    {
        $parts = array_filter([
            $this->organisatie_naam,
            $this->straat,
            trim($this->postcode . ' ' . $this->plaats),
            $this->land !== 'Nederland' ? $this->land : null,
        ]);
        return implode("\n", $parts);
    }
}
