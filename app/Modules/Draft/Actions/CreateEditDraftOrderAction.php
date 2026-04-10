<?php

namespace App\Modules\Draft\Actions;

use App\Events\EndDraftEvent;
use App\Modules\Draft\Models\BanOrder;
use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftOrder;
use App\Modules\League\Models\League;
use App\Modules\Teams\Models\Team;
use Carbon\Carbon;

class CreateEditDraftOrderAction
{
    public function __invoke($data)
    {
        if (isset($data['command']) && $data['command'] === 'create_ban_order') {
            $this->createBanOrder($data['league_id']);

            return;
        }

        $AvailableTeamsCount = Team::where('league_id', $data['league_id'])->where('draft_points', '>', 0)->count();
        if ($AvailableTeamsCount == 0) {
            $draft = Draft::where('league_id', $data['league_id'])->first();
            $draft->status = 0;
            $draft->save();

            $this->adjustSetStartDateIfNeeded((int) $data['league_id']);

            EndDraftEvent::dispatch([
                'league_id' => $data['league_id'],
                'end_draft' => 1,
                'draft_id' => $draft->id,
            ]);
        } else {
            $draft = Draft::where('league_id', $data['league_id'])->first();
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

    private function adjustSetStartDateIfNeeded(int $leagueId): void
    {
        $league = League::find($leagueId);
        if ($league === null) {
            return;
        }

        $today = Carbon::today()->toDateString();
        if ($league->set_start_date === null || $league->set_start_date < $today) {
            $league->set_start_date = $today;
            $league->save();
        }
    }

    private function createBanOrder(int $leagueId): void
    {
        $draftConfig = League::with('draftConfig')->find($leagueId)->draftConfig;
        $bansPerUser = $draftConfig->bans_per_user;

        for ($round = 1; $round <= $bansPerUser; $round++) {
            $teams = ($round % 2 == 0)
                ? Team::where('league_id', $leagueId)->orderBy('pick_position', 'desc')->get()
                : Team::where('league_id', $leagueId)->orderBy('pick_position', 'asc')->get();

            $banNumber = 1;
            foreach ($teams as $team) {
                BanOrder::create([
                    'league_id' => $leagueId,
                    'team_id' => $team->id,
                    'user_id' => $team->user_id,
                    'team_name' => $team->name,
                    'ban_number' => $banNumber,
                    'round_number' => $round,
                    'status' => 1,
                    'is_last_ban' => 0,
                ]);
                $banNumber++;
            }
        }

        $lastBan = BanOrder::where('league_id', $leagueId)->orderBy('round_number', 'desc')->orderBy('ban_number', 'desc')->first();
        if ($lastBan) {
            $lastBan->is_last_ban = 1;
            $lastBan->save();
        }
    }
}
