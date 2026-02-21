<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckFreemiumPrint;
use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckFreemiumPrintTest extends TestCase
{
    use RefreshDatabase;

    private function runMiddleware(Toernooi $toernooi): \Symfony\Component\HttpFoundation\Response
    {
        $middleware = new CheckFreemiumPrint();

        $request = Request::create('/test');
        $request->setRouteResolver(function () use ($toernooi) {
            $route = new \Illuminate\Routing\Route('GET', '/test/{toernooi}', fn() => 'ok');
            $route->bind($request ?? Request::create('/test'));
            $route->setParameter('toernooi', $toernooi);
            return $route;
        });

        return $middleware->handle($request, function () {
            return new Response('ok', 200);
        });
    }

    // =========================================================================
    // MIDDLEWARE BEHAVIOR
    // =========================================================================

    #[Test]
    public function free_tier_is_blocked_from_print(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'plan_type' => 'free',
        ]);

        $response = $this->runMiddleware($toernooi);

        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function paid_tier_can_access_print(): void
    {
        $org = Organisator::factory()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'plan_type' => 'paid',
            'paid_max_judokas' => 100,
        ]);

        $response = $this->runMiddleware($toernooi);

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function wimpel_abo_can_access_print(): void
    {
        $org = Organisator::factory()->wimpelAbo()->create();
        $toernooi = Toernooi::factory()->create([
            'organisator_id' => $org->id,
            'plan_type' => 'wimpel_abo',
        ]);

        $response = $this->runMiddleware($toernooi);

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function middleware_passes_without_toernooi(): void
    {
        $middleware = new CheckFreemiumPrint();

        $request = Request::create('/test');
        $request->setRouteResolver(function () use ($request) {
            $route = new \Illuminate\Routing\Route('GET', '/test', fn() => 'ok');
            $route->bind($request);
            return $route;
        });

        $response = $middleware->handle($request, function () {
            return new Response('ok', 200);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }
}
