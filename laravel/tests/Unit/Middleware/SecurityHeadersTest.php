<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    private function runMiddleware(?string $env = 'testing', bool $secure = false): \Symfony\Component\HttpFoundation\Response
    {
        if ($env !== null) {
            $this->app->detectEnvironment(fn () => $env);
        }

        $request = Request::create('/', 'GET');
        if ($secure) {
            $request->server->set('HTTPS', 'on');
        }

        return (new SecurityHeaders)->handle($request, fn () => new Response('ok'));
    }

    #[Test]
    public function sets_clickjacking_and_mime_and_xss_and_referrer_headers(): void
    {
        $response = $this->runMiddleware();

        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('1; mode=block', $response->headers->get('X-XSS-Protection'));
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
    }

    #[Test]
    public function sets_permissions_policy_with_camera_allowed_microphone_denied(): void
    {
        $policy = $this->runMiddleware()->headers->get('Permissions-Policy');

        $this->assertStringContainsString('camera=(self)', $policy);
        $this->assertStringContainsString('microphone=()', $policy);
        $this->assertStringContainsString('geolocation=()', $policy);
        $this->assertStringContainsString('payment=()', $policy);
    }

    #[Test]
    public function generates_csp_nonce_and_binds_it_in_container(): void
    {
        $this->runMiddleware();

        $nonce = $this->app['csp-nonce'];
        $this->assertNotEmpty($nonce);
        $this->assertSame(24, strlen($nonce), 'base64(16 bytes) = 24 tekens.');
    }

    #[Test]
    public function nonce_is_unique_per_request(): void
    {
        $this->runMiddleware();
        $first = $this->app['csp-nonce'];

        $this->runMiddleware();
        $second = $this->app['csp-nonce'];

        $this->assertNotSame($first, $second);
    }

    #[Test]
    public function csp_header_is_set_in_non_local_environment(): void
    {
        $csp = $this->runMiddleware(env: 'testing')->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp);
        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringContainsString("'nonce-", $csp);
    }

    #[Test]
    public function csp_header_is_omitted_in_local_environment(): void
    {
        $csp = $this->runMiddleware(env: 'local')->headers->get('Content-Security-Policy');

        $this->assertNull($csp, 'Local env skipt CSP om dev-tools niet te breken.');
    }

    #[Test]
    public function hsts_header_only_set_in_production_over_https(): void
    {
        // testing env, geen HTTPS → geen HSTS
        $this->assertNull($this->runMiddleware(env: 'testing', secure: false)
            ->headers->get('Strict-Transport-Security'));

        // production + HTTPS → wel HSTS
        $hsts = $this->runMiddleware(env: 'production', secure: true)
            ->headers->get('Strict-Transport-Security');
        $this->assertNotNull($hsts);
        $this->assertStringContainsString('max-age=31536000', $hsts);
        $this->assertStringContainsString('includeSubDomains', $hsts);
        $this->assertStringContainsString('preload', $hsts);

        // production maar geen HTTPS → geen HSTS (voorkomt mixed-content lock-out)
        $this->assertNull($this->runMiddleware(env: 'production', secure: false)
            ->headers->get('Strict-Transport-Security'));
    }

    #[Test]
    public function strips_x_powered_by_and_server_headers(): void
    {
        $response = new Response('ok');
        $response->headers->set('X-Powered-By', 'PHP/8.2');
        $response->headers->set('Server', 'nginx/1.20');

        $this->app->detectEnvironment(fn () => 'testing');
        $result = (new SecurityHeaders)->handle(Request::create('/'), fn () => $response);

        $this->assertFalse($result->headers->has('X-Powered-By'));
        $this->assertFalse($result->headers->has('Server'));
    }
}
