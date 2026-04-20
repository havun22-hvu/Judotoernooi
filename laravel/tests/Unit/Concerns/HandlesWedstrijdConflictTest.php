<?php

namespace Tests\Unit\Concerns;

use App\Http\Controllers\Concerns\HandlesWedstrijdConflict;
use App\Models\Wedstrijd;
use Illuminate\Http\JsonResponse;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HandlesWedstrijdConflictTest extends TestCase
{
    private object $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = new class
        {
            use HandlesWedstrijdConflict {
                checkConflict as public publicCheckConflict;
            }
        };
    }

    private function fakeWedstrijd(?\Carbon\Carbon $serverUpdatedAt): Wedstrijd
    {
        $wedstrijd = Mockery::mock(Wedstrijd::class)->makePartial();
        $wedstrijd->updated_at = $serverUpdatedAt;
        return $wedstrijd;
    }

    #[Test]
    public function returns_null_when_no_client_timestamp_provided(): void
    {
        $wedstrijd = $this->fakeWedstrijd(now());

        $this->assertNull($this->sut->publicCheckConflict($wedstrijd, null));
    }

    #[Test]
    public function returns_null_when_server_has_no_updated_at(): void
    {
        $this->assertNull(
            $this->sut->publicCheckConflict($this->fakeWedstrijd(null), '2026-04-20T12:00:00Z')
        );
    }

    #[Test]
    public function returns_null_when_client_and_server_are_in_sync(): void
    {
        $now = now();
        $wedstrijd = $this->fakeWedstrijd($now);

        $this->assertNull(
            $this->sut->publicCheckConflict($wedstrijd, $now->toISOString())
        );
    }

    #[Test]
    public function returns_409_conflict_when_server_is_newer_than_client_plus_tolerance(): void
    {
        $serverTime = now();
        $clientTime = $serverTime->copy()->subSeconds(5);

        $response = $this->sut->publicCheckConflict(
            $this->fakeWedstrijd($serverTime),
            $clientTime->toISOString()
        );

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(409, $response->getStatusCode());

        $payload = $response->getData(true);
        $this->assertFalse($payload['success']);
        $this->assertTrue($payload['conflict']);
        $this->assertArrayHasKey('server_updated_at', $payload);
    }

    #[Test]
    public function tolerates_one_second_clock_drift(): void
    {
        // Server slechts 0.5s nieuwer → binnen tolerantie, geen conflict
        $serverTime = now();
        $clientTime = $serverTime->copy()->subMillisecond(500);

        $this->assertNull(
            $this->sut->publicCheckConflict(
                $this->fakeWedstrijd($serverTime),
                $clientTime->toISOString()
            ),
            'Klok-drift tot 1s mag geen 409 triggeren — anders crashen mat-clients onnodig.'
        );
    }
}
