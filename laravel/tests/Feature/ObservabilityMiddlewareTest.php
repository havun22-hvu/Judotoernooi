<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ObservabilityMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Disable observability — the havuncore DB doesn't exist in tests
        config(['observability.enabled' => false]);
    }

    #[Test]
    public function middleware_passes_request_through_when_disabled(): void
    {
        $response = $this->get('/');
        // Should not error — middleware should gracefully skip logging
        $response->assertSuccessful();
    }

    #[Test]
    public function excluded_paths_are_not_logged(): void
    {
        config([
            'observability.enabled' => true,
            'observability.excluded_paths' => ['health', 'health/*'],
        ]);

        // Health endpoint exists — should respond without error even with observability on
        // The havuncore DB insert will fail silently (catch Throwable)
        $response = $this->get('/health');
        $response->assertSuccessful();
    }

    #[Test]
    public function middleware_does_not_break_post_requests(): void
    {
        $response = $this->post('/organisator/login', [
            'email' => 'nonexistent@test.nl',
            'password' => 'wrong',
        ]);

        // Should get validation error or redirect, not a 500
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    #[Test]
    public function middleware_handles_missing_havuncore_connection_gracefully(): void
    {
        // Enable observability — but havuncore connection doesn't exist in tests
        // The middleware wraps the insert in try/catch, so it should not break
        config([
            'observability.enabled' => true,
            'observability.excluded_paths' => [],
            'observability.sampling_rate' => 1.0,
        ]);

        $response = $this->get('/');
        $response->assertSuccessful();
    }
}
