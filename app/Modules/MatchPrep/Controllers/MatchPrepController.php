<?php

namespace App\Modules\MatchPrep\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\MatchPrep\UpdateMatchPrepShareRequest;
use App\Http\Requests\MatchPrep\UpsertMatchPrepNoteRequest;
use App\Modules\Matches\Models\Set;
use App\Modules\MatchPrep\Actions\ReadMatchPrepIndexPayloadAction;
use App\Modules\MatchPrep\Actions\ReadMatchPrepSharePayloadAction;
use App\Modules\MatchPrep\Actions\UpdateMatchPrepShareAction;
use App\Modules\MatchPrep\Actions\UpsertMatchPrepNoteAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MatchPrepController extends Controller
{
    public function index(Request $request, ReadMatchPrepIndexPayloadAction $readMatchPrepIndexPayloadAction): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return Inertia::render('match-prep/MatchPrepIndex', $readMatchPrepIndexPayloadAction($user, $request));
    }

    public function update(
        UpsertMatchPrepNoteRequest $request,
        Set $set,
        UpsertMatchPrepNoteAction $upsertMatchPrepNoteAction,
    ): RedirectResponse {
        $user = $request->user();
        abort_if($user === null, 403);

        $upsertMatchPrepNoteAction($user, $set, $request->validated());

        return redirect()->route('match-prep.index', ['league_id' => $set->league_id]);
    }

    public function updateShare(
        UpdateMatchPrepShareRequest $request,
        Set $set,
        UpdateMatchPrepShareAction $updateMatchPrepShareAction,
    ): RedirectResponse {
        $user = $request->user();
        abort_if($user === null, 403);

        $updateMatchPrepShareAction(
            $user,
            $set,
            $request->boolean('share_enabled'),
            $request->boolean('regenerate_uuid'),
        );

        return redirect()->route('match-prep.index', ['league_id' => $set->league_id]);
    }

    public function showShare(string $shareUuid, ReadMatchPrepSharePayloadAction $readMatchPrepSharePayloadAction): Response
    {
        $payload = $readMatchPrepSharePayloadAction($shareUuid);
        abort_if($payload === null, 404);

        return Inertia::render('match-prep/MatchPrepShareShow', $payload);
    }
}
