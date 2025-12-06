<?php

namespace App\Enums;

enum Geslacht: string
{
    case MAN = 'M';
    case VROUW = 'V';

    public function label(): string
    {
        return match($this) {
            self::MAN => 'Man',
            self::VROUW => 'Vrouw',
        };
    }

    public static function fromString(string $value): ?self
    {
        $value = strtoupper(trim($value));

        return match($value) {
            'M', 'MAN', 'JONGEN', 'HEREN', 'HEER' => self::MAN,
            'V', 'VROUW', 'MEISJE', 'DAMES', 'DAME' => self::VROUW,
            default => null,
        };
    }
}
