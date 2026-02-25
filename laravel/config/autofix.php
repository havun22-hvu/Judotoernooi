<?php

return [
    // Enable/disable the autofix system
    'enabled' => env('AUTOFIX_ENABLED', false),

    // HavunCore API URL (AI Proxy)
    'havuncore_url' => env('HAVUNCORE_API_URL', 'https://havuncore.havun.nl'),

    // Email to send fix proposals to
    'email' => env('AUTOFIX_EMAIL', 'havun22@gmail.com'),

    // Rate limit: minutes between duplicate error analyses
    'rate_limit_minutes' => (int) env('AUTOFIX_RATE_LIMIT', 60),

    // Max file size to send to Claude (bytes)
    'max_file_size' => 50000,

    // Max number of stack trace files to include
    'max_context_files' => 5,

    // Exceptions that should never trigger autofix
    'excluded_exceptions' => [
        \Illuminate\Validation\ValidationException::class,
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Session\TokenMismatchException::class,
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class,
        \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException::class,
        // Tinker/PsySH errors - user syntax errors, not fixable
        'Psy\Exception\ParseErrorException',
        'Psy\Exception\ErrorException',
        // Missing artisan commands - not fixable by code changes
        'Symfony\Component\Console\Exception\NamespaceNotFoundException',
        'Symfony\Component\Console\Exception\CommandNotFoundException',
    ],

    // File path patterns to exclude (errors from these paths are ignored)
    'excluded_file_patterns' => [
        '#/tmp/#',
        '#vendor/psy/#',
        '#vendor/laravel/tinker/#',
    ],

    // Files that should never be modified by AutoFix
    'protected_files' => [
        'artisan',
        'public/index.php',
        'bootstrap/app.php',
        'composer.json',
        'composer.lock',
    ],
];
