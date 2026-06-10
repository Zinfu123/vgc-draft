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
        if ((int) $user->id === (int) $league->league_owner) {
            return true;
        }

        return Team::query()
            ->where('user_id', $user->id)
            ->where('league_id', $league->id)
            ->where('admin_flag', 1)
            ->whereNull('dropped_at')
            ->exists();
    }

    /**
     * Determine whether the user owns the league (may manage co-admins).
     */
    public function own(User $user, League $league): bool
    {
        return (int) $user->id === (int) $league->league_owner;
    }
}
