<?php

namespace App\Modules\V2\Matches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Match\SendMatchMessageRequest;
use App\Kernel\Contracts\MatchSetOperations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MatchMessageController extends Controller
{
    public function index(int $set, MatchSetOperations $matchSetOperations): JsonResponse
    {
        return response()->json($matchSetOperations->listMessages($set));
    }

    public function store(SendMatchMessageRequest $request, int $set, MatchSetOperations $matchSetOperations): RedirectResponse
    {
        $user = Auth::user();
        abort_if($user === null, 403);

        $matchSetOperations->storeMessage($set, (int) $user->id, $request->validated('body'));

        return redirect()->back();
    }

    public function markRead(Request $request, int $set, MatchSetOperations $matchSetOperations): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return response()->json($matchSetOperations->markMessagesRead($set, (int) $user->id));
    }
}
