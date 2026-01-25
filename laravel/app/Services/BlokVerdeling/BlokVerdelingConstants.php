<?php

namespace App\Services\BlokVerdeling;

/**
 * Constants for block distribution algorithm
 */
final class BlokVerdelingConstants
{
    // Time limits
    public const MAX_TIJD_SECONDEN = 3.0;
    public const MAX_POGINGEN = 50000;

    // Distribution limits
    public const MAX_AFWIJKING_PERCENTAGE = 25;
    public const MAX_VULGRAAD_FACTOR = 1.30; // 130% of target

    // Scoring weights
    public const AANSLUITING_ZELFDE_BLOK = 0;
    public const AANSLUITING_VOLGEND_BLOK = 10;
    public const AANSLUITING_VORIG_BLOK = 20;
    public const AANSLUITING_TWEE_BLOKKEN = 30;
    public const AANSLUITING_VERDER = 50;
    public const AANSLUITING_AFLOPEND_PENALTY = 200;

    // Variation parameters
    public const AANTAL_VARIANTEN = 5;
    public const AANTAL_AANSLUITING_STRATEGIEEN = 6;
    public const AANTAL_SORTEER_STRATEGIEEN = 10;
    public const AANTAL_SHUFFLE_OPTIES = 8;

    // Block adjacency options: same, +1, -1, +2
    public const AANSLUITING_OPTIES = [0, 1, -1, 2];
}
