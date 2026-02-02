<?php

namespace Tests\Unit\Support;

use App\Support\CircuitBreaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /** @test */
    public function it_executes_callback_when_circuit_is_closed(): void
    {
        $breaker = new CircuitBreaker('test-service');

        $result = $breaker->call(fn () => 'success');

        $this->assertEquals('success', $result);
        $this->assertEquals('closed', $breaker->getState());
    }

    /** @test */
    public function it_opens_circuit_after_threshold_failures(): void
    {
        $breaker = new CircuitBreaker('test-service', failureThreshold: 3);

        // Cause 3 failures
        for ($i = 0; $i < 3; $i++) {
            try {
                $breaker->call(fn () => throw new \Exception('fail'));
            } catch (\Exception $e) {
                // Expected
            }
        }

        $this->assertEquals('open', $breaker->getState());
        $this->assertFalse($breaker->isAvailable());
    }

    /** @test */
    public function it_uses_fallback_when_circuit_is_open(): void
    {
        $breaker = new CircuitBreaker('test-service', failureThreshold: 1);

        // Open the circuit
        try {
            $breaker->call(fn () => throw new \Exception('fail'));
        } catch (\Exception $e) {
            // Expected
        }

        // Should use fallback
        $result = $breaker->call(
            fn () => 'primary',
            fn () => 'fallback'
        );

        $this->assertEquals('fallback', $result);
    }

    /** @test */
    public function it_throws_when_open_and_no_fallback(): void
    {
        $breaker = new CircuitBreaker('test-service', failureThreshold: 1);

        // Open the circuit
        try {
            $breaker->call(fn () => throw new \Exception('fail'));
        } catch (\Exception $e) {
            // Expected
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('temporarily unavailable');

        $breaker->call(fn () => 'should not execute');
    }

    /** @test */
    public function it_can_be_manually_reset(): void
    {
        $breaker = new CircuitBreaker('test-service', failureThreshold: 1);

        // Open the circuit
        try {
            $breaker->call(fn () => throw new \Exception('fail'));
        } catch (\Exception $e) {
            // Expected
        }

        $this->assertEquals('open', $breaker->getState());

        $breaker->reset();

        $this->assertEquals('closed', $breaker->getState());
        $this->assertTrue($breaker->isAvailable());
    }

    /** @test */
    public function it_returns_status_for_monitoring(): void
    {
        $breaker = new CircuitBreaker('test-service', failureThreshold: 3, recoveryTimeout: 30);

        $status = $breaker->getStatus();

        $this->assertEquals('test-service', $status['service']);
        $this->assertEquals('closed', $status['state']);
        $this->assertEquals(0, $status['failures']);
        $this->assertEquals(3, $status['threshold']);
    }
}
