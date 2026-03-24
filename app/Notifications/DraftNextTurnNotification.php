<?php

namespace App\Notifications;

use App\Models\User;
use App\Modules\League\Models\League;
use App\Notifications\Channels\DiscordChannel;
use Illuminate\Notifications\Notification;

class DraftNextTurnNotification extends Notification
{
    public function __construct(
        public readonly League $league,
        public readonly User $nextUser,
        public readonly string $phase,
    ) {
        if (! in_array($this->phase, ['ban', 'pick'], true)) {
            throw new \InvalidArgumentException('phase must be "ban" or "pick".');
        }
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return [DiscordChannel::class];
    }

    /**
     * @return array{content: string, embeds: array<int, mixed>}
     */
    public function toDiscord(mixed $notifiable): array
    {
        $mention = $this->nextUser->discord_id
            ? "<@{$this->nextUser->discord_id}>"
            : $this->nextUser->name;

        $actionLine = $this->phase === 'ban'
            ? "It's your turn to ban"
            : "It's your turn to pick";

        $draftUrl = route('draft.detail', ['league_id' => $this->league->id]);

        return [
            'content' => $mention,
            'embeds' => [
                [
                    'title' => '📋 Draft',
                    'description' => "{$actionLine} in **{$this->league->name}**.\n\n[Open the draft]({$draftUrl})",
                    'color' => 0x5865F2,
                    'footer' => ['text' => 'VGC Draft'],
                ],
            ],
        ];
    }
}
