<?php

namespace App\Modules\Teams\Actions;

use App\Modules\League\Models\League;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class ReadTeamsAction
{
    public function __invoke($data)
    {
        $teams = League::find($data)->teams;
        $teams = $teams->map(function ($team) {
            if ($team->logo !== null) {
                $team->logo = str_replace('\\', '/', Storage::disk('s3-team-logos')->url( $team->logo));
            }
            return $team;
        });
        $teams = $teams->map(function ($team) {
            $team->coach = User::find($team->user_id)->name;
            return $team;
        });
        Log::info($teams);
        return $teams;

    }
}