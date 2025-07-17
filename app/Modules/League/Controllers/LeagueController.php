<?php

namespace App\Modules\League\Controllers;

use App\Modules\League\Models\League;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use App\Modules\League\Actions\CreateLeague;

class LeagueController extends Controller
{
    public function index()
    {
        $currentLeagues = League::where('status', 1)->get();
        return Inertia::render('league/LeagueIndex', [
            'currentLeagues' => $currentLeagues
        ]);
    }

    public function show(League $league)
    {
        return Inertia::render('league/LeagueDetail', [
            'league' => $league
        ]);
    }

    public function create(Request $request)
    {
        $league = (new CreateLeague())->create($request);
        return redirect()->route('leagues.index', ['league_id' => $league->id]);
    }
}