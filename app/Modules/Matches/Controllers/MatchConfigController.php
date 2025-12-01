<?php

namespace App\Modules\Matches\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matches\Models\MatchConfig;
use Illuminate\Http\Request;
use App\Modules\Matches\Actions\CreateEditMatchConfigAction;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class MatchConfigController extends Controller
{
    public function createEditShow(Request $request, CreateEditMatchConfigAction $createEditMatchConfigAction)
    {
        $matchConfig = $createEditMatchConfigAction($request);
        if ($request->command == 'show') {
            return inertia('MatchConfigDetail', [
                'matchConfig' => $matchConfig,
            ]);
        }
        else {
            return inertia::render('league/LeagueDetail', [
                'league' => $request->league_id,
            ]);
        }
    }

}