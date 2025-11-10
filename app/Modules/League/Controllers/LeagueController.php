<?php

namespace App\Modules\League\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\League\Actions\CreateEditLeagueAction;
use App\Modules\League\Actions\ReadLeagueDraftAction;
use App\Modules\League\Models\League;
use App\Modules\League\Actions\ReadLeaguePokemonAction;
use App\Modules\Teams\Actions\ReadTeamAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LeagueController extends Controller
{
    public function index()
    {
        $currentLeagues = League::where('status', 1)->get();
        $currentLeaguesUrl = $currentLeagues->map(function ($league) {
            if ($league->logo !== null) {
                $league->logo = str_replace('\\', '/', Storage::disk('s3-league-logos')->url($league->logo));
            }

            return $league;
        });

        return Inertia::render('league/LeagueIndex', [
            'currentLeagues' => $currentLeaguesUrl,
        ]);
    }

    public function show(League $league, ReadTeamAction $readTeamAction, ReadLeaguePokemonAction $readLeaguePokemonAction, ReadLeagueDraftAction $readLeagueDraftAction)
    {
        $pokemon = $readLeaguePokemonAction(['league_id' => $league->id]);
        $teams = $readTeamAction(['league_id' => $league->id, 'command' => 'league']);
        $adminflag = Team::where('league_id', $league->id)->where('user_id', Auth::user()->id)->first();
        if ($adminflag) {
            $adminflag = $adminflag->admin_flag;
        } else {
            $adminflag = 0;
        }
        Log::info("adminflag: ".$adminflag);
        return Inertia::render('league/LeagueDetail', [
            'league' => $league,
            'teams' => $teams,
            'pokemon' => $pokemon,
            'costHeaders' => $pokemon->unique('cost')->pluck('cost'),
            'draft' => $readLeagueDraftAction(['league_id' => $league->id]),
            'adminFlag' => $adminflag,
        ]);
    }

    public function create(Request $request)
    {
        $league = (new CreateEditLeagueAction)->create($request);

        return redirect()->route('leagues.detail', ['league' => $league->id]);
    }
}
