<?php

namespace App\Modules\Teams\Actions;

use App\Modules\League\Models\League;
use App\Modules\Teams\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        if (team::where('league_id', $request->league_id)->where('user_id', $request->user_id)->exists()) {
            throw new \Exception('Team already exists');
        } else {
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
            $teamcount = Team::where('league_id', $request->league_id)->count();
            if ($teamcount == 1) {
                $team->admin_flag = 1;
                $team->save();
            }
        }

        return $team;
    }

    public function edit(Request $request)
    {
        $team = Team::where('id', $request->team_id)->first();
        $team->name = $request->name;
        $team->save();
        if ($request->hasFile('logo')) {
            if ($team->logo !== null) {
                $oldlogo = $team->logo;
                Storage::disk('s3-team-logos')->delete($oldlogo);
            }
            $logo = (new TeamLogoUploadAction)->upload($request);
            $team->logo = $logo;
        }
        $team->save();

        return $team;
    }
}
