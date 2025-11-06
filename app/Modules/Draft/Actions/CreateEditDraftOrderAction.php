<?php

namespace App\Modules\Draft\Actions;

use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftOrder;
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
class CreateEditDraftOrderAction

{
    public function __invoke($data)
    {
        Log::info("data: ".$data['league_id']);
        $roundnumber = Draft::where('league_id', $data['league_id'])->select('round_number')->first();
        Log::info("roundnumber: ".$roundnumber->round_number);
        $roundnumber = $roundnumber->round_number;
        $teams = ($roundnumber % 2 == 0) ? Team::where('league_id', $data['league_id'])->orderBy('pick_position', 'desc')->where('draft_points', '>', 0)->get() : Team::where('league_id', $data['league_id'])->orderBy('pick_position', 'asc')->where('draft_points', '>', 0)->get();
        $i = 1;
        foreach ($teams as $team) {
            DraftOrder::create([
                'league_id' => $data['league_id'],
                'user_id' => Auth::user()->id,
                'team_name' => $team->name,
                'pick_number' => $i,
                'team_id' => $team->id,
            ]);
            $i++;
        }
    }
}
