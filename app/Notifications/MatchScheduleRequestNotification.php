<?php

namespace App\Notifications;

use App\Models\User;
use App\Modules\League\Models\League;
use App\Modules\Matches\Models\MatchScheduleRequest;
use App\Modules\Matches\Models\Set;
use App\Notifications\Channels\DiscordChannel;
use Illuminate\Notifications\Notification;

class MatchScheduleRequestNotification extends Notification
{
    public function __construct(
        public readonly League $league,
        public readonly Set $set,
        public readonly MatchScheduleRequest $scheduleRequest,
        public readonly User $proposer,
        public readonly User $opponent,
    ) {}

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
        $mention = $this->opponent->discord_id
            ? "<@{$this->opponent->discord_id}>"
            : $this->opponent->name;

        $proposerName = $this->proposer->discord_id
            ? "<@{$this->proposer->discord_id}>"
            : $this->proposer->name;

        $matchUrl = route('sets.show', ['set_id' => $this->set->id]);

        $proposedTime = $this->scheduleRequest->proposed_at->format('D, M j \a\t g:i A T');

        return [
            'content' => $mention,
            'embeds' => [
                [
                    'title' => '📅 Match Schedule Request',
                    'description' => "{$proposerName} has proposed a time to play in **{$this->league->name}**.\n\n[View the match & respond]({$matchUrl})",
                    'color' => 0x57F287,
                    'fields' => [
                        [
                            'name' => '🕐 Proposed time',
                            'value' => $proposedTime,
                            'inline' => false,
                        ],
                    ],
                    'footer' => ['text' => 'VGC Draft'],
                ],
            ],
        ];
    }
}
