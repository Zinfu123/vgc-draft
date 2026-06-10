<?php

namespace App\Notifications;

use App\Modules\League\Models\League;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class DraftStartedBroadcastNotification extends Notification implements ShouldBroadcastNow
{
    public function __construct(public readonly League $league) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['broadcast'];
    }

    public function toBroadcast(mixed $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'league_id' => $this->league->id,
            'league_name' => $this->league->name,
        ]);
    }

    public function broadcastType(): string
    {
        return 'DraftStartedBroadcastNotification';
    }
}
