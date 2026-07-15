<?php

namespace Tests\Feature\Api;

use App\Events\ScoreboardEvent;
use App\Models\Blok;
use App\Models\DeviceToegang;
use App\Models\Judoka;
use App\Models\Mat;
use App\Models\Poule;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Security regression tests for the JudoScoreBoard API.
 *
 * Pins two real vulnerabilities found in the 2026-07-15 review:
 *  1. POST /result accepted any wedstrijd_id, so a token for tournament A could
 *     overwrite results in tournament B (cross-tenant write).
 *  2. POST /event broadcast $request->all() — which carried the middleware-merged
 *     DeviceToegang model, leaking api_token/code/email on a PUBLIC Reverb channel.
 */
class ScoreboardApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a tournament with one mat, one match on it, and a bound scoreboard device.
     *
     * @return array{toernooi: Toernooi, mat: Mat, wedstrijd: Wedstrijd, toegang: DeviceToegang, token: string}
     */
    private function maakToernooiMetScorebord(int $matNummer = 1): array
    {
        $toernooi = Toernooi::factory()->create();
        $mat = Mat::factory()->create(['toernooi_id' => $toernooi->id, 'nummer' => $matNummer]);
        $blok = Blok::factory()->create(['toernooi_id' => $toernooi->id]);
        $poule = Poule::factory()->create([
            'toernooi_id' => $toernooi->id,
            'blok_id' => $blok->id,
            'mat_id' => $mat->id,
        ]);

        $wit = Judoka::factory()->create(['toernooi_id' => $toernooi->id]);
        $blauw = Judoka::factory()->create(['toernooi_id' => $toernooi->id]);
        $wedstrijd = Wedstrijd::factory()->create([
            'poule_id' => $poule->id,
            'judoka_wit_id' => $wit->id,
            'judoka_blauw_id' => $blauw->id,
            'is_gespeeld' => false,
        ]);

        $token = DeviceToegang::generateDeviceToken();
        $toegang = DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'naam' => 'Tafel',
            'telefoon' => '0600000000',
            'email' => 'tafel@example.test',
            'rol' => 'scoreboard',
            'mat_nummer' => $matNummer,
            'api_token' => $token,
        ]);

        return compact('toernooi', 'mat', 'wedstrijd', 'toegang', 'token');
    }

    public function test_result_rejects_a_match_from_another_tournament(): void
    {
        $eigen = $this->maakToernooiMetScorebord();
        $vreemd = $this->maakToernooiMetScorebord(2);

        $slachtoffer = $vreemd['wedstrijd'];

        $response = $this->withHeader('Authorization', 'Bearer ' . $eigen['token'])
            ->postJson('/api/scoreboard/result', [
                'wedstrijd_id' => $slachtoffer->id,
                'winnaar_id' => $slachtoffer->judoka_wit_id,
                'uitslag_type' => 'ippon',
            ]);

        $response->assertNotFound();

        $this->assertDatabaseHas('wedstrijden', [
            'id' => $slachtoffer->id,
            'is_gespeeld' => false,
            'winnaar_id' => null,
        ]);
    }

    public function test_result_still_accepts_a_match_from_the_own_tournament(): void
    {
        $eigen = $this->maakToernooiMetScorebord();
        $wedstrijd = $eigen['wedstrijd'];

        $this->withHeader('Authorization', 'Bearer ' . $eigen['token'])
            ->postJson('/api/scoreboard/result', [
                'wedstrijd_id' => $wedstrijd->id,
                'winnaar_id' => $wedstrijd->judoka_wit_id,
                'uitslag_type' => 'ippon',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('wedstrijden', [
            'id' => $wedstrijd->id,
            'is_gespeeld' => true,
            'winnaar_id' => $wedstrijd->judoka_wit_id,
        ]);
    }

    public function test_event_broadcast_never_carries_device_credentials(): void
    {
        Event::fake([ScoreboardEvent::class]);

        $eigen = $this->maakToernooiMetScorebord();
        $toegang = $eigen['toegang'];

        $this->withHeader('Authorization', 'Bearer ' . $eigen['token'])
            ->postJson('/api/scoreboard/event', ['event' => 'timer.start', 'remaining' => 240])
            ->assertOk();

        Event::assertDispatched(ScoreboardEvent::class, function (ScoreboardEvent $event) use ($eigen, $toegang) {
            $payload = json_encode($event->broadcastWith());

            $this->assertStringNotContainsString($eigen['token'], $payload, 'api_token leaked into the public broadcast');
            $this->assertStringNotContainsString($toegang->code, $payload, 'access code leaked into the public broadcast');
            $this->assertStringNotContainsString($toegang->email, $payload, 'volunteer e-mail leaked into the public broadcast');
            $this->assertStringNotContainsString('device_toegang', $payload, 'device record leaked into the public broadcast');

            // The functional payload must survive the fix.
            $this->assertSame('timer.start', $event->broadcastWith()['event']);
            $this->assertSame(240, $event->broadcastWith()['data']['remaining']);

            return true;
        });
    }

    /**
     * Resetting a mat used to leave api_token intact, so a "reset" device kept full
     * write access with the Bearer token it already held.
     */
    public function test_reset_revokes_the_api_token(): void
    {
        $eigen = $this->maakToernooiMetScorebord();

        $this->withHeader('Authorization', 'Bearer ' . $eigen['token'])
            ->postJson('/api/scoreboard/heartbeat')
            ->assertOk();

        $eigen['toegang']->reset();

        $this->withHeader('Authorization', 'Bearer ' . $eigen['token'])
            ->postJson('/api/scoreboard/heartbeat')
            ->assertUnauthorized();
    }

    /**
     * A reset must withdraw access for real: the old code may not be tradeable for a
     * new token, or anyone who wrote it down simply walks back in.
     */
    public function test_reset_issues_a_new_code_and_kills_the_old_one(): void
    {
        $eigen = $this->maakToernooiMetScorebord();
        $oudeCode = $eigen['toegang']->code;

        $nieuweCode = $eigen['toegang']->reset();

        $this->assertNotSame($oudeCode, $nieuweCode);

        $this->postJson('/api/scoreboard/auth', ['code' => $oudeCode])->assertUnauthorized();

        $response = $this->postJson('/api/scoreboard/auth', ['code' => $nieuweCode])->assertOk();
        $this->assertNotSame($eigen['token'], $response->json('token'));

        $this->withHeader('Authorization', 'Bearer ' . $response->json('token'))
            ->postJson('/api/scoreboard/heartbeat')
            ->assertOk();
    }

    public function test_device_record_hides_credentials_when_serialised(): void
    {
        $toegang = $this->maakToernooiMetScorebord()['toegang'];

        $array = $toegang->toArray();

        $this->assertArrayNotHasKey('api_token', $array);
        $this->assertArrayNotHasKey('device_token', $array);
        $this->assertArrayNotHasKey('code', $array);
        $this->assertArrayHasKey('rol', $array);
    }

    public function test_protected_routes_carry_the_scoreboard_throttle(): void
    {
        foreach (['current-match', 'result', 'event', 'heartbeat', 'tv-link'] as $naam) {
            $middleware = Route::getRoutes()->getByName("api.scoreboard.{$naam}")->gatherMiddleware();

            $this->assertContains('throttle:scoreboard', $middleware, "Route {$naam} is not throttled");
            $this->assertContains('scoreboard.token', $middleware, "Route {$naam} is not authenticated");
        }
    }

    /**
     * Keying on the token instead of the IP is deliberate: every mat in a sports hall
     * shares one NAT IP, so an IP-keyed limit would take a whole tournament down.
     */
    public function test_scoreboard_limiter_is_keyed_per_token_not_per_ip(): void
    {
        $limiter = RateLimiter::limiter('scoreboard');
        $this->assertNotNull($limiter, "The 'scoreboard' rate limiter is not registered");

        $maakRequest = function (?string $token): Request {
            $request = Request::create('/api/scoreboard/heartbeat', 'POST');
            $request->server->set('REMOTE_ADDR', '198.51.100.7');
            if ($token !== null) {
                $request->headers->set('Authorization', 'Bearer ' . $token);
            }

            return $request;
        };

        $eersteMat = $limiter($maakRequest('token-mat-een'));
        $tweedeMat = $limiter($maakRequest('token-mat-twee'));

        $this->assertSame(120, $eersteMat->maxAttempts);
        $this->assertNotSame(
            $eersteMat->key,
            $tweedeMat->key,
            'Two devices behind the same hall IP must not share a rate-limit budget'
        );

        // Without a token there is nothing to key on, so fall back to the IP.
        $this->assertSame('198.51.100.7', $limiter($maakRequest(null))->key);
    }
}
