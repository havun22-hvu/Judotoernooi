<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Local Server Configuration
    |--------------------------------------------------------------------------
    |
    | This file stores the local server role configuration for redundancy.
    | Role can be 'primary' or 'standby'.
    |
    */

    'role' => env('LOCAL_SERVER_ROLE', null),
    'ip' => env('LOCAL_SERVER_IP', null),
    'configured_at' => env('LOCAL_SERVER_CONFIGURED_AT', null),
    'device_name' => env('LOCAL_SERVER_DEVICE_NAME', null),

    // Network settings
    'primary_ip' => '192.168.1.100',
    'standby_ip' => '192.168.1.101',
    'port' => 8000,

    // Auth token for remote access to sync routes (set in .env)
    'sync_token' => env('LOCAL_SYNC_TOKEN'),

    // Sync settings
    'heartbeat_interval' => 5, // seconds
    'sync_interval' => 5, // seconds
    'heartbeat_timeout' => 15, // seconds (3 missed heartbeats)
];
