<?php

namespace App\Modules\League\Actions;

use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Models\League;
use App\Modules\Matches\Models\MatchConfig;
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
            'ban_enabled' => 'boolean',
            'bans_per_user' => 'nullable|integer|min:1',
            'minimum_cost_to_ban' => 'nullable|integer|min:0',
        ]);
        if ($request->hasFile('logo')) {
            $logo = (new LeagueLogoUploadAction)->upload($request);
        } else {
            $logo = null;
        }
        $league = League::create([
            'name' => $request->name,
            'set_start_date' => $request->set_start_date,
            'set_frequency' => $request->set_frequency,
            'logo' => $logo,
            'league_owner' => Auth::user()->id,
        ]);

        DraftConfig::create([
            'league_id' => $league->id,
            'draft_date' => $request->draft_date,
            'draft_points' => $request->draft_points,
            'ban_enabled' => $request->boolean('ban_enabled'),
            'bans_per_user' => $request->ban_enabled ? $request->bans_per_user : null,
            'minimum_cost_to_ban' => $request->ban_enabled ? $request->minimum_cost_to_ban : null,
        ]);

        MatchConfig::updateOrCreate(
            ['league_id' => $league->id],
            [
                'enforce_round_count' => $request->boolean('enforce_round_count'),
                'round_count' => $request->enforce_round_count ? $request->round_count : null,
            ]
        );

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
            'ban_enabled' => 'boolean',
            'bans_per_user' => 'nullable|integer|min:1',
            'minimum_cost_to_ban' => 'nullable|integer|min:0',
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
        $league->set_start_date = $request->set_start_date;
        $league->set_frequency = $request->set_frequency;
        $league->logo = $logo ?? $league->logo;
        $league->save();

        $league->draftConfig()->updateOrCreate(
            ['league_id' => $league->id],
            [
                'draft_date' => $request->draft_date,
                'draft_points' => $request->draft_points,
                'minimum_drafts' => $request->minimum_drafts,
                'ban_enabled' => $request->boolean('ban_enabled'),
                'bans_per_user' => $request->ban_enabled ? $request->bans_per_user : null,
                'minimum_cost_to_ban' => $request->ban_enabled ? $request->minimum_cost_to_ban : null,
            ]
        );

        $league->matchConfig()->updateOrCreate(
            ['league_id' => $league->id],
            [
                'enforce_round_count' => $request->boolean('enforce_round_count'),
                'round_count' => $request->enforce_round_count ? $request->round_count : null,
            ]
        );

        return $league;
    }
}
