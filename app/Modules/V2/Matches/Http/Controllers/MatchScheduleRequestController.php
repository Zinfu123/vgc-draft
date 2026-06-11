<?php

namespace App\Modules\V2\Matches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Match\RespondMatchScheduleRequestRequest;
use App\Http\Requests\Match\StoreMatchScheduleRequestRequest;
use App\Kernel\Contracts\MatchSetOperations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class MatchScheduleRequestController extends Controller
{
    public function store(StoreMatchScheduleRequestRequest $request, int $set, MatchSetOperations $matchSetOperations): RedirectResponse
    {
        $user = Auth::user();
        abort_if($user === null, 403);

        $result = $matchSetOperations->storeScheduleRequest(
            $set,
            (int) $user->id,
            $request->validated('proposed_at'),
        );

        return redirect()->route('sets.show', ['set_id' => $result['set_id']])
            ->with('success', $result['flash']['success']);
    }

    public function respond(RespondMatchScheduleRequestRequest $request, int $scheduleRequest, MatchSetOperations $matchSetOperations): RedirectResponse
    {
        $user = Auth::user();
        abort_if($user === null, 403);

        $result = $matchSetOperations->respondScheduleRequest(
            $scheduleRequest,
            (int) $user->id,
            $request->validated(),
        );

        return redirect()->route('sets.show', ['set_id' => $result['set_id']])
            ->with('success', $result['flash']['success']);
    }
}
