<?php

namespace App\Providers;

use App\Models\Judoka;
use App\Models\Wedstrijd;
use App\Observers\SyncQueueObserver;
use App\Services\BackupService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register sync queue observer for local server sync
        // Only observes score/weight changes to push to cloud
        Wedstrijd::observe(SyncQueueObserver::class);
        Judoka::observe(SyncQueueObserver::class);

        // PROTECT staging/production: block migrate:fresh, auto-backup before migrations
        Event::listen(MigrationsStarted::class, function () {
            $isMySQL = config('database.default') === 'mysql';
            $isServer = PHP_OS_FAMILY !== 'Windows';

            if ($isMySQL && $isServer) {
                // Check if this is a migrate:fresh (tables are being dropped)
                // If called from RefreshDatabase or direct migrate:fresh, block it
                $isSafeFresh = app()->bound('migrate:safe-fresh-running');

                if (!$isSafeFresh && $this->isMigrateFreshRunning()) {
                    $db = config('database.connections.mysql.database');
                    throw new \RuntimeException(
                        "⛔ migrate:fresh is BLOCKED on server (database: {$db}). "
                        . "Use: php artisan migrate:safe-fresh"
                    );
                }

                // Normal migrate: just backup
                app(BackupService::class)->maakMilestoneBackup('voor-migratie');
            }
        });

        // Configure rate limiters
        $this->configureRateLimiting();
    }

    /**
     * Detect if migrate:fresh is running (vs normal migrate).
     * Checks: direct CLI call, Artisan::call from RefreshDatabase, or test runner.
     */
    private function isMigrateFreshRunning(): bool
    {
        // Check CLI args (direct: php artisan migrate:fresh)
        $argv = $_SERVER['argv'] ?? [];
        foreach ($argv as $arg) {
            if (str_contains($arg, 'migrate:fresh')) {
                return true;
            }
        }

        // Check if running via test suite (RefreshDatabase calls migrate:fresh internally)
        foreach ($argv as $arg) {
            if (str_contains($arg, 'phpunit') || str_contains($arg, '--testsuite') || str_contains($arg, 'pest')) {
                return true;
            }
        }

        // Check if APP_ENV=testing (tests running)
        if (app()->environment('testing')) {
            return true;
        }

        return false;
    }

    /**
     * Configure rate limiting for API routes.
     */
    protected function configureRateLimiting(): void
    {
        // General API rate limit: 60 requests per minute
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // Stricter limit for public endpoints (favorieten, QR scan)
        RateLimiter::for('public-api', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        // Very strict limit for form submissions to prevent spam
        RateLimiter::for('form-submit', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Authentication attempts
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Webhook endpoints (Mollie callbacks)
        RateLimiter::for('webhook', function (Request $request) {
            return Limit::perMinute(100)->by($request->ip());
        });
    }
}
