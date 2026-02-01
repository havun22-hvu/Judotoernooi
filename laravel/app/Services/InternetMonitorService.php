<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class InternetMonitorService
{
    private const CACHE_KEY = 'internet_status';
    private const CACHE_TTL = 10; // seconds
    private const CLOUD_URL = 'https://judotournament.org';
    private const TIMEOUT = 3; // seconds

    /**
     * Get current internet status
     * Returns: 'good', 'poor', 'offline'
     */
    public function getStatus(): string
    {
        $cached = Cache::get(self::CACHE_KEY);
        if ($cached) {
            return $cached['status'];
        }

        $result = $this->checkConnection();
        Cache::put(self::CACHE_KEY, $result, self::CACHE_TTL);

        return $result['status'];
    }

    /**
     * Get latency to cloud server in milliseconds
     */
    public function getLatency(): ?int
    {
        $cached = Cache::get(self::CACHE_KEY);
        if ($cached) {
            return $cached['latency'];
        }

        $result = $this->checkConnection();
        Cache::put(self::CACHE_KEY, $result, self::CACHE_TTL);

        return $result['latency'];
    }

    /**
     * Check if cloud server is reachable
     */
    public function canReachCloud(): bool
    {
        return $this->getStatus() !== 'offline';
    }

    /**
     * Force a fresh check (bypasses cache)
     */
    public function freshCheck(): array
    {
        $result = $this->checkConnection();
        Cache::put(self::CACHE_KEY, $result, self::CACHE_TTL);
        return $result;
    }

    /**
     * Get full status info
     */
    public function getFullStatus(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if ($cached) {
            return $cached;
        }

        $result = $this->checkConnection();
        Cache::put(self::CACHE_KEY, $result, self::CACHE_TTL);

        return $result;
    }

    /**
     * Perform the actual connection check
     */
    private function checkConnection(): array
    {
        $startTime = microtime(true);
        $status = 'offline';
        $latency = null;

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->get(self::CLOUD_URL . '/api/health');

            $latency = (int) round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                // Good connection: < 500ms
                // Poor connection: 500ms - 2000ms
                $status = $latency < 500 ? 'good' : 'poor';
            } else {
                $status = 'poor';
            }
        } catch (\Exception $e) {
            $status = 'offline';
            $latency = null;
        }

        return [
            'status' => $status,
            'latency' => $latency,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get status color for UI
     */
    public static function getStatusColor(string $status): string
    {
        return match ($status) {
            'good' => 'green',
            'poor' => 'orange',
            'offline' => 'red',
            default => 'gray',
        };
    }

    /**
     * Get status label for UI (Dutch)
     */
    public static function getStatusLabel(string $status): string
    {
        return match ($status) {
            'good' => 'Goed',
            'poor' => 'Matig',
            'offline' => 'Offline',
            default => 'Onbekend',
        };
    }
}
