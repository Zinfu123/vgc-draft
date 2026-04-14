<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchMessageSentEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public array $data) {}

    public function broadcastWith(): array
    {
        return [
            'id' => $this->data['id'],
            'set_id' => $this->data['set_id'],
            'user_id' => $this->data['user_id'],
            'user_name' => $this->data['user_name'],
            'body' => $this->data['body'],
            'is_read' => $this->data['is_read'] ?? false,
            'created_at' => $this->data['created_at'],
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('match.chat.'.$this->data['set_id']),
        ];
    }
}
