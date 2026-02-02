<?php

namespace App\Providers;

use App\Models\Judoka;
use App\Models\Wedstrijd;
use App\Observers\SyncQueueObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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

        // Configure rate limiters
        $this->configureRateLimiting();
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
