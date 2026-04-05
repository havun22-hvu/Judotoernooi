<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Heartbeat event that broadcasts full mat state every second.
 * Received by publiek PWA to keep live matten display current.
 */
class MatHeartbeat implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels, Concerns\SafelyBroadcasts {
        Concerns\SafelyBroadcasts::dispatch insteadof Dispatchable;
    }

    public int $toernooiId;
    public array $matten;

    public function __construct(int $toernooiId, array $matten)
    {
        $this->toernooiId = $toernooiId;
        $this->matten = $matten;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel("toernooi.{$this->toernooiId}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'toernooi_id' => $this->toernooiId,
            'matten' => $this->matten,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'mat.heartbeat';
    }
}
