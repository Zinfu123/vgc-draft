<?php

namespace App\Modules\Matches\Actions;

/* Define Models */
use App\Modules\Matches\Models\Set;
use App\Modules\Matches\Models\Pool;
use App\Modules\Teams\Models\Team;
use App\Models\User;
/* End Define Models */

/* Define Dependencies */
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
/* End Define Dependencies */

class ShowSetsAction
{
    public function __invoke($data)
    {
        if ($data['command'] == 'all') {
            $sets = Set::where('league_id', $data['league_id'])->orderBy('round', 'asc')->get();
            $sets = $sets->map(function ($set) {
                $set->team1 = Team::where('id', $set->team1_id)->select('id', 'name', 'logo', 'user_id')->first();
                if ($set->team1->logo !== null) {
                    $set->team1->logo = str_replace('\\', '/', Storage::disk('s3-team-logos')->url($set->team1->logo));
                }
                $set->team1->coach = User::where('id', $set->team1->user_id)->select('name')->first();
                $set->team2 = Team::where('id', $set->team2_id)->select('id', 'name', 'logo', 'user_id')->first();
                if ($set->team2->logo !== null) {
                    $set->team2->logo = str_replace('\\', '/', Storage::disk('s3-team-logos')->url($set->team2->logo));
                }
                $set->team2->coach = User::where('id', $set->team2->user_id)->select('name')->first();
                return $set;
            });
            return $sets;
        }
        elseif ($data['command'] == 'round') {
            $sets = Set::where('league_id', $data['league_id'])->where('round', $data['round'])->get();
            return $sets;
        }
        elseif ($data['command'] == 'team') {
            $sets = Set::where('league_id', $data['league_id'])->where('team1_id', $data['team_id'])->orWhere('team2_id', $data['team_id'])->get();
            $sets = $sets->map(function ($set) {
                $set->team1 = Team::where('id', $set->team1_id)->select('id', 'name', 'logo', 'user_id')->first();
                $set->team1->coach = User::where('id', $set->team1->user_id)->select('name')->first();
                $set->team2 = Team::where('id', $set->team2_id)->select('id', 'name', 'logo', 'user_id')->first();
                $set->team2->coach = User::where('id', $set->team2->user_id)->select('name')->first();
                return $set;
            });
            return $sets;
        }
        elseif ($data['command'] == 'team_active') {
            $sets = Set::where('league_id', $data['league_id'])
            ->where('status', 1)
            ->where('team1_id', $data['team_id'])->orWhere('team2_id', $data['team_id'])
            ->orderBy('round', 'asc')->first();
            $sets->team1 = Team::where('id', $sets->team1_id)->select('id', 'name', 'logo', 'user_id')->first();
            $sets->team1->coach = User::where('id', $sets->team1->user_id)->select('name')->first();
            if ($sets->team1->logo !== null) {
                $sets->team1->logo = str_replace('\\', '/', Storage::disk('s3-team-logos')->url($sets->team1->logo));
            }
            $sets->team2 = Team::where('id', $sets->team2_id)->select('id', 'name', 'logo', 'user_id')->first();
            $sets->team2->coach = User::where('id', $sets->team2->user_id)->select('name')->first();
            if ($sets->team2->logo !== null) {
                $sets->team2->logo = str_replace('\\', '/', Storage::disk('s3-team-logos')->url($sets->team2->logo));
            }
            return $sets;
        }
        elseif ($data['command'] == 'team_played') {
            $sets = Set::where('league_id', $data['league_id'])
            ->where('status', 0)
            ->where('team1_id', $data['team_id'])->orWhere('team2_id', $data['team_id'])
            ->orderBy('round', 'asc')->get();
            if ($sets->count() > 1) {
            $sets = $sets->map(function ($set) {
                $set->team1 = Team::where('id', $set->team1_id)->select('id', 'name', 'logo', 'user_id')->first();
                if ($set->team1->logo !== null) {
                    $set->team1->logo = str_replace('\\', '/', Storage::disk('s3-team-logos')->url($set->team1->logo));
                }
                $set->team1->coach = User::where('id', $set->team1->user_id)->select('name')->first();
                $set->team2 = Team::where('id', $set->team2_id)->select('id', 'name', 'logo', 'user_id')->first();
                if ($set->team2->logo !== null) {
                    $set->team2->logo = str_replace('\\', '/', Storage::disk('s3-team-logos')->url($set->team2->logo));
                }
                $set->team2->coach = User::where('id', $set->team2->user_id)->select('name')->first();
                return $set;
            });
            return $sets;
        }
        elseif ($sets->count() == 1) {
            $sets = $sets->first();
            $sets->team1 = Team::where('id', $sets->team1_id)->select('id', 'name', 'logo', 'user_id')->first();
            $sets->team1->coach = User::where('id', $sets->team1->user_id)->select('name')->first();
            if ($sets->team1->logo !== null) {
                $sets->team1->logo = str_replace('\\', '/', Storage::disk('s3-team-logos')->url($sets->team1->logo));
            }
            $sets->team2 = Team::where('id', $sets->team2_id)->select('id', 'name', 'logo', 'user_id')->first();
            if ($sets->team2->logo !== null) {
                $sets->team2->logo = str_replace('\\', '/', Storage::disk('s3-team-logos')->url($sets->team2->logo));
            }
            $sets->team2->coach = User::where('id', $sets->team2->user_id)->select('name')->first();
            return $sets;
        }
        else {
            return null;
        }
        }

        elseif ($data['command'] == 'team_next') {
            $sets = Set::where('league_id', $data['league_id'])
            ->where('status', 1)
            ->where('team1_id', $data['team_id'])->orWhere('team2_id', $data['team_id'])
            ->orderBy('round', 'asc')->first();
            if ($sets) {
            $sets->team1 = Team::where('id', $sets->team1_id)->select('id', 'name', 'logo', 'user_id')->first();
            if ($sets->team1->logo !== null) {
                $sets->team1->logo = str_replace('\\', '/', Storage::disk('s3-team-logos')->url($sets->team1->logo));
            }
            $sets->team1->coach = User::where('id', $sets->team1->user_id)->select('name')->first();
            $sets->team2 = Team::where('id', $sets->team2_id)->select('id', 'name', 'logo', 'user_id')->first();
            if ($sets->team2->logo !== null) {
                $sets->team2->logo = str_replace('\\', '/', Storage::disk('s3-team-logos')->url($sets->team2->logo));
            }
            $sets->team2->coach = User::where('id', $sets->team2->user_id)->select('name')->first();
            return $sets;
            }
            else {
                return null;
            }
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