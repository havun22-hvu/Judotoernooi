<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Circuit Breaker pattern for external services.
 *
 * Prevents cascading failures by tracking failures and
 * temporarily blocking calls to failing services.
 *
 * States:
 * - CLOSED: Normal operation, requests go through
 * - OPEN: Service is down, requests fail immediately
 * - HALF_OPEN: Testing if service recovered
 */
class CircuitBreaker
{
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    private string $service;
    private int $failureThreshold;
    private int $recoveryTimeout;
    private int $halfOpenMaxAttempts;

    public function __construct(
        string $service,
        int $failureThreshold = 3,
        int $recoveryTimeout = 30,
        int $halfOpenMaxAttempts = 1
    ) {
        $this->service = $service;
        $this->failureThreshold = $failureThreshold;
        $this->recoveryTimeout = $recoveryTimeout;
        $this->halfOpenMaxAttempts = $halfOpenMaxAttempts;
    }

    /**
     * Execute a callable with circuit breaker protection.
     *
     * @template T
     * @param callable(): T $callback
     * @param callable(): T|null $fallback Optional fallback when circuit is open
     * @return T
     * @throws \Exception When circuit is open and no fallback provided
     */
    public function call(callable $callback, ?callable $fallback = null): mixed
    {
        $state = $this->getState();

        // Circuit is open - fail fast
        if ($state === self::STATE_OPEN) {
            Log::warning("Circuit breaker OPEN for {$this->service}", [
                'failures' => $this->getFailureCount(),
                'opens_at' => $this->getOpenedAt(),
            ]);

            if ($fallback) {
                return $fallback();
            }

            throw new \RuntimeException("Service {$this->service} is temporarily unavailable");
        }

        // Half-open: allow limited attempts to test recovery
        if ($state === self::STATE_HALF_OPEN) {
            $halfOpenAttempts = $this->getHalfOpenAttempts();
            if ($halfOpenAttempts >= $this->halfOpenMaxAttempts) {
                if ($fallback) {
                    return $fallback();
                }
                throw new \RuntimeException("Service {$this->service} is still recovering");
            }
            $this->incrementHalfOpenAttempts();
        }

        try {
            $result = $callback();
            $this->recordSuccess();
            return $result;
        } catch (\Exception $e) {
            $this->recordFailure($e);
            throw $e;
        }
    }

    /**
     * Check if calls are allowed (for pre-check without executing).
     */
    public function isAvailable(): bool
    {
        return $this->getState() !== self::STATE_OPEN;
    }

    /**
     * Get current circuit state.
     */
    public function getState(): string
    {
        $failures = $this->getFailureCount();
        $openedAt = $this->getOpenedAt();

        // Check if we should transition from OPEN to HALF_OPEN
        if ($openedAt && (time() - $openedAt) >= $this->recoveryTimeout) {
            return self::STATE_HALF_OPEN;
        }

        // Circuit is open if we have recorded an open state
        if ($openedAt) {
            return self::STATE_OPEN;
        }

        return self::STATE_CLOSED;
    }

    /**
     * Record a successful call - reset failure count.
     */
    private function recordSuccess(): void
    {
        $this->resetCircuit();

        if ($this->getState() === self::STATE_HALF_OPEN) {
            Log::info("Circuit breaker RECOVERED for {$this->service}");
        }
    }

    /**
     * Record a failed call.
     */
    private function recordFailure(\Exception $e): void
    {
        $failures = $this->incrementFailureCount();

        Log::warning("Circuit breaker failure for {$this->service}", [
            'failures' => $failures,
            'threshold' => $this->failureThreshold,
            'error' => $e->getMessage(),
        ]);

        if ($failures >= $this->failureThreshold) {
            $this->openCircuit();
        }
    }

    /**
     * Open the circuit (block all calls).
     */
    private function openCircuit(): void
    {
        Cache::put($this->cacheKey('opened_at'), time(), $this->recoveryTimeout + 60);
        Cache::put($this->cacheKey('half_open_attempts'), 0, $this->recoveryTimeout + 60);

        Log::error("Circuit breaker OPENED for {$this->service}", [
            'recovery_in_seconds' => $this->recoveryTimeout,
        ]);
    }

    /**
     * Reset the circuit to closed state.
     */
    private function resetCircuit(): void
    {
        Cache::forget($this->cacheKey('failures'));
        Cache::forget($this->cacheKey('opened_at'));
        Cache::forget($this->cacheKey('half_open_attempts'));
    }

    private function getFailureCount(): int
    {
        return (int) Cache::get($this->cacheKey('failures'), 0);
    }

    private function incrementFailureCount(): int
    {
        $key = $this->cacheKey('failures');
        $count = $this->getFailureCount() + 1;
        Cache::put($key, $count, 300); // Keep for 5 minutes
        return $count;
    }

    private function getOpenedAt(): ?int
    {
        return Cache::get($this->cacheKey('opened_at'));
    }

    private function getHalfOpenAttempts(): int
    {
        return (int) Cache::get($this->cacheKey('half_open_attempts'), 0);
    }

    private function incrementHalfOpenAttempts(): void
    {
        $key = $this->cacheKey('half_open_attempts');
        Cache::put($key, $this->getHalfOpenAttempts() + 1, $this->recoveryTimeout + 60);
    }

    private function cacheKey(string $suffix): string
    {
        return "circuit_breaker:{$this->service}:{$suffix}";
    }

    /**
     * Manually reset the circuit (for admin/testing).
     */
    public function reset(): void
    {
        $this->resetCircuit();
        Log::info("Circuit breaker manually reset for {$this->service}");
    }

    /**
     * Get circuit status for monitoring.
     */
    public function getStatus(): array
    {
        return [
            'service' => $this->service,
            'state' => $this->getState(),
            'failures' => $this->getFailureCount(),
            'threshold' => $this->failureThreshold,
            'opened_at' => $this->getOpenedAt(),
            'recovery_timeout' => $this->recoveryTimeout,
        ];
    }
}
