<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

/**
 * Guard against the config bugs that caused the 2026-04-05 outage.
 * See: docs/postmortem/2026-04-05-reverb-broadcasting-failure.md
 */
class ReverbConfigTest extends TestCase
{
    public function test_allowed_origins_is_array(): void
    {
        $origins = config('reverb.apps.apps.0.allowed_origins');

        $this->assertIsArray(
            $origins,
            'allowed_origins must be array — Reverb 1.7+ crashes with TypeError if string. '
            . 'Use explode() around env() in config/reverb.php'
        );
    }

    public function test_reverb_app_key_is_set(): void
    {
        $this->assertNotEmpty(
            config('reverb.apps.apps.0.key'),
            'REVERB_APP_KEY must be set — Reverb cannot authenticate without it'
        );
    }

    public function test_reverb_app_secret_is_set(): void
    {
        $this->assertNotEmpty(
            config('reverb.apps.apps.0.secret'),
            'REVERB_APP_SECRET must be set — broadcast auth will fail'
        );
    }

    public function test_reverb_app_id_is_set(): void
    {
        $this->assertNotEmpty(
            config('reverb.apps.apps.0.app_id'),
            'REVERB_APP_ID must be set — Reverb cannot match applications'
        );
    }

    public function test_broadcasting_key_matches_reverb_key(): void
    {
        $broadcastKey = config('broadcasting.connections.reverb.key');
        $reverbKey = config('reverb.apps.apps.0.key');

        $this->assertEquals(
            $broadcastKey,
            $reverbKey,
            'Broadcasting key and Reverb app key must match — mismatched keys cause silent auth failures'
        );
    }

    public function test_broadcasting_secret_matches_reverb_secret(): void
    {
        $broadcastSecret = config('broadcasting.connections.reverb.secret');
        $reverbSecret = config('reverb.apps.apps.0.secret');

        $this->assertEquals(
            $broadcastSecret,
            $reverbSecret,
            'Broadcasting secret and Reverb app secret must match'
        );
    }

    public function test_broadcast_driver_not_null_when_reverb_configured(): void
    {
        // If reverb key is set, the driver should be reverb (not null)
        if (empty(config('reverb.apps.apps.0.key'))) {
            $this->markTestSkipped('No Reverb key configured');
        }

        $driver = config('broadcasting.default');

        $this->assertNotEquals(
            'null',
            $driver,
            'BROADCAST_CONNECTION should not be "null" when Reverb is configured — events will be silently dropped'
        );
    }

    public function test_reverb_port_is_valid(): void
    {
        $port = config('reverb.apps.apps.0.options.port');

        $this->assertNotNull($port, 'Reverb port must not be null');
        $this->assertIsInt((int) $port, 'Reverb port must be numeric');
        $this->assertGreaterThan(0, (int) $port, 'Reverb port must be > 0 (was the bug: env() returning null → port 0)');
    }

    public function test_reverb_scheme_is_valid(): void
    {
        $scheme = config('reverb.apps.apps.0.options.scheme');

        $this->assertNotNull($scheme, 'Reverb scheme must not be null');
        $this->assertContains($scheme, ['http', 'https'], 'Reverb scheme must be http or https');
    }
}
