<?php

namespace App\Providers;

use App\Models\Judoka;
use App\Models\Poule;
use App\Models\Wedstrijd;
use App\Observers\PublicTournamentCacheObserver;
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

        // Invalidate public tournament cache on data changes
        Poule::observe(PublicTournamentCacheObserver::class);
        Wedstrijd::observe(PublicTournamentCacheObserver::class);

        // PROTECT staging/production: block migrate:fresh, auto-backup before migrations
        Event::listen(MigrationsStarted::class, function () {
            if (!BackupService::isServerEnvironment()) {
                return;
            }

            // Skip if SafeMigrateFresh is running (it handles its own backup)
            if (app()->bound('migrate:safe-fresh-running')) {
                return;
            }

            // Block migrate:fresh on server (RefreshDatabase, direct CLI call)
            if ($this->isMigrateFreshCall()) {
                $db = config('database.connections.mysql.database');
                throw new \RuntimeException(
                    "⛔ migrate:fresh is BLOCKED on server (database: {$db}). "
                    . "Use: php artisan migrate:safe-fresh"
                );
            }

            // Normal migrate: auto-backup
            app(BackupService::class)->maakMilestoneBackup('voor-migratie');
        });

        // Configure rate limiters
        $this->configureRateLimiting();
    }

    /**
     * Detect if migrate:fresh is running (vs normal migrate).
     * Only checks CLI args — does NOT block based on APP_ENV to avoid false positives.
     */
    private function isMigrateFreshCall(): bool
    {
        $argv = $_SERVER['argv'] ?? [];
        foreach ($argv as $arg) {
            if (str_contains($arg, 'migrate:fresh')
                || str_contains($arg, 'phpunit')
                || str_contains($arg, 'pest')
                || str_contains($arg, '--testsuite')) {
                return true;
            }
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
