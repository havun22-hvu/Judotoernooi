<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\ObservabilityMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ObservabilityMiddlewareTest extends TestCase
{
    private function shouldLog(ObservabilityMiddleware $mw, Request $request): bool
    {
        $reflection = new \ReflectionClass($mw);
        $method = $reflection->getMethod('shouldLog');
        $method->setAccessible(true);

        return $method->invoke($mw, $request);
    }

    #[Test]
    public function should_log_returns_false_when_disabled(): void
    {
        config(['observability.enabled' => false]);

        $this->assertFalse(
            $this->shouldLog(new ObservabilityMiddleware, Request::create('/foo'))
        );
    }

    #[Test]
    public function should_log_returns_true_when_enabled_and_path_not_excluded(): void
    {
        config([
            'observability.enabled' => true,
            'observability.sampling_rate' => 1.0,
            'observability.excluded_paths' => [],
        ]);

        $this->assertTrue(
            $this->shouldLog(new ObservabilityMiddleware, Request::create('/api/x'))
        );
    }

    #[Test]
    public function should_log_returns_false_for_excluded_paths(): void
    {
        config([
            'observability.enabled' => true,
            'observability.sampling_rate' => 1.0,
            'observability.excluded_paths' => ['_debugbar/*', 'health'],
        ]);

        $this->assertFalse($this->shouldLog(new ObservabilityMiddleware, Request::create('/_debugbar/log')));
        $this->assertFalse($this->shouldLog(new ObservabilityMiddleware, Request::create('/health')));
        $this->assertTrue($this->shouldLog(new ObservabilityMiddleware, Request::create('/api/something')));
    }

    #[Test]
    public function should_log_with_zero_sampling_rate_returns_false(): void
    {
        config([
            'observability.enabled' => true,
            'observability.sampling_rate' => 0.0,
            'observability.excluded_paths' => [],
        ]);

        // Met sampling 0.0 mag het nooit doorlaten — over 100 calls willen we
        // 100% afwijzingen
        $mw = new ObservabilityMiddleware;
        $request = Request::create('/x');
        for ($i = 0; $i < 100; $i++) {
            $this->assertFalse($this->shouldLog($mw, $request));
        }
    }

    #[Test]
    public function handle_swallows_db_errors_and_returns_response(): void
    {
        config([
            'observability.enabled' => true,
            'observability.sampling_rate' => 1.0,
            'observability.excluded_paths' => [],
            // Bewust ongeldige connectie → DB::insert gooit, middleware moet dat
            // stilletjes opvangen (catch \Throwable)
            'database.connections.havuncore' => [
                'driver' => 'sqlite',
                'database' => '/nonexistent/path.sqlite',
            ],
        ]);

        $mw = new ObservabilityMiddleware;
        $response = $mw->handle(Request::create('/x'), fn () => new Response('ok'));

        $this->assertSame('ok', $response->getContent(),
            'Middleware-failure mag user-response NOOIT breken.');
    }
}
