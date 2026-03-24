<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\League\Models\League;
use App\Modules\Teams\Models\Team;

class LeaguePolicy
{
    /**
     * Determine whether the user is an admin of the league.
     */
    public function admin(User $user, League $league): bool
    {
        return Team::where('user_id', $user->id)
            ->where('league_id', $league->id)
            ->where('admin_flag', 1)
            ->exists();
    }
}
