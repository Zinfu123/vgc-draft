<?php

namespace App\Modules\League\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\League\Actions\CreateEditLeagueAction;
use App\Modules\League\Actions\ReadLeagueDraftAction;
use App\Modules\League\Actions\ReadLeaguePokemonAction;
use App\Modules\League\Models\League;
use App\Modules\Matches\Actions\ShowSetsAction;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Teams\Actions\ReadTeamAction;
use App\Modules\Teams\Models\Team;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
class LeagueController extends Controller
{
    public function index()
    {
        $currentLeagues = League::where('status', 1)->get();
        $currentLeaguesUrl = $currentLeagues->map(function ($league) {
            if ($league->logo !== null) {
                /** @var FilesystemAdapter $disk */
                $disk = Storage::disk('s3-league-logos');
                $league->logo = str_replace('\\', '/', $disk->url($league->logo));
            }

            return $league;
        });

        return Inertia::render('league/LeagueIndex', [
            'currentLeagues' => $currentLeaguesUrl,
        ]);
    }

    public function show(League $league, ReadTeamAction $readTeamAction, ReadLeaguePokemonAction $readLeaguePokemonAction, ReadLeagueDraftAction $readLeagueDraftAction, ShowSetsAction $showSetsAction)
    {
        $pokemon = $readLeaguePokemonAction(['league_id' => $league->id, 'command' => 'available']);
        $pokemon_drafted = $readLeaguePokemonAction(['league_id' => $league->id, 'command' => 'draftedpokemon']);
        $teams = $readTeamAction(['league_id' => $league->id, 'command' => 'league']);
        $user_team = Team::where('user_id', Auth::user()->id)->where('league_id', $league->id)->select('id', 'admin_flag')->first();
        $adminflag = $user_team ? $user_team->admin_flag : 0;
        $match_config = MatchConfig::where('league_id', $league->id)->first();
        $played_sets = $showSetsAction(['league_id' => $league->id, 'command' => 'played']);
        $upcoming_sets = $showSetsAction(['league_id' => $league->id, 'command' => 'upcoming']);
        $team_next = $showSetsAction(['league_id' => $league->id, 'command' => 'team_next', 'team_id' => $user_team?->id]);
        $standings = $readTeamAction(['league_id' => $league->id, 'command' => 'standings']);
        Log::info($standings);
        if ($match_config === null) {
            $match_config = (object) [
                'id' => 0,
                'league_id' => $league->id,
                'number_of_pools' => 0,
                'wins_required' => 0,
                'frequency_type' => 0,
                'frequency_value' => 0,
                'duration' => 0,
                'status' => 0,
            ];
        }

        // Convert logo to full URL if it exists
        if ($league->logo !== null) {
            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk('s3-league-logos');
            $league->logo = str_replace('\\', '/', $disk->url($league->logo));
        }

        return Inertia::render('league/LeagueDetail', [
            'league' => fn () => $league,
            'teams' => fn () => $teams,
            'pokemon' => fn () => $pokemon,
            'pokemon_drafted' => fn () => $pokemon_drafted,
            'costHeaders' => fn () => $pokemon->unique('cost')->pluck('cost'),
            'draft' => fn () => $readLeagueDraftAction(['league_id' => $league->id]),
            'user_team' => fn () => $user_team,
            'adminFlag' => fn () => $adminflag,
            'matchConfig' => fn () => $match_config,
            'played_sets' => fn () => $played_sets,
            'upcoming_sets' => fn () => $upcoming_sets,
            'team_next' => fn () => $team_next,
            'standings' => fn () => $standings,
        ]);
    }

    public function create(Request $request)
    {
        $league = (new CreateEditLeagueAction)->create($request);

        return redirect()->route('leagues.detail', ['league' => $league->id]);
    }
}
