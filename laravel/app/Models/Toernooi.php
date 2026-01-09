<?php

namespace App\Models;

use App\Http\Controllers\RoleToegang;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Toernooi extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Toernooi $toernooi) {
            // Generate slug from name
            if (empty($toernooi->slug) && !empty($toernooi->naam)) {
                $toernooi->slug = static::generateUniqueSlug($toernooi->naam);
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
                $toernooi->slug = static::generateUniqueSlug($toernooi->naam, $toernooi->id);
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

    protected $table = 'toernooien';

    protected $fillable = [
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
        'clubspreiding',
        'dubbel_bij_2_judokas',
        'dubbel_bij_3_judokas',
        'dubbel_bij_4_judokas',
        'wedstrijd_systeem',
        'eliminatie_gewichtsklassen',
        'kruisfinales_aantal',
        'eliminatie_type',
        'aantal_brons',
        'gewicht_tolerantie',
        'judoka_code_volgorde',
        'max_kg_verschil',
        'max_leeftijd_verschil',
        'gebruik_gewichtsklassen',
        'weging_verplicht',
        'max_wegingen',
        'judokas_per_coach',
        'is_actief',
        'poules_gegenereerd_op',
        'blokken_verdeeld_op',
        'weegkaarten_gemaakt_op',
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
        'thema_kleur',
        'afgesloten_at',
        'herinnering_datum',
        'herinnering_verstuurd',
        'betaling_actief',
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
        'clubspreiding' => 'boolean',
        'dubbel_bij_2_judokas' => 'boolean',
        'dubbel_bij_3_judokas' => 'boolean',
        'dubbel_bij_4_judokas' => 'boolean',
        'weging_verplicht' => 'boolean',
        'gebruik_gewichtsklassen' => 'boolean',
        'wedstrijd_systeem' => 'array',
        'eliminatie_gewichtsklassen' => 'array',
        'aantal_brons' => 'integer',
        'poules_gegenereerd_op' => 'datetime',
        'blokken_verdeeld_op' => 'datetime',
        'weegkaarten_gemaakt_op' => 'datetime',
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
    ];

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

    public function scopeActief($query)
    {
        return $query->where('is_actief', true);
    }

    public function getTotaalWedstrijdenAttribute(): int
    {
        return $this->poules()->sum('aantal_wedstrijden');
    }

    public function getTotaalJudokasAttribute(): int
    {
        return $this->judokas()->count();
    }

    public function clubUitnodigingen(): HasMany
    {
        return $this->hasMany(ClubUitnodiging::class);
    }

    public function deviceToegangen(): HasMany
    {
        return $this->hasMany(DeviceToegang::class);
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

    public function getPlaatsenOverAttribute(): ?int
    {
        if (!$this->max_judokas) {
            return null;
        }
        return max(0, $this->max_judokas - $this->judokas()->count());
    }

    // Wachtwoord methodes
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

    // Gewichtsklassen methodes
    public static function getStandaardGewichtsklassen(string $jbnVersie = '2025'): array
    {
        if ($jbnVersie === '2026') {
            return self::getJbn2026Gewichtsklassen();
        }
        return self::getJbn2025Gewichtsklassen();
    }

    /**
     * JBN 2025 regels (huidige): -8, -10, -12, -15, -18, -21, Senioren
     * Officieel volgens BondsVademecum hoofdstuk 4.03a (1 juli 2025)
     * Gemengd tot -12 jaar, gescheiden vanaf -15 jaar
     */
    public static function getJbn2025Gewichtsklassen(): array
    {
        return [
            'minis' => [
                'label' => "Mini's",
                'max_leeftijd' => 7,
                'geslacht' => 'gemengd',
                'gewichten' => ['-18', '-21', '-24', '-27', '-30', '-34', '-38', '+38'],
            ],
            'pupillen_a' => [
                'label' => 'Pupillen A',
                'max_leeftijd' => 9,
                'geslacht' => 'gemengd',
                'gewichten' => ['-21', '-24', '-27', '-30', '-34', '-38', '-42', '-46', '-50', '+50'],
            ],
            'pupillen_b' => [
                'label' => 'Pupillen B',
                'max_leeftijd' => 11,
                'geslacht' => 'gemengd',
                'gewichten' => ['-24', '-27', '-30', '-34', '-38', '-42', '-46', '-50', '-55', '+55'],
            ],
            'dames_15' => [
                'label' => 'Dames -15',
                'max_leeftijd' => 14,
                'geslacht' => 'V',
                'gewichten' => ['-32', '-36', '-40', '-44', '-48', '-52', '-57', '-63', '+63'],
            ],
            'heren_15' => [
                'label' => 'Heren -15',
                'max_leeftijd' => 14,
                'geslacht' => 'M',
                'gewichten' => ['-34', '-38', '-42', '-46', '-50', '-55', '-60', '-66', '+66'],
            ],
            'dames_18' => [
                'label' => 'Dames -18',
                'max_leeftijd' => 17,
                'geslacht' => 'V',
                'gewichten' => ['-40', '-44', '-48', '-52', '-57', '-63', '-70', '+70'],
            ],
            'heren_18' => [
                'label' => 'Heren -18',
                'max_leeftijd' => 17,
                'geslacht' => 'M',
                'gewichten' => ['-46', '-50', '-55', '-60', '-66', '-73', '-81', '-90', '+90'],
            ],
            'dames_21' => [
                'label' => 'Dames -21',
                'max_leeftijd' => 20,
                'geslacht' => 'V',
                'gewichten' => ['-48', '-52', '-57', '-63', '-70', '-78', '+78'],
            ],
            'heren_21' => [
                'label' => 'Heren -21',
                'max_leeftijd' => 20,
                'geslacht' => 'M',
                'gewichten' => ['-60', '-66', '-73', '-81', '-90', '-100', '+100'],
            ],
            'dames' => [
                'label' => 'Dames Senioren',
                'max_leeftijd' => 99,
                'geslacht' => 'V',
                'gewichten' => ['-48', '-52', '-57', '-63', '-70', '-78', '+78'],
            ],
            'heren' => [
                'label' => 'Heren Senioren',
                'max_leeftijd' => 99,
                'geslacht' => 'M',
                'gewichten' => ['-60', '-66', '-73', '-81', '-90', '-100', '+100'],
            ],
        ];
    }

    /**
     * JBN 2026 regels (nieuw): -7, -9, -11, -13, -15
     * Leeftijdsgrenzen volgens BondsVademecum 4.02 (31 jan 2025)
     * Gewichtsklassen: voorlopig gebaseerd op JBN 2025 (officiële 2026 nog niet gepubliceerd)
     * Gemengd tot -13 jaar, gescheiden vanaf -15 jaar
     */
    public static function getJbn2026Gewichtsklassen(): array
    {
        return [
            'minis' => [
                'label' => "Mini's",
                'max_leeftijd' => 6,
                'geslacht' => 'gemengd',
                'gewichten' => ['-18', '-21', '-24', '-27', '-30', '-34', '+34'],
            ],
            'pupillen_a' => [
                'label' => 'Pupillen A',
                'max_leeftijd' => 8,
                'geslacht' => 'gemengd',
                'gewichten' => ['-21', '-24', '-27', '-30', '-34', '-38', '-42', '+42'],
            ],
            'pupillen_b' => [
                'label' => 'Pupillen B',
                'max_leeftijd' => 10,
                'geslacht' => 'gemengd',
                'gewichten' => ['-24', '-27', '-30', '-34', '-38', '-42', '-46', '-50', '+50'],
            ],
            'pupillen_c' => [
                'label' => 'Pupillen C',
                'max_leeftijd' => 12,
                'geslacht' => 'gemengd',
                'gewichten' => ['-27', '-30', '-34', '-38', '-42', '-46', '-50', '-55', '+55'],
            ],
            'dames_15' => [
                'label' => 'Dames -15',
                'max_leeftijd' => 14,
                'geslacht' => 'V',
                'gewichten' => ['-32', '-36', '-40', '-44', '-48', '-52', '-57', '-63', '+63'],
            ],
            'heren_15' => [
                'label' => 'Heren -15',
                'max_leeftijd' => 14,
                'geslacht' => 'M',
                'gewichten' => ['-34', '-38', '-42', '-46', '-50', '-55', '-60', '-66', '+66'],
            ],
            'dames_18' => [
                'label' => 'Dames -18',
                'max_leeftijd' => 17,
                'geslacht' => 'V',
                'gewichten' => ['-40', '-44', '-48', '-52', '-57', '-63', '-70', '+70'],
            ],
            'heren_18' => [
                'label' => 'Heren -18',
                'max_leeftijd' => 17,
                'geslacht' => 'M',
                'gewichten' => ['-46', '-50', '-55', '-60', '-66', '-73', '-81', '-90', '+90'],
            ],
            'dames_21' => [
                'label' => 'Dames -21',
                'max_leeftijd' => 20,
                'geslacht' => 'V',
                'gewichten' => ['-48', '-52', '-57', '-63', '-70', '-78', '+78'],
            ],
            'heren_21' => [
                'label' => 'Heren -21',
                'max_leeftijd' => 20,
                'geslacht' => 'M',
                'gewichten' => ['-60', '-66', '-73', '-81', '-90', '-100', '+100'],
            ],
            'dames' => [
                'label' => 'Dames Senioren',
                'max_leeftijd' => 99,
                'geslacht' => 'V',
                'gewichten' => ['-48', '-52', '-57', '-63', '-70', '-78', '+78'],
            ],
            'heren' => [
                'label' => 'Heren Senioren',
                'max_leeftijd' => 99,
                'geslacht' => 'M',
                'gewichten' => ['-60', '-66', '-73', '-81', '-90', '-100', '+100'],
            ],
        ];
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

        // Sort by max_leeftijd (youngest first, seniors last)
        uasort($klassen, function ($a, $b) {
            return ($a['max_leeftijd'] ?? 99) <=> ($b['max_leeftijd'] ?? 99);
        });

        return $klassen;
    }

    public function resetGewichtsklassenNaarStandaard(): void
    {
        $this->update(['gewichtsklassen' => self::getStandaardGewichtsklassen()]);
    }

    // Role URL methods
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
    | Mollie Payment Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if tournament uses Mollie Connect (organizer's own Mollie)
     */
    public function usesMollieConnect(): bool
    {
        return $this->mollie_mode === 'connect' && $this->mollie_onboarded;
    }

    /**
     * Check if tournament uses platform mode (JudoToernooi's Mollie)
     */
    public function usesPlatformPayments(): bool
    {
        return $this->mollie_mode === 'platform' || !$this->mollie_onboarded;
    }

    /**
     * Check if Mollie is properly configured for this tournament
     */
    public function hasMollieConfigured(): bool
    {
        if ($this->mollie_mode === 'connect') {
            return $this->mollie_onboarded && !empty($this->mollie_access_token);
        }

        // Platform mode: check if platform keys are configured
        return !empty(config('services.mollie.platform_key'))
            || !empty(config('services.mollie.platform_test_key'));
    }

    /**
     * Get the platform fee for this tournament
     */
    public function getPlatformFee(): float
    {
        if ($this->mollie_mode !== 'platform') {
            return 0;
        }

        return $this->platform_toeslag ?? config('services.mollie.default_platform_fee', 0.50);
    }

    /**
     * Calculate total payment amount including platform fee
     */
    public function calculatePaymentAmount(int $aantalJudokas): float
    {
        $baseAmount = $aantalJudokas * ($this->inschrijfgeld ?? 0);

        if ($this->mollie_mode !== 'platform') {
            return $baseAmount;
        }

        $fee = $this->getPlatformFee();

        if ($this->platform_toeslag_percentage) {
            return $baseAmount * (1 + ($fee / 100));
        }

        return $baseAmount + $fee;
    }

    /**
     * Get Mollie status display text
     */
    public function getMollieStatusText(): string
    {
        if (!$this->betaling_actief) {
            return 'Betalingen uitgeschakeld';
        }

        if ($this->mollie_mode === 'connect') {
            return $this->mollie_onboarded
                ? 'Gekoppeld: ' . ($this->mollie_organization_name ?? 'Eigen Mollie')
                : 'Niet gekoppeld';
        }

        return 'Via JudoToernooi platform';
    }
}
