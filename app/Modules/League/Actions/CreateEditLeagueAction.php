<?php

namespace App\Modules\League\Actions;

use App\Modules\League\Models\League;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CreateEditLeagueAction
{
    public function __invoke($data)
    {
        if ($data['command'] == 'create') {
            return $this->create($data);
        } elseif ($data['command'] == 'edit') {
            return $this->edit($data);
        }
    }

    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'draft_date' => 'required|date',
            'set_start_date' => 'required|date',
            'set_frequency' => 'required|integer',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'draft_points' => 'required|integer',
            'enforce_round_count' => 'required|boolean',
            'round_count' => 'required|integer',
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
            'enforce_round_count' => $request->enforce_round_count,
            'round_count' => $request->round_count,
        ]);

        return $league;
    }

    public function edit(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'draft_date' => 'required|date',
            'set_start_date' => 'required|date',
            'set_frequency' => 'required|integer',
            'enforce_round_count' => 'required|boolean',
            'round_count' => 'required|integer',
            'draft_points' => 'required|integer',
            'minimum_drafts' => 'required|integer',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);
        $league = League::where('id', $request->league_id)->first();
        if ($request->hasFile('logo')) {
            $oldlogo = $league->logo;
            if ($oldlogo !== null) {
                Storage::disk('s3-league-logos')->delete($oldlogo);
            }
            $logo = (new LeagueLogoUploadAction)->upload($request);
        }
        $league->name = $request->name;
        $league->draft_date = $request->draft_date;
        $league->set_start_date = $request->set_start_date;
        $league->set_frequency = $request->set_frequency;
        $league->enforce_round_count = $request->enforce_round_count;
        $league->round_count = $request->round_count;
        $league->draft_points = $request->draft_points;
        $league->minimum_drafts = $request->minimum_drafts;
        $league->logo = $logo;
        $league->save();

        return $league;
    }
}
