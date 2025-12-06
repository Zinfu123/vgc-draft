<?php

namespace App\Modules\League\Actions;

use App\Modules\League\Models\League;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CreateEditLeagueAction
{
    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'draft_date' => 'required|date',
            'set_start_date' => 'required|date',
            'set_frequency' => 'required|integer',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'draft_points' => 'required|integer',
        ]);
        if ($request->hasFile('logo')) {
            $logo = (new LeagueLogoUploadAction)->upload($request);
        } else {
            $logo = null;
        }
        $league = League::create([
            'name' => $request->name,
            'draft_date' => $request->draft_date,
            'set_start_date' => $request->set_start_date,
            'set_frequency' => $request->set_frequency,
            'logo' => $logo,
            'league_owner' => Auth::user()->id,
            'draft_points' => $request->draft_points,
        ]);

        return $league;
    }
}
