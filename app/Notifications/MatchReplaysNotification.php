<?php

namespace App\Notifications;

use App\Modules\Matches\Models\Set;
use App\Notifications\Channels\DiscordReplayChannel;
use Illuminate\Notifications\Notification;

class MatchReplaysNotification extends Notification
{
    public function __construct(public readonly Set $set) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return [DiscordReplayChannel::class];
    }

    /**
     * @return array{embeds: array<int, mixed>}
     */
    public function toDiscord(mixed $notifiable): array
    {
        $team1Name = $this->set->team1?->name ?? 'Team 1';
        $team2Name = $this->set->team2?->name ?? 'Team 2';

        $replays = array_filter([
            $this->set->replay1,
            $this->set->replay2,
            $this->set->replay3,
        ]);

        $replayLines = implode("\n", array_map(
            fn (string $url, int $i) => 'Game '.($i + 1).": {$url}",
            array_values($replays),
            array_keys($replays),
        ));

        return [
            'embeds' => [
                [
                    'title' => "🎮 Replays — {$team1Name} vs {$team2Name}",
                    'description' => $replayLines,
                    'color' => 0xEB459E,
                    'footer' => ['text' => "Round {$this->set->round} · VGC Draft"],
                ],
            ],
        ];
    }
}
