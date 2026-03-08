<?php

namespace App\Services;

use App\Models\Club;
use App\Models\Judoka;
use App\Models\Toernooi;
use App\Models\Organisator;

class FreemiumService
{
    // Free tier limits
    public const FREE_MAX_JUDOKAS = 50;
    public const FREE_MAX_CLUBS = 2;
    public const FREE_MAX_PRESETS = 1;
    public const FREE_MAX_SCHEMAS = 2;
    public const DEMO_JUDOKAS_COUNT = 30;

    // Pricing tiers
    public const STAFFELS = [
        '51-100' => ['min' => 51, 'max' => 100, 'prijs' => 20],
        '101-150' => ['min' => 101, 'max' => 150, 'prijs' => 30],
        '151-200' => ['min' => 151, 'max' => 200, 'prijs' => 40],
        '201-250' => ['min' => 201, 'max' => 250, 'prijs' => 50],
        '251-300' => ['min' => 251, 'max' => 300, 'prijs' => 60],
        '301-350' => ['min' => 301, 'max' => 350, 'prijs' => 70],
        '351-400' => ['min' => 351, 'max' => 400, 'prijs' => 80],
        '401-500' => ['min' => 401, 'max' => 500, 'prijs' => 100],
    ];

    /**
     * Check if a toernooi is on the free tier
     */
    public function isFreeTier(Toernooi $toernooi): bool
    {
        return $toernooi->plan_type === 'free';
    }

    /**
     * Check if a toernooi is on a paid tier
     */
    public function isPaidTier(Toernooi $toernooi): bool
    {
        return $toernooi->plan_type === 'paid';
    }

    /**
     * Check if a toernooi is on the wimpel subscription
     */
    public function isWimpelAbo(Toernooi $toernooi): bool
    {
        return $toernooi->plan_type === 'wimpel_abo';
    }

    /**
     * Get the effective max judokas for a toernooi
     */
    public function getEffectiveMaxJudokas(Toernooi $toernooi): int
    {
        if ($this->isWimpelAbo($toernooi)) {
            return PHP_INT_MAX;
        }
        if ($this->isPaidTier($toernooi)) {
            return $toernooi->paid_max_judokas ?? self::FREE_MAX_JUDOKAS;
        }
        return self::FREE_MAX_JUDOKAS;
    }

    /**
     * Check if more judokas can be added to a toernooi
     */
    public function canAddMoreJudokas(Toernooi $toernooi, int $toevoegen = 1): bool
    {
        $huidige = $toernooi->judokas()->count();
        $max = $this->getEffectiveMaxJudokas($toernooi);
        return ($huidige + $toevoegen) <= $max;
    }

    /**
     * Get the number of judokas that can still be added
     */
    public function getRemainingJudokaSlots(Toernooi $toernooi): int
    {
        $huidige = $toernooi->judokas()->count();
        $max = $this->getEffectiveMaxJudokas($toernooi);
        return max(0, $max - $huidige);
    }

    /**
     * Check if more clubs can be added for an organisator (free tier only)
     */
    public function canAddMoreClubs(Organisator $organisator): bool
    {
        // For now, we don't enforce club limits per organisator
        // This could be implemented if needed
        return true;
    }

    /**
     * Check if more presets can be added for an organisator
     */
    public function canAddMorePresets(Organisator $organisator): bool
    {
        $huidige = $organisator->gewichtsklassenPresets()->count();
        return $huidige < self::FREE_MAX_PRESETS;
    }

    /**
     * Get the number of presets an organisator can have
     */
    public function getMaxPresets(Organisator $organisator): int
    {
        // Could be extended to check for paid organisator subscription
        return self::FREE_MAX_PRESETS;
    }

    /**
     * Check if print functionality is available
     */
    public function canUsePrint(Toernooi $toernooi): bool
    {
        return $this->isPaidTier($toernooi) || $this->isWimpelAbo($toernooi);
    }

    /**
     * Get available upgrade options for a toernooi
     * Bij re-upgrade: prijs = verschil met al betaalde staffel
     */
    public function getUpgradeOptions(Toernooi $toernooi): array
    {
        $huidigeJudokas = $toernooi->judokas()->count();
        $alBetaald = $this->getAlBetaaldePrijs($toernooi);

        return collect(self::STAFFELS)
            ->filter(fn($staffel) => $staffel['max'] > $huidigeJudokas)
            ->map(fn($staffel, $key) => [
                'tier' => $key,
                'min' => $staffel['min'],
                'max' => $staffel['max'],
                'prijs' => max(0, $staffel['prijs'] - $alBetaald),
                'volle_prijs' => $staffel['prijs'],
                'label' => "Tot {$staffel['max']} judoka's",
            ])
            ->values()
            ->all();
    }

    /**
     * Get the price already paid for this toernooi (from successful payments)
     */
    public function getAlBetaaldePrijs(Toernooi $toernooi): float
    {
        if (!$this->isPaidTier($toernooi) || !$toernooi->paid_tier) {
            return 0;
        }

        return self::STAFFELS[$toernooi->paid_tier]['prijs'] ?? 0;
    }

    /**
     * Get price for a specific tier
     */
    public function getStaffelPrijs(string $tier): ?float
    {
        return self::STAFFELS[$tier]['prijs'] ?? null;
    }

    /**
     * Get tier info by tier key
     */
    public function getTierInfo(string $tier): ?array
    {
        return self::STAFFELS[$tier] ?? null;
    }

