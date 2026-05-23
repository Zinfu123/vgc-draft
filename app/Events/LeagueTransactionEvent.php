<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeagueTransactionEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $leagueId) {}

    public function broadcastWith(): array
    {
        return [
            'league_id' => $this->leagueId,
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('league.transactions.'.$this->leagueId),
        ];
    }
}
