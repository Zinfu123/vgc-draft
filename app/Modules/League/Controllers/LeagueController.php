<?php

namespace App\Modules\League\Controllers;

use App\Modules\League\Models\League;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use App\Modules\League\Actions\CreateEditLeagueAction;
use Illuminate\Support\Facades\Storage;
use App\Modules\Teams\Actions\ReadTeamsAction;

class LeagueController extends Controller
{
    public function index()
    {
        $currentLeagues = League::where('status', 1)->get();
        $currentLeaguesUrl = $currentLeagues->map(function ($league) {
            if ($league->logo !== null) {
                $league->logo = str_replace('\\', '/', Storage::disk('s3-league-logos')->url( $league->logo));
            }
            return $league;
        });
        Log::info($currentLeaguesUrl);
        return Inertia::render('league/LeagueIndex', [
            'currentLeagues' => $currentLeaguesUrl,
        ]);
    }

    public function show(League $league, ReadTeamsAction $readTeamsAction)
    {
        return Inertia::render('league/LeagueDetail', [
            'league' => $league,
            'teams' => $readTeamsAction($league->id),
        ]);
    }

    public function create(Request $request)
    {
        $league = (new CreateEditLeagueAction())->create($request);
        return redirect()->route('leagues.detail', ['league' => $league->id]);
    }
}