    /**
     * Check if toernooi needs upgrade based on current judoka count
     */
    public function needsUpgrade(Toernooi $toernooi): bool
    {
        if ($this->isPaidTier($toernooi) || $this->isWimpelAbo($toernooi)) {
            return false;
        }

        $huidige = $toernooi->judokas()->count();
        return $huidige >= self::FREE_MAX_JUDOKAS;
    }

    /**
     * Seed demo judokas for a free tier toernooi.
     * Creates a demo club and 30 judokas, ages 6-12, weights 30-45kg.
     */
    public function seedDemoJudokas(Toernooi $toernooi): int
    {
        if (!$this->isFreeTier($toernooi)) {
            return 0;
        }

        // Don't seed if demo judokas already exist
        if ($toernooi->judokas()->where('is_demo', true)->exists()) {
            return 0;
        }

        // Create demo club for this organisator
        $organisatorId = $toernooi->organisator_id;
        $demoClub = Club::firstOrCreate(
            ['organisator_id' => $organisatorId, 'naam' => 'Demo Judoschool'],
            ['plaats' => 'Amsterdam', 'afkorting' => 'DEMO']
        );

        // Link club to toernooi
        $demoClub->toernooien()->syncWithoutDetaching([$toernooi->id]);
        $clubId = $demoClub->id;

        $voornamenJongens = [
            'Takeshi', 'Yuki', 'Haruto', 'Kenji', 'Riku', 'Sota', 'Hayato', 'Ren',
            'Daan', 'Liam', 'Noah', 'Sem', 'Finn', 'Lucas', 'Jesse', 'Milan',
        ];
        $voornamenMeisjes = [
            'Sakura', 'Yui', 'Hana', 'Aoi', 'Mei', 'Rin', 'Mio', 'Saki',
            'Emma', 'Tessa', 'Sophie', 'Julia', 'Sara', 'Noor', 'Lotte', 'Eva',
        ];
        $achternamen = [
            'Tanaka', 'Yamamoto', 'Suzuki', 'Watanabe', 'Sato', 'Takahashi',
            'Nakamura', 'Kobayashi', 'Kato', 'Yoshida', 'Yamada', 'Sasaki',
            'Matsumoto', 'Inoue', 'Kimura', 'Shimizu', 'Hayashi', 'Saito',
            'De Vries', 'Jansen', 'De Jong', 'Van den Berg', 'Bakker', 'Visser',
            'Smit', 'Meijer', 'Mulder', 'Bos',
        ];

        $banden = ['wit', 'geel', 'oranje'];
        $judokas = [];
        $now = now();

        for ($i = 0; $i < self::DEMO_JUDOKAS_COUNT; $i++) {
            $geslacht = $i % 2 === 0 ? 'M' : 'V';
            $leeftijd = rand(6, 12);
            $geboortejaar = (int) date('Y') - $leeftijd;

            // Weight 30-45kg with realistic spread based on age
            $baseWeight = 28 + ($leeftijd - 6) * 1.5;
            $gewicht = round($baseWeight + (rand(0, 60) / 10), 1);
            $gewicht = max(30.0, min(45.0, $gewicht));

            if ($geslacht === 'M') {
                $voornaam = $voornamenJongens[array_rand($voornamenJongens)];
            } else {
                $voornaam = $voornamenMeisjes[array_rand($voornamenMeisjes)];
            }
            $achternaam = $achternamen[array_rand($achternamen)];

            // Band passend bij leeftijd
            $bandIndex = min(count($banden) - 1, intdiv($leeftijd - 6, 2));
            $band = $banden[rand(0, $bandIndex)];

            $judokas[] = [
                'toernooi_id' => $toernooi->id,
                'club_id' => $clubId,
                'naam' => $achternaam . ', ' . $voornaam,
                'voornaam' => $voornaam,
                'achternaam' => $achternaam,
                'geboortejaar' => $geboortejaar,
                'geslacht' => $geslacht,
                'band' => $band,
                'gewicht' => $gewicht,
                'aanwezigheid' => 'onbekend',
                'is_onvolledig' => false,
                'is_demo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        Judoka::insert($judokas);

        return self::DEMO_JUDOKAS_COUNT;
    }

    /**
     * Remove all demo judokas from a toernooi.
     * Called during upgrade to paid plan.
     */
    public function removeDemoJudokas(Toernooi $toernooi): int
    {
        return $toernooi->judokas()->where('is_demo', true)->delete();
    }

    /**
     * Get the freemium status summary for a toernooi
     */
    public function getStatus(Toernooi $toernooi): array
    {
        $isFreeTier = $this->isFreeTier($toernooi);
        $isWimpelAbo = $this->isWimpelAbo($toernooi);
        $huidigeJudokas = $toernooi->judokas()->count();
        $maxJudokas = $this->getEffectiveMaxJudokas($toernooi);

        return [
            'plan_type' => $toernooi->plan_type,
            'is_free_tier' => $isFreeTier,
            'is_paid_tier' => $this->isPaidTier($toernooi),
            'is_wimpel_abo' => $isWimpelAbo,
            'current_judokas' => $huidigeJudokas,
            'max_judokas' => $maxJudokas,
            'remaining_slots' => $this->getRemainingJudokaSlots($toernooi),
            'can_add_judokas' => $this->canAddMoreJudokas($toernooi),
            'can_use_print' => $this->canUsePrint($toernooi),
            'needs_upgrade' => $this->needsUpgrade($toernooi),
            'paid_tier' => $toernooi->paid_tier,
            'paid_at' => $toernooi->paid_at,
        ];
    }
}
