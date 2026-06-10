<?php

namespace App\Modules\Draft\Actions;

use App\Models\User;
use App\Modules\Draft\Models\BanOrder;
use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftOrder;
use App\Modules\League\Models\League;
use App\Notifications\DraftNextTurnNotification;

class NotifyDraftNextTurnAction
{
    public function __invoke(array $data): void
    {
        $leagueId = (int) $data['league_id'];

        $league = League::query()->find($leagueId);
        if (! $league || ! $league->discord_webhook_url) {
            return;
        }

        $draft = Draft::query()->where('league_id', $leagueId)->first();
        if (! $draft || (int) $draft->status === 0) {
            return;
        }

        $status = (int) $draft->status;

        if ($status === 2) {
            $next = BanOrder::query()
                ->where('league_id', $leagueId)
                ->where('status', 1)
                ->orderBy('round_number')
                ->orderBy('ban_number')
                ->first();
            $phase = 'ban';
        } else {
            $next = DraftOrder::query()
                ->where('league_id', $leagueId)
                ->where('status', 1)
                ->orderBy('pick_number')
                ->first();
            $phase = 'pick';
        }

        if (! $next) {
            return;
        }

        $userId = $next->user_id;
        if (! $userId) {
            $next->loadMissing('team');
            $userId = $next->team?->user_id;
        }

        if (! $userId) {
            return;
        }

        $nextUser = User::query()->find($userId);
        if (! $nextUser) {
            return;
        }

        $league->notify(new DraftNextTurnNotification($league, $nextUser, $phase));
    }
}
