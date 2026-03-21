<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast live scoreboard state (timer, scores, shido, osaekomi)
 * from the Control view to the Display view via server relay.
 */
class ScoreboardState implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $toernooiId,
        public int $matId,
        public array $state,
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
            'state' => $this->state,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'scoreboard.state';
    }
}
