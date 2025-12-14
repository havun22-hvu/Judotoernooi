<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'rol.sessie' => \App\Http\Middleware\CheckRolSessie::class,
        ]);

        // Exclude public API routes from CSRF verification
        $middleware->validateCsrfTokens(except: [
            'publiek/*/favorieten',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
