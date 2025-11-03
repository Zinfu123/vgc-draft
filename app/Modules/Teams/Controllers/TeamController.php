<?php

namespace App\Modules\Teams\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Teams\Actions\CreateEditTeamAction;
use App\Modules\Teams\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        $teams = Team::where('league_id', $request->league_id)->get();

        return Inertia::render('teams/TeamIndex', [
            'Teams' => $teams,
        ]);
    }

    public function create(Request $request)
    {
        $pick_position = Team::where('league_id', $request->league_id)->count() + 1;
        $request->merge(['pick_position' => $pick_position]);
        $team = (new CreateEditTeamAction)->create($request);

        return redirect()->route('teams.index', ['league_id' => $request->league_id, 'team_id' => $team->id]);
    }
}
