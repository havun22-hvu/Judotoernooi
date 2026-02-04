<?php

namespace App\Enums;

/**
 * Judo band kleuren enum
 *
 * VOLGORDE (beginner → expert):
 *   wit → geel → oranje → groen → blauw → bruin → zwart
 *
 * OPSLAG: alleen lowercase kleur naam (wit, geel, oranje, groen, blauw, bruin, zwart)
 * WEERGAVE: alleen kleur naam met hoofdletter (Wit, Geel, etc.) - NOOIT kyu nummers
 *
 * De int value is voor sortering (0=zwart/hoogste, 6=wit/laagste)
 * Voor sortering beginner→expert: gebruik niveau() methode
 */
enum Band: int
{
    // Values: 0=hoogste (zwart), 6=laagste (wit) - voor sortering expert→beginner
    case ZWART = 0;
    case BRUIN = 1;
    case BLAUW = 2;
    case GROEN = 3;
    case ORANJE = 4;
    case GEEL = 5;
    case WIT = 6;

    /**
     * Display label (alleen kleur, NOOIT kyu)
     */
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
     * Get kleur naam van band value (nummer of string)
     * Gebruik dit ALTIJD in views voor weergave
     *
     * @param mixed $band - kan zijn: int (0-6), string ("wit"), string ("Geel (5e kyu)")
     * @return string - kleurnaam met hoofdletter (Wit, Geel, etc.) of lege string
     */
    public static function toKleur(mixed $band): string
    {
        if ($band === null || $band === '') {
            return '';
        }

        // Nummer: direct via enum
        if (is_numeric($band)) {
            return self::tryFrom((int)$band)?->label() ?? '';
        }

        // String: probeer als enum, anders strip kyu
        $enumVal = self::fromString((string)$band);
        if ($enumVal) {
            return $enumVal->label();
        }

        // Fallback: strip alles na " (" en capitalize
        $pos = strpos($band, ' (');
        $kleur = $pos !== false ? substr($band, 0, $pos) : $band;
        return ucfirst(strtolower(trim($kleur)));
    }

    /**
     * @deprecated Gebruik toKleur() in plaats hiervan
     */
    public static function stripKyu(string $band): string
    {
        return self::toKleur($band);
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

    /**
     * Niveau voor sortering beginner→expert (0=wit, 6=zwart)
     * Inverse van enum value
     */
    public function niveau(): int
    {
        return 6 - $this->value;
    }
}
