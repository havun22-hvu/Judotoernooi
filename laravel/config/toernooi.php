<?php

return [

    /*
    |--------------------------------------------------------------------------
    | App Versie
    |--------------------------------------------------------------------------
    |
    | Versienummer voor PWA updates. Verhoog bij elke release.
    |
    */

    'version' => '1.0.8',
    'version_date' => '2026-01-04',

    /*
    |--------------------------------------------------------------------------
    | Poule Instellingen
    |--------------------------------------------------------------------------
    |
    | Configuratie voor de poule-indeling algoritme.
    |
    */

    'min_judokas_poule' => env('TOERNOOI_MIN_JUDOKAS_POULE', 3),
    'optimal_judokas_poule' => env('TOERNOOI_OPTIMAL_JUDOKAS_POULE', 5),
    'max_judokas_poule' => env('TOERNOOI_MAX_JUDOKAS_POULE', 6),

    /*
    |--------------------------------------------------------------------------
    | Weging Instellingen
    |--------------------------------------------------------------------------
    |
    | Configuratie voor de weging en gewichtscontrole.
    |
    */

    'gewicht_tolerantie' => env('TOERNOOI_GEWICHT_TOLERANTIE', 0.5),
    'gewicht_min' => 15,
    'gewicht_max' => 150,

    /*
    |--------------------------------------------------------------------------
    | Admin Instellingen
    |--------------------------------------------------------------------------
    |
    | Configuratie voor admin toegang.
    |
    */

    'admin_password' => env('ADMIN_PASSWORD', 'WestFries2026'),
    'superadmin_pin' => env('SUPERADMIN_PIN', '1234'),

    /*
    |--------------------------------------------------------------------------
    | Standaard Toernooi Configuratie
    |--------------------------------------------------------------------------
    |
    | Standaard waarden voor nieuwe toernooien.
    |
    */

    'defaults' => [
        'organisatie' => 'Judoschool Cees Veen',
        'aantal_matten' => 7,
        'aantal_blokken' => 6,
    ],

    /*
    |--------------------------------------------------------------------------
    | Leeftijdsklassen
    |--------------------------------------------------------------------------
    |
    | Definitie van leeftijdsklassen met hun gewichtsklassen.
    |
    */

    'leeftijdsklassen' => [
        'minis' => [
            'label' => "Mini's",
            'max_leeftijd' => 8,
            'gewichtsklassen' => [-20, -23, -26, -29, 29],
        ],
        'a_pupillen' => [
            'label' => 'A-pupillen',
            'max_leeftijd' => 10,
            'gewichtsklassen' => [-24, -27, -30, -34, -38, 38],
        ],
        'b_pupillen' => [
            'label' => 'B-pupillen',
            'max_leeftijd' => 12,
            'gewichtsklassen' => [-27, -30, -34, -38, -42, -46, -50, 50],
        ],
        'dames_15' => [
            'label' => 'Dames -15',
            'max_leeftijd' => 15,
            'geslacht' => 'V',
            'gewichtsklassen' => [-36, -40, -44, -48, -52, -57, -63, 63],
        ],
        'heren_15' => [
            'label' => 'Heren -15',
            'max_leeftijd' => 15,
            'geslacht' => 'M',
            'gewichtsklassen' => [-34, -38, -42, -46, -50, -55, -60, -66, 66],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Band Combinaties
    |--------------------------------------------------------------------------
    |
    | Definieert welke banden samen in een poule mogen.
    | 0=zwart, 1=bruin, 2=blauw, 3=groen, 4=oranje, 5=geel, 6=wit
    |
    */

    'band_combinaties' => [
        'minis' => [[0, 1, 2, 3, 4, 5, 6]], // Alle banden samen
        'a_pupillen' => [[6], [0, 1, 2, 3, 4, 5]], // Wit apart, rest samen
        'b_pupillen' => [[5, 6], [0, 1, 2, 3, 4]], // Wit+geel, rest samen
        'dames_15' => [[3, 4, 5, 6], [1, 2]], // Wit t/m groen, blauw+bruin
        'heren_15' => [[3, 4, 5, 6], [1, 2]], // Wit t/m groen, blauw+bruin
    ],

];
