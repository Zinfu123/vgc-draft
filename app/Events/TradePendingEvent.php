<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TradePendingEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $targetTeamId) {}

    public function broadcastWith(): array
    {
        return [
            'target_team_id' => $this->targetTeamId,
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('trade.pending.'.$this->targetTeamId),
        ];
    }
}
