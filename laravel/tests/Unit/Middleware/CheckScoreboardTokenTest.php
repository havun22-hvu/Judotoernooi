<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\CheckScoreboardToken;
use App\Models\DeviceToegang;
use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

/**
 * Guards the Bearer-token gate in front of the scoreboard API.
 * Unlike the session-based device-binding, this middleware lets a
 * scoreboard-device (TV / LCD on the mat) poll the API without a
 * cookie — it presents a `api_token` instead.
 *
 * A regression here has two failure modes:
 *   - accepting a non-scoreboard token (rol != 'scoreboard'/'mat')
 *     → random volunteers could fetch live match data
 *   - accepting an invalid token without the Bearer-header check
 *     → scraping becomes possible
 */
class CheckScoreboardTokenTest extends TestCase
{
    use RefreshDatabase;

    private function toegang(string $rol = 'scoreboard', string $apiToken = 'sb-token-xyz'): DeviceToegang
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $org->id]);

        return DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'naam' => 'Scoreboard 1',
            'rol' => $rol,
            'api_token' => $apiToken,
        ]);
    }

    private function bearer(string $token): Request
    {
        return Request::create('/api/scoreboard/state', 'GET', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);
    }

    public function test_missing_bearer_token_returns_401(): void
    {
        $response = (new CheckScoreboardToken)->handle(
            Request::create('/api/scoreboard/state'),
            fn () => throw new \RuntimeException('next() must not be called'),
        );

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Token ontbreekt.', $response->getData(true)['message']);
    }

    public function test_unknown_token_returns_401(): void
    {
        $this->toegang(rol: 'scoreboard', apiToken: 'real-token');

        $response = (new CheckScoreboardToken)->handle(
            $this->bearer('wrong-token'),
            fn () => throw new \RuntimeException('next() must not be called'),
        );

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Ongeldig token.', $response->getData(true)['message']);
    }

    public function test_token_for_unauthorized_role_returns_401(): void
    {
        // Token bestaat en is geldig, maar rol is geen scoreboard/mat.
        $this->toegang(rol: 'weging', apiToken: 'weger-token');

        $response = (new CheckScoreboardToken)->handle(
            $this->bearer('weger-token'),
            fn () => throw new \RuntimeException('next() must not be called'),
        );

        $this->assertSame(
            401,
            $response->getStatusCode(),
            'Tokens for non-scoreboard roles (weging/spreker/etc.) must NOT grant scoreboard-API access'
        );
    }

    public function test_valid_scoreboard_token_forwards_and_merges_toegang(): void
    {
        $toegang = $this->toegang(rol: 'scoreboard', apiToken: 'valid-sb');
        $called = false;

        $response = (new CheckScoreboardToken)->handle(
            $this->bearer('valid-sb'),
            function (Request $r) use (&$called, $toegang) {
                $called = true;
                $this->assertSame($toegang->id, $r->get('device_toegang')->id);

                return new Response('ok');
            },
        );

        $this->assertTrue($called);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_mat_role_token_is_also_accepted(): void
    {
        // Both 'scoreboard' and 'mat' rollen may use the scoreboard API.
        $toegang = $this->toegang(rol: 'mat', apiToken: 'mat-token');
        $called = false;

        (new CheckScoreboardToken)->handle(
            $this->bearer('mat-token'),
            function () use (&$called) {
                $called = true;

                return new Response('ok');
            },
        );

        $this->assertTrue($called, 'mat-role tokens must also be allowed on the scoreboard API');
    }
}
