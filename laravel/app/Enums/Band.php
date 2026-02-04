<?php

namespace App\Enums;

/**
 * Judo band kleuren enum - ENIGE BRON VAN WAARHEID voor band volgorde
 *
 * VOLGORDE (beginner → expert):
 *   wit → geel → oranje → groen → blauw → bruin → zwart
 *    0      1       2        3        4        5       6    (niveau)
 *    6      5       4        3        2        1       0    (enum value)
 *
 * OPSLAG: alleen lowercase kleur naam (wit, geel, oranje, groen, blauw, bruin, zwart)
 * WEERGAVE: alleen kleur naam met hoofdletter (Wit, Geel, etc.) - NOOIT kyu nummers
 *
 * SORTERING:
 *   - niveau()     → beginner eerst (wit=0, zwart=6) - voor UI lijsten
 *   - sortNiveau() → beginner eerst, 1-indexed (wit=1, zwart=7) - voor database sort_band
 *   - value        → expert eerst (zwart=0, wit=6) - voor filtering
 */
enum Band: int
{
    // Enum values: expert→beginner (zwart=0, wit=6)
    // Dit is handig voor filters: "tm_groen" betekent value >= GROEN->value
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
     * Gebruik voor UI waar beginners eerst komen
     */
    public function niveau(): int
    {
        return 6 - $this->value;
    }

    /**
     * Sort niveau 1-indexed voor database (1=wit, 7=zwart)
     * Gebruik voor sort_band kolom in database
     */
    public function sortNiveau(): int
    {
        return $this->niveau() + 1;
    }

    /**
     * Get sort niveau van string (1=wit, 7=zwart)
     * Gebruik voor sortering waar beginners eerst komen
     */
    public static function getSortNiveau(?string $band): int
    {
        if (empty($band)) {
            return 7; // Unknown = treat as beginner (hoogste nummer)
        }
        $enum = self::fromString($band);
        return $enum ? $enum->sortNiveau() : 7;
    }

    /**
     * Check of band past in filter
     * Filters: "tm_groen" (beginners t/m groen), "vanaf_blauw" (gevorderden vanaf blauw)
     *
     * @param string|null $band - band kleur string
     * @param string|null $filter - "tm_kleur" of "vanaf_kleur"
     */
    public static function pastInFilter(?string $band, ?string $filter): bool
    {
        if (empty($filter) || empty($band)) {
            return true;
        }

        $bandEnum = self::fromString($band);
        if (!$bandEnum) {
            return true; // Unknown band = allow
        }

        if (str_starts_with($filter, 'tm_')) {
            // "tm_groen" = beginners t/m groen (wit, geel, oranje, groen)
            // Band moet enum value >= filter value (hoger value = lager niveau)
            $filterBand = str_replace('tm_', '', $filter);
            $filterEnum = self::fromString($filterBand);
            return $filterEnum && $bandEnum->value >= $filterEnum->value;
        }

        if (str_starts_with($filter, 'vanaf_')) {
            // "vanaf_blauw" = gevorderden vanaf blauw (blauw, bruin, zwart)
            // Band moet enum value <= filter value (lager value = hoger niveau)
            $filterBand = str_replace('vanaf_', '', $filter);
            $filterEnum = self::fromString($filterBand);
            return $filterEnum && $bandEnum->value <= $filterEnum->value;
        }

        return true;
    }
}
