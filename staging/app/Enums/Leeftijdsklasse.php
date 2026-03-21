<?php

namespace App\Enums;

/**
 * @deprecated Since Jan 2026 - Use preset config for classification
 *
 * This enum contains hardcoded JBN2025 categories and weight classes.
 * New code should use toernooi->getAlleGewichtsklassen() from the active preset.
 *
 * Classification is now handled by:
 * - PouleIndelingService::classificeerJudoka() - determines category from config
 * - judokas.categorie_key - stores the config key
 * - judokas.sort_categorie - stores the sort order
 *
 * This enum is kept only for backwards compatibility during transition.
 */
enum Leeftijdsklasse: string
{
    case MINIS = 'minis';
    case A_PUPILLEN = 'a_pupillen';
    case B_PUPILLEN = 'b_pupillen';
    case DAMES_15 = 'dames_15';
    case HEREN_15 = 'heren_15';
    case DAMES_18 = 'dames_18';
    case HEREN_18 = 'heren_18';
    case DAMES = 'dames';
    case HEREN = 'heren';

    public function label(): string
    {
        return match($this) {
            self::MINIS => "Mini's",
            self::A_PUPILLEN => 'A-pupillen',
            self::B_PUPILLEN => 'B-pupillen',
            self::DAMES_15 => 'Dames -15',
            self::HEREN_15 => 'Heren -15',
            self::DAMES_18 => 'Dames -18',
            self::HEREN_18 => 'Heren -18',
            self::DAMES => 'Dames',
            self::HEREN => 'Heren',
        };
    }

    public function code(): string
    {
        return match($this) {
            self::MINIS => '08',
            self::A_PUPILLEN => '10',
            self::B_PUPILLEN => '12',
            self::DAMES_15 => '15',
            self::HEREN_15 => '15',
            self::DAMES_18 => '18',
            self::HEREN_18 => '18',
            self::DAMES => '21',
            self::HEREN => '21',
        };
    }

    public function maxLeeftijd(): int
    {
        return match($this) {
            self::MINIS => 8,
            self::A_PUPILLEN => 10,
            self::B_PUPILLEN => 12,
            self::DAMES_15, self::HEREN_15 => 15,
            self::DAMES_18, self::HEREN_18 => 18,
            self::DAMES, self::HEREN => 99,
        };
    }

    /**
     * Returns the config key used in gewichtsklassen array
     */
    public function configKey(): string
    {
        return match($this) {
            self::MINIS => 'minis',
            self::A_PUPILLEN => 'a_pupillen',
            self::B_PUPILLEN => 'b_pupillen',
            self::DAMES_15 => 'dames_15',
            self::HEREN_15 => 'heren_15',
            self::DAMES_18 => 'dames_18',
            self::HEREN_18 => 'heren_18',
            self::DAMES => 'dames',
            self::HEREN => 'heren',
        };
    }

    /**
     * Returns the default weight classes for this age category
     * Negative values = maximum weight, Positive values = minimum weight (plus category)
     */
    public function gewichtsklassen(): array
    {
        return match($this) {
            self::MINIS => [-20, -23, -26, -29, 29],
            self::A_PUPILLEN => [-24, -27, -30, -34, -38, 38],
            self::B_PUPILLEN => [-27, -30, -34, -38, -42, -46, -50, 50],
            self::DAMES_15 => [-36, -40, -44, -48, -52, -57, -63, 63],
            self::HEREN_15 => [-34, -38, -42, -46, -50, -55, -60, -66, 66],
            self::DAMES_18 => [-40, -44, -48, -52, -57, -63, -70, 70],
            self::HEREN_18 => [-46, -50, -55, -60, -66, -73, -81, -90, 90],
            self::DAMES => [-48, -52, -57, -63, -70, -78, 78],
            self::HEREN => [-60, -66, -73, -81, -90, -100, 100],
        };
    }

    public static function fromLeeftijdEnGeslacht(int $leeftijd, string $geslacht): self
    {
        $isVrouw = strtoupper($geslacht) === 'V';

        if ($leeftijd < 8) return self::MINIS;
        if ($leeftijd < 10) return self::A_PUPILLEN;
        if ($leeftijd < 12) return self::B_PUPILLEN;
        if ($leeftijd < 15) return $isVrouw ? self::DAMES_15 : self::HEREN_15;
        if ($leeftijd < 18) return $isVrouw ? self::DAMES_18 : self::HEREN_18;
        return $isVrouw ? self::DAMES : self::HEREN;
    }
}
