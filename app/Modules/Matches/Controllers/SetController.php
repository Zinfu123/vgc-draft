<?php

namespace App\Modules\Matches\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Match\UpdateSetRequest;
use App\Modules\Matches\Actions\CreateEditSetsAction;
use App\Modules\Matches\Actions\ShowSetsAction;
use App\Modules\Pokepaste\Actions\ReadMatchPokepastePayloadAction;
use App\Modules\Pokepaste\Actions\ReadMatchPokepasteSideSummariesAction;
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

    public function show(
        $match_id,
        ShowSetsAction $showSetsAction,
        ReadMatchPokepastePayloadAction $readMatchPokepastePayloadAction,
        ReadMatchPokepasteSideSummariesAction $readMatchPokepasteSideSummariesAction,
    ) {
        $set = $showSetsAction(['set_id' => $match_id, 'command' => 'detail']);
        if (! $set) {
            abort(404, 'Set not found');
        }

        $currentUserTeam = Team::query()
            ->where('user_id', Auth::id())
            ->where('league_id', $set->league_id)
            ->first();

        $isTeam1 = $currentUserTeam !== null && $currentUserTeam->id === $set->team1_id;
        $isTeam2 = $currentUserTeam !== null && $currentUserTeam->id === $set->team2_id;
        if ($isTeam1 && ! $isTeam2) {
            $set->setAttribute('team2_pokepaste', null);
        } elseif ($isTeam2 && ! $isTeam1) {
            $set->setAttribute('team1_pokepaste', null);
        } else {
            $set->setAttribute('team1_pokepaste', null);
            $set->setAttribute('team2_pokepaste', null);
        }

        $matchPokepaste = null;
        if ($currentUserTeam !== null
            && ($currentUserTeam->id === $set->team1_id || $currentUserTeam->id === $set->team2_id)) {
            $matchPokepaste = $readMatchPokepastePayloadAction($set, $currentUserTeam);
        }

        return Inertia::render('match/MatchDetail', [
            'set' => fn () => $set,
            'currentUserTeam' => fn () => $currentUserTeam,
            'matchPokepaste' => fn () => $matchPokepaste,
            'matchPokepasteSides' => fn () => $readMatchPokepasteSideSummariesAction($set),
        ]);
    }

    public function update(UpdateSetRequest $request, CreateEditSetsAction $createEditSetsAction)
    {
        $createEditSetsAction($request->validated());

        return redirect()->route('sets.show', ['set_id' => $request->set_id]);
    }

    public function updatePokepaste(Request $request, CreateEditSetsAction $createEditSetsAction)
    {
        $result = $createEditSetsAction($request->all());

        return redirect()->route('sets.show', ['set_id' => $request->set_id]);
    }

    public function updateReplays(Request $request, CreateEditSetsAction $createEditSetsAction)
    {
        $request->validate([
            'set_id' => 'required|integer|exists:sets,id',
            'replay1' => 'nullable|url|max:500',
            'replay2' => 'nullable|url|max:500',
            'replay3' => 'nullable|url|max:500',
        ]);

        $createEditSetsAction([
            'command' => 'updateReplays',
            'set_id' => $request->set_id,
            'replay1' => $request->replay1,
            'replay2' => $request->replay2,
            'replay3' => $request->replay3,
        ]);

        return redirect()->route('sets.show', ['set_id' => $request->set_id]);
    }
}
