<?php

namespace App\Modules\V2\Matches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Kernel\Contracts\PoolOperations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PoolController extends Controller
{
    public function create(Request $request, PoolOperations $poolOperations): RedirectResponse
    {
        $leagueId = $poolOperations->createPools((int) $request->integer('league_id'));

        return redirect()->route('leagues.detail', ['league' => $leagueId]);
    }

    public function assignTeamsToPools(Request $request, PoolOperations $poolOperations): RedirectResponse
    {
        $leagueId = $poolOperations->assignTeamsToPools((int) $request->integer('league_id'));

        return redirect()->route('leagues.detail', ['league' => $leagueId]);
    }
}
