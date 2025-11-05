<?php

namespace App\Modules\Draft\Actions;

use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftOrder;
use App\Modules\Teams\Models\Team;

class CreateEditDraftOrderAction
{
    public function __invoke($data)
    {
        $roundnumber = Draft::where('league_id', $data['league_id'])->round_number;
        $teams = ($roundnumber % 2 == 0) ? Team::where('league_id', $data['league_id'])->orderBy('pick_position', 'desc')->where('draft_points', '>', 0)->get() : Team::where('league_id', $data['league_id'])->orderBy('pick_position', 'asc')->where('draft_points', '>', 0)->get();
        $i = 1;
        foreach ($teams as $team) {
            DraftOrder::create([
                'league_id' => $data['league_id'],
                'user_id' => $team->id,
                'team_name' => $team->name,
                'pick_number' => $i,
            ]);
            $i++;
        }
    }
}
