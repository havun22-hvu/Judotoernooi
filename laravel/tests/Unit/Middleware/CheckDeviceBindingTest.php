<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\CheckDeviceBinding;
use App\Models\DeviceToegang;
use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Tests\TestCase;

/**
 * Guards the device-binding middleware — the gate that keeps a
 * volunteer's DeviceToegang link tied to the specific phone/tablet
 * it was first opened on. A regression here lets a lost/shared link
 * impersonate a jury-member on another device.
 *
 * The middleware is used on public non-login routes (dojo, weging,
 * scoreboard, spreker) so the failure modes are:
 *
 *   1. Missing toegang-id → friendly 404 error page (NOT a login page:
 *      volunteers have no login).
 *   2. Unknown toegang-id → friendly 404.
 *   3. Role mismatch (middleware called with required role + wrong
 *      toegang->rol) → friendly 404.
 *   4. Cookie missing / mismatches device_token → redirect to
 *      /toegang/{code} for re-binding (NOT 403 — flow must resume).
 *   5. Valid → next(), laatst_actief updated, device_toegang merged
 *      into request.
 */
class CheckDeviceBindingTest extends TestCase
{
    use RefreshDatabase;

    private function requestWithToegang(?int $toegangId, array $cookies = []): Request
    {
        $request = Request::create('/whatever', 'GET', [], $cookies);
        $route = new Route(['GET'], '/whatever', fn () => 'ok');
        $route->parameters = ['toegang' => $toegangId];
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    private function makeToegang(string $rol = 'mat'): DeviceToegang
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);

        return DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'naam' => 'Test Vrijwilliger',
            'rol' => $rol,
            'device_token' => 'bound-token-123',
        ]);
    }

    public function test_missing_toegang_returns_404(): void
    {
        $request = $this->requestWithToegang(null);

        $response = (new CheckDeviceBinding)->handle(
            $request,
            fn () => throw new \RuntimeException('next() must not be called'),
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_unknown_toegang_id_returns_404(): void
    {
        $request = $this->requestWithToegang(999999);

        $response = (new CheckDeviceBinding)->handle(
            $request,
            fn () => throw new \RuntimeException('next() must not be called'),
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_role_mismatch_returns_404_and_does_not_forward(): void
    {
        $toegang = $this->makeToegang('mat');
        $request = $this->requestWithToegang($toegang->id);

        // Middleware called with required rol='weging' but toegang has 'mat'.
        $response = (new CheckDeviceBinding)->handle(
            $request,
            fn () => throw new \RuntimeException('next() must not be called'),
            'weging'
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_missing_cookie_redirects_to_pin_entry(): void
    {
        $toegang = $this->makeToegang('mat');
        $request = $this->requestWithToegang($toegang->id); // no cookie

        $response = (new CheckDeviceBinding)->handle(
            $request,
            fn () => throw new \RuntimeException('next() must not be called on missing cookie'),
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString($toegang->code, (string) $response->headers->get('location'));
    }

    public function test_wrong_cookie_redirects_to_pin_entry(): void
    {
        $toegang = $this->makeToegang('mat');
        $request = $this->requestWithToegang(
            $toegang->id,
            ['device_token_' . $toegang->id => 'totally-different-token']
        );

        $response = (new CheckDeviceBinding)->handle(
            $request,
            fn () => throw new \RuntimeException('next() must not be called on cookie mismatch'),
        );

        $this->assertSame(302, $response->getStatusCode());
    }

    public function test_matching_cookie_calls_next_and_merges_toegang(): void
    {
        $toegang = $this->makeToegang('mat');
        $request = $this->requestWithToegang(
            $toegang->id,
            ['device_token_' . $toegang->id => 'bound-token-123']
        );

        $called = false;
        $response = (new CheckDeviceBinding)->handle(
            $request,
            function (Request $r) use (&$called, $toegang) {
                $called = true;
                $this->assertInstanceOf(DeviceToegang::class, $r->get('device_toegang'));
                $this->assertSame($toegang->id, $r->get('device_toegang')->id);

                return new Response('ok');
            },
        );

        $this->assertTrue($called, 'next() must be invoked when the cookie matches the bound device_token');
        $this->assertSame(200, $response->getStatusCode());
    }
}
