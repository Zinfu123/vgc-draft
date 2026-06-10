<?php

namespace App\Notifications;

use App\Models\User;
use App\Modules\League\Models\League;
use App\Notifications\Channels\DiscordChannel;
use Illuminate\Notifications\Notification;

class DraftTurnReminderNotification extends Notification
{
    public function __construct(
        public readonly League $league,
        public readonly User $currentUser,
        public readonly string $phase,
        public readonly int $remainingSeconds,
    ) {
        if (! in_array($this->phase, ['ban', 'pick'], true)) {
            throw new \InvalidArgumentException('phase must be "ban" or "pick".');
        }

        if ($this->remainingSeconds <= 0) {
            throw new \InvalidArgumentException('remainingSeconds must be greater than zero.');
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
        $mention = $this->currentUser->discord_id
            ? "<@{$this->currentUser->discord_id}>"
            : $this->currentUser->name;

        $minutes = (int) round($this->remainingSeconds / 60);
        $minuteLabel = $minutes === 1 ? 'minute' : 'minutes';

        $verb = $this->phase === 'ban' ? 'ban' : 'pick';
        $draftUrl = route('draft.detail', ['league_id' => $this->league->id]);

        return [
            'content' => $mention,
            'embeds' => [
                [
                    'title' => '⏰ Pick timer reminder',
                    'description' => "**{$minutes} {$minuteLabel}** left to {$verb} in **{$this->league->name}**.\n\n[Open the draft]({$draftUrl})",
                    'color' => 0xF59E0B,
                    'footer' => ['text' => 'VGC Draft'],
                ],
            ],
        ];
    }
}
