<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Real-time mat updates for scores, beurten (groen/geel/blauw), and poule status.
 * Broadcasts to jurytafel, publiek, and spreker interfaces.
 */
class MatUpdate implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $toernooiId;
    public int $matId;
    public string $type;
    public array $data;

    /**
     * Create a new event instance.
     *
     * @param int $toernooiId
     * @param int $matId
     * @param string $type Type of update: 'score', 'beurt', 'poule_klaar', 'wedstrijd_actief'
     * @param array $data The update data
     */
    public function __construct(int $toernooiId, int $matId, string $type, array $data)
    {
        $this->toernooiId = $toernooiId;
        $this->matId = $matId;
        $this->type = $type;
        $this->data = $data;
    }

    /**
     * Get the channels the event should broadcast on.
     * Using public channels - no auth needed for toernooi updates
     */
    public function broadcastOn(): array
    {
        return [
            // Mat-specific channel (for jurytafel filtering)
            new Channel("mat.{$this->toernooiId}.{$this->matId}"),
            // Toernooi-wide channel (for publiek/spreker)
            new Channel("toernooi.{$this->toernooiId}"),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'toernooi_id' => $this->toernooiId,
            'mat_id' => $this->matId,
            'type' => $this->type,
            'data' => $this->data,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'mat.update';
    }
}
