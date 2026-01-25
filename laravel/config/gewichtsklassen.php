<?php

/**
 * JBN Gewichtsklassen configuratie
 *
 * JBN 2025: Mini's (-8), A-pupillen (-10), B-pupillen (-12), -15, -18, -21, Senioren
 * JBN 2026: U7/U9 dynamisch, U11+ gescheiden M/V met vaste klassen
 */

return [
    /*
    |--------------------------------------------------------------------------
    | JBN 2025 Gewichtsklassen
    |--------------------------------------------------------------------------
    |
    | Gemengd t/m B-pupillen, gescheiden vanaf -15 (Heren/Dames)
    | VASTE gewichtsklassen
    |
    */
    '2025' => [
        'minis' => [
            'label' => "Mini's",
            'max_leeftijd' => 7,
            'geslacht' => 'gemengd',
            'gewichten' => ['-18', '-21', '-24', '-27', '-30', '-34', '-38', '+38'],
        ],
        'a_pupillen' => [
            'label' => 'A-pupillen',
            'max_leeftijd' => 9,
            'geslacht' => 'gemengd',
            'gewichten' => ['-21', '-24', '-27', '-30', '-34', '-38', '-42', '-46', '-50', '+50'],
        ],
        'b_pupillen' => [
            'label' => 'B-pupillen',
            'max_leeftijd' => 11,
            'geslacht' => 'gemengd',
            'gewichten' => ['-24', '-27', '-30', '-34', '-38', '-42', '-46', '-50', '-55', '+55'],
        ],
        'dames_15' => [
            'label' => 'Dames -15',
            'max_leeftijd' => 14,
            'geslacht' => 'V',
            'gewichten' => ['-32', '-36', '-40', '-44', '-48', '-52', '-57', '-63', '+63'],
        ],
        'heren_15' => [
            'label' => 'Heren -15',
            'max_leeftijd' => 14,
            'geslacht' => 'M',
            'gewichten' => ['-34', '-38', '-42', '-46', '-50', '-55', '-60', '-66', '+66'],
        ],
        'dames_18' => [
            'label' => 'Dames -18',
            'max_leeftijd' => 17,
            'geslacht' => 'V',
            'gewichten' => ['-40', '-44', '-48', '-52', '-57', '-63', '-70', '+70'],
        ],
        'heren_18' => [
            'label' => 'Heren -18',
            'max_leeftijd' => 17,
            'geslacht' => 'M',
            'gewichten' => ['-46', '-50', '-55', '-60', '-66', '-73', '-81', '-90', '+90'],
        ],
        'dames_21' => [
            'label' => 'Dames -21',
            'max_leeftijd' => 20,
            'geslacht' => 'V',
            'gewichten' => ['-48', '-52', '-57', '-63', '-70', '-78', '+78'],
        ],
        'heren_21' => [
            'label' => 'Heren -21',
            'max_leeftijd' => 20,
            'geslacht' => 'M',
            'gewichten' => ['-60', '-66', '-73', '-81', '-90', '-100', '+100'],
        ],
        'dames' => [
            'label' => 'Dames',
            'max_leeftijd' => 99,
            'geslacht' => 'V',
            'gewichten' => ['-48', '-52', '-57', '-63', '-70', '-78', '+78'],
        ],
        'heren' => [
            'label' => 'Heren',
            'max_leeftijd' => 99,
            'geslacht' => 'M',
            'gewichten' => ['-60', '-66', '-73', '-81', '-90', '-100', '+100'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | JBN 2026 Gewichtsklassen (officieel jan 2026)
    |--------------------------------------------------------------------------
    |
    | U7/U9: dynamisch (zelf gewichtsklassen bepalen)
    | U11+: gescheiden M/V met vaste gewichtsklassen
    |
    */
    '2026' => [
        // U7 en U9: dynamisch, gemengd
        'u7' => [
            'label' => 'U7',
            'max_leeftijd' => 6,
            'geslacht' => 'gemengd',
            'max_kg_verschil' => 3,
            'band_scheiding' => 'oranje',
            'gewichten' => [],
        ],
        'u9' => [
            'label' => 'U9',
            'max_leeftijd' => 8,
            'geslacht' => 'gemengd',
            'max_kg_verschil' => 3,
            'band_scheiding' => 'oranje',
            'gewichten' => [],
        ],
        // U11: gescheiden, vaste klassen
        'u11_d' => [
            'label' => 'U11 Meisjes',
            'max_leeftijd' => 10,
            'geslacht' => 'V',
            'gewichten' => ['-22', '-25', '-28', '-32', '-36', '-40', '-44', '+44'],
        ],
        'u11_h' => [
            'label' => 'U11 Jongens',
            'max_leeftijd' => 10,
            'geslacht' => 'M',
            'gewichten' => ['-21', '-24', '-27', '-30', '-34', '-38', '-42', '-50', '+50'],
        ],
        // U13: gescheiden, vaste klassen
        'u13_d' => [
            'label' => 'U13 Meisjes',
            'max_leeftijd' => 12,
            'geslacht' => 'V',
            'gewichten' => ['-25', '-28', '-32', '-36', '-40', '-44', '-48', '+48'],
        ],
        'u13_h' => [
            'label' => 'U13 Jongens',
            'max_leeftijd' => 12,
            'geslacht' => 'M',
            'gewichten' => ['-24', '-27', '-30', '-34', '-38', '-42', '-46', '-50', '+50'],
        ],
        // U15: gescheiden, vaste klassen
        'u15_d' => [
            'label' => 'U15 Meisjes',
            'max_leeftijd' => 14,
            'geslacht' => 'V',
            'gewichten' => ['-32', '-36', '-40', '-44', '-48', '-52', '-57', '-63', '+63'],
        ],
        'u15_h' => [
            'label' => 'U15 Jongens',
            'max_leeftijd' => 14,
            'geslacht' => 'M',
            'gewichten' => ['-34', '-38', '-42', '-46', '-50', '-55', '-60', '-66', '+66'],
        ],
        // U18: gescheiden, vaste klassen
        'u18_d' => [
            'label' => 'U18 Dames',
            'max_leeftijd' => 17,
            'geslacht' => 'V',
            'gewichten' => ['-40', '-44', '-48', '-52', '-57', '-63', '-70', '+70'],
        ],
        'u18_h' => [
            'label' => 'U18 Heren',
            'max_leeftijd' => 17,
            'geslacht' => 'M',
            'gewichten' => ['-42', '-46', '-50', '-55', '-60', '-66', '-73', '-81', '-90', '+90'],
        ],
        // U21: gescheiden, vaste klassen
        'u21_d' => [
            'label' => 'U21 Dames',
            'max_leeftijd' => 20,
            'geslacht' => 'V',
            'gewichten' => ['-48', '-52', '-57', '-63', '-70', '-78', '+78'],
        ],
        'u21_h' => [
            'label' => 'U21 Heren',
            'max_leeftijd' => 20,
            'geslacht' => 'M',
            'gewichten' => ['-50', '-55', '-60', '-66', '-73', '-81', '-90', '-100', '+100'],
        ],
        // Senioren: gescheiden, vaste klassen
        'sen_d' => [
            'label' => 'Senioren Dames',
            'max_leeftijd' => 99,
            'geslacht' => 'V',
            'gewichten' => ['-48', '-52', '-57', '-63', '-70', '-78', '+78'],
        ],
        'sen_h' => [
            'label' => 'Senioren Heren',
            'max_leeftijd' => 99,
            'geslacht' => 'M',
            'gewichten' => ['-60', '-66', '-73', '-81', '-90', '-100', '+100'],
        ],
    ],
];
