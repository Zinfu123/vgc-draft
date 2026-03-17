<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DraftDetailEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public array $data) {}

    public function broadcastWith(): array
    {
        return [
            'league_id' => $this->data['league_id'],
            'end_draft' => $this->data['end_draft'],
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('draft.detail.'.$this->data['league_id']),
        ];
    }
}
