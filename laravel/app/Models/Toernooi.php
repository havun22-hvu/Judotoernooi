<?php

namespace App\Models;

use App\Http\Controllers\RoleToegang;
use App\Models\Concerns\HasCategorieBepaling;
use App\Models\Concerns\HasMolliePayments;
use App\Models\Concerns\HasPortaalModus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Toernooi extends Model
{
    use HasFactory;
    use HasMolliePayments;
    use HasPortaalModus;
    use HasCategorieBepaling;

    protected static function booted(): void
    {
        static::creating(function (Toernooi $toernooi) {
            // Generate slug from name (scoped to organisator)
            if (empty($toernooi->slug) && !empty($toernooi->naam)) {
                $toernooi->slug = static::generateUniqueSlug($toernooi->naam, $toernooi->organisator_id);
            }

            // Generate unique codes for each role
            if (empty($toernooi->code_hoofdjury)) {
                $toernooi->code_hoofdjury = RoleToegang::generateCode();
            }
            if (empty($toernooi->code_weging)) {
                $toernooi->code_weging = RoleToegang::generateCode();
            }
            if (empty($toernooi->code_mat)) {
                $toernooi->code_mat = RoleToegang::generateCode();
            }
            if (empty($toernooi->code_spreker)) {
                $toernooi->code_spreker = RoleToegang::generateCode();
            }
            if (empty($toernooi->code_dojo)) {
                $toernooi->code_dojo = RoleToegang::generateCode();
            }
        });

        static::updating(function (Toernooi $toernooi) {
            // Update slug if name changed and slug not manually set
            if ($toernooi->isDirty('naam') && !$toernooi->isDirty('slug')) {
                $toernooi->slug = static::generateUniqueSlug($toernooi->naam, $toernooi->organisator_id, $toernooi->id);
            }
        });
    }

    /**
     * Generate unique slug scoped to organisator
     */
    public static function generateUniqueSlug(string $naam, ?int $organisatorId = null, ?int $excludeId = null): string
    {
        $baseSlug = Str::slug($naam);
        $slug = $baseSlug;
        $counter = 1;

        $query = static::where('slug', $slug)->where('organisator_id', $organisatorId);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
            $query = static::where('slug', $slug)->where('organisator_id', $organisatorId);
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

    /**
     * Get route parameters for this toernooi (includes organisator)
     * Usage: route('toernooi.show', $toernooi->routeParams())
     */
    public function routeParams(): array
    {
        return [
            'organisator' => $this->organisator?->slug ?? $this->organisator_id,
            'toernooi' => $this->slug,
        ];
    }

    /**
     * Get route parameters with additional params
     * Usage: route('toernooi.judoka.show', $toernooi->routeParamsWith(['judoka' => $judoka]))
     */
    public function routeParamsWith(array $params): array
    {
        return array_merge($this->routeParams(), $params);
    }

    protected $table = 'toernooien';

    protected $attributes = [
        'portaal_modus' => 'mutaties',
    ];

    protected $fillable = [
        'organisator_id',
        'plan_type',
        'paid_tier',
        'paid_max_judokas',
        'paid_at',
        'toernooi_betaling_id',
        'naam',
        'slug',
        'organisatie',
        'datum',
        'inschrijving_deadline',
        'max_judokas',
        'locatie',
        'verwacht_aantal_judokas',
        'aantal_matten',
        'aantal_blokken',
        'poule_grootte_voorkeur',
        'dubbel_bij_2_judokas',
        'best_of_three_bij_2',
        'dubbel_bij_3_judokas',
        'dubbel_bij_4_judokas',
        'wedstrijd_systeem',
        'eliminatie_gewichtsklassen',
        'kruisfinales_aantal',
        'eliminatie_type',
        'aantal_brons',
        'gewicht_tolerantie',
        'max_kg_verschil',
        'max_leeftijd_verschil',
        'gebruik_gewichtsklassen',
        'weging_verplicht',
        'max_wegingen',
        'judokas_per_coach',
        'coach_incheck_actief',
        'danpunten_actief',
        'is_actief',
        'poules_gegenereerd_op',
        'blokken_verdeeld_op',
        'weegkaarten_gemaakt_op',
        'voorbereiding_klaar_op',
        'gewichtsklassen',
        'mat_voorkeuren',
        'wachtwoord_admin',
        'wachtwoord_jury',
        'wachtwoord_weging',
        'wachtwoord_mat',
        'wachtwoord_spreker',
        'verdeling_prioriteiten',
        'code_hoofdjury',
        'code_weging',
        'code_mat',
        'code_spreker',
        'code_dojo',
        'wedstrijd_schemas',
        'pagina_content',
        'spreker_notities',
        'thema_kleur',
        'afgesloten_at',
        'herinnering_datum',
        'herinnering_verstuurd',
        'betaling_actief',
        'portaal_modus',
        'weegkaarten_publiek',
        'inschrijfgeld',
        'mollie_mode',
        'platform_toeslag',
        'platform_toeslag_percentage',
        'mollie_account_id',
        'mollie_access_token',
        'mollie_refresh_token',
        'mollie_token_expires_at',
        'mollie_onboarded',
        'mollie_organization_name',
        'punten_competitie_wedstrijden',
        'import_fouten',
        'local_server_primary_ip',
        'local_server_standby_ip',
        'heeft_eigen_router',
        'eigen_router_ssid',
        'eigen_router_wachtwoord',
        'hotspot_ssid',
        'hotspot_wachtwoord',
        'hotspot_ip',
        'locale',
    ];

    protected $hidden = [
        'wachtwoord_admin',
        'wachtwoord_jury',
        'wachtwoord_weging',
        'wachtwoord_mat',
        'wachtwoord_spreker',
        'code_hoofdjury',
        'code_weging',
        'code_mat',
        'code_spreker',
        'code_dojo',
        'mollie_access_token',
        'mollie_refresh_token',
    ];

    protected $casts = [
        'datum' => 'date',
        'inschrijving_deadline' => 'date',
        'is_actief' => 'boolean',
        'dubbel_bij_2_judokas' => 'boolean',
        'best_of_three_bij_2' => 'boolean',
        'dubbel_bij_3_judokas' => 'boolean',
        'dubbel_bij_4_judokas' => 'boolean',
        'weging_verplicht' => 'boolean',
        'danpunten_actief' => 'boolean',
        'gebruik_gewichtsklassen' => 'boolean',
        'wedstrijd_systeem' => 'array',
        'eliminatie_gewichtsklassen' => 'array',
        'aantal_brons' => 'integer',
        'poules_gegenereerd_op' => 'datetime',
        'blokken_verdeeld_op' => 'datetime',
        'weegkaarten_gemaakt_op' => 'datetime',
        'voorbereiding_klaar_op' => 'datetime',
        'gewicht_tolerantie' => 'decimal:1',
        'gewichtsklassen' => 'array',
        'poule_grootte_voorkeur' => 'array',
        'mat_voorkeuren' => 'array',
        'verdeling_prioriteiten' => 'array',
        'wedstrijd_schemas' => 'array',
        'pagina_content' => 'array',
        'afgesloten_at' => 'datetime',
        'herinnering_datum' => 'date',
        'herinnering_verstuurd' => 'boolean',
        'betaling_actief' => 'boolean',
        'inschrijfgeld' => 'decimal:2',
        'platform_toeslag' => 'decimal:2',
        'platform_toeslag_percentage' => 'boolean',
        'mollie_token_expires_at' => 'datetime',
        'mollie_onboarded' => 'boolean',
        'punten_competitie_wedstrijden' => 'array',
        'import_fouten' => 'array',
        'coach_incheck_actief' => 'boolean',
        'weegkaarten_publiek' => 'boolean',
        'paid_max_judokas' => 'integer',
        'paid_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Pool Size Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Get the pool size preferences, with fallback to default order
     */
    public function getPouleGrootteVoorkeurOfDefault(): array
    {
        if (!empty($this->poule_grootte_voorkeur)) {
            return $this->poule_grootte_voorkeur;
        }

        // Default preference order: 5, 4, 6, 3
        return [5, 4, 6, 3];
    }

    /**
     * Get minimum pool size from preference list
     */
    public function getMinJudokasPouleAttribute(): int
    {
        $voorkeur = $this->getPouleGrootteVoorkeurOfDefault();
        return !empty($voorkeur) ? min($voorkeur) : 3;
    }

    /**
     * Get maximum pool size from preference list
     */
    public function getMaxJudokasPouleAttribute(): int
    {
        $voorkeur = $this->getPouleGrootteVoorkeurOfDefault();
        return !empty($voorkeur) ? max($voorkeur) : 6;
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function judokas(): HasMany
    {
        return $this->hasMany(Judoka::class);
    }

    public function blokken(): HasMany
    {
        return $this->hasMany(Blok::class)->orderBy('nummer');
    }

    public function betalingen(): HasMany
    {
        return $this->hasMany(Betaling::class);
    }

    public function matten(): HasMany
    {
        return $this->hasMany(Mat::class)->orderBy('nummer');
    }

    public function poules(): HasMany
    {
        return $this->hasMany(Poule::class)->orderBy('nummer');
    }

    public function clubUitnodigingen(): HasMany
    {
        return $this->hasMany(ClubUitnodiging::class);
    }

    /**
     * Get all clubs linked to this tournament via pivot.
     */
    public function clubs(): BelongsToMany
    {
        return $this->belongsToMany(Club::class, 'club_toernooi')
            ->withPivot('portal_code', 'pincode')
            ->withTimestamps();
    }

    /**
     * Get all clubs that have judokas in this tournament (legacy method).
     */
    public function clubsMetJudokas(): \Illuminate\Database\Eloquent\Builder
    {
        $clubIds = $this->judokas()->whereNotNull('club_id')->pluck('club_id')->unique();
        return Club::whereIn('id', $clubIds);
    }

    /**
     * Get club by portal code for this tournament.
     */
    public function getClubByPortalCode(string $code): ?Club
    {
        return $this->clubs()->wherePivot('portal_code', $code)->first();
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function deviceToegangen(): HasMany
    {
        return $this->hasMany(DeviceToegang::class);
    }

    /**
     * Get the owner organisator of this toernooi
     */
    public function organisator(): BelongsTo
    {
        return $this->belongsTo(Organisator::class);
    }

    /**
     * Get all organisatoren linked to this toernooi (for access control)
     */
    public function organisatoren(): BelongsToMany
    {
        return $this->belongsToMany(Organisator::class, 'organisator_toernooi')
            ->withPivot('rol')
            ->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActief($query)
    {
        return $query->where('is_actief', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors / Status Methods
    |--------------------------------------------------------------------------
    */

    public function getTotaalWedstrijdenAttribute(): int
    {
        return $this->poules()->sum('aantal_wedstrijden');
    }

    public function getTotaalJudokasAttribute(): int
    {
        return $this->judokas()->count();
    }

    public function isInschrijvingOpen(): bool
    {
        if (!$this->inschrijving_deadline) {
            return true;
        }
        return now()->startOfDay()->lte($this->inschrijving_deadline);
    }

    public function isMaxJudokasBereikt(): bool
    {
        if (!$this->max_judokas) {
            return false;
        }
        return $this->judokas()->count() >= $this->max_judokas;
    }

    public function getBezettingsPercentageAttribute(): ?int
    {
        if (!$this->max_judokas) {
            return null;
        }
        return (int) round(($this->judokas()->count() / $this->max_judokas) * 100);
    }

    public function isBijna80ProcentVol(): bool
    {
        $percentage = $this->bezettings_percentage;
        return $percentage !== null && $percentage >= 80 && $percentage < 100;
    }

    public function isAfgesloten(): bool
    {
        return $this->afgesloten_at !== null;
    }

    /**
     * Check of wedstrijddag is gestart (minimaal één blok heeft weging gesloten)
     * Na dit punt zijn Poules/Blokken pagina's LOCKED
     */
    public function isWedstrijddagGestart(): bool
    {
        return $this->blokken()->where('weging_gesloten', true)->exists();
    }

    /**
     * Get timestamp van eerste gesloten weging (start wedstrijddag)
     */
    public function getWedstrijddagStartTijd(): ?\Carbon\Carbon
    {
        return $this->blokken()
            ->whereNotNull('weging_gesloten_op')
            ->orderBy('weging_gesloten_op')
            ->value('weging_gesloten_op');
    }

    public function getPlaatsenOverAttribute(): ?int
    {
        if (!$this->max_judokas) {
            return null;
        }
        return max(0, $this->max_judokas - $this->judokas()->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Wachtwoord Methods
    |--------------------------------------------------------------------------
    */

    public function setWachtwoord(string $rol, string $wachtwoord): void
    {
        $veld = "wachtwoord_{$rol}";
        if (in_array($veld, ['wachtwoord_admin', 'wachtwoord_jury', 'wachtwoord_weging', 'wachtwoord_mat', 'wachtwoord_spreker'])) {
            $this->$veld = bcrypt($wachtwoord);
            $this->save();
        }
    }

    public function checkWachtwoord(string $rol, ?string $wachtwoord): bool
    {
        $veld = "wachtwoord_{$rol}";
        if (!in_array($veld, ['wachtwoord_admin', 'wachtwoord_jury', 'wachtwoord_weging', 'wachtwoord_mat', 'wachtwoord_spreker'])) {
            return false;
        }

        $hash = $this->$veld;
        if (!$hash) {
            return false;
        }

        if ($wachtwoord === null) {
            return false;
        }

        return password_verify($wachtwoord, $hash);
    }

    public function heeftWachtwoord(string $rol): bool
    {
        $veld = "wachtwoord_{$rol}";
        return !empty($this->$veld);
    }

    /*
    |--------------------------------------------------------------------------
    | Gewichtsklassen Methods
    |--------------------------------------------------------------------------
    */

    public static function getStandaardGewichtsklassen(string $jbnVersie = '2025'): array
    {
        return config("gewichtsklassen.{$jbnVersie}", config('gewichtsklassen.2025'));
    }

    /**
     * @deprecated Use config('gewichtsklassen.2025') instead
     */
    public static function getJbn2025Gewichtsklassen(): array
    {
        return config('gewichtsklassen.2025');
    }

    /**
     * @deprecated Use config('gewichtsklassen.2026') instead
     */
    public static function getJbn2026Gewichtsklassen(): array
    {
        return config('gewichtsklassen.2026');
    }

    public function getGewichtsklassenVoorLeeftijd(string $leeftijdsklasseKey): array
    {
        $klassen = $this->gewichtsklassen ?? self::getStandaardGewichtsklassen();
        return $klassen[$leeftijdsklasseKey]['gewichten'] ?? [];
    }

    public function getAlleGewichtsklassen(): array
    {
        // Return empty array if no categories configured (user starts fresh)
        $klassen = $this->gewichtsklassen ?? [];

        // Filter metadata keys (start with _)
        $klassen = array_filter($klassen, function ($key) {
            return !str_starts_with($key, '_');
        }, ARRAY_FILTER_USE_KEY);

        // Sort by max_leeftijd (youngest first = Mini's, then Jeugd, then Dames/Heren)
        uasort($klassen, function ($a, $b) {
            return ($a['max_leeftijd'] ?? 99) <=> ($b['max_leeftijd'] ?? 99);
        });

        return $klassen;
    }

    /**
     * Get category order mapping from preset config.
     * Returns array of [label => order_number] based on preset key order.
     */
    public function getCategorieVolgorde(): array
    {
        $config = $this->getAlleGewichtsklassen();
        $volgorde = [];
        $i = 0;
        foreach ($config as $key => $data) {
            $volgorde[$data['label'] ?? $key] = $i++;
        }
        return $volgorde;
    }

    /**
     * Get category key by label from preset config.
     */
    public function getCategorieKeyByLabel(string $label): ?string
    {
        $config = $this->getAlleGewichtsklassen();
        foreach ($config as $key => $data) {
            if (($data['label'] ?? '') === $label) {
                return $key;
            }
        }
        return null;
    }

    public function resetGewichtsklassenNaarStandaard(): void
    {
        $this->update(['gewichtsklassen' => self::getStandaardGewichtsklassen()]);
    }

    /*
    |--------------------------------------------------------------------------
    | Role URL Methods
    |--------------------------------------------------------------------------
    */

    public function getRoleUrl(string $rol): ?string
    {
        $code = match ($rol) {
            'hoofdjury' => $this->code_hoofdjury,
            'weging' => $this->code_weging,
            'mat' => $this->code_mat,
            'spreker' => $this->code_spreker,
            'dojo' => $this->code_dojo,
            default => null,
        };

        return $code ? route('rol.toegang', $code) : null;
    }

    public function regenerateRoleCode(string $rol): ?string
    {
        $veld = "code_{$rol}";
        if (!in_array($veld, ['code_hoofdjury', 'code_weging', 'code_mat', 'code_spreker', 'code_dojo'])) {
            return null;
        }

        $this->$veld = RoleToegang::generateCode();
        $this->save();

        return $this->$veld;
    }

    /*
    |--------------------------------------------------------------------------
    | Freemium Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Get the toernooi betaling (upgrade payment) for this tournament
     */
    public function toernooiBetaling(): BelongsTo
    {
        return $this->belongsTo(ToernooiBetaling::class);
    }

    /**
     * Check if this tournament is on the free tier
     * Testfase: Cees Veen en sitebeheerder hebben altijd volledige toegang
     */
    public function isFreeTier(): bool
    {
        // Testfase: bepaalde organisatoren hebben gratis volledige toegang
        $freeAccessSlugs = ['cees-veen', 'judoschool-cees-veen'];
        if ($this->organisator && in_array($this->organisator->slug, $freeAccessSlugs)) {
            return false;
        }

        // Sitebeheerder (henkvu) heeft altijd volledige toegang
        if (auth()->check() && auth()->user()->is_sitebeheerder) {
            return false;
        }

        return ($this->plan_type ?? 'free') === 'free';
    }

    /**
     * Check if this tournament is on a paid tier
     */
    public function isPaidTier(): bool
    {
        return ($this->plan_type ?? 'free') === 'paid';
    }

    /**
     * Get the effective max judokas limit based on plan
     */
    public function getEffectiveMaxJudokas(): int
    {
        if ($this->isPaidTier()) {
            return $this->paid_max_judokas ?? 50;
        }
        return 50; // Free tier limit
    }

    /**
     * Check if more judokas can be added (freemium limit)
     */
    public function canAddMoreJudokas(int $toevoegen = 1): bool
    {
        $huidige = $this->judokas()->count();
        $max = $this->getEffectiveMaxJudokas();
        return ($huidige + $toevoegen) <= $max;
    }

    /**
     * Get remaining judoka slots
     */
    public function getRemainingJudokaSlots(): int
    {
        return max(0, $this->getEffectiveMaxJudokas() - $this->judokas()->count());
    }

    /**
     * Check if print functionality is available (paid tier only)
     */
    public function canUsePrint(): bool
    {
        return $this->isPaidTier();
    }

    /**
     * Check if this toernooi needs an upgrade
     */
    public function needsUpgrade(): bool
    {
        if ($this->isPaidTier()) {
            return false;
        }
        return $this->judokas()->count() >= 50;
    }

    /**
     * Get the staffel price for a given tier
     */
    public static function getStaffelPrijs(string $tier): ?float
    {
        $staffels = [
            '51-100' => 20,
            '101-150' => 30,
            '151-200' => 40,
            '201-250' => 50,
            '251-300' => 60,
            '301-350' => 70,
            '351-400' => 80,
            '401-500' => 100,
        ];
        return $staffels[$tier] ?? null;
    }
}
