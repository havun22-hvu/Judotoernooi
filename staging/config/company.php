<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bedrijfsgegevens
    |--------------------------------------------------------------------------
    |
    | Bedrijfsinformatie voor facturen en officiële communicatie.
    |
    */

    'name' => env('COMPANY_NAME', 'Havun'),
    'legal_name' => env('COMPANY_LEGAL_NAME', 'Havun'),
    'address' => env('COMPANY_ADDRESS', 'Jacques Bloemhof 57'),
    'postal_code' => env('COMPANY_POSTAL_CODE', '1628 VN'),
    'city' => env('COMPANY_CITY', 'Hoorn'),
    'country' => env('COMPANY_COUNTRY', 'Nederland'),

    /*
    |--------------------------------------------------------------------------
    | Registratie Gegevens
    |--------------------------------------------------------------------------
    */

    'kvk' => env('COMPANY_KVK', '98516000'),
    'vat_number' => env('COMPANY_VAT_NUMBER', 'NL002995910B70'), // BTW-ID
    'iban' => env('COMPANY_IBAN', 'NL75BUNQ2167592531'), // Bank account

    /*
    |--------------------------------------------------------------------------
    | Contact Gegevens
    |--------------------------------------------------------------------------
    */

    'email' => env('COMPANY_EMAIL', 'havun22@gmail.com'),
    'phone' => env('COMPANY_PHONE', ''),
    'website' => env('APP_URL', 'https://judotoernooi.nl'),

    /*
    |--------------------------------------------------------------------------
    | BTW Configuratie
    |--------------------------------------------------------------------------
    |
    | BTW percentage voor digitale diensten in Nederland
    | KOR (Kleine Ondernemers Regeling): 0% BTW
    |
    */

    'vat_rate' => 0.00, // KOR - Geen BTW (was: 0.21 voor 21% BTW)
    'vat_exempt' => true, // KOR - BTW vrijgesteld
    'vat_exempt_reason' => 'Kleine Ondernemers Regeling (KOR) - Artikel 25 Wet OB',

    /*
    |--------------------------------------------------------------------------
    | Factuur Configuratie
    |--------------------------------------------------------------------------
    */

    'invoice' => [
        'prefix' => env('INVOICE_PREFIX', date('Y') . '-'), // 2025-001, 2025-002, etc.
        'starting_number' => 1,
        'logo_path' => 'assets/images/logo.png', // Relatief aan storage/app/public
    ],

];
