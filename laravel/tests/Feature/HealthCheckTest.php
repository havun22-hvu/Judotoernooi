<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function health_endpoint_returns_healthy_status(): void
    {
        $response = $this->getJson('/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'healthy',
            ])
            ->assertJsonStructure([
                'status',
                'timestamp',
                'checks' => [
                    'database' => ['ok'],
                    'disk' => ['ok'],
                    'cache' => ['ok'],
                ],
            ]);
    }

    /** @test */
    public function health_detailed_endpoint_returns_more_info(): void
    {
        $response = $this->getJson('/health/detailed');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'timestamp',
                'environment',
                'version',
                'checks' => [
                    'database' => ['ok', 'response_time_ms', 'driver'],
                    'disk' => ['ok', 'free_gb', 'used_percent'],
                    'cache' => ['ok', 'driver'],
                    'app' => ['ok', 'debug', 'timezone'],
                ],
            ]);
    }

    /** @test */
    public function ping_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/ping');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
            ])
            ->assertJsonStructure([
                'status',
                'timestamp',
            ]);
    }
}
