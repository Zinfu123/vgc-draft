<?php

namespace App\Modules\League\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\League\Actions\CreateEditLeagueAction;
use App\Modules\League\Actions\LeagueDetailLayoutDataAction;
use App\Modules\League\Actions\ReadLeagueAction;
use App\Modules\League\Models\League;
use App\Modules\Matches\Actions\ShowSetsAction;
use App\Modules\Teams\Actions\ReadTeamAction;
use App\Modules\Teams\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LeagueController extends Controller
{
    public function index(ReadLeagueAction $readLeagueAction)
    {
        $currentLeagues = $readLeagueAction(['command' => 'active']);
        $pastLeagues = $readLeagueAction(['command' => 'past']);

        return Inertia::render('league/LeagueIndex', [
            'currentLeagues' => $currentLeagues,
            'pastLeagues' => $pastLeagues,
        ]);
    }

    public function show(League $league)
    {
        return redirect()->route('leagues.matches', ['league' => $league->id]);
    }

    public function showTeams(League $league, LeagueDetailLayoutDataAction $leagueDetailLayoutDataAction)
    {
        return Inertia::render('league/LeagueDetailTeams', [
            ...$leagueDetailLayoutDataAction($league),
            'section' => 'teams',
        ]);
    }

    public function showMatches(League $league, LeagueDetailLayoutDataAction $leagueDetailLayoutDataAction, ShowSetsAction $showSetsAction)
    {
        $user_team = Team::where('user_id', Auth::user()->id)->where('league_id', $league->id)->select('id')->first();

        return Inertia::render('league/LeagueDetailMatches', [
            ...$leagueDetailLayoutDataAction($league),
            'section' => 'matches',
            'played_sets' => $showSetsAction(['league_id' => $league->id, 'command' => 'played']),
            'upcoming_sets' => $showSetsAction(['league_id' => $league->id, 'command' => 'upcoming']),
            'team_next' => $showSetsAction(['league_id' => $league->id, 'command' => 'team_next', 'team_id' => $user_team?->id]),
        ]);
    }

    public function showStandings(League $league, LeagueDetailLayoutDataAction $leagueDetailLayoutDataAction, ReadTeamAction $readTeamAction)
    {
        return Inertia::render('league/LeagueDetailStandings', [
            ...$leagueDetailLayoutDataAction($league),
            'section' => 'standings',
            'standings' => $readTeamAction(['league_id' => $league->id, 'command' => 'standings']),
        ]);
    }

    public function showTrades(League $league, LeagueDetailLayoutDataAction $leagueDetailLayoutDataAction)
    {
        return Inertia::render('league/LeagueDetailTrades', [
            ...$leagueDetailLayoutDataAction($league),
            'section' => 'trades',
        ]);
    }

    public function showDraft(League $league, LeagueDetailLayoutDataAction $leagueDetailLayoutDataAction)
    {
        return Inertia::render('league/LeagueDetailDraft', [
            ...$leagueDetailLayoutDataAction($league),
            'section' => 'draft',
        ]);
    }

    public function create(Request $request)
    {
        $league = (new CreateEditLeagueAction)->create($request);

        return redirect()->route('leagues.matches', ['league' => $league->id]);
    }

    public function setWinner(Request $request, League $league)
    {
        $request->validate([
            'winner_user_id' => 'required|integer|exists:users,id',
        ]);

        $league->winner = $request->winner_user_id;
        $league->status = 0;
        $league->save();

        return back();
    }

    public function createEditShow(Request $request, ReadLeagueAction $readLeagueAction)
    {
        $league = $readLeagueAction(['league_id' => $request->league_id, 'command' => 'league']);

        return Inertia::render('league/LeagueCreateEdit', [
            'command' => $request->command,
            'league_id' => $request->league_id ?? 0,
            'league_name' => $league->name ?? '',
            'draft_date' => $league->draft_date ?? null,
            'set_start_date' => $league->set_start_date ?? null,
            'set_frequency' => $league->set_frequency ?? 3,
            'enforce_round_count' => (bool) ($league->enforce_round_count ?? false),
            'round_count' => $league->round_count ?? null,
            'draft_points' => $league->draft_points ?? 80,
            'minimum_drafts' => $league->minimum_drafts ?? 1,
            'logo' => $league->logo ?? null,
        ]);
    }
}
