<?php

namespace App\Modules\Pokepaste\Services;

use App\Models\User;
use App\Modules\Matches\Models\Set;
use App\Modules\Playoffs\Models\PlayoffMatch;
use App\Modules\Playoffs\Services\PlayoffMatchPokepastePlanningService;
use App\Modules\Pokepaste\Models\SetTeamPokepaste;

class AuthorizePokepasteEditor
{
    public function __construct(
        private PlayoffMatchPokepastePlanningService $planningService,
    ) {}

    public function userMayEdit(SetTeamPokepaste $pokepaste, User $user): bool
    {
        $pokepaste->loadMissing(['team', 'matchable']);

        if ($pokepaste->team === null) {
            return false;
        }

        $set = $pokepaste->setModel();
        if ($set instanceof Set) {
            if (! in_array((int) $pokepaste->team_id, [(int) $set->team1_id, (int) $set->team2_id], true)) {
                return false;
            }

            if ((int) $set->status !== 0 && $this->userMayEditSetAsLeagueAdmin($set, $user)) {
                return true;
            }

            return (int) $pokepaste->team->user_id === (int) $user->id;
        }

        if ((int) $pokepaste->team->user_id !== (int) $user->id) {
            return false;
        }

        $match = $pokepaste->playoffMatch();
        if ($match instanceof PlayoffMatch) {
            if ($match->isComplete()) {
                return in_array((int) $pokepaste->team_id, [(int) $match->team1_id, (int) $match->team2_id], true);
            }

            return $this->planningService->mayCoachPlanPokepaste($match, (int) $pokepaste->team_id);
        }

        return false;
    }

    private function userMayEditSetAsLeagueAdmin(Set $set, User $user): bool
    {
        $set->loadMissing('league');
        $league = $set->league;

        return $league !== null && $user->can('admin', $league);
    }
}
