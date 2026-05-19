<?php

namespace App\Modules\Draft\Actions;

use App\Modules\Draft\Models\Draft;
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

        if ($league->draftConfig !== null && (bool) $league->draftConfig->ban_enabled === true) {
            ($this->createEditDraftAction)(['league_id' => $leagueId, 'command' => 'create_ban']);
            ($this->createEditDraftOrderAction)(['league_id' => $leagueId, 'command' => 'create_ban_order']);
        } else {
            ($this->createEditDraftOrderAction)(['league_id' => $leagueId]);
        }

        $league->notify(new DraftStartedNotification($league));

        $league->load('teams.user');
        foreach ($league->teams as $team) {
            if ($team->user !== null) {
                $team->user->notifyNow(new DraftStartedBroadcastNotification($league));
            }
        }

        ($this->draftTimerAction)(['league_id' => $leagueId, 'command' => DraftTimerAction::COMMAND_START_TURN]);

        return true;
    }
}
