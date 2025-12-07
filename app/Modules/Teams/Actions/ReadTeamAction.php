<?php

namespace App\Modules\Teams\Actions;

use App\Models\User;
use App\Modules\Teams\Models\Team;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Shared\Actions\LogoToUrlAction;
use Illuminate\Support\Facades\Log;

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
                $action = new LogoToUrlAction();
                $team->logo = $action->logoToUrl($team->logo);
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
            $team = Team::where('id', $data['team_id'])
            ->with('pokemon')
            ->first();

            $team->pokemon = $team->pokemon->map(function ($pokemon) {
                $pokemon->pokemon = Pokedex::where('id', $pokemon->pokedex_id)->select('id', 'name', 'sprite_url', 'type1', 'type2')->first();
                return $pokemon;
            });
            if ($team->logo !== null) {
                $action = new LogoToUrlAction();
                $team->logo = $action->logoToUrl($team->logo);
            }
            $team->coach = User::find($team->user_id)->name;
            return $team;
        }
    }
}
