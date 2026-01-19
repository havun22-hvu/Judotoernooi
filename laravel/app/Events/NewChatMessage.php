<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewChatMessage implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ChatMessage $message;

    public function __construct(ChatMessage $message)
    {
        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
     * Using public channels for simplicity (no auth needed for toernooi chat)
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [];
        $toernooiId = $this->message->toernooi_id;

        // Determine which channels to broadcast to based on recipient
        switch ($this->message->naar_type) {
            case 'hoofdjury':
                $channels[] = new Channel("chat.{$toernooiId}.hoofdjury");
                break;

            case 'mat':
                // Specific mat
                $channels[] = new Channel("chat.{$toernooiId}.mat.{$this->message->naar_id}");
                break;

            case 'alle_matten':
                // Broadcast to all mats channel
                $channels[] = new Channel("chat.{$toernooiId}.alle_matten");
                break;

            case 'weging':
                $channels[] = new Channel("chat.{$toernooiId}.weging");
                break;

            case 'spreker':
                $channels[] = new Channel("chat.{$toernooiId}.spreker");
                break;

            case 'dojo':
                $channels[] = new Channel("chat.{$toernooiId}.dojo");
                break;

            case 'iedereen':
                // Broadcast channel for everyone
                $channels[] = new Channel("chat.{$toernooiId}.iedereen");
                break;
        }

        return $channels;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'van_type' => $this->message->van_type,
            'van_id' => $this->message->van_id,
            'van_naam' => $this->message->afzender_naam,
            'naar_type' => $this->message->naar_type,
            'naar_id' => $this->message->naar_id,
            'naar_naam' => $this->message->ontvanger_naam,
            'bericht' => $this->message->bericht,
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'chat.message';
    }
}
