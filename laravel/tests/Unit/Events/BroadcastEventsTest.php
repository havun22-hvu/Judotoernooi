<?php

namespace Tests\Unit\Events;

use App\Events\MatUpdate;
use App\Events\ScoreboardAssignment;
use App\Events\ScoreboardEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Coverage voor 3 ShouldBroadcastNow events: payload-shape, kanaal-naam,
 * broadcastAs identifier. Constructor-side-effect (heartbeat cache) checken.
 */
class BroadcastEventsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    #[Test]
    public function mat_update_writes_heartbeat_to_cache_with_15min_ttl(): void
    {
        new MatUpdate(toernooiId: 42, matId: 1, type: 'score', data: []);

        $this->assertTrue(Cache::get('toernooi:42:heartbeat_active'));
    }

    #[Test]
    public function mat_update_broadcasts_on_mat_and_toernooi_channels(): void
    {
        $event = new MatUpdate(7, 3, 'beurt', []);

        $channels = collect($event->broadcastOn())->map(fn (Channel $c) => $c->name)->all();

        $this->assertContains('mat.7.3', $channels);
        $this->assertContains('toernooi.7', $channels);
    }

    #[Test]
    public function mat_update_broadcast_payload_has_required_shape(): void
    {
        $event = new MatUpdate(7, 3, 'score', ['score' => 'ippon']);

        $payload = $event->broadcastWith();

        $this->assertSame(7, $payload['toernooi_id']);
        $this->assertSame(3, $payload['mat_id']);
        $this->assertSame('score', $payload['type']);
        $this->assertSame(['score' => 'ippon'], $payload['data']);
        $this->assertArrayHasKey('timestamp', $payload);
    }

    #[Test]
    public function mat_update_broadcast_name_is_mat_dot_update(): void
    {
        $event = new MatUpdate(1, 1, 'score', []);

        $this->assertSame('mat.update', $event->broadcastAs());
    }

    #[Test]
    public function scoreboard_event_broadcasts_on_display_channel(): void
    {
        $event = new ScoreboardEvent(11, 2, ['event' => 'timer.start']);

        $channels = collect($event->broadcastOn())->map(fn (Channel $c) => $c->name)->all();

        $this->assertSame(['scoreboard-display.11.2'], $channels);
    }

    #[Test]
    public function scoreboard_event_payload_lifts_event_field_to_top_level(): void
    {
        $event = new ScoreboardEvent(11, 2, ['event' => 'score.update', 'value' => 'wazari']);

        $payload = $event->broadcastWith();

        $this->assertSame('score.update', $payload['event'],
            'Display app filtert op `event`-key — moet aan de top staan, niet alleen in `data`.');
        $this->assertSame(['event' => 'score.update', 'value' => 'wazari'], $payload['data']);
    }

    #[Test]
    public function scoreboard_event_broadcast_name(): void
    {
        $this->assertSame('scoreboard.event',
            (new ScoreboardEvent(1, 1, ['event' => 'x']))->broadcastAs());
    }

    #[Test]
    public function scoreboard_assignment_broadcasts_on_assignment_channel(): void
    {
        $event = new ScoreboardAssignment(99, 5, ['judoka1' => 'A', 'judoka2' => 'B']);

        $channels = collect($event->broadcastOn())->map(fn (Channel $c) => $c->name)->all();

        $this->assertSame(['scoreboard.99.5'], $channels);
    }

    #[Test]
    public function scoreboard_assignment_payload_includes_match_object(): void
    {
        $match = ['judoka1' => 'Hans', 'judoka2' => 'Piet'];

        $payload = (new ScoreboardAssignment(99, 5, $match))->broadcastWith();

        $this->assertSame(99, $payload['toernooi_id']);
        $this->assertSame(5, $payload['mat_id']);
        $this->assertSame($match, $payload['match']);
        $this->assertArrayHasKey('timestamp', $payload);
    }

    #[Test]
    public function scoreboard_assignment_broadcast_name(): void
    {
        $this->assertSame('scoreboard.assignment',
            (new ScoreboardAssignment(1, 1, []))->broadcastAs());
    }
}
