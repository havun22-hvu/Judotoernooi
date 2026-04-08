<?php

namespace Tests\Unit;

use App\Http\Middleware\CheckDeviceBinding;
use App\Http\Middleware\CheckScoreboardToken;
use App\Http\Middleware\CheckToernooiRol;
use App\Http\Middleware\LocalSyncAuth;
use App\Http\Middleware\OfflineMode;
use App\Http\Middleware\SetLocale;
use App\Models\DeviceToegang;
use App\Models\Judoka;
use App\Models\Organisator;
use App\Models\SyncQueueItem;
use App\Models\Toernooi;
use App\Models\Wedstrijd;
use App\Observers\SyncQueueObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MiddlewareObserverCoverageTest extends TestCase
{
    use RefreshDatabase;

    // ========================================================================
    // CheckDeviceBinding
    // ========================================================================

    #[Test]
    public function check_device_binding_returns_error_when_no_toegang_id_in_route(): void
    {
        $middleware = new CheckDeviceBinding();
        $request = Request::create('/test');
        $route = new \Illuminate\Routing\Route('GET', '/test', fn () => null);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        $response = $middleware->handle($request, fn ($r) => new Response('ok'));

        $this->assertEquals(404, $response->getStatusCode());
    }

    #[Test]
    public function check_device_binding_returns_error_when_toegang_not_found(): void
    {
        $middleware = new CheckDeviceBinding();
        $request = Request::create('/test');

        // Create a route that returns a non-existent ID
        $route = new \Illuminate\Routing\Route('GET', '/test/{toegang}', fn () => null);
        $route->bind($request);
        $route->setParameter('toegang', 99999);
        $request->setRouteResolver(fn () => $route);

        $response = $middleware->handle($request, fn ($r) => new Response('ok'));

        $this->assertEquals(404, $response->getStatusCode());
    }

    #[Test]
    public function check_device_binding_returns_error_when_role_mismatch(): void
    {
        $toernooi = Toernooi::factory()->create();
        $toegang = DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'rol' => 'weging',
            'naam' => 'Test',
        ]);

        $middleware = new CheckDeviceBinding();
        $request = Request::create('/test');
        $route = new \Illuminate\Routing\Route('GET', '/test/{toegang}', fn () => null);
        $route->bind($request);
        $route->setParameter('toegang', $toegang->id);
        $request->setRouteResolver(fn () => $route);

        $response = $middleware->handle($request, fn ($r) => new Response('ok'), 'mat');

        $this->assertEquals(404, $response->getStatusCode());
    }

    #[Test]
    public function check_device_binding_redirects_when_no_device_token(): void
    {
        $toernooi = Toernooi::factory()->create();
        $toegang = DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'rol' => 'mat',
            'naam' => 'Test',
            'device_token' => 'abc123',
        ]);

        $middleware = new CheckDeviceBinding();
        $request = Request::create('/test');
        $route = new \Illuminate\Routing\Route('GET', '/test/{toegang}', fn () => null);
        $route->bind($request);
        $route->setParameter('toegang', $toegang->id);
        $request->setRouteResolver(fn () => $route);

        $response = $middleware->handle($request, fn ($r) => new Response('ok'), 'mat');

        $this->assertTrue($response->isRedirection());
    }

    #[Test]
    public function check_device_binding_passes_when_device_token_matches(): void
    {
        $toernooi = Toernooi::factory()->create();
        $toegang = DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'rol' => 'mat',
            'naam' => 'Test',
            'device_token' => 'valid-token-123',
        ]);

        $middleware = new CheckDeviceBinding();
        $request = Request::create('/test');
        $request->cookies->set('device_token_' . $toegang->id, 'valid-token-123');
        $route = new \Illuminate\Routing\Route('GET', '/test/{toegang}', fn () => null);
        $route->bind($request);
        $route->setParameter('toegang', $toegang->id);
        $request->setRouteResolver(fn () => $route);

        $response = $middleware->handle($request, fn ($r) => new Response('ok'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('ok', $response->getContent());
    }

    // ========================================================================
    // CheckScoreboardToken
    // ========================================================================

    #[Test]
    public function check_scoreboard_token_returns_401_when_no_token(): void
    {
        $middleware = new CheckScoreboardToken();
        $request = Request::create('/api/test');

        $response = $middleware->handle($request, fn ($r) => new Response('ok'));

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('Token ontbreekt', $response->getContent());
    }

    #[Test]
    public function check_scoreboard_token_returns_401_when_invalid_token(): void
    {
        $middleware = new CheckScoreboardToken();
        $request = Request::create('/api/test');
        $request->headers->set('Authorization', 'Bearer invalid-token');

        $response = $middleware->handle($request, fn ($r) => new Response('ok'));

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('Ongeldig token', $response->getContent());
    }

    #[Test]
    public function check_scoreboard_token_returns_401_when_token_has_wrong_role(): void
    {
        $toernooi = Toernooi::factory()->create();
        $toegang = DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'rol' => 'weging',
            'naam' => 'Test',
            'api_token' => 'weging-token-123',
        ]);

        $middleware = new CheckScoreboardToken();
        $request = Request::create('/api/test');
        $request->headers->set('Authorization', 'Bearer weging-token-123');

        $response = $middleware->handle($request, fn ($r) => new Response('ok'));

        $this->assertEquals(401, $response->getStatusCode());
    }

    #[Test]
    public function check_scoreboard_token_passes_with_valid_mat_token_for_scoreboard(): void
    {
        $toernooi = Toernooi::factory()->create();
        // Use 'mat' role which is in the allowed enum AND accepted by CheckScoreboardToken
        $toegang = DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'rol' => 'mat',
            'naam' => 'Mat Scoreboard',
            'api_token' => 'valid-scoreboard-token',
        ]);

        $middleware = new CheckScoreboardToken();
        $request = Request::create('/api/test');
        $request->headers->set('Authorization', 'Bearer valid-scoreboard-token');

        $response = $middleware->handle($request, fn ($r) => new Response('ok'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotNull($request->get('device_toegang'));
    }

    #[Test]
    public function check_scoreboard_token_passes_with_valid_mat_token(): void
    {
        $toernooi = Toernooi::factory()->create();
        $toegang = DeviceToegang::create([
            'toernooi_id' => $toernooi->id,
            'rol' => 'mat',
            'naam' => 'Mat 1',
            'api_token' => 'valid-mat-token',
        ]);

        $middleware = new CheckScoreboardToken();
        $request = Request::create('/api/test');
        $request->headers->set('Authorization', 'Bearer valid-mat-token');

        $response = $middleware->handle($request, fn ($r) => new Response('ok'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    // ========================================================================
    // CheckToernooiRol
    // ========================================================================

    #[Test]
    public function check_toernooi_rol_skips_auth_in_offline_mode(): void
    {
        Config::set('app.offline_mode', true);

        $middleware = new CheckToernooiRol();
        $request = Request::create('/test');
        $request->setLaravelSession(app('session.store'));

        $response = $middleware->handle($request, fn ($r) => new Response('ok'), 'admin');

        $this->assertEquals(200, $response->getStatusCode());

        Config::set('app.offline_mode', false);
    }

    #[Test]
    public function check_toernooi_rol_redirects_when_toernooi_not_found(): void
    {
        Config::set('app.offline_mode', false);

        $middleware = new CheckToernooiRol();
        $request = Request::create('/test');
        $request->setLaravelSession(app('session.store'));
        $route = new \Illuminate\Routing\Route('GET', '/test/{toernooi}', fn () => null);
        $route->bind($request);
        $route->setParameter('toernooi', 'non-existent-slug');
        $request->setRouteResolver(fn () => $route);

        $response = $middleware->handle($request, fn ($r) => new Response('ok'), 'admin');

        $this->assertTrue($response->isRedirection());
    }

    #[Test]
    public function check_toernooi_rol_returns_json_when_toernooi_not_found_and_expects_json(): void
    {
        Config::set('app.offline_mode', false);

        $middleware = new CheckToernooiRol();
        $request = Request::create('/test', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $request->setLaravelSession(app('session.store'));
        $route = new \Illuminate\Routing\Route('GET', '/test/{toernooi}', fn () => null);
        $route->bind($request);
        $route->setParameter('toernooi', 'non-existent-slug');
        $request->setRouteResolver(fn () => $route);

        $response = $middleware->handle($request, fn ($r) => new Response('ok'), 'admin');

        $this->assertEquals(404, $response->getStatusCode());
    }

    #[Test]
    public function check_toernooi_rol_allows_authenticated_organisator_with_access(): void
    {
        Config::set('app.offline_mode', false);

        $organisator = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create(['organisator_id' => $organisator->id]);
        // Attach via pivot so hasAccessToToernooi returns true
        $organisator->toernooien()->attach($toernooi->id, ['rol' => 'eigenaar']);

        $this->actingAs($organisator, 'organisator');

        $middleware = new CheckToernooiRol();
        $request = Request::create('/test');
        $request->setLaravelSession(app('session.store'));
        $route = new \Illuminate\Routing\Route('GET', '/test/{toernooi}', fn () => null);
        $route->bind($request);
        $route->setParameter('toernooi', $toernooi);
        $request->setRouteResolver(fn () => $route);

        $response = $middleware->handle($request, fn ($r) => new Response('ok'), 'admin');

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function check_toernooi_rol_returns_401_json_when_not_logged_in(): void
    {
        Config::set('app.offline_mode', false);

        $toernooi = Toernooi::factory()->create();

        $middleware = new CheckToernooiRol();
        $request = Request::create('/test', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $request->setLaravelSession(app('session.store'));
        $route = new \Illuminate\Routing\Route('GET', '/test/{toernooi}', fn () => null);
        $route->bind($request);
        $route->setParameter('toernooi', $toernooi);
        $request->setRouteResolver(fn () => $route);

        $response = $middleware->handle($request, fn ($r) => new Response('ok'), 'admin');

        $this->assertEquals(401, $response->getStatusCode());
    }

    #[Test]
    public function check_toernooi_rol_redirects_when_not_logged_in_html(): void
    {
        Config::set('app.offline_mode', false);

        $toernooi = Toernooi::factory()->create();

        $middleware = new CheckToernooiRol();
        $request = Request::create('/test');
        $request->setLaravelSession(app('session.store'));
        $route = new \Illuminate\Routing\Route('GET', '/test/{toernooi}', fn () => null);
        $route->bind($request);
        $route->setParameter('toernooi', $toernooi);
        $request->setRouteResolver(fn () => $route);

        $response = $middleware->handle($request, fn ($r) => new Response('ok'), 'admin');

        $this->assertTrue($response->isRedirection());
    }

    #[Test]
    public function check_toernooi_rol_admin_session_has_full_access(): void
    {
        Config::set('app.offline_mode', false);

        $toernooi = Toernooi::factory()->create();

        $middleware = new CheckToernooiRol();
        $request = Request::create('/test');
        $session = app('session.store');
        $session->put("toernooi_{$toernooi->id}_rol", 'admin');
        $request->setLaravelSession($session);

        $route = new \Illuminate\Routing\Route('GET', '/test/{toernooi}', fn () => null);
        $route->bind($request);
        $route->setParameter('toernooi', $toernooi);
        $request->setRouteResolver(fn () => $route);

        $response = $middleware->handle($request, fn ($r) => new Response('ok'), 'jury');

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function check_toernooi_rol_denies_wrong_role_json(): void
    {
        Config::set('app.offline_mode', false);

        $toernooi = Toernooi::factory()->create();

        $middleware = new CheckToernooiRol();
        $request = Request::create('/test', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $session = app('session.store');
        $session->put("toernooi_{$toernooi->id}_rol", 'weging');
        $request->setLaravelSession($session);

        $route = new \Illuminate\Routing\Route('GET', '/test/{toernooi}', fn () => null);
        $route->bind($request);
        $route->setParameter('toernooi', $toernooi);
        $request->setRouteResolver(fn () => $route);

        $response = $middleware->handle($request, fn ($r) => new Response('ok'), 'admin', 'jury');

        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function check_toernooi_rol_denies_wrong_role_html(): void
    {
        Config::set('app.offline_mode', false);

        $toernooi = Toernooi::factory()->create();

        $middleware = new CheckToernooiRol();
        $request = Request::create('/test');
        $session = app('session.store');
        $session->put("toernooi_{$toernooi->id}_rol", 'weging');
        $request->setLaravelSession($session);

        $route = new \Illuminate\Routing\Route('GET', '/test/{toernooi}', fn () => null);
        $route->bind($request);
        $route->setParameter('toernooi', $toernooi);
        $request->setRouteResolver(fn () => $route);

        $response = $middleware->handle($request, fn ($r) => new Response('ok'), 'admin', 'jury');

        $this->assertTrue($response->isRedirection());
    }

    #[Test]
    public function check_toernooi_rol_allows_matching_role(): void
    {
        Config::set('app.offline_mode', false);

        $toernooi = Toernooi::factory()->create();

        $middleware = new CheckToernooiRol();
        $request = Request::create('/test');
        $session = app('session.store');
        $session->put("toernooi_{$toernooi->id}_rol", 'jury');
        $request->setLaravelSession($session);

        $route = new \Illuminate\Routing\Route('GET', '/test/{toernooi}', fn () => null);
        $route->bind($request);
        $route->setParameter('toernooi', $toernooi);
        $request->setRouteResolver(fn () => $route);

        $response = $middleware->handle($request, fn ($r) => new Response('ok'), 'jury', 'mat');

        $this->assertEquals(200, $response->getStatusCode());
    }

    // ========================================================================
    // LocalSyncAuth
    // ========================================================================

    #[Test]
    public function local_sync_auth_allows_in_offline_mode(): void
    {
        Config::set('app.offline_mode', true);

        $middleware = new LocalSyncAuth();
        $request = Request::create('/api/sync');

        $response = $middleware->handle($request, fn ($r) => new Response('ok'));

        $this->assertEquals(200, $response->getStatusCode());

        Config::set('app.offline_mode', false);
    }

    #[Test]
    public function local_sync_auth_allows_private_ip(): void
    {
        Config::set('app.offline_mode', false);

        $middleware = new LocalSyncAuth();
        $request = Request::create('/api/sync', 'GET', [], [], [], ['REMOTE_ADDR' => '192.168.1.100']);

        $response = $middleware->handle($request, fn ($r) => new Response('ok'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function local_sync_auth_allows_localhost(): void
    {
        Config::set('app.offline_mode', false);

        $middleware = new LocalSyncAuth();
        $request = Request::create('/api/sync', 'GET', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);

        $response = $middleware->handle($request, fn ($r) => new Response('ok'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function local_sync_auth_allows_valid_bearer_token(): void
    {
        Config::set('app.offline_mode', false);
        Config::set('local-server.sync_token', 'my-secret-token');

        $middleware = new LocalSyncAuth();
        $request = Request::create('/api/sync', 'GET', [], [], [], ['REMOTE_ADDR' => '8.8.8.8']);
        $request->headers->set('Authorization', 'Bearer my-secret-token');

        $response = $middleware->handle($request, fn ($r) => new Response('ok'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function local_sync_auth_denies_public_ip_without_token(): void
    {
        Config::set('app.offline_mode', false);
        Config::set('local-server.sync_token', null);

        $middleware = new LocalSyncAuth();
        $request = Request::create('/api/sync', 'GET', [], [], [], ['REMOTE_ADDR' => '8.8.8.8']);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $middleware->handle($request, fn ($r) => new Response('ok'));
    }

    #[Test]
    public function local_sync_auth_denies_wrong_bearer_token(): void
    {
        Config::set('app.offline_mode', false);
        Config::set('local-server.sync_token', 'correct-token');

        $middleware = new LocalSyncAuth();
        $request = Request::create('/api/sync', 'GET', [], [], [], ['REMOTE_ADDR' => '8.8.8.8']);
        $request->headers->set('Authorization', 'Bearer wrong-token');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $middleware->handle($request, fn ($r) => new Response('ok'));
    }

    #[Test]
    public function local_sync_auth_allows_10_x_private_ip(): void
    {
        Config::set('app.offline_mode', false);

        $middleware = new LocalSyncAuth();
        $request = Request::create('/api/sync', 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.0.5']);

        $response = $middleware->handle($request, fn ($r) => new Response('ok'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function local_sync_auth_allows_172_16_private_ip(): void
    {
        Config::set('app.offline_mode', false);

        $middleware = new LocalSyncAuth();
        $request = Request::create('/api/sync', 'GET', [], [], [], ['REMOTE_ADDR' => '172.16.0.1']);

        $response = $middleware->handle($request, fn ($r) => new Response('ok'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function local_sync_auth_allows_ipv6_localhost(): void
    {
        Config::set('app.offline_mode', false);

        $middleware = new LocalSyncAuth();
        $request = Request::create('/api/sync', 'GET', [], [], [], ['REMOTE_ADDR' => '::1']);

        $response = $middleware->handle($request, fn ($r) => new Response('ok'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    // ========================================================================
    // OfflineMode
    // ========================================================================

    #[Test]
    public function offline_mode_passes_through_when_not_offline(): void
    {
        Config::set('app.offline_mode', false);

        $middleware = new OfflineMode();
        $request = Request::create('/test');

        $response = $middleware->handle($request, fn ($r) => new Response('ok'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function offline_mode_shares_variables_when_offline(): void
    {
        Config::set('app.offline_mode', true);
        Config::set('app.offline_toernooi_id', 42);

        $middleware = new OfflineMode();
        $request = Request::create('/test');

        $response = $middleware->handle($request, fn ($r) => new Response('ok'));

        $this->assertEquals(200, $response->getStatusCode());

        // Check that view shared data was set
        $shared = view()->getShared();
        $this->assertTrue($shared['offlineMode']);
        $this->assertEquals(42, $shared['offlineToernooiId']);

        Config::set('app.offline_mode', false);
    }

    #[Test]
    public function offline_mode_is_offline_static_method(): void
    {
        Config::set('app.offline_mode', false);
        $this->assertFalse(OfflineMode::isOffline());

        Config::set('app.offline_mode', true);
        $this->assertTrue(OfflineMode::isOffline());

        Config::set('app.offline_mode', false);
    }

    // ========================================================================
    // SetLocale
    // ========================================================================

    #[Test]
    public function set_locale_uses_session_locale(): void
    {
        $middleware = new SetLocale();
        $request = Request::create('/test');
        $session = app('session.store');
        $session->put('locale', 'en');
        $request->setLaravelSession($session);
        $request->setRouteResolver(fn () => null);

        $middleware->handle($request, fn ($r) => new Response('ok'));

        $this->assertEquals('en', app()->getLocale());
    }

    #[Test]
    public function set_locale_falls_back_to_default_when_no_context(): void
    {
        $defaultLocale = config('app.locale');

        $middleware = new SetLocale();
        $request = Request::create('/test');
        $session = app('session.store');
        $request->setLaravelSession($session);

        // Create route with no relevant name
        $route = new \Illuminate\Routing\Route('GET', '/test', fn () => null);
        $route->name('some.random.route');
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        $middleware->handle($request, fn ($r) => new Response('ok'));

        $this->assertEquals($defaultLocale, app()->getLocale());
    }

    #[Test]
    public function set_locale_detects_from_toernooi_route(): void
    {
        $toernooi = Toernooi::factory()->create(['locale' => 'en']);

        $middleware = new SetLocale();
        $request = Request::create('/test');
        $session = app('session.store');
        $request->setLaravelSession($session);

        $route = new \Illuminate\Routing\Route('GET', '/test/{toernooi}', fn () => null);
        $route->name('toernooi.dashboard');
        $route->bind($request);
        $route->setParameter('toernooi', $toernooi);
        $request->setRouteResolver(fn () => $route);

        $middleware->handle($request, fn ($r) => new Response('ok'));

        $this->assertEquals('en', app()->getLocale());
        $this->assertEquals('en', $session->get('locale'));
    }

    #[Test]
    public function set_locale_detects_from_organisator_guard(): void
    {
        $organisator = Organisator::factory()->create(['locale' => 'en']);
        $this->actingAs($organisator, 'organisator');

        $middleware = new SetLocale();
        $request = Request::create('/test');
        $session = app('session.store');
        $request->setLaravelSession($session);

        $route = new \Illuminate\Routing\Route('GET', '/dashboard', fn () => null);
        $route->name('dashboard');
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        $middleware->handle($request, fn ($r) => new Response('ok'));

        $this->assertEquals('en', app()->getLocale());
    }

    #[Test]
    public function set_locale_ignores_invalid_locale(): void
    {
        $defaultLocale = config('app.locale');

        $middleware = new SetLocale();
        $request = Request::create('/test');
        $session = app('session.store');
        $session->put('locale', 'xx');
        $request->setLaravelSession($session);
        $request->setRouteResolver(fn () => null);

        $middleware->handle($request, fn ($r) => new Response('ok'));

        $this->assertEquals($defaultLocale, app()->getLocale());
    }

    // ========================================================================
    // SyncQueueObserver
    // ========================================================================

    #[Test]
    public function sync_queue_observer_created_does_nothing(): void
    {
        $observer = new SyncQueueObserver();
        $wedstrijd = new Wedstrijd();

        // Should not throw or create anything
        $observer->created($wedstrijd);

        $this->assertEquals(0, SyncQueueItem::count());
    }

    #[Test]
    public function sync_queue_observer_updated_skips_on_cloud_server(): void
    {
        Config::set('local-server.role', 'cloud');

        $observer = new SyncQueueObserver();
        $toernooi = Toernooi::factory()->create();
        $wedstrijd = Wedstrijd::factory()->create([
            'poule_id' => \App\Models\Poule::factory()->create(['toernooi_id' => $toernooi->id])->id,
        ]);

        // Simulate an update
        $wedstrijd->score_wit = 10;
        $wedstrijd->save();

        $observer->updated($wedstrijd);

        $this->assertEquals(0, SyncQueueItem::count());
    }

    #[Test]
    public function sync_queue_observer_updated_skips_irrelevant_fields(): void
    {
        Config::set('local-server.role', 'primary');

        $observer = new SyncQueueObserver();
        $toernooi = Toernooi::factory()->create();
        $wedstrijd = Wedstrijd::factory()->create([
            'poule_id' => \App\Models\Poule::factory()->create(['toernooi_id' => $toernooi->id])->id,
        ]);

        // Change a non-sync field
        $wedstrijd->volgorde = 99;
        $wedstrijd->save();

        $observer->updated($wedstrijd);

        $this->assertEquals(0, SyncQueueItem::count());

        Config::set('local-server.role', null);
    }

    #[Test]
    public function sync_queue_observer_updated_queues_relevant_wedstrijd_changes(): void
    {
        Config::set('local-server.role', 'primary');

        $toernooi = Toernooi::factory()->create();
        $poule = \App\Models\Poule::factory()->create(['toernooi_id' => $toernooi->id]);
        $wedstrijd = Wedstrijd::factory()->create(['poule_id' => $poule->id]);

        $countBefore = SyncQueueItem::count();

        // Change a sync-relevant field - observer fires automatically via model event
        $wedstrijd->score_wit = 10;
        $wedstrijd->save();

        $this->assertEquals($countBefore + 1, SyncQueueItem::count());
        $this->assertEquals('update', SyncQueueItem::latest('id')->first()->action);

        Config::set('local-server.role', null);
    }

    #[Test]
    public function sync_queue_observer_updated_queues_relevant_judoka_changes(): void
    {
        Config::set('local-server.role', 'primary');

        $judoka = Judoka::factory()->create();

        $countBefore = SyncQueueItem::count();

        // Change a sync-relevant field - observer fires automatically
        $judoka->aanwezigheid = 'aanwezig';
        $judoka->save();

        $this->assertEquals($countBefore + 1, SyncQueueItem::count());

        Config::set('local-server.role', null);
    }

    #[Test]
    public function sync_queue_observer_deleted_queues_on_local_server(): void
    {
        Config::set('local-server.role', 'primary');

        $observer = new SyncQueueObserver();
        $toernooi = Toernooi::factory()->create();
        $poule = \App\Models\Poule::factory()->create(['toernooi_id' => $toernooi->id]);
        $wedstrijd = Wedstrijd::factory()->create(['poule_id' => $poule->id]);

        $observer->deleted($wedstrijd);

        $this->assertEquals(1, SyncQueueItem::count());
        $this->assertEquals('delete', SyncQueueItem::first()->action);

        Config::set('local-server.role', null);
    }

    #[Test]
    public function sync_queue_observer_deleted_skips_on_cloud_server(): void
    {
        Config::set('local-server.role', 'cloud');

        $observer = new SyncQueueObserver();
        $wedstrijd = new Wedstrijd(['id' => 1]);

        $observer->deleted($wedstrijd);

        $this->assertEquals(0, SyncQueueItem::count());
    }

    #[Test]
    public function sync_queue_observer_updated_logs_warning_on_exception(): void
    {
        Config::set('local-server.role', 'standby');

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'Failed to queue sync item');
            });

        $observer = new SyncQueueObserver();

        // Create a Wedstrijd without a poule (so toernooi_id can't be determined)
        $wedstrijd = new Wedstrijd(['id' => 999]);
        $wedstrijd->exists = true;
        // Simulate wasChanged returning true by syncing original and changing
        $wedstrijd->syncOriginal();
        $wedstrijd->score_wit = 5;
        $wedstrijd->syncChanges();

        $observer->updated($wedstrijd);
    }

    #[Test]
    public function sync_queue_observer_deleted_logs_warning_on_exception(): void
    {
        Config::set('local-server.role', 'primary');

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'Failed to queue sync delete');
            });

        $observer = new SyncQueueObserver();

        // Model without toernooi_id will throw
        $wedstrijd = new Wedstrijd(['id' => 888]);
        $wedstrijd->exists = true;

        $observer->deleted($wedstrijd);
    }
}
