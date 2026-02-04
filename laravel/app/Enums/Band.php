<?php

namespace App\Enums;

enum Band: int
{
    case ZWART = 0;
    case BRUIN = 1;
    case BLAUW = 2;
    case GROEN = 3;
    case ORANJE = 4;
    case GEEL = 5;
    case WIT = 6;

    public function label(): string
    {
        return match($this) {
            self::ZWART => 'Zwart',
            self::BRUIN => 'Bruin',
            self::BLAUW => 'Blauw',
            self::GROEN => 'Groen',
            self::ORANJE => 'Oranje',
            self::GEEL => 'Geel',
            self::WIT => 'Wit',
        };
    }

    public function kleurCode(): string
    {
        return match($this) {
            self::ZWART => '#000000',
            self::BRUIN => '#8B4513',
            self::BLAUW => '#0000FF',
            self::GROEN => '#008000',
            self::ORANJE => '#FFA500',
            self::GEEL => '#FFFF00',
            self::WIT => '#FFFFFF',
        };
    }

    /**
     * Strip kyu notation from band string: "Geel (5e kyu)" → "Geel"
     */
    public static function stripKyu(string $band): string
    {
        // Remove everything from " (" onwards
        $pos = strpos($band, ' (');
        return $pos !== false ? substr($band, 0, $pos) : $band;
    }

    public static function fromString(string $band): ?self
    {
        $band = strtolower(trim($band));

        // Direct match
        $direct = match($band) {
            'zwart' => self::ZWART,
            'bruin' => self::BRUIN,
            'blauw' => self::BLAUW,
            'groen' => self::GROEN,
            'oranje' => self::ORANJE,
            'geel' => self::GEEL,
            'wit' => self::WIT,
            default => null,
        };

        if ($direct) return $direct;

        // Extract eerste woord: "groen (3e kyu)" → "groen"
        $eersteWoord = explode(' ', $band)[0];
        $fromFirstWord = match($eersteWoord) {
            'zwart' => self::ZWART,
            'bruin' => self::BRUIN,
            'blauw' => self::BLAUW,
            'groen' => self::GROEN,
            'oranje' => self::ORANJE,
            'geel' => self::GEEL,
            'wit' => self::WIT,
            default => null,
        };

        if ($fromFirstWord) return $fromFirstWord;

        // Zoek of string een kleur bevat
        foreach (['zwart', 'bruin', 'blauw', 'groen', 'oranje', 'geel', 'wit'] as $kleur) {
            if (str_contains($band, $kleur)) {
                return self::fromString($kleur);
            }
        }

        return null;
    }
}
