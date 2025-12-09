<?php

namespace App\Modules\Matches\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matches\Actions\CreateEditSetsAction;
use App\Modules\Matches\Actions\ShowSetsAction;
use App\Modules\Matches\Models\Set;
use App\Modules\Teams\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SetController extends Controller
{
    public function create(Request $request, CreateEditSetsAction $createEditSetsAction)
    {
        $sets = $createEditSetsAction(['league_id' => $request->league_id, 'command' => 'create']);

        return redirect()->route('leagues.detail', ['league' => $request->league_id]);
    }

    public function show($match_id, ShowSetsAction $showSetsAction)
    {
        $set = $showSetsAction(['set_id' => $match_id, 'command' => 'detail']);
        $CurrentUserTeam = Team::where('user_id', Auth::user()->id)->where('league_id', $set->league_id)->first();
        if (! $set) {
            abort(404, 'Set not found');
        }

        return Inertia::render('match/MatchDetail', [
            'set' => fn () => $set,
            'currentUserTeam' => fn () => $CurrentUserTeam,
        ]);
    }

    public function update(Request $request, CreateEditSetsAction $createEditSetsAction)
    {
        $data = collect($request);
        $set = Set::where('id', $request->set_id)->first();
        $result = $createEditSetsAction($data->toArray());
        return redirect()->route('sets.show', ['set_id' => $request->set_id]);
    }

    public function updatePokepaste(Request $request, CreateEditSetsAction $createEditSetsAction)
    {
        $data = collect($request);
        $set = Set::where('id', $request->set_id)->first();
        $result = $createEditSetsAction($data->toArray());
        return redirect()->route('sets.show', ['set_id' => $request->set_id]);
    }
}
