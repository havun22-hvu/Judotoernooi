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
            '*/*/favorieten',  // Public favorites API: /{organisator}/{toernooi}/favorieten
            '*/*/scan-qr',     // Public QR scan API
            'mollie/webhook',
            'mollie/webhook/toernooi',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle 419 Page Expired (CSRF token expired) - redirect to login
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'CSRF token mismatch'], 419);
            }
            return redirect()
                ->guest(route('organisator.login'))
                ->with('warning', 'Sessie verlopen. Log opnieuw in.');
        });

        // Also catch the Symfony HttpException for 419
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, $request) {
            if ($e->getStatusCode() === 419) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Page expired'], 419);
                }
                return redirect()
                    ->guest(route('organisator.login'))
                    ->with('warning', 'Sessie verlopen. Log opnieuw in.');
            }
        });
    })->create();
