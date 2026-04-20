<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\TrackResponseTime;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TrackResponseTimeTest extends TestCase
{
    #[Test]
    public function fast_response_does_not_log(): void
    {
        Log::spy();

        $response = (new TrackResponseTime)->handle(
            Request::create('/x'),
            fn () => new Response('ok')
        );

        $this->assertSame('ok', $response->getContent());
        Log::shouldNotHaveReceived('channel');
    }

    #[Test]
    public function slow_response_above_1s_triggers_warning_log(): void
    {
        $logged = false;
        Log::shouldReceive('channel')
            ->with('response-time')
            ->once()
            ->andReturnSelf();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($msg, $ctx) use (&$logged) {
                $logged = true;
                return $msg === 'Slow response'
                    && $ctx['path'] === 'slow'
                    && $ctx['method'] === 'GET'
                    && $ctx['response_time_ms'] > 1000;
            });

        (new TrackResponseTime)->handle(
            Request::create('/slow'),
            function () {
                usleep(1_010_000); // 1.01s
                return new Response('ok');
            }
        );

        $this->assertTrue($logged, 'Slow-response warning had to be emitted.');
    }

    #[Test]
    public function response_is_returned_unchanged(): void
    {
        $expected = new Response('payload', 201);

        $actual = (new TrackResponseTime)->handle(Request::create('/x'), fn () => $expected);

        $this->assertSame($expected, $actual);
    }
}
