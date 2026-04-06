<?php

namespace App\Events;

use App\Modules\Matches\Models\Battle;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BattleUpdatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Battle $battle,
        public readonly array $output,
    ) {}

    public function broadcastWith(): array
    {
        return [
            'battle_id' => $this->battle->id,
            'status' => $this->battle->status,
            'winner' => $this->battle->winner,
            'output' => $this->output,
            'battle_log' => $this->battle->battle_log,
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new Channel("battle.{$this->battle->id}"),
        ];
    }
}
