<?php

namespace App\Models;

use App\Http\Controllers\RoleToegang;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Toernooi extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Toernooi $toernooi) {
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
        });
    }

    protected $table = 'toernooien';

    protected $fillable = [
        'naam',
        'organisatie',
        'datum',
        'inschrijving_deadline',
        'max_judokas',
        'locatie',
        'aantal_matten',
        'aantal_blokken',
        'poule_grootte_voorkeur',
        'clubspreiding',
        'dubbel_bij_2_judokas',
        'dubbel_bij_3_judokas',
        'dubbel_bij_4_judokas',
        'wedstrijd_systeem',
        'kruisfinales_aantal',
        'gewicht_tolerantie',
        'weging_verplicht',
        'max_wegingen',
        'is_actief',
        'poules_gegenereerd_op',
        'blokken_verdeeld_op',
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
        'wedstrijd_systeem' => 'array',
        'poules_gegenereerd_op' => 'datetime',
        'blokken_verdeeld_op' => 'datetime',
        'gewicht_tolerantie' => 'decimal:1',
        'gewichtsklassen' => 'array',
        'poule_grootte_voorkeur' => 'array',
        'mat_voorkeuren' => 'array',
        'verdeling_prioriteiten' => 'array',
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
     */
    public static function getJbn2025Gewichtsklassen(): array
    {
        return [
            'minis_m' => [
                'label' => "Mini's (jongens)",
                'max_leeftijd' => 7,
                'geslacht' => 'M',
                'gewichten' => ['-18', '-21', '-24', '-27', '-30', '-34', '-38', '+38'],
            ],
            'minis_v' => [
                'label' => "Mini's (meisjes)",
                'max_leeftijd' => 7,
                'geslacht' => 'V',
                'gewichten' => ['-18', '-20', '-22', '-25', '-28', '-32', '-36', '+36'],
            ],
            'pupillen_a_m' => [
                'label' => 'Pupillen A (jongens)',
                'max_leeftijd' => 9,
                'geslacht' => 'M',
                'gewichten' => ['-21', '-24', '-27', '-30', '-34', '-38', '-42', '-46', '-50', '+50'],
            ],
            'pupillen_a_v' => [
                'label' => 'Pupillen A (meisjes)',
                'max_leeftijd' => 9,
                'geslacht' => 'V',
                'gewichten' => ['-20', '-22', '-25', '-28', '-32', '-36', '-40', '-44', '-48', '+48'],
            ],
            'pupillen_b_m' => [
                'label' => 'Pupillen B (jongens)',
                'max_leeftijd' => 11,
                'geslacht' => 'M',
                'gewichten' => ['-24', '-27', '-30', '-34', '-38', '-42', '-46', '-50', '-55', '+55'],
            ],
            'pupillen_b_v' => [
                'label' => 'Pupillen B (meisjes)',
                'max_leeftijd' => 11,
                'geslacht' => 'V',
                'gewichten' => ['-22', '-25', '-28', '-32', '-36', '-40', '-44', '-48', '-52', '+52'],
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
     * JBN 2025 gemengd (tot 12 jaar)
     */
    public static function getJbn2025GewichtsklassenGemengd(): array
    {
        return [
            'minis' => [
                'label' => "Mini's",
                'max_leeftijd' => 7,
                'geslacht' => null,
                'gewichten' => ['-18', '-21', '-24', '-27', '-30', '-34', '-38', '+38'],
            ],
            'pupillen_a' => [
                'label' => 'Pupillen A',
                'max_leeftijd' => 9,
                'geslacht' => null,
                'gewichten' => ['-21', '-24', '-27', '-30', '-34', '-38', '-42', '-46', '-50', '+50'],
            ],
            'pupillen_b' => [
                'label' => 'Pupillen B',
                'max_leeftijd' => 11,
                'geslacht' => null,
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
     */
    public static function getJbn2026Gewichtsklassen(): array
    {
        return [
            'minis_m' => [
                'label' => "Mini's (jongens)",
                'max_leeftijd' => 6,
                'geslacht' => 'M',
                'gewichten' => ['-18', '-21', '-24', '-27', '-30', '-34', '+34'],
            ],
            'minis_v' => [
                'label' => "Mini's (meisjes)",
                'max_leeftijd' => 6,
                'geslacht' => 'V',
                'gewichten' => ['-18', '-20', '-22', '-25', '-28', '-32', '+32'],
            ],
            'pupillen_a_m' => [
                'label' => 'Pupillen A (jongens)',
                'max_leeftijd' => 8,
                'geslacht' => 'M',
                'gewichten' => ['-21', '-24', '-27', '-30', '-34', '-38', '-42', '+42'],
            ],
            'pupillen_a_v' => [
                'label' => 'Pupillen A (meisjes)',
                'max_leeftijd' => 8,
                'geslacht' => 'V',
                'gewichten' => ['-20', '-22', '-25', '-28', '-32', '-36', '-40', '+40'],
            ],
            'pupillen_b_m' => [
                'label' => 'Pupillen B (jongens)',
                'max_leeftijd' => 10,
                'geslacht' => 'M',
                'gewichten' => ['-24', '-27', '-30', '-34', '-38', '-42', '-46', '-50', '+50'],
            ],
            'pupillen_b_v' => [
                'label' => 'Pupillen B (meisjes)',
                'max_leeftijd' => 10,
                'geslacht' => 'V',
                'gewichten' => ['-22', '-25', '-28', '-32', '-36', '-40', '-44', '-48', '+48'],
            ],
            'pupillen_c_m' => [
                'label' => 'Pupillen C (jongens)',
                'max_leeftijd' => 12,
                'geslacht' => 'M',
                'gewichten' => ['-27', '-30', '-34', '-38', '-42', '-46', '-50', '-55', '+55'],
            ],
            'pupillen_c_v' => [
                'label' => 'Pupillen C (meisjes)',
                'max_leeftijd' => 12,
                'geslacht' => 'V',
                'gewichten' => ['-25', '-28', '-32', '-36', '-40', '-44', '-48', '-52', '+52'],
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
     * JBN 2026 gemengd (tot 13 jaar)
     */
    public static function getJbn2026GewichtsklassenGemengd(): array
    {
        return [
            'minis' => [
                'label' => "Mini's",
                'max_leeftijd' => 6,
                'geslacht' => null,
                'gewichten' => ['-18', '-21', '-24', '-27', '-30', '-34', '+34'],
            ],
            'pupillen_a' => [
                'label' => 'Pupillen A',
                'max_leeftijd' => 8,
                'geslacht' => null,
                'gewichten' => ['-21', '-24', '-27', '-30', '-34', '-38', '-42', '+42'],
            ],
            'pupillen_b' => [
                'label' => 'Pupillen B',
                'max_leeftijd' => 10,
                'geslacht' => null,
                'gewichten' => ['-24', '-27', '-30', '-34', '-38', '-42', '-46', '-50', '+50'],
            ],
            'pupillen_c' => [
                'label' => 'Pupillen C',
                'max_leeftijd' => 12,
                'geslacht' => null,
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
        return $this->gewichtsklassen ?? self::getStandaardGewichtsklassen();
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
            default => null,
        };

        return $code ? route('rol.toegang', $code) : null;
    }

    public function regenerateRoleCode(string $rol): ?string
    {
        $veld = "code_{$rol}";
        if (!in_array($veld, ['code_hoofdjury', 'code_weging', 'code_mat', 'code_spreker'])) {
            return null;
        }

        $this->$veld = RoleToegang::generateCode();
        $this->save();

        return $this->$veld;
    }
}
