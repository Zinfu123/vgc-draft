<?php

namespace App\Notifications;

use App\Models\User;
use App\Modules\Matches\Enums\ScheduleRequestStatus;
use App\Modules\Matches\Models\MatchScheduleRequest;
use App\Modules\Matches\Models\Set;
use App\Notifications\Channels\DiscordChannel;
use Illuminate\Notifications\Notification;

class MatchScheduleRespondedNotification extends Notification
{
    public function __construct(
        public readonly MatchScheduleRequest $scheduleRequest,
        public readonly Set $set,
        public readonly User $respondingUser,
        public readonly User $otherUser,
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
        $mentions = collect([$this->respondingUser, $this->otherUser])
            ->map(fn (User $user) => $user->discord_id ? "<@{$user->discord_id}>" : $user->name)
            ->join(' ');

        $team1 = $this->set->team1;
        $team2 = $this->set->team2;
        $matchUrl = route('sets.show', ['set_id' => $this->set->id]);

        [$title, $description, $color] = match ($this->scheduleRequest->status) {
            ScheduleRequestStatus::Accepted => [
                '✅ Match Time Confirmed',
                "**{$this->respondingUser->name}** accepted the proposed time for **{$team1->name}** vs **{$team2->name}**.\n\nProposed time: **{$this->scheduleRequest->proposed_at->format('D, M j \a\t g:i A T')}**\n\n[View match]({$matchUrl})",
                0x57F287,
            ],
            ScheduleRequestStatus::Declined => [
                '❌ Match Time Request Declined',
                "**{$this->respondingUser->name}** declined the proposed time for **{$team1->name}** vs **{$team2->name}**.\n\n[View match & propose a new time]({$matchUrl})",
                0xED4245,
            ],
            ScheduleRequestStatus::Reschedule => [
                '🔄 New Match Time Proposed',
                "**{$this->respondingUser->name}** has proposed a new time for **{$team1->name}** vs **{$team2->name}**.\n\nNew proposed time: **{$this->scheduleRequest->proposed_at->format('D, M j \a\t g:i A T')}**\n\n[View & respond]({$matchUrl})",
                0xFEE75C,
            ],
            default => [
                '📅 Match Schedule Update',
                "The match schedule for **{$team1->name}** vs **{$team2->name}** has been updated.\n\n[View match]({$matchUrl})",
                0x5865F2,
            ],
        };

        return [
            'content' => $mentions,
            'embeds' => [
                [
                    'title' => $title,
                    'description' => $description,
                    'color' => $color,
                    'footer' => ['text' => 'VGC Draft'],
                ],
            ],
        ];
    }
}
