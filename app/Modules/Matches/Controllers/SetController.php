<?php

namespace App\Modules\Matches\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matches\Models\Set;
use App\Modules\Matches\Actions\CreateEditSetsAction;
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

}