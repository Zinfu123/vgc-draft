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

        $offeredNames = $this->trade->offeredPokemon
            ->map(fn ($tp) => $tp->leaguePokemon?->name ?? 'Unknown')
            ->join(', ');

        $requestedNames = $this->trade->requestedPokemon
            ->map(fn ($tp) => $tp->leaguePokemon?->name ?? 'Unknown')
            ->join(', ');

        $mention = $this->targetUser->discord_id
            ? "<@{$this->targetUser->discord_id}>"
            : $this->targetUser->name;

        $tradesUrl = route('leagues.trades', ['league' => $league->id]);

        return [
            'content' => $mention,
            'embeds' => [
                [
                    'title' => '🔄 New Trade Request',
                    'description' => "**{$requestingTeam->name}** has sent a trade request to **{$targetTeam->name}** in **{$league->name}**.\n\n[View & respond to the trade]({$tradesUrl})",
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
