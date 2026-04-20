<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\CheckFreemiumPrint;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckFreemiumPrintTest extends TestCase
{
    private function requestWithToernooi($toernooi): Request
    {
        $request = Request::create('/print');
        $route = new Route(['GET'], '/print/{toernooi}', []);
        $route->bind($request);
        $route->setParameter('toernooi', $toernooi);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    #[Test]
    public function passes_through_when_no_toernooi_parameter(): void
    {
        $request = Request::create('/no-route-binding');
        $route = new Route(['GET'], '/x', []);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        $response = (new CheckFreemiumPrint)->handle($request, fn () => new Response('ok'));

        $this->assertSame('ok', $response->getContent());
    }

    #[Test]
    public function passes_through_when_toernooi_is_not_free_tier(): void
    {
        $toernooi = Mockery::mock();
        $toernooi->shouldReceive('isFreeTier')->andReturn(false);

        $response = (new CheckFreemiumPrint)->handle(
            $this->requestWithToernooi($toernooi),
            fn () => new Response('paid-tier-content')
        );

        $this->assertSame('paid-tier-content', $response->getContent());
    }

    #[Test]
    public function blocks_next_handler_when_toernooi_is_free_tier(): void
    {
        // De upgrade-required view koppelt aan layouts die DB-queries doen
        // (chat widgets etc.). We mocken response()->view() om alleen het
        // branch-gedrag te testen — riep middleware next() WEL/NIET aan?
        $factory = Mockery::mock(\Illuminate\Contracts\Routing\ResponseFactory::class);
        $factory->shouldReceive('view')
            ->andReturn(new Response('upgrade-required-stub', 403));
        $this->app->instance(\Illuminate\Contracts\Routing\ResponseFactory::class, $factory);

        $toernooi = Mockery::mock();
        $toernooi->shouldReceive('isFreeTier')->andReturn(true);

        $nextCalled = false;
        $response = (new CheckFreemiumPrint)->handle(
            $this->requestWithToernooi($toernooi),
            function () use (&$nextCalled) {
                $nextCalled = true;
                return new Response('should-not-appear');
            }
        );

        $this->assertFalse($nextCalled, 'Free-tier toernooi mag NIET de print-route raken.');
        $this->assertSame(403, $response->getStatusCode());
    }
}
