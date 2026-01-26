<?php

namespace App\Services;

use App\Models\Toernooi;
use App\Models\Organisator;

class FreemiumService
{
    // Free tier limits
    public const FREE_MAX_JUDOKAS = 50;
    public const FREE_MAX_CLUBS = 2;
    public const FREE_MAX_PRESETS = 1;
    public const FREE_MAX_SCHEMAS = 2;

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
     * Get the effective max judokas for a toernooi
     */
    public function getEffectiveMaxJudokas(Toernooi $toernooi): int
    {
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
        return $this->isPaidTier($toernooi);
    }

    /**
     * Get available upgrade options for a toernooi
     */
    public function getUpgradeOptions(Toernooi $toernooi): array
    {
        $huidigeJudokas = $toernooi->judokas()->count();

        return collect(self::STAFFELS)
            ->filter(fn($staffel) => $staffel['max'] > $huidigeJudokas)
            ->map(fn($staffel, $key) => [
                'tier' => $key,
                'min' => $staffel['min'],
                'max' => $staffel['max'],
                'prijs' => $staffel['prijs'],
                'label' => "{$staffel['min']}-{$staffel['max']} judoka's",
            ])
            ->values()
            ->all();
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
        if ($this->isPaidTier($toernooi)) {
            return false;
        }

        $huidige = $toernooi->judokas()->count();
        return $huidige >= self::FREE_MAX_JUDOKAS;
    }

    /**
     * Get the freemium status summary for a toernooi
     */
    public function getStatus(Toernooi $toernooi): array
    {
        $isFreeTier = $this->isFreeTier($toernooi);
        $huidigeJudokas = $toernooi->judokas()->count();
        $maxJudokas = $this->getEffectiveMaxJudokas($toernooi);

        return [
            'plan_type' => $toernooi->plan_type,
            'is_free_tier' => $isFreeTier,
            'is_paid_tier' => !$isFreeTier,
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
