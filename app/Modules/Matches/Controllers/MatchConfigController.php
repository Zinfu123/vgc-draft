<?php

namespace App\Modules\Matches\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matches\Actions\CreateEditMatchConfigAction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MatchConfigController extends Controller
{
    public function createEditShow(Request $request, CreateEditMatchConfigAction $createEditMatchConfigAction)
    {
        $matchConfig = $createEditMatchConfigAction($request);
        if ($request->command == 'show') {
            return inertia('MatchConfigDetail', [
                'matchConfig' => $matchConfig,
            ]);
        } else {
            return inertia::render('league/LeagueDetail', [
                'league' => $request->league_id,
            ]);
        }
    }
}
