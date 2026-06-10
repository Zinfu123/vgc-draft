<?php

namespace App\Modules\V2\TeamCoverage\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\TeamCoverage\TeamCoverageLearnsetRequest;
use App\Http\Requests\TeamCoverage\TeamCoveragePokedexSearchRequest;
use App\Http\Requests\TeamCoverage\TeamCoverageTeamRosterRequest;
use App\Kernel\Contracts\TeamCoveragePlanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TeamCoveragePlannerController extends Controller
{
    public function show(TeamCoveragePlanner $planner): Response
    {
        $user = Auth::user();
        abort_if($user === null, 403);

        return Inertia::render('tools/TeamCoveragePlanner', $planner->showProps((int) $user->id));
    }

    public function search(
        TeamCoveragePokedexSearchRequest $request,
        TeamCoveragePlanner $planner,
    ): JsonResponse {
        $validated = $request->validated();
        $perPage = isset($validated['per_page']) ? (int) $validated['per_page'] : 36;

        return response()->json($planner->searchPokedex($perPage, $validated));
    }

    public function learnset(
        TeamCoverageLearnsetRequest $request,
        int $pokedex,
        TeamCoveragePlanner $planner,
    ): JsonResponse {
        $validated = $request->validated();
        $game = isset($validated['game']) && is_string($validated['game']) && $validated['game'] !== ''
            ? $validated['game']
            : null;

        return response()->json($planner->learnsetPayload($pokedex, $game));
    }

    public function roster(
        TeamCoverageTeamRosterRequest $request,
        int $team,
        TeamCoveragePlanner $planner,
    ): JsonResponse {
        return response()->json($planner->rosterPayload($team));
    }
}
