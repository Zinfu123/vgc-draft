<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SetUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public array $data)
    {
    
    }
    public function broadcastWith(): array
    {
        return [
            'id' => $this->data['set_id'],
            'status' => $this->data['status'],
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('set_updated.'.$this->data['set_id']),
        ];
    }
}
