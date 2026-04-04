<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event-based sync from scoreboard bediening to web display.
 * Only fires on state changes (timer.start, score.update, etc.),
 * not continuously. Display runs its own timer locally.
 */
class ScoreboardEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    use Concerns\SafelyBroadcasts;

    public function __construct(
        public int $toernooiId,
        public int $matId,
        public array $eventData,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("scoreboard-display.{$this->toernooiId}.{$this->matId}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'toernooi_id' => $this->toernooiId,
            'mat_id' => $this->matId,
            'event' => $this->eventData['event'],
            'data' => $this->eventData,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'scoreboard.event';
    }
}
