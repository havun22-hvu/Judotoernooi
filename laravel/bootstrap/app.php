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
            'device.binding' => \App\Http\Middleware\CheckDeviceBinding::class,
        ]);

        // Redirect guests to organisator login
        $middleware->redirectGuestsTo('/organisator/login');

        // Redirect authenticated users away from guest routes (login/register)
        // to the dashboard instead of default home
        $middleware->redirectUsersTo('/organisator/dashboard');

        // Exclude public API routes from CSRF verification
        $middleware->validateCsrfTokens(except: [
            'publiek/*/favorieten',
            'mollie/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle 419 Page Expired (CSRF token expired) - redirect to login
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, $request) {
            return redirect()
                ->route('organisator.login')
                ->with('warning', 'Sessie verlopen. Log opnieuw in.');
        });
    })->create();
