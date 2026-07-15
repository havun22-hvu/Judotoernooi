<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Published to replace the framework default of allowed_origins: ['*'].
    |
    | The scoreboard app is native — it sends no Origin header and CORS does not
    | apply to it. The PWA and blade views are same-origin. So the only thing a
    | wildcard bought us was letting arbitrary websites call the API from a
    | visitor's browser. Restricted to our own origin instead.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter([
        config('app.url'),
    ]),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Bearer-token API, no cookies — keep credentials off.
    'supports_credentials' => false,

];
