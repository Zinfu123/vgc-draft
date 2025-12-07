<?php

namespace App\Modules\Draft\Actions;

use App\Events\EndDraftEvent;
use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftOrder;
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Facades\Log;

class CreateEditDraftOrderAction
{
    public function __invoke($data)
    {
        $AvailableTeamsCount = Team::where('league_id', $data['league_id'])->where('draft_points', '>', 0)->count();
        if ($AvailableTeamsCount == 0) {
            $draft = Draft::where('league_id', $data['league_id'])->first();
            $draft->status = 0;
            $draft->save();
            EndDraftEvent::dispatch([
                'league_id' => $data['league_id'],
                'end_draft' => 1,
            ]);
        } else {
            $draft = Draft::where('league_id', $data['league_id'])->first();
            log::info('draft: '.$draft);
            $roundnumber = $draft->round_number;
            $teams = ($roundnumber % 2 == 0) ? Team::where('league_id', $data['league_id'])->where('draft_points', '>', 0)->orderBy('pick_position', 'desc')->get() : Team::where('league_id', $data['league_id'])->where('draft_points', '>', 0)->orderBy('pick_position', 'asc')->get();
            $i = 1;
            foreach ($teams as $team) {
                DraftOrder::create([
                    'league_id' => $data['league_id'],
                    'user_id' => $team->user_id,
                    'team_name' => $team->name,
                    'pick_number' => $i,
                    'team_id' => $team->id,
                    'round_number' => $roundnumber,
                ]);
                $i = $i + 1;
            }

            $lastPick = DraftOrder::where('league_id', $data['league_id'])->where('status', 1)->orderBy('pick_number', 'desc')->first();
            $lastPick->is_last_pick = 1;
            $lastPick->save();
        }
    }
}
