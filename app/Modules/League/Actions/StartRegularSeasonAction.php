<?php

namespace App\Modules\League\Actions;

use App\Modules\Draft\Models\Draft;
use App\Modules\League\Enums\LeagueStagingStatus;
use App\Modules\League\Enums\LeagueStatus;
use App\Modules\League\Models\League;

class StartRegularSeasonAction
{
    public function __invoke(League $league): bool
    {
        if ($league->status !== LeagueStatus::Staging) {
            return false;
        }

        if (! $this->draftIsComplete($league)) {
            return false;
        }

        $league->status = LeagueStatus::RegularSeason;
        $league->staging_sub_status = null;
        $league->save();

        return true;
    }

    private function draftIsComplete(League $league): bool
    {
        if ($league->staging_sub_status === LeagueStagingStatus::FreeTradeWindow) {
            return true;
        }

        return Draft::query()
            ->where('league_id', $league->id)
            ->where('status', 0)
            ->exists();
    }
}
