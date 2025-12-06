<?php

namespace App\Enums;

enum AanwezigheidsStatus: string
{
    case ONBEKEND = 'onbekend';
    case AANWEZIG = 'aanwezig';
    case AFWEZIG = 'afwezig';
    case AFGEMELD = 'afgemeld';

    public function label(): string
    {
        return match($this) {
            self::ONBEKEND => 'Onbekend',
            self::AANWEZIG => 'Aanwezig',
            self::AFWEZIG => 'Afwezig',
            self::AFGEMELD => 'Afgemeld',
        };
    }

    public function kleur(): string
    {
        return match($this) {
            self::ONBEKEND => '#FFFFFF',
            self::AANWEZIG => '#D9EAD3',
            self::AFWEZIG => '#F4CCCC',
            self::AFGEMELD => '#FCE5CD',
        };
    }
}
