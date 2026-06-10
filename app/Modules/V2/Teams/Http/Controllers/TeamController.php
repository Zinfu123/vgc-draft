<?php

namespace App\Modules\V2\Teams\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Kernel\Contracts\TeamOperations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request, TeamOperations $teamOperations): Response
    {
        $leagueId = $request->integer('league_id');

        return Inertia::render('teams/TeamIndex', [
            'Teams' => $teamOperations->teamsForLeague($leagueId),
        ]);
    }

    public function create(Request $request, TeamOperations $teamOperations): RedirectResponse
    {
        $leagueId = $teamOperations->createTeam($request);

        return redirect()->route('leagues.dashboard', ['league' => $leagueId]);
    }

    public function show(int $team_id, TeamOperations $teamOperations): RedirectResponse
    {
        $target = $teamOperations->showRedirectTarget($team_id);

        return redirect()->route('leagues.dashboard', [
            'league' => $target['league_id'],
            'team' => $target['team_id'],
        ]);
    }

    public function edit(Request $request, int $team_id, TeamOperations $teamOperations): RedirectResponse
    {
        $leagueId = $teamOperations->editTeam($request, $team_id);

        return redirect()->route('leagues.dashboard', ['league' => $leagueId]);
    }
}
