<?php

namespace App\Modules\Dashboard\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dashboard\Actions\ReadDashboardAction;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(ReadDashboardAction $readDashboardAction)
    {
        $userId = auth()->id();

        return Inertia::render('Dashboard', [
            'usersActiveLeagues' => $readDashboardAction([
                'command' => 'usersActiveLeagues',
                'user_id' => $userId,
            ]),
            'usersPastLeagues' => $readDashboardAction([
                'command' => 'usersPastLeagues',
                'user_id' => $userId,
            ]),
            'openLeagues' => $readDashboardAction([
                'command' => 'openLeagues',
                'user_id' => $userId,
            ]),
        ]);
    }
}
