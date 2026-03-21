<?php

namespace App\Providers;

use App\WebAuthn\DatabaseChallengeRepository;
use Illuminate\Support\ServiceProvider;
use Laragear\WebAuthn\Contracts\WebAuthnChallengeRepository;

class WebAuthnServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Use database challenge storage instead of session (needed for cross-device flows)
        $this->app->bind(WebAuthnChallengeRepository::class, DatabaseChallengeRepository::class);
    }
}
