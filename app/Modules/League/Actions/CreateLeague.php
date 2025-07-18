<?php

namespace App\Modules\League\Actions;

use App\Modules\League\Models\League;
use Illuminate\Http\Request;
use App\Modules\League\Actions\LeagueLogoUpload;


class CreateLeague
{
    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'draft_date' => 'required|date',
            'set_start_date' => 'required|date',
            'set_frequency' => 'required|integer',
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);
        $league = League::create([
            'name' => $request->name,
            'draft_date' => $request->draft_date,
            'set_start_date' => $request->set_start_date,
            'set_frequency' => $request->set_frequency,
            'logo' => (new LeagueLogoUpload())->upload($request),
        ]);
        return $league;
    }
}