<?php

namespace App\Modules\Matches\Actions;

/* Define Models */
use App\Modules\Matches\Models\Pool;
use App\Modules\Matches\Models\MatchConfig;
/* End Define Models */

/* Define Dependencies */
/* End Define Dependencies */

class CreateEditPoolAction
{
    public function __invoke($data)
    {
        $matchConfig = MatchConfig::where('league_id', $data['league_id'])->select('id', 'number_of_pools')->first();
        $poolcount = Pool::where('league_id', $data['league_id'])->count();
        if ($poolcount == $matchConfig->number_of_pools) {
            throw new \Exception('Pool count is equal to number of pools');
        }
        $numberOfPools = $matchConfig->number_of_pools - $poolcount;
        $match_config_id = $matchConfig->id;
        if ($data['command'] == 'create') {
            for ($i = 0; $i < $numberOfPools; $i++) {
                $pools[] = $pool = Pool::create([
                    'match_config_id' => $match_config_id,
                    'league_id' => $data['league_id'],
                ]);
                $pool->save();
            }
            return $pools;
        }
    }
}