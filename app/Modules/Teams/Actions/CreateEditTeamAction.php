<?php

namespace App\Modules\Teams\Actions;

use App\Modules\Teams\Models\Team;
use App\Modules\League\Models\League;
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
        $draftPoints = League::where('id', $request->league_id)->select('draft_points')->first();
        $draftPoints = $draftPoints->draft_points;
        $team = Team::create([
            'name' => $request->name,
            'league_id' => $request->league_id,
            'user_id' => $request->user_id,
            'logo' => $logo,
            'pick_position' => $request->pick_position,
            'draft_points' => $draftPoints,
        ]);

        return $team;
    }
}
