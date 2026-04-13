<?php

namespace App\Modules\Teams\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Teams\Actions\CreateEditTeamAction;
use App\Modules\Teams\Actions\ReadTeamAction;
use App\Modules\Teams\Models\Team;
use Illuminate\Http\Request;

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

        return redirect()->route('leagues.dashboard', ['league' => $team->league_id]);
    }

    public function show(Request $request, ReadTeamAction $readTeamAction): \Illuminate\Http\RedirectResponse
    {
        $team = $readTeamAction(['team_id' => $request->team_id, 'command' => 'team']);

        return redirect()->route('leagues.dashboard', ['league' => $team->league_id, 'team' => $team->id]);
    }

    public function edit(Request $request, int $team_id)
    {
        $team = (new CreateEditTeamAction)->edit($request->merge(['team_id' => $team_id]));

        return redirect()->route('leagues.dashboard', ['league' => $team->league_id]);
    }
}
