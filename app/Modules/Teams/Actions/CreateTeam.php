<?php

namespace App\Modules\Teams\Actions;

use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Actions\TeamLogoUpload;
use Illuminate\Http\Request;

class CreateTeam
{
    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'league_id' => 'required|exists:leagues,id',
            'user_id' => 'required|exists:users,id',
        ]);
        $logo = (new TeamLogoUpload())->upload($request->file('logo'));
        $request->merge(['logo' => $logo]);
        $team = Team::create($request->all());
        return $team;
    }
}
