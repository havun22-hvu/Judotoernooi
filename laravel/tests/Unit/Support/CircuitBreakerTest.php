<?php

namespace Tests\Unit\Support;

use App\Support\CircuitBreaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    use RefreshDatabase;
    private CircuitBreaker $breaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->breaker = new CircuitBreaker('test-service');
    }

    /** @test */
    public function it_executes_callback_when_circuit_is_closed(): void
    {
        $result = $this->breaker->call(fn() => 'success');

        $this->assertEquals('success', $result);
    }

    /** @test */
    public function it_opens_circuit_after_threshold_failures(): void
    {
        // Cause 3 failures (threshold)
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->breaker->call(fn() => throw new \Exception('fail'));
            } catch (\Exception $e) {
                // Expected
            }
        }

        // Circuit should now be open
        $this->assertTrue($this->breaker->isOpen());
    }

    /** @test */
    public function it_executes_fallback_when_circuit_is_open(): void
    {
        // Force circuit open
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->breaker->call(fn() => throw new \Exception('fail'));
            } catch (\Exception $e) {
                // Expected
            }
        }

        $result = $this->breaker->call(
            fn() => 'primary',
            fn() => 'fallback'
        );

        $this->assertEquals('fallback', $result);
    }

    /** @test */
    public function it_resets_failure_count_on_success(): void
    {
        // Cause 2 failures (below threshold)
        for ($i = 0; $i < 2; $i++) {
            try {
                $this->breaker->call(fn() => throw new \Exception('fail'));
            } catch (\Exception $e) {
                // Expected
            }
        }

        // Success should reset
        $this->breaker->call(fn() => 'success');

        // Should still be closed
        $this->assertFalse($this->breaker->isOpen());
    }

    /** @test */
    public function it_tracks_state_correctly(): void
    {
        $this->assertEquals('CLOSED', $this->breaker->getState());

        // Open the circuit
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->breaker->call(fn() => throw new \Exception('fail'));
            } catch (\Exception $e) {
                // Expected
            }
        }

        $this->assertEquals('OPEN', $this->breaker->getState());
    }

    /** @test */
    public function it_returns_stats(): void
    {
        $stats = $this->breaker->getStats();

        $this->assertArrayHasKey('state', $stats);
        $this->assertArrayHasKey('failures', $stats);
        $this->assertArrayHasKey('last_failure_time', $stats);
        $this->assertArrayHasKey('service', $stats);
    }

    /** @test */
    public function different_services_have_independent_circuits(): void
    {
        $breaker1 = new CircuitBreaker('service-1');
        $breaker2 = new CircuitBreaker('service-2');

        // Open circuit for service-1
        for ($i = 0; $i < 3; $i++) {
            try {
                $breaker1->call(fn() => throw new \Exception('fail'));
            } catch (\Exception $e) {
                // Expected
            }
        }

        // Service-1 should be open
        $this->assertTrue($breaker1->isOpen());

        // Service-2 should still be closed
        $this->assertFalse($breaker2->isOpen());
    }
}
