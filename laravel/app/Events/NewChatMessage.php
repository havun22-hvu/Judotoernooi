<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewChatMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ChatMessage $message;

    public function __construct(ChatMessage $message)
    {
        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
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
                $channels[] = new PrivateChannel("chat.hoofdjury.{$toernooiId}");
                break;

            case 'mat':
                // Specific mat
                $channels[] = new PrivateChannel("chat.mat.{$toernooiId}.{$this->message->naar_id}");
                break;

            case 'alle_matten':
                // Broadcast to all mats - they subscribe to their own channel + alle_matten
                $channels[] = new PrivateChannel("chat.alle_matten.{$toernooiId}");
                break;

            case 'weging':
                $channels[] = new PrivateChannel("chat.weging.{$toernooiId}");
                break;

            case 'spreker':
                $channels[] = new PrivateChannel("chat.spreker.{$toernooiId}");
                break;

            case 'dojo':
                $channels[] = new PrivateChannel("chat.dojo.{$toernooiId}");
                break;

            case 'iedereen':
                // Broadcast channel for all
                $channels[] = new PrivateChannel("chat.toernooi.{$toernooiId}");
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
