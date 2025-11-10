<?php

namespace App\Modules\Teams\Actions;

use App\Models\User;
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Facades\Storage;

class ReadTeamAction
{
    public function __invoke($data)
    {
        if($data['command'] == 'league') {
        $teams = Team::where('league_id', $data['league_id'])
            ->select('id', 'name', 'logo', 'user_id', 'admin_flag')
            ->with('user')
            ->get();

        $teams = $teams->map(function ($team) {
            if ($team->logo !== null) {
                $team->logo = str_replace('\\', '/', Storage::disk('s3-team-logos')->url($team->logo));
            }

            return $team;
        });
        $teams = $teams->map(function ($team) {
            $team->coach = User::find($team->user_id)->name;

            return $team;
        });

        return $teams;
        }
        elseif($data['command'] == 'team') {
            $team = Team::where('id', $data['team_id'])->with('draftPicks.leaguePokemon.pokemon')->first();
            if ($team->logo !== null) {
                $team->logo = str_replace('\\', '/', Storage::disk('s3-team-logos')->url($team->logo));
            }
            return $team;
        }
    }
}
