<?php

namespace App\Helpers;

use App\Enums\Band;

/**
 * @deprecated Gebruik App\Enums\Band direct
 *
 * Deze helper bestaat alleen voor backwards compatibility.
 * Alle logica staat nu in de Band enum.
 */
class BandHelper
{
    /**
     * @deprecated Gebruik Band::fromString($band)?->value ?? 6
     */
    public static function getNiveau(string $band): int
    {
        $enum = Band::fromString($band);
        return $enum ? $enum->value : 6;
    }

    /**
     * @deprecated Gebruik Band::pastInFilter($band, $filter)
     */
    public static function pastInFilter(?string $band, ?string $filter): bool
    {
        return Band::pastInFilter($band, $filter);
    }

    /**
     * @deprecated Gebruik Band::getSortNiveau($band)
     */
    public static function getSortNiveau(string $band): int
    {
        return Band::getSortNiveau($band);
    }
}
