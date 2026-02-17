<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

return Application::configure(basePath: dirname(__DIR__))
    ->booted(function () {
        // Rate limiters moeten hier gedefinieerd worden (voor routes laden)
        RateLimiter::for('login', fn ($request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('api', fn ($request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('public-api', fn ($request) => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('form-submit', fn ($request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('webhook', fn ($request) => Limit::perMinute(100)->by($request->ip()));
    })
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
    )
    ->withSchedule(function (Schedule $schedule) {
        // Wedstrijddag backup: elke minuut (command checkt zelf of wedstrijddag actief is)
        $schedule->command('backup:wedstrijddag')->everyMinute();
    })
    ->withMiddleware(function (Middleware $middleware) {
        // Global middleware - runs on every request
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // Web middleware group additions (session is available here)
        $middleware->web(\App\Http\Middleware\SetLocale::class);
        $middleware->web(\App\Http\Middleware\OfflineMode::class);

        $middleware->alias([
            'rol.sessie' => \App\Http\Middleware\CheckRolSessie::class,
            'device.binding' => \App\Http\Middleware\CheckDeviceBinding::class,
            'local-sync.auth' => \App\Http\Middleware\LocalSyncAuth::class,
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
            'coach-kaart/*/activeer',  // Coach card activation (public, uses pincode)
            'coach-kaart/*/checkin',   // Coach check-in (public)
            'coach-kaart/*/checkout',  // Coach check-out (public)
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle custom JudoToernooi exceptions
        $exceptions->render(function (\App\Exceptions\JudoToernooiException $e, $request) {
            $e->log();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getUserMessage(),
                    'error_code' => $e->getCode(),
                ], 422);
            }

            return back()->with('error', $e->getUserMessage());
        });

        // Store debug info in session for error reporting + notify admin
        $exceptions->report(function (\Throwable $e) {
            // Store debug info in session so the error page "Meld probleem" button can send it
            try {
                $trace = collect($e->getTrace())->take(10)->map(function ($frame) {
                    $file = $frame['file'] ?? '';
                    // Strip server paths for readability
                    $file = str_replace([base_path() . '/', base_path() . '\\'], '', $file);
                    return ($file ? $file . ':' . ($frame['line'] ?? '?') : '') .
                        ' ' . ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '');
                })->filter()->values()->toArray();

                session()->put('_last_error', [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => str_replace([base_path() . '/', base_path() . '\\'], '', $e->getFile()) . ':' . $e->getLine(),
                    'url' => request()?->fullUrl(),
                    'method' => request()?->method(),
                    'route' => request()?->route()?->getName() ?? request()?->route()?->uri(),
                    'route_params' => request()?->route()?->parameters() ?? [],
                    'input' => request()?->except(['password', 'password_confirmation', '_token']),
                    'user_id' => auth()->id(),
                    'trace' => $trace,
                    'timestamp' => now()->format('d-m-Y H:i:s'),
                ]);
            } catch (\Exception $sessionError) {
                // Session may not be available (console, etc.)
            }

            // Only notify in production for critical errors
            if (!app()->environment('local', 'testing')) {
                // Skip common non-critical exceptions
                $ignoredExceptions = [
                    \Illuminate\Session\TokenMismatchException::class,
                    \Illuminate\Database\Eloquent\ModelNotFoundException::class,
                    \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
                    \Illuminate\Validation\ValidationException::class,
                ];

                foreach ($ignoredExceptions as $ignored) {
                    if ($e instanceof $ignored) {
                        return;
                    }
                }

                // Send notification for critical errors
                try {
                    app(\App\Services\ErrorNotificationService::class)->notifyException($e, [
                        'url' => request()?->fullUrl(),
                        'method' => request()?->method(),
                        'input' => request()?->except(['password', 'password_confirmation']),
                    ]);
                } catch (\Exception $notifyError) {
                    // Don't let notification failure break error handling
                    \Illuminate\Support\Facades\Log::warning('Error notification failed', [
                        'error' => $notifyError->getMessage(),
                    ]);
                }

                // AutoFix: analyze error with Claude and propose a fix
                try {
                    app(\App\Services\AutoFixService::class)->handle($e);
                } catch (\Exception $autofixError) {
                    \Illuminate\Support\Facades\Log::warning('AutoFix failed', [
                        'error' => $autofixError->getMessage(),
                    ]);
                }
            }
        });

        // Handle deleted/missing models (judoka verwijderd terwijl pagina open, etc.)
        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Dit item bestaat niet meer. Mogelijk is het verwijderd of verplaatst.',
                ], 404);
            }

            return redirect()->back()
                ->with('error', 'Dit item bestaat niet meer. Mogelijk is het verwijderd of verplaatst door de organisator.');
        });

        // Handle 419 Page Expired (CSRF token expired)
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'CSRF token mismatch'], 419);
            }

            // For public routes (coach-kaart, weegkaart), redirect back with error
            $path = $request->path();
            if (str_starts_with($path, 'coach-kaart/') || str_starts_with($path, 'weegkaart/')) {
                return redirect()->back()->with('error', 'Formulier verlopen. Probeer opnieuw.');
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

                // For public routes, redirect back instead of to login
                $path = $request->path();
                if (str_starts_with($path, 'coach-kaart/') || str_starts_with($path, 'weegkaart/')) {
                    return redirect()->back()->with('error', 'Formulier verlopen. Probeer opnieuw.');
                }

                return redirect()
                    ->guest(route('organisator.login'))
                    ->with('warning', 'Sessie verlopen. Log opnieuw in.');
            }
        });
    })->create();
