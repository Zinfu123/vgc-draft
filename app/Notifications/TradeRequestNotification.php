<?php

namespace App\Notifications;

use App\Models\User;
use App\Modules\Trade\Models\Trade;
use App\Notifications\Channels\DiscordChannel;
use Illuminate\Notifications\Notification;

class TradeRequestNotification extends Notification
{
    public function __construct(
        public readonly Trade $trade,
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
        $requestingTeam = $this->trade->requestingTeam;
        $targetTeam = $this->trade->targetTeam;
        $league = $this->trade->league;

        $offeredParts = $this->trade->offeredPokemon
            ->map(fn ($tp) => $tp->leaguePokemon?->name ?? 'Unknown')
            ->all();

        if ($this->trade->draft_points_delta !== null && $this->trade->draft_points_delta < 0) {
            $offeredParts[] = abs($this->trade->draft_points_delta).' draft pts';
        }

        $offeredNames = implode(', ', $offeredParts);

        $requestedNames = $this->trade->requestedPokemon
            ->map(fn ($tp) => $tp->leaguePokemon?->name ?? 'Unknown')
            ->join(', ');

        $mention = $this->targetUser->discord_id
            ? "<@{$this->targetUser->discord_id}>"
            : $this->targetUser->name;

        $dashboardUrl = route('leagues.dashboard', ['league' => $league->id]);

        return [
            'content' => $mention,
            'embeds' => [
                [
                    'title' => '🔄 New Trade Request',
                    'description' => "**{$requestingTeam->name}** has sent a trade request to **{$targetTeam->name}** in **{$league->name}**.\n\n[View & respond to the trade]({$dashboardUrl})",
                    'color' => 0xED4245,
                    'fields' => [
                        [
                            'name' => "📤 {$requestingTeam->name} offers",
                            'value' => $offeredNames ?: 'None',
                            'inline' => true,
                        ],
                        [
                            'name' => "📥 {$requestingTeam->name} wants",
                            'value' => $requestedNames ?: 'None',
                            'inline' => true,
                        ],
                    ],
                    'footer' => ['text' => 'VGC Draft'],
                ],
            ],
        ];
    }
}
