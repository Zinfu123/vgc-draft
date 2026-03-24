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
        $pools = Pool::where('league_id', $data['league_id'])->where('status', 1)->orderBy('id')->get();
        $poolIds = $pools->pluck('id');
        $numberOfPools = $poolIds->count();

        if ($numberOfPools === 0) {
            throw new \Exception('No active pools found for this league.');
        }

        $teams = Team::where('league_id', $data['league_id'])->select('id', 'name', 'seed', 'pool_id')->get();

        foreach ($teams as $team) {
            $seed = $team->seed ?? 0;
            $team->pool_id = $poolIds[$seed % $numberOfPools];
            $team->save();
        }

        return $teams;
    }
}
