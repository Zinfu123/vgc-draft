<?php

namespace App\Modules\Matches\Actions;

/* Define Models */
use App\Modules\Matches\Models\Set;
use App\Modules\Matches\Models\Pool;
use App\Modules\Teams\Models\Team;
/* End Define Models */

/* Define Dependencies */
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;
/* End Define Dependencies */

class ShowSetsAction
{
    public function __invoke($data)
    {
        if ($data['command'] == 'all') {
            $sets = Set::where('league_id', $data['league_id'])->orderBy('round', 'asc')->get();
            $sets = $sets->map(function ($set) {
                $set->team1 = Team::where('id', $set->team1_id)->select('id', 'name', 'logo')->first();
                $set->team2 = Team::where('id', $set->team2_id)->select('id', 'name', 'logo')->first();
                return $set;
            });
            Log::info("sets: " . json_encode($sets));
            return $sets;
        }
        elseif ($data['command'] == 'round') {
            $sets = Set::where('league_id', $data['league_id'])->where('round', $data['round'])->get();
            return $sets;
        }
        elseif ($data['command'] == 'team') {
            $sets = Set::where('league_id', $data['league_id'])->where('team1_id', $data['team_id'])->orWhere('team2_id', $data['team_id'])->get();
            return $sets;
        }
        elseif ($data['command'] == 'team_next') {
            $sets = Set::where('league_id', $data['league_id'])->where('team1_id', $data['team_id'])->orWhere('team2_id', $data['team_id'])->where('status', 1)->orderBy('round', 'asc')->first();
            return $sets;
        }
        elseif ($data['command'] == 'pool') {
            $sets = Set::where('league_id', $data['league_id'])->where('pool_id', $data['pool_id'])->get();
            return $sets;
        }
        elseif ($data['command'] == 'round_and_pool') {
            $sets = Set::where('league_id', $data['league_id'])->where('round', $data['round'])->where('pool_id', $data['pool_id'])->get();
            return $sets;
        }
    }
}