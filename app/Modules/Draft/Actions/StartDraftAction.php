<?php

namespace App\Modules\Draft\Actions;

use App\Models\User;
use App\Modules\Draft\Models\BanOrder;
use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftOrder;
use App\Modules\League\Models\League;
use App\Notifications\DraftStartedBroadcastNotification;
use App\Notifications\DraftStartedNotification;

class StartDraftAction
{
    public function __construct(
        private readonly CreateEditDraftAction $createEditDraftAction,
        private readonly CreateEditDraftOrderAction $createEditDraftOrderAction,
        private readonly DraftTimerAction $draftTimerAction,
    ) {}

    /**
     * Idempotently start the draft for the given league: create the draft row,
     * generate the draft (or ban) order, notify participants, and start the timer.
     *
     * Returns true if a new draft was started, false if one already existed.
     */
    public function __invoke(int $leagueId): bool
    {
        if (Draft::query()->where('league_id', $leagueId)->exists()) {
            return false;
        }

        $league = League::with('draftConfig')->find($leagueId);
        if ($league === null) {
            return false;
        }

        ($this->createEditDraftAction)(['league_id' => $leagueId, 'command' => 'create']);

        $banEnabled = $league->draftConfig !== null && (bool) $league->draftConfig->ban_enabled === true;

        if ($banEnabled) {
            ($this->createEditDraftAction)(['league_id' => $leagueId, 'command' => 'create_ban']);
            ($this->createEditDraftOrderAction)(['league_id' => $leagueId, 'command' => 'create_ban_order']);
        } else {
            ($this->createEditDraftOrderAction)(['league_id' => $leagueId]);
        }

        [$firstUser, $phase] = $this->resolveFirstUserAndPhase($leagueId, $banEnabled);

        $league->notify(new DraftStartedNotification($league, $firstUser, $phase));

        $league->load('teams.user');
        foreach ($league->teams as $team) {
            if ($team->user !== null) {
                $team->user->notifyNow(new DraftStartedBroadcastNotification($league));
            }
        }

        ($this->draftTimerAction)(['league_id' => $leagueId, 'command' => DraftTimerAction::COMMAND_START_TURN]);

        return true;
    }

    /**
     * @return array{0: ?User, 1: ?string}
     */
    private function resolveFirstUserAndPhase(int $leagueId, bool $banEnabled): array
    {
        if ($banEnabled) {
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

        if ($next === null) {
            return [null, null];
        }

        $userId = $next->user_id;
        if (! $userId) {
            $next->loadMissing('team');
            $userId = $next->team?->user_id;
        }

        if (! $userId) {
            return [null, null];
        }

        $firstUser = User::query()->find($userId);

        if ($firstUser === null) {
            return [null, null];
        }

        return [$firstUser, $phase];
    }
}
