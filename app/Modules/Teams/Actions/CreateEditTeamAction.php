<?php

namespace App\Modules\Teams\Actions;

use App\Modules\Teams\Models\Team;
use Illuminate\Http\Request;

class CreateEditTeamAction
{
    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'league_id' => 'required|exists:leagues,id',
            'user_id' => 'required|exists:users,id',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'pick_position' => 'required|integer',
        ]);
        if ($request->hasFile('logo')) {
            $logo = (new TeamLogoUploadAction)->upload($request);
        } else {
            $logo = null;
        }
        $team = Team::create([
            'name' => $request->name,
            'league_id' => $request->league_id,
            'user_id' => $request->user_id,
            'logo' => $logo,
            'pick_position' => $request->pick_position,
        ]);

        return $team;
    }
}
