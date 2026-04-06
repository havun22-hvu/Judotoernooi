<?php

namespace App\Events;

use App\Events\Concerns\SafelyBroadcasts;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class TvLinked implements ShouldBroadcastNow
{
    use Dispatchable, SafelyBroadcasts {
        SafelyBroadcasts::dispatch insteadof Dispatchable;
    }

    public function __construct(
        public string $code,
        public string $redirect,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('tv-koppeling.' . $this->code)];
    }

    public function broadcastAs(): string
    {
        return 'tv.linked';
    }

    public function broadcastWith(): array
    {
        return ['redirect' => $this->redirect];
    }
}
