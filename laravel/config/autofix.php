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
    ],
];
