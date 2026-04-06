<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchScheduleRequestUpdatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public array $data) {}

    public function broadcastWith(): array
    {
        return [
            'id' => $this->data['id'],
            'set_id' => $this->data['set_id'],
            'proposed_by_user_id' => $this->data['proposed_by_user_id'],
            'proposed_by_user_name' => $this->data['proposed_by_user_name'],
            'proposed_at' => $this->data['proposed_at'],
            'status' => $this->data['status'],
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('match.chat.'.$this->data['set_id']),
        ];
    }
}
