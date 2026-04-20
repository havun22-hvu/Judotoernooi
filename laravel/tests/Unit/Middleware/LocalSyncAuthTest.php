<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\LocalSyncAuth;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

/**
 * Guards the three-way gate in front of the local-sync routes. A
 * misconfiguration here has two distinct failure modes:
 *   - false-positive (internet-facing prod accepts sync traffic) →
 *     anyone can push fake wedstrijd-data from the open web
 *   - false-negative (LAN during toernooidag gets blocked) →
 *     scoreboard/mat-devices can't sync scores mid-tournament
 *
 * The 3 allow-paths must be independently verifiable.
 */
class LocalSyncAuthTest extends TestCase
{
    private function requestFromIp(?string $ip, ?string $bearer = null): Request
    {
        $server = [];
        if ($ip !== null) {
            $server['REMOTE_ADDR'] = $ip;
        }
        if ($bearer !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $bearer;
        }

        return Request::create('/sync/whatever', 'POST', server: $server);
    }

    protected function tearDown(): void
    {
        config(['app.offline_mode' => false]);
        config(['local-server.sync_token' => null]);
        parent::tearDown();
    }

    public function test_offline_mode_allows_everything_including_public_ip(): void
    {
        config(['app.offline_mode' => true]);

        $called = false;
        (new LocalSyncAuth)->handle(
            $this->requestFromIp('8.8.8.8'), // public IP
            function () use (&$called) {
                $called = true;

                return new Response('ok');
            },
        );

        $this->assertTrue($called, 'offline_mode must short-circuit every other check');
    }

    public function test_private_ip_192_is_allowed(): void
    {
        $called = false;
        (new LocalSyncAuth)->handle(
            $this->requestFromIp('192.168.1.42'),
            function () use (&$called) {
                $called = true;

                return new Response('ok');
            },
        );

        $this->assertTrue($called);
    }

    public function test_private_ip_10_is_allowed(): void
    {
        $called = false;
        (new LocalSyncAuth)->handle(
            $this->requestFromIp('10.0.0.5'),
            function () use (&$called) {
                $called = true;

                return new Response('ok');
            },
        );

        $this->assertTrue($called);
    }

    public function test_loopback_ipv4_is_allowed(): void
    {
        $called = false;
        (new LocalSyncAuth)->handle(
            $this->requestFromIp('127.0.0.1'),
            function () use (&$called) {
                $called = true;

                return new Response('ok');
            },
        );

        $this->assertTrue($called);
    }

    public function test_public_ip_without_bearer_token_aborts_403(): void
    {
        config(['local-server.sync_token' => 'real-shared-secret']);

        $response = null;
        try {
            (new LocalSyncAuth)->handle(
                $this->requestFromIp('8.8.8.8'),
                fn () => throw new \RuntimeException('next() must not be called'),
            );
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $response = $e;
        }

        $this->assertNotNull(
            $response,
            'A public IP without a bearer token must trigger abort(403)'
        );
        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_public_ip_with_valid_bearer_token_is_allowed(): void
    {
        config(['local-server.sync_token' => 'shared-secret-xyz']);

        $called = false;
        (new LocalSyncAuth)->handle(
            $this->requestFromIp('8.8.8.8', bearer: 'shared-secret-xyz'),
            function () use (&$called) {
                $called = true;

                return new Response('ok');
            },
        );

        $this->assertTrue(
            $called,
            'A matching bearer token must unlock remote sync access'
        );
    }

    public function test_public_ip_with_wrong_bearer_token_aborts_403(): void
    {
        config(['local-server.sync_token' => 'real-secret']);

        $aborted = false;
        try {
            (new LocalSyncAuth)->handle(
                $this->requestFromIp('8.8.8.8', bearer: 'wrong-secret'),
                fn () => throw new \RuntimeException('next() must not be called'),
            );
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
            $aborted = true;
        }

        $this->assertTrue($aborted, 'Wrong bearer token must NOT grant access');
    }
}
