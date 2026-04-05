<?php

namespace App\Console\Commands;

use App\Support\CircuitBreaker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verify Reverb is running and can accept broadcasts.
 *
 * Use after deploy, in monitoring, or as post-merge hook.
 * Exit code 0 = healthy, 1 = unhealthy.
 */
class ReverbHealthCheck extends Command
{
    protected $signature = 'reverb:health {--fix : Auto-fix recoverable issues (circuit breaker reset)}';
    protected $description = 'Check Reverb WebSocket server health and broadcast capability';

    public function handle(): int
    {
        $failures = [];

        // 1. Config validation
        $this->info('Checking config...');
        $failures = array_merge($failures, $this->checkConfig());

        // 2. Reverb reachability
        $this->info('Checking Reverb server...');
        $failures = array_merge($failures, $this->checkReverbServer());

        // 3. Broadcast test (actual event dispatch)
        $this->info('Testing broadcast...');
        $failures = array_merge($failures, $this->checkBroadcast());

        // 4. Circuit breaker state
        $this->info('Checking circuit breaker...');
        $failures = array_merge($failures, $this->checkCircuitBreaker());

        if (empty($failures)) {
            $this->info('✓ Reverb is healthy — all checks passed.');
            return Command::SUCCESS;
        }

        $this->error('✗ Reverb health check FAILED:');
        foreach ($failures as $f) {
            $this->line("  - {$f}");
        }

        Log::error('Reverb health check failed', ['failures' => $failures]);
        return Command::FAILURE;
    }

    private function checkConfig(): array
    {
        $failures = [];

        // Broadcasting driver must be 'reverb' on server
        $driver = config('broadcasting.default');
        if ($driver !== 'reverb' && app()->environment('production', 'staging')) {
            $failures[] = "BROADCAST_CONNECTION is '{$driver}', expected 'reverb'";
        }

        // Reverb app credentials must not be empty
        foreach (['key', 'secret', 'app_id'] as $field) {
            if (empty(config("broadcasting.connections.reverb.{$field}"))) {
                $failures[] = "broadcasting.connections.reverb.{$field} is empty";
            }
        }

        // Host/port must be set
        $host = config('broadcasting.connections.reverb.options.host');
        $port = config('broadcasting.connections.reverb.options.port');
        if (empty($host)) {
            $failures[] = "Reverb host is empty (REVERB_HOST not set?)";
        }
        if (empty($port) || $port === 0) {
            $failures[] = "Reverb port is empty or 0 (REVERB_PORT not set?)";
        }

        // allowed_origins must be array (the bug that caused this post-mortem)
        $origins = config('reverb.apps.apps.0.allowed_origins');
        if (!is_array($origins)) {
            $type = gettype($origins);
            $failures[] = "allowed_origins is {$type}, must be array — Reverb will crash on every broadcast";
        }

        // Reverb app key must match broadcasting key
        $reverbKey = config('reverb.apps.apps.0.key');
        $broadcastKey = config('broadcasting.connections.reverb.key');
        if ($reverbKey !== $broadcastKey) {
            $failures[] = "Key mismatch: reverb.apps key '{$reverbKey}' ≠ broadcasting key '{$broadcastKey}'";
        }

        if (empty($failures)) {
            $this->line('  ✓ Config valid');
        }

        return $failures;
    }

    private function checkReverbServer(): array
    {
        $host = config('broadcasting.connections.reverb.options.host');
        $port = config('broadcasting.connections.reverb.options.port');

        if (empty($host) || empty($port)) {
            return ['Cannot check server — host/port not configured'];
        }

        try {
            $response = Http::timeout(3)->get("http://{$host}:{$port}/");
            // Reverb returns 404 on root — that's fine, it means it's running
            if ($response->status() === 404) {
                $this->line("  ✓ Reverb responding on {$host}:{$port}");
                return [];
            }
            return ["Unexpected status {$response->status()} from Reverb at {$host}:{$port}"];
        } catch (\Throwable $e) {
            return ["Cannot reach Reverb at {$host}:{$port}: {$e->getMessage()}"];
        }
    }

    private function checkBroadcast(): array
    {
        try {
            // Direct event() call — bypasses SafelyBroadcasts circuit breaker
            event(new \App\Events\ScoreboardEvent(0, 0, [
                'event' => 'health.check',
                'timestamp' => now()->toISOString(),
            ]));
            $this->line('  ✓ Broadcast successful');
            return [];
        } catch (\Throwable $e) {
            return ["Broadcast failed: {$e->getMessage()}"];
        }
    }

    private function checkCircuitBreaker(): array
    {
        $breaker = new CircuitBreaker(service: 'reverb');
        $status = $breaker->getStatus();
        $failures = [];

        if ($status['state'] !== 'closed') {
            $msg = "Circuit breaker is {$status['state']} (failures: {$status['failures']})";

            if ($this->option('fix')) {
                $breaker->reset();
                $this->line("  ⚡ Circuit breaker was {$status['state']} — auto-reset applied");
            } else {
                $failures[] = "{$msg} — run with --fix to reset";
            }
        } else {
            $this->line("  ✓ Circuit breaker closed (failures: {$status['failures']})");
        }

        return $failures;
    }
}
