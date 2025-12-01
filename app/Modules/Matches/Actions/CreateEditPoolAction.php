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
        $numberOfPools = $matchConfig->number_of_pools;
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