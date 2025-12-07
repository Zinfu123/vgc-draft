<?php

namespace App\Modules\Matches\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matches\Actions\CreateEditPoolAction;
use App\Modules\Matches\Actions\TeamsToPoolsAction;
use App\Modules\Matches\Models\Pool;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PoolController extends Controller
{
    public function show(Request $request)
    {
        $pool = Pool::where('id', $request->pool_id)->first();

        return Inertia::render('pools/PoolDetail', [
            'pool' => $pool,
        ]);
    }

    public function create(Request $request, CreateEditPoolAction $createEditPoolAction)
    {
        $pools = $createEditPoolAction(['league_id' => $request->league_id, 'command' => 'create']);

        return redirect()->route('leagues.detail', ['league' => $request->league_id]);
    }

    public function assignTeamsToPools(Request $request, TeamsToPoolsAction $teamsToPoolsAction)
    {
        $teamsToPoolsAction(['league_id' => $request->league_id]);

        return redirect()->route('leagues.detail', ['league' => $request->league_id]);
    }
}
