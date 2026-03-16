<?php

namespace App\Modules\Dashboard\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dashboard\Actions\ReadDashboardAction;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(ReadDashboardAction $readDashboardAction)
    {
        $user = auth()->user();
        $userId = $user->id;

        return Inertia::render('Dashboard', [
            'userName' => $user->name,
            'userStats' => $readDashboardAction([
                'command' => 'userStats',
                'user_id' => $userId,
            ]),
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
