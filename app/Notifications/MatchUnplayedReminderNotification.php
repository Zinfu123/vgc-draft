<?php

namespace App\Notifications;

use App\Modules\Matches\Models\Set;
use App\Notifications\Channels\DiscordChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class MatchUnplayedReminderNotification extends Notification
{
    /**
     * @param  Collection<int, Set>  $sets
     */
    public function __construct(
        public readonly Collection $sets,
        public readonly string $roundLabel,
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
        $lines = $this->sets->map(function (Set $set): string {
            $team1User = $set->team1?->user;
            $team2User = $set->team2?->user;

            $mention1 = $team1User?->discord_id
                ? "<@{$team1User->discord_id}>"
                : ($team1User?->name ?? $set->team1?->name ?? 'Unknown');

            $mention2 = $team2User?->discord_id
                ? "<@{$team2User->discord_id}>"
                : ($team2User?->name ?? $set->team2?->name ?? 'Unknown');

            $matchUrl = route('sets.show', ['set_id' => $set->id]);

            return "• {$mention1} vs {$mention2} — [View match]({$matchUrl})";
        })->join("\n");

        return [
            'embeds' => [
                [
                    'title' => "⏳ Unplayed Matches — {$this->roundLabel}",
                    'description' => "The following matches from **{$this->roundLabel}** have not been completed yet. Please schedule and play your match!\n\n{$lines}",
                    'color' => 0xFF9800,
                    'footer' => ['text' => 'VGC Draft'],
                ],
            ],
        ];
    }
}
