<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\CheckRolSessie;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Session\Store;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CheckRolSessieTest extends TestCase
{
    private function requestWithSession(array $sessionData): Request
    {
        $session = $this->app->make(Store::class);
        foreach ($sessionData as $k => $v) {
            $session->put($k, $v);
        }

        $request = Request::create('/');
        $request->setLaravelSession($session);

        return $request;
    }

    #[Test]
    public function passes_when_both_rol_and_toernooi_id_in_session(): void
    {
        $request = $this->requestWithSession([
            'rol_toernooi_id' => 42,
            'rol_type' => 'jurytafel',
        ]);

        $response = (new CheckRolSessie)->handle($request, fn () => new Response('ok'));

        $this->assertSame('ok', $response->getContent());
    }

    #[Test]
    public function aborts_403_when_toernooi_id_missing(): void
    {
        $request = $this->requestWithSession(['rol_type' => 'jurytafel']);

        try {
            (new CheckRolSessie)->handle($request, fn () => new Response('ok'));
            $this->fail('Verwacht HttpException');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    #[Test]
    public function aborts_403_when_rol_type_missing(): void
    {
        $request = $this->requestWithSession(['rol_toernooi_id' => 42]);

        try {
            (new CheckRolSessie)->handle($request, fn () => new Response('ok'));
            $this->fail('Verwacht HttpException');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    #[Test]
    public function aborts_403_when_session_is_empty(): void
    {
        try {
            (new CheckRolSessie)->handle($this->requestWithSession([]), fn () => new Response('ok'));
            $this->fail('Verwacht HttpException');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
