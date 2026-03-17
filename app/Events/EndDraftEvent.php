<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EndDraftEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public array $data) {}

    public function broadcastWith(): array
    {
        return [
            'end_draft' => $this->data['end_draft'],
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('end.draft.'.$this->data['draft_id']),
        ];
    }
}
