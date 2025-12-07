<?php

namespace App\Modules\Teams\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Teams\Actions\CreateEditTeamAction;
use App\Modules\Teams\Models\Team;
use App\Modules\League\Models\League;
use App\Modules\Teams\Actions\ReadTeamAction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

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

        return redirect()->route('teams.detail', ['team_id' => $team->id]);
    }

    public function show(Request $request, ReadTeamAction $readTeamAction)
    {
        $team = $readTeamAction(['team_id' => $request->team_id, 'command' => 'team']);
        $league = League::find($team->league_id);
        return Inertia::render('teams/TeamDetail', [
            'league' => $league,
            'team' => $team,
        ]);
    }

    public function edit(Request $request)
    {
        $team = (new CreateEditTeamAction)->edit($request);
        return redirect()->route('leagues.detail', ['league' => $team->league_id]);
    }
}
