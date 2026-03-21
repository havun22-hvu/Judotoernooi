<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class HealthController extends Controller
{
    /**
     * Basic health check endpoint for monitoring.
     * Returns 200 if healthy, 503 if any critical check fails.
     */
    public function check(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'disk' => $this->checkDisk(),
            'cache' => $this->checkCache(),
        ];

        $healthy = !in_array(false, array_column($checks, 'ok'));
        $status = $healthy ? 200 : 503;

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $status);
    }

    /**
     * Detailed health check with more information (protected).
     */
    public function detailed(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(true),
            'disk' => $this->checkDisk(true),
            'cache' => $this->checkCache(true),
            'app' => $this->checkApp(),
        ];

        $healthy = !in_array(false, array_column($checks, 'ok'));

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'environment' => config('app.env'),
            'version' => config('app.version', '1.0.0'),
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    private function checkDatabase(bool $detailed = false): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            $result = ['ok' => true];
            if ($detailed) {
                $result['response_time_ms'] = $responseTime;
                $result['driver'] = config('database.default');
            }

            return $result;
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'error' => $detailed ? $e->getMessage() : 'Connection failed',
            ];
        }
    }

    private function checkDisk(bool $detailed = false): array
    {
        $path = storage_path();
        $freeBytes = disk_free_space($path);
        $totalBytes = disk_total_space($path);

        if ($freeBytes === false || $totalBytes === false) {
            return ['ok' => false, 'error' => 'Unable to read disk space'];
        }

        $freeGb = round($freeBytes / 1024 / 1024 / 1024, 2);
        $usedPercent = round((1 - ($freeBytes / $totalBytes)) * 100, 1);

        // Alert if less than 1GB free or more than 90% used
        $ok = $freeGb >= 1 && $usedPercent < 90;

        $result = ['ok' => $ok];
        if ($detailed) {
            $result['free_gb'] = $freeGb;
            $result['used_percent'] = $usedPercent;
        }

        return $result;
    }

    private function checkCache(bool $detailed = false): array
    {
        try {
            $testKey = 'health_check_' . time();
            Cache::put($testKey, 'ok', 10);
            $value = Cache::get($testKey);
            Cache::forget($testKey);

            $result = ['ok' => $value === 'ok'];
            if ($detailed) {
                $result['driver'] = config('cache.default');
            }

            return $result;
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'error' => $detailed ? $e->getMessage() : 'Cache test failed',
            ];
        }
    }

    private function checkApp(): array
    {
        return [
            'ok' => true,
            'debug' => config('app.debug'),
            'timezone' => config('app.timezone'),
            'locale' => config('app.locale'),
        ];
    }
}
