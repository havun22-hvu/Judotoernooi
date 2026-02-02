<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mollie Configuration
    |--------------------------------------------------------------------------
    |
    | Platform mode: betalingen gaan naar JudoToernooi's Mollie account
    | Connect mode: betalingen gaan naar organisator's eigen Mollie account
    |
    */

    'mollie' => [
        // Platform mode keys (JudoToernooi's eigen Mollie account)
        'platform_key' => env('MOLLIE_PLATFORM_API_KEY'),
        'platform_test_key' => env('MOLLIE_PLATFORM_TEST_KEY'),

        // OAuth App credentials (voor Mollie Connect)
        'client_id' => env('MOLLIE_CLIENT_ID'),
        'client_secret' => env('MOLLIE_CLIENT_SECRET'),
        'redirect_uri' => env('MOLLIE_REDIRECT_URI'),

        // API endpoints
        'api_url' => 'https://api.mollie.com/v2',
        'oauth_url' => 'https://www.mollie.com/oauth2',
        'oauth_token_url' => 'https://api.mollie.com/oauth2',

        // Default platform fee
        'default_platform_fee' => env('MOLLIE_PLATFORM_FEE', 0.50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Notification
    |--------------------------------------------------------------------------
    |
    | Configure webhook URL for real-time error notifications to HavunCore.
    | Set ERROR_NOTIFICATION_WEBHOOK in .env to enable.
    |
    */

    'error_notification' => [
        'webhook_url' => env('ERROR_NOTIFICATION_WEBHOOK'),
    ],

];
