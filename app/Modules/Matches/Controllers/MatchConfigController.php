<?php

namespace App\Modules\Matches\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matches\Actions\CreateEditMatchConfigAction;
use Illuminate\Http\Request;

class MatchConfigController extends Controller
{
    public function createEditShow(Request $request, CreateEditMatchConfigAction $createEditMatchConfigAction)
    {
        $matchConfig = $createEditMatchConfigAction($request);
        if ($request->command == 'show') {
            return inertia('match/MatchConfigDetail', [
                'matchConfig' => $matchConfig,
            ]);
        } else {
            return redirect()->back();
        }
    }
}
