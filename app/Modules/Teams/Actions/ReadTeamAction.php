<?php

namespace App\Modules\Teams\Actions;

use App\Modules\Shared\Actions\LogoToUrlAction;
use App\Modules\Teams\Models\Team;

class ReadTeamAction
{
    public function __invoke($data)
    {
        if ($data['command'] == 'league') {
            $teams = Team::query()->where('league_id', $data['league_id'])
                ->notDropped()
                ->select('id', 'league_id', 'name', 'showdown_username', 'logo', 'user_id', 'admin_flag', 'pick_position', 'set_wins', 'set_losses', 'victory_points', 'trades')
                ->with('user')
                ->orderBy('name')
                ->get();

            $teams = $teams->map(function ($team) {
                if ($team->logo !== null && trim($team->logo) !== '') {
                    $action = new LogoToUrlAction;
                    $team->logo = $action->logoToUrl($team->logo);
                } else {
                    $team->logo = null;
                }

                return $team;
            });
            $teams = $teams->map(function ($team) {
                $team->coach = $team->user?->name ?? '—';

                return $team;
            });

            return $teams;
        } elseif ($data['command'] == 'team') {
            $team = Team::where('id', $data['team_id'])
                ->with([
                    'user',
                    'pokemon.pokemon' => fn ($q) => $q->select('id', 'name', 'sprite_url', 'type1', 'type2'),
                ])
                ->first();

            if ($team->logo !== null && trim($team->logo) !== '') {
                $action = new LogoToUrlAction;
                $team->logo = $action->logoToUrl($team->logo);
            } else {
                $team->logo = null;
            }
            $team->coach = $team->user->name;

            return $team;
        } elseif ($data['command'] == 'standings') {
            $standings = Team::query()->where('league_id', $data['league_id'])
                ->notDropped()
                ->select('id', 'league_id', 'name', 'logo', 'user_id', 'set_wins', 'set_losses', 'victory_points', 'pool_id')
                ->with('user')
                ->orderBy('victory_points', 'desc')
                ->get();
            $standings = $standings->map(function ($team) {
                if ($team->logo !== null && trim($team->logo) !== '') {
                    $action = new LogoToUrlAction;
                    $team->logo = $action->logoToUrl($team->logo);
                } else {
                    $team->logo = null;
                }

                return $team;
            });
            $standings = $standings->groupBy('pool_id');

            return $standings;
        }
    }
}
