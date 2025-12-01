<?php

namespace App\Modules\Matches\Actions;

/* Define Models */
use App\Modules\Matches\Models\MatchConfig;
/* End Define Models */

/* Define Dependencies */
use Illuminate\Support\Facades\Log;
/* End Define Dependencies */

class CreateEditMatchConfigAction
{
    public function __invoke($data)
    {
        if ($data['command'] == 'create') {
            $matchConfig = MatchConfig::create([
                'league_id' => $data['league_id'],
                'number_of_pools' => $data['number_of_pools'], 
                'frequency_type' => $data['frequency_type'],
            ]);
            return $matchConfig;
        }
        elseif ($data['command'] == 'update') {
            $matchConfig = MatchConfig::where('league_id', $data['league_id'])->first();
            $matchConfig->number_of_pools = $data['number_of_pools'];
            $matchConfig->frequency_type = $data['frequency_type'];
            $matchConfig->save();
            redirect()->route('leagues.detail', ['league' => $data['league_id']]);
            return $matchConfig;
        }
        elseif ($data['command'] == 'show') {
            $matchConfig = MatchConfig::where('league_id', $data['league_id'])->first();
            return $matchConfig;
        }
    }
}