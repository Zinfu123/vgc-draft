<?php

namespace App\Notifications;

use App\Models\User;
use App\Modules\Matches\Models\MatchScheduleRequest;
use App\Modules\Matches\Models\Set;
use App\Notifications\Channels\DiscordChannel;
use Illuminate\Notifications\Notification;

class MatchScheduleRequestedNotification extends Notification
{
    public function __construct(
        public readonly MatchScheduleRequest $scheduleRequest,
        public readonly Set $set,
        public readonly User $targetUser,
    ) {}

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
        $mention = $this->targetUser->discord_id
            ? "<@{$this->targetUser->discord_id}>"
            : $this->targetUser->name;

        $proposer = $this->scheduleRequest->proposedByUser;
        $proposerName = $proposer?->name ?? 'Unknown';

        $team1 = $this->set->team1;
        $team2 = $this->set->team2;

        $proposedTime = $this->scheduleRequest->proposed_at->format('D, M j \a\t g:i A T');
        $matchUrl = route('sets.show', ['set_id' => $this->set->id]);

        return [
            'content' => $mention,
            'embeds' => [
                [
                    'title' => '📅 Match Time Request',
                    'description' => "**{$proposerName}** has proposed a time to play **{$team1->name}** vs **{$team2->name}**.\n\n[View & respond to the request]({$matchUrl})",
                    'color' => 0x5865F2,
                    'fields' => [
                        [
                            'name' => '⏰ Proposed Time',
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
