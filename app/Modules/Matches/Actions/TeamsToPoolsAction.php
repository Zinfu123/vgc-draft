<?php

namespace App\Modules\Matches\Actions;

/* Define Models */
use App\Modules\Matches\Models\Pool;
use App\Modules\Teams\Models\Team;

/* End Define Models */

/* Define Dependencies */

/* End Define Dependencies */

class TeamsToPoolsAction
{
    public function __invoke($data)
    {
        $pools = Pool::where('league_id', $data['league_id'])->where('status', 1)->get();
        $pools = $pools->pluck('id');
        $teams = Team::where('league_id', $data['league_id'])->select('id', 'name', 'seed')->get();
        $numberOfPools = $pools->count();
        foreach ($teams as $team) {
            $team->pool_id = $pools[$team->seed % $numberOfPools];
            $team->save();
        }

        return $teams;
    }
}
