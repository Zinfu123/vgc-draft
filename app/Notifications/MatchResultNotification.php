<?php

namespace App\Notifications;

use App\Modules\Matches\Models\Set;
use App\Notifications\Channels\DiscordChannel;
use Illuminate\Notifications\Notification;

class MatchResultNotification extends Notification
{
    public function __construct(public readonly Set $set) {}

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
        $team1 = $this->set->team1;
        $team2 = $this->set->team2;
        $winnerId = $this->set->winner_id;

        $team1Name = $team1?->name ?? 'Team 1';
        $team2Name = $team2 === null ? 'Bye' : ($team2->name ?? 'Team 2');
        $winnerName = $winnerId === $this->set->team1_id ? $team1Name : $team2Name;

        return [
            'embeds' => [
                [
                    'title' => '🏆 Match Result',
                    'description' => "**{$team1Name}** {$this->set->team1_score} — {$this->set->team2_score} **{$team2Name}**\n\n🥇 Winner: **{$winnerName}**",
                    'color' => 0xFEE75C,
                    'footer' => ['text' => "Round {$this->set->round} · VGC Draft"],
                ],
            ],
        ];
    }
}
