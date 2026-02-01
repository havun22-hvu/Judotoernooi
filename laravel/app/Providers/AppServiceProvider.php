<?php

namespace App\Providers;

use App\Models\Judoka;
use App\Models\Wedstrijd;
use App\Observers\SyncQueueObserver;
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
    }
}
