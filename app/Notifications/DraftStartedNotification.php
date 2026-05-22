<?php

namespace App\Notifications;

use App\Models\User;
use App\Modules\League\Models\League;
use App\Notifications\Channels\DiscordChannel;
use Illuminate\Notifications\Notification;

class DraftStartedNotification extends Notification
{
    public function __construct(
        public readonly League $league,
        public readonly ?User $firstUser = null,
        public readonly ?string $phase = null,
    ) {
        if ($this->phase !== null && ! in_array($this->phase, ['ban', 'pick'], true)) {
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
     * @return array{content?: string, embeds: array<int, mixed>}
     */
    public function toDiscord(mixed $notifiable): array
    {
        $description = "The draft for **{$this->league->name}** has begun. Head over and make your picks!";
        $payload = [];

        if ($this->firstUser !== null) {
            $mention = $this->firstUser->discord_id
                ? "<@{$this->firstUser->discord_id}>"
                : $this->firstUser->name;

            $draftUrl = route('draft.detail', ['league_id' => $this->league->id]);

            $description = "The draft for **{$this->league->name}** has begun. You're first up!\n\n[Open the draft]({$draftUrl})";
            $payload['content'] = $mention;
        }

        $payload['embeds'] = [
            [
                'title' => '🎉 Draft Started!',
                'description' => $description,
                'color' => 0x5865F2,
                'footer' => ['text' => 'VGC Draft'],
            ],
        ];

        return $payload;
    }
}
