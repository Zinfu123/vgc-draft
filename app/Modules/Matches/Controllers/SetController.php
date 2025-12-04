<?php

namespace App\Modules\Matches\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matches\Models\Set;
use App\Modules\Matches\Actions\CreateEditSetsAction;
use App\Modules\Matches\Actions\ShowSetsAction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class SetController extends Controller
{
    public function index(Request $request)
    {
    }

    public function create(Request $request, CreateEditSetsAction $createEditSetsAction)
    {
        $sets = $createEditSetsAction(['league_id' => $request->league_id, 'command' => 'create']);
        return redirect()->route('leagues.detail', ['league' => $request->league_id]);
    }

    public function show($match_id, ShowSetsAction $showSetsAction)
    {
        $set = $showSetsAction(['match_id' => $match_id, 'command' => 'detail']);
        
        if (!$set) {
            abort(404, 'Match not found');
        }
        
        return Inertia::render('match/SetDetail', [
            'set' => $set,
        ]);
    }

}