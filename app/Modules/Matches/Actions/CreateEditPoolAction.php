<?php

namespace App\Modules\Matches\Actions;

/* Define Models */
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\Pool;

/* End Define Models */

/* Define Dependencies */
/* End Define Dependencies */

class CreateEditPoolAction
{
    public function __invoke($data)
    {
        $matchConfig = MatchConfig::where('league_id', $data['league_id'])->select('id', 'number_of_pools')->first();

        if (! $matchConfig) {
            throw new \Exception('Match config not found for this league. Please create a match config first.');
        }

        $poolcount = Pool::where('league_id', $data['league_id'])->count();
        if ($poolcount == $matchConfig->number_of_pools) {
            throw new \Exception('Pool count is equal to number of pools');
        }
        $numberOfPools = $matchConfig->number_of_pools - $poolcount;
        $match_config_id = $matchConfig->id;
        if ($data['command'] == 'create') {
            $pools = [];
            for ($i = 0; $i < $numberOfPools; $i++) {
                $pool = Pool::create([
                    'match_config_id' => $match_config_id,
                    'league_id' => $data['league_id'],
                ]);
                $pools[] = $pool;
            }

            return $pools;
        }
    }
}
