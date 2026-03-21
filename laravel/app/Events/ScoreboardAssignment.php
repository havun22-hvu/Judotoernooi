<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a match is set as active (green) on a mat.
 * Scoreboard app listens on this channel to receive match assignments.
 */
class ScoreboardAssignment implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $toernooiId,
        public int $matId,
        public array $match,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("scoreboard.{$this->toernooiId}.{$this->matId}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'toernooi_id' => $this->toernooiId,
            'mat_id' => $this->matId,
            'match' => $this->match,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'scoreboard.assignment';
    }
}
