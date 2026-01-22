<?php

namespace App\Models;

use App\Helpers\BandHelper;
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
        'dubbel_bij_2_judokas',
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
        'import_fouten',
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
        'import_fouten' => 'array',
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
     * JBN 2025 regels: Mini's (-8), A-pupillen (-10), B-pupillen (-12), -15, -18, -21, Senioren
     * Gemengd t/m B-pupillen, gescheiden vanaf -15 (Heren/Dames)
     * VASTE gewichtsklassen
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
            'a_pupillen' => [
                'label' => 'A-pupillen',
                'max_leeftijd' => 9,
                'geslacht' => 'gemengd',
                'gewichten' => ['-21', '-24', '-27', '-30', '-34', '-38', '-42', '-46', '-50', '+50'],
            ],
            'b_pupillen' => [
                'label' => 'B-pupillen',
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
                'label' => 'Dames',
                'max_leeftijd' => 99,
                'geslacht' => 'V',
                'gewichten' => ['-48', '-52', '-57', '-63', '-70', '-78', '+78'],
            ],
            'heren' => [
                'label' => 'Heren',
                'max_leeftijd' => 99,
                'geslacht' => 'M',
                'gewichten' => ['-60', '-66', '-73', '-81', '-90', '-100', '+100'],
            ],
        ];
    }

    /**
     * JBN 2026 regels (officieel jan 2026)
     * U7/U9: dynamisch (zelf gewichtsklassen bepalen)
     * U11+: gescheiden M/V met vaste gewichtsklassen
     */
    public static function getJbn2026Gewichtsklassen(): array
    {
        return [
            // U7 en U9: dynamisch, gemengd
            'u7' => [
                'label' => 'U7',
                'max_leeftijd' => 6,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 3,
                'band_scheiding' => 'oranje',
                'gewichten' => [],
            ],
            'u9' => [
                'label' => 'U9',
                'max_leeftijd' => 8,
                'geslacht' => 'gemengd',
                'max_kg_verschil' => 3,
                'band_scheiding' => 'oranje',
                'gewichten' => [],
            ],
            // U11: gescheiden, vaste klassen
            'u11_d' => [
                'label' => 'U11 Meisjes',
                'max_leeftijd' => 10,
                'geslacht' => 'V',
                'gewichten' => ['-22', '-25', '-28', '-32', '-36', '-40', '-44', '+44'],
            ],
            'u11_h' => [
                'label' => 'U11 Jongens',
                'max_leeftijd' => 10,
                'geslacht' => 'M',
                'gewichten' => ['-21', '-24', '-27', '-30', '-34', '-38', '-42', '-50', '+50'],
            ],
            // U13: gescheiden, vaste klassen
            'u13_d' => [
                'label' => 'U13 Meisjes',
                'max_leeftijd' => 12,
                'geslacht' => 'V',
                'gewichten' => ['-25', '-28', '-32', '-36', '-40', '-44', '-48', '+48'],
            ],
            'u13_h' => [
                'label' => 'U13 Jongens',
                'max_leeftijd' => 12,
                'geslacht' => 'M',
                'gewichten' => ['-24', '-27', '-30', '-34', '-38', '-42', '-46', '-50', '+50'],
            ],
            // U15: gescheiden, vaste klassen
            'u15_d' => [
                'label' => 'U15 Meisjes',
                'max_leeftijd' => 14,
                'geslacht' => 'V',
                'gewichten' => ['-32', '-36', '-40', '-44', '-48', '-52', '-57', '-63', '+63'],
            ],
            'u15_h' => [
                'label' => 'U15 Jongens',
                'max_leeftijd' => 14,
                'geslacht' => 'M',
                'gewichten' => ['-34', '-38', '-42', '-46', '-50', '-55', '-60', '-66', '+66'],
            ],
            // U18: gescheiden, vaste klassen
            'u18_d' => [
                'label' => 'U18 Dames',
                'max_leeftijd' => 17,
                'geslacht' => 'V',
                'gewichten' => ['-40', '-44', '-48', '-52', '-57', '-63', '-70', '+70'],
            ],
            'u18_h' => [
                'label' => 'U18 Heren',
                'max_leeftijd' => 17,
                'geslacht' => 'M',
                'gewichten' => ['-42', '-46', '-50', '-55', '-60', '-66', '-73', '-81', '-90', '+90'],
            ],
            // U21: gescheiden, vaste klassen
            'u21_d' => [
                'label' => 'U21 Dames',
                'max_leeftijd' => 20,
                'geslacht' => 'V',
                'gewichten' => ['-48', '-52', '-57', '-63', '-70', '-78', '+78'],
            ],
            'u21_h' => [
                'label' => 'U21 Heren',
                'max_leeftijd' => 20,
                'geslacht' => 'M',
                'gewichten' => ['-50', '-55', '-60', '-66', '-73', '-81', '-90', '-100', '+100'],
            ],
            // Senioren: gescheiden, vaste klassen
            'sen_d' => [
                'label' => 'Senioren Dames',
                'max_leeftijd' => 99,
                'geslacht' => 'V',
                'gewichten' => ['-48', '-52', '-57', '-63', '-70', '-78', '+78'],
            ],
            'sen_h' => [
                'label' => 'Senioren Heren',
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

        // Filter metadata keys (start with _)
        $klassen = array_filter($klassen, function ($key) {
            return !str_starts_with($key, '_');
        }, ARRAY_FILTER_USE_KEY);

        // Sort by max_leeftijd (youngest first, seniors last)
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

    /*
    |--------------------------------------------------------------------------
    | Categorisatie Check Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Get judoka's die niet in een categorie passen.
     * Dit is een CONFIGURATIE probleem (geen categorie past).
     * Anders dan orphans (wel categorie, geen gewichtsmatch).
     *
     * BELANGRIJK: Leeftijdscategorieën zijn HARDE grenzen!
     * Een 8-jarige mag NOOIT doorvallen naar Heren alleen omdat band niet past.
     */
    public function getNietGecategoriseerdeJudokas(): \Illuminate\Database\Eloquent\Collection
    {
        $config = $this->getAlleGewichtsklassen();
        $toernooiJaar = $this->datum?->year ?? (int) date('Y');

        // Sorteer config op max_leeftijd (jong → oud)
        uasort($config, fn($a, $b) => ($a['max_leeftijd'] ?? 99) <=> ($b['max_leeftijd'] ?? 99));

        return $this->judokas()
            ->get()
            ->filter(function ($judoka) use ($config, $toernooiJaar) {
                $leeftijd = $toernooiJaar - $judoka->geboortejaar;
                $geslacht = strtoupper($judoka->geslacht ?? '');
                $band = $judoka->band ?? '';

                // Vind de eerste (laagste) max_leeftijd waar judoka in past
                $eersteMatchLeeftijd = null;
                foreach ($config as $cat) {
                    $maxLeeftijd = $cat['max_leeftijd'] ?? 99;
                    if ($leeftijd <= $maxLeeftijd) {
                        $eersteMatchLeeftijd = $maxLeeftijd;
                        break;
                    }
                }

                // Als geen leeftijdsmatch → niet gecategoriseerd
                if ($eersteMatchLeeftijd === null) {
                    return true;
                }

                // Check ALLEEN categorieën met deze max_leeftijd (niet doorvallen!)
                foreach ($config as $cat) {
                    $maxLeeftijd = $cat['max_leeftijd'] ?? 99;

                    // Skip categorieën met andere max_leeftijd
                    if ($maxLeeftijd !== $eersteMatchLeeftijd) continue;

                    $catGeslacht = strtoupper($cat['geslacht'] ?? 'gemengd');
                    if ($catGeslacht === 'MEISJES') $catGeslacht = 'V';
                    if ($catGeslacht === 'JONGENS') $catGeslacht = 'M';

                    // Check geslacht
                    if ($catGeslacht !== 'GEMENGD' && $catGeslacht !== $geslacht) continue;

                    // Check band filter
                    $bandFilter = $cat['band_filter'] ?? '';
                    if (!empty($bandFilter) && !BandHelper::pastInFilter($band, $bandFilter)) continue;

                    // Categorie gevonden - judoka is gecategoriseerd
                    return false;
                }

                // Geen categorie met juiste leeftijd past → NIET GECATEGORISEERD
                return true;
            });
    }

    /**
     * Tel aantal niet-gecategoriseerde judoka's (cached voor performance).
     */
    public function countNietGecategoriseerd(): int
    {
        return $this->getNietGecategoriseerdeJudokas()->count();
    }

    /**
     * Get sort value for a leeftijdsklasse label (youngest first).
     * Uses max_leeftijd from config, falls back to U-number parsing.
     */
    public function getLeeftijdsklasseSortValue(string $leeftijdsklasse): int
    {
        $config = $this->getAlleGewichtsklassen();

        // Find category by label in config
        foreach ($config as $cat) {
            $label = $cat['label'] ?? '';
            if ($label === $leeftijdsklasse) {
                return (int) ($cat['max_leeftijd'] ?? 99);
            }
        }

        // Fallback: parse U-number (U11 → 11)
        if (preg_match('/U(\d+)/', $leeftijdsklasse, $matches)) {
            return (int) $matches[1];
        }

        return 99;
    }

    /**
     * Bepaal leeftijdsklasse label op basis van toernooi config (NIET hardcoded enum).
     * Zoekt de eerste categorie waar judoka in past qua leeftijd, geslacht en band.
     *
     * BELANGRIJK: Een 6-jarige in U7 mag NOOIT doorvallen naar U11!
     * Check alleen categorieën met de eerste leeftijdsmatch.
     *
     * @return string|null Label van de categorie, of null als geen match
     */
    public function bepaalLeeftijdsklasse(int $leeftijd, string $geslacht, ?string $band = null): ?string
    {
        $config = $this->getAlleGewichtsklassen();
        if (empty($config)) {
            return null;
        }

        $geslacht = strtoupper($geslacht);

        // Config is al gesorteerd op max_leeftijd (jong → oud) door getAlleGewichtsklassen()

        // STAP 1: Vind de eerste (laagste) max_leeftijd waar judoka in past
        $eersteMatchLeeftijd = null;
        foreach ($config as $cat) {
            $maxLeeftijd = (int) ($cat['max_leeftijd'] ?? 99);
            if ($leeftijd <= $maxLeeftijd) {
                $eersteMatchLeeftijd = $maxLeeftijd;
                break;
            }
        }

        // Geen leeftijdsmatch → niet gecategoriseerd
        if ($eersteMatchLeeftijd === null) {
            return null;
        }

        // STAP 2: Check ALLEEN categorieën met deze max_leeftijd
        foreach ($config as $key => $cat) {
            $maxLeeftijd = (int) ($cat['max_leeftijd'] ?? 99);

            // Skip categorieën met andere max_leeftijd
            if ($maxLeeftijd !== $eersteMatchLeeftijd) {
                continue;
            }

            // Geslacht moet passen (gemengd past altijd)
            $catGeslacht = strtoupper($cat['geslacht'] ?? 'GEMENGD');
            if ($catGeslacht !== 'GEMENGD' && $catGeslacht !== $geslacht) {
                continue;
            }

            // Band filter moet passen (als ingesteld)
            $bandFilter = $cat['band_filter'] ?? '';
            if (!empty($bandFilter) && !empty($band) && !BandHelper::pastInFilter($band, $bandFilter)) {
                continue;
            }

            // Match gevonden
            return $cat['label'] ?? $key;
        }

        return null; // Geen categorie past binnen de leeftijdscategorie
    }

    /**
     * Bepaal gewichtsklasse op basis van gewicht en toernooi config.
     *
     * BELANGRIJK: Een 6-jarige in U7 mag NOOIT doorvallen naar U11!
     * Check alleen categorieën met de eerste leeftijdsmatch.
     *
     * @return string|null Gewichtsklasse (bijv. "-38" of "+73"), of null als geen match
     */
    public function bepaalGewichtsklasse(float $gewicht, int $leeftijd, string $geslacht, ?string $band = null): ?string
    {
        $config = $this->getAlleGewichtsklassen();
        if (empty($config)) {
            return null;
        }

        $geslacht = strtoupper($geslacht);
        $tolerantie = $this->gewicht_tolerantie ?? 0.5;

        // Config is al gesorteerd op max_leeftijd (jong → oud) door getAlleGewichtsklassen()

        // STAP 1: Vind de eerste (laagste) max_leeftijd waar judoka in past
        $eersteMatchLeeftijd = null;
        foreach ($config as $cat) {
            $maxLeeftijd = (int) ($cat['max_leeftijd'] ?? 99);
            if ($leeftijd <= $maxLeeftijd) {
                $eersteMatchLeeftijd = $maxLeeftijd;
                break;
            }
        }

        // Geen leeftijdsmatch → niet gecategoriseerd
        if ($eersteMatchLeeftijd === null) {
            return null;
        }

        // STAP 2: Check ALLEEN categorieën met deze max_leeftijd
        foreach ($config as $key => $cat) {
            $maxLeeftijd = (int) ($cat['max_leeftijd'] ?? 99);

            // Skip categorieën met andere max_leeftijd
            if ($maxLeeftijd !== $eersteMatchLeeftijd) {
                continue;
            }

            $catGeslacht = strtoupper($cat['geslacht'] ?? 'GEMENGD');
            if ($catGeslacht !== 'GEMENGD' && $catGeslacht !== $geslacht) {
                continue;
            }

            $bandFilter = $cat['band_filter'] ?? '';
            if (!empty($bandFilter) && !empty($band) && !BandHelper::pastInFilter($band, $bandFilter)) {
                continue;
            }

            // Categorie gevonden - bepaal gewichtsklasse
            $gewichten = $cat['gewichten'] ?? [];
            if (empty($gewichten)) {
                return null; // Dynamische categorie, geen vaste klassen
            }

            foreach ($gewichten as $klasse) {
                $klasseInt = (int) preg_replace('/[^0-9-]/', '', $klasse);
                if ($klasseInt > 0) {
                    // Plus categorie (laatste)
                    return "+{$klasseInt}";
                } else {
                    // Minus categorie
                    $limiet = abs($klasseInt);
                    if ($gewicht <= $limiet + $tolerantie) {
                        return "-{$limiet}";
                    }
                }
            }

            // Geen gewichtsklasse past, fallback naar plus
            $laatsteKlasse = end($gewichten);
            $laatsteInt = abs((int) preg_replace('/[^0-9]/', '', $laatsteKlasse));
            return "+{$laatsteInt}";
        }

        return null; // Geen categorie past binnen de leeftijdscategorie
    }
}
