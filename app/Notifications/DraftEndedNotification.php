<?php

namespace App\Notifications;

use App\Modules\League\Models\League;
use App\Notifications\Channels\DiscordChannel;
use Illuminate\Notifications\Notification;

class DraftEndedNotification extends Notification
{
    public function __construct(public readonly League $league) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return [DiscordChannel::class];
    }

    /**
     * @return array{embeds: array<int, mixed>}
     */
    public function toDiscord(mixed $notifiable): array
    {
        return [
            'embeds' => [
                [
                    'title' => '✅ Draft Complete!',
                    'description' => "The draft for **{$this->league->name}** has finished. All picks are locked in — may the best trainer win!",
                    'color' => 0x57F287,
                    'footer' => ['text' => 'VGC Draft'],
                ],
            ],
        ];
    }
}
