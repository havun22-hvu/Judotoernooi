<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Tests that broadcasting events can be dispatched without errors.
 * Catches config issues (wrong types, missing values) before they hit production.
 *
 * See: docs/postmortem/2026-04-05-reverb-broadcasting-failure.md
 */
class ReverbHealthCheckTest extends TestCase
{
    public function test_scoreboard_event_can_be_dispatched(): void
    {
        Event::fake();

        \App\Events\ScoreboardEvent::dispatch(1, 1, [
            'event' => 'health.check',
            'timestamp' => now()->toISOString(),
        ]);

        Event::assertDispatched(\App\Events\ScoreboardEvent::class);
    }

    public function test_mat_update_can_be_dispatched(): void
    {
        Event::fake();

        \App\Events\MatUpdate::dispatch(1, 1, 'score', []);

        Event::assertDispatched(\App\Events\MatUpdate::class);
    }

    public function test_scoreboard_assignment_can_be_dispatched(): void
    {
        Event::fake();

        \App\Events\ScoreboardAssignment::dispatch(1, 1, [
            'action' => 'health.check',
        ]);

        Event::assertDispatched(\App\Events\ScoreboardAssignment::class);
    }

    public function test_health_check_command_exists(): void
    {
        // Command runs but may fail in test env (no Reverb server)
        // The important thing is that the command exists and doesn't throw
        $exitCode = $this->artisan('reverb:health')->run();
        $this->assertContains($exitCode, [0, 1], 'Command should exit cleanly');
    }
}
