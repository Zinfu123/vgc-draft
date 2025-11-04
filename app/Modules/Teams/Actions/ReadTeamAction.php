<?php

namespace App\Modules\Teams\Actions;

use App\Models\User;
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Facades\Storage;

class ReadTeamAction
{
    public function __invoke($data)
    {
        $teams = Team::where('league_id', $data)
            ->select('id', 'name', 'logo', 'user_id', 'league_id')
            ->with('user')
            ->get();

        $teams = $teams->map(function ($team) {
            if ($team->logo !== null) {
                $team->logo = str_replace('\\', '/', Storage::disk('s3-team-logos')->url($team->league_id.'/'.$team->logo));
            }

            return $team;
        });
        $teams = $teams->map(function ($team) {
            $team->coach = User::find($team->user_id)->name;

            return $team;
        });

        return $teams;

    }
}
