<?php

namespace Tests\Unit\Support;

use App\Support\Result;
use Tests\TestCase;

class ResultTest extends TestCase
{
    /** @test */
    public function it_creates_success_result(): void
    {
        $result = Result::success('data');

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isFailure());
        $this->assertEquals('data', $result->getValue());
        $this->assertNull($result->getError());
    }

    /** @test */
    public function it_creates_failure_result(): void
    {
        $result = Result::failure('error message');

        $this->assertTrue($result->isFailure());
        $this->assertFalse($result->isSuccess());
        $this->assertEquals('error message', $result->getError());
        $this->assertNull($result->getValueOr(null)); // Use getValueOr for failures
    }

    /** @test */
    public function it_maps_success_value(): void
    {
        $result = Result::success(5)
            ->map(fn($x) => $x * 2);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(10, $result->getValue());
    }

    /** @test */
    public function it_does_not_map_failure(): void
    {
        $result = Result::failure('error')
            ->map(fn($x) => $x * 2);

        $this->assertTrue($result->isFailure());
        $this->assertEquals('error', $result->getError());
    }

    /** @test */
    public function it_flat_maps_success(): void
    {
        $result = Result::success(5)
            ->flatMap(fn($x) => Result::success($x * 2));

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(10, $result->getValue());
    }

    /** @test */
    public function it_flat_maps_to_failure(): void
    {
        $result = Result::success(5)
            ->flatMap(fn($x) => Result::failure('failed'));

        $this->assertTrue($result->isFailure());
        $this->assertEquals('failed', $result->getError());
    }

    /** @test */
    public function it_returns_value_or_default(): void
    {
        $success = Result::success('value');
        $failure = Result::failure('error');

        $this->assertEquals('value', $success->getValueOr('default'));
        $this->assertEquals('default', $failure->getValueOr('default'));
    }

    /** @test */
    public function it_converts_success_to_json_response(): void
    {
        $result = Result::success(['name' => 'test']);
        $response = $result->toResponse();

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals(['name' => 'test'], $data['data']);
    }

    /** @test */
    public function it_converts_failure_to_json_response(): void
    {
        $result = Result::failure('something went wrong');
        $response = $result->toResponse();

        $this->assertEquals(400, $response->getStatusCode()); // Default failure code

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('something went wrong', $data['error']);
    }

    /** @test */
    public function it_chains_multiple_operations(): void
    {
        $result = Result::success(2)
            ->map(fn($x) => $x + 3)     // 5
            ->map(fn($x) => $x * 2)     // 10
            ->map(fn($x) => "Result: $x");

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Result: 10', $result->getValue());
    }

    /** @test */
    public function it_stops_chain_on_failure(): void
    {
        $called = false;

        $result = Result::success(5)
            ->flatMap(fn($x) => Result::failure('error'))
            ->map(function ($x) use (&$called) {
                $called = true;
                return $x * 2;
            });

        $this->assertTrue($result->isFailure());
        $this->assertFalse($called);
    }
}
