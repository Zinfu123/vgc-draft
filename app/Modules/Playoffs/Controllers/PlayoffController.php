<?php

namespace App\Modules\Playoffs\Controllers;

use App\Enums\Playoffs\PlayoffFormat;
use App\Enums\Playoffs\PlayoffStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Playoff\ClosePlayoffsRequest;
use App\Http\Requests\Playoff\GeneratePlayoffBracketRequest;
use App\Http\Requests\Playoff\RecordPlayoffMatchResultRequest;
use App\Http\Requests\Playoff\RollbackPlayoffMatchRequest;
use App\Http\Requests\Playoff\UpdatePlayoffConfigRequest;
use App\Modules\League\Actions\LeagueDetailLayoutDataAction;
use App\Modules\League\Enums\LeagueStatus;
use App\Modules\League\Models\League;
use App\Modules\Playoffs\Models\Playoff;
use App\Modules\Playoffs\Models\PlayoffMatch;
use App\Modules\Playoffs\Services\PlayoffBracketService;
use App\Modules\Pokepaste\Actions\ReadPlayoffMatchPokepasteSideSummariesAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class PlayoffController extends Controller
{
    public function show(League $league, LeagueDetailLayoutDataAction $leagueDetailLayoutDataAction, PlayoffBracketService $playoffBracketService): Response
    {
        $this->authorize('admin', $league);

        $data = $leagueDetailLayoutDataAction($league);

        $playoff = $league->playoff()->firstOrCreate(
            ['league_id' => $league->id],
            [
                'format' => PlayoffFormat::SingleElimination,
                'bracket_size' => 4,
                'status' => PlayoffStatus::Draft,
                'seed_order' => null,
            ]
        );

        $currentTeamIds = $data['teams']->pluck('id')->all();
        $existingSeedOrder = $playoff->seed_order ?? [];
        $needsReseed = $existingSeedOrder === []
            || ! empty(array_diff($existingSeedOrder, $currentTeamIds));

        if ($playoff->status === PlayoffStatus::Draft && $needsReseed) {
            $playoff->seed_order = $playoffBracketService->suggestedSeedTeams($league)->pluck('id')->all();
            $playoff->save();
        }

        $playoff->load(['matches.team1', 'matches.team2']);

        $league->loadMissing('matchConfig');

        return Inertia::render('league/admin/Playoffs', [
            ...$data,
            'playoff' => $this->playoffPayloadWithPokepaste($playoff, $league, Auth::user()),
            'allowedBracketSizes' => PlayoffBracketService::allowedBracketSizes(),
            'doubleEliminationSupported' => false,
        ]);
    }

    public function update(UpdatePlayoffConfigRequest $request, League $league): RedirectResponse
    {
        $playoff = $league->playoff;
        if ($playoff === null || $playoff->status !== PlayoffStatus::Draft) {
            return back()->withErrors(['playoff' => 'Playoff settings can only be changed while the bracket is in draft.']);
        }

        $playoff->format = $request->validated('format');
        $playoff->bracket_size = (int) $request->validated('bracket_size');
        $playoff->seed_order = array_values(array_map('intval', $request->validated('seed_order')));
        $playoff->save();

        return back()->with('success', 'Playoff configuration saved.');
    }

    public function generate(GeneratePlayoffBracketRequest $request, League $league, PlayoffBracketService $playoffBracketService): RedirectResponse
    {
        if ($league->status !== LeagueStatus::Playoffs) {
            return back()->withErrors(['playoff' => 'The playoff bracket can only be generated when the league is in the Playoffs phase.']);
        }

        $playoff = $league->playoff;
        if ($playoff === null || $playoff->status !== PlayoffStatus::Draft) {
            return back()->withErrors(['playoff' => 'The bracket can only be generated from draft.']);
        }

        if ($playoff->matches()->exists()) {
            return back()->withErrors(['playoff' => 'Reset the bracket before generating again.']);
        }

        if ($playoff->format === PlayoffFormat::DoubleElimination) {
            return back()->withErrors(['format' => 'Double elimination is not available yet.']);
        }

        try {
            $playoffBracketService->generateSingleElimination($playoff);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['playoff' => $e->getMessage()]);
        }

        return back()->with('success', 'Playoff bracket generated.');
    }

    public function recordResult(RecordPlayoffMatchResultRequest $request, League $league, PlayoffBracketService $playoffBracketService): RedirectResponse
    {
        if ($league->status !== LeagueStatus::Playoffs) {
            return back()->withErrors(['playoff' => 'Match results can only be recorded when the league is in the Playoffs phase.']);
        }

        $playoff = $league->playoff;
        if ($playoff === null || $playoff->status !== PlayoffStatus::Active) {
            return back()->withErrors(['playoff' => 'Playoffs are not active.']);
        }

        $match = $request->playoffMatch();
        if ($match === null || $match->winner_team_id !== null) {
            return back()->withErrors(['playoff_match_id' => 'This match cannot accept a new result.']);
        }

        try {
            $playoffBracketService->recordResult(
                $match,
                (int) $request->validated('team1_score'),
                (int) $request->validated('team2_score'),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['playoff' => $e->getMessage()]);
        }

        return back()->with('success', 'Playoff result saved.');
    }

    public function rollback(RollbackPlayoffMatchRequest $request, League $league, PlayoffBracketService $playoffBracketService): RedirectResponse
    {
        if ($league->status !== LeagueStatus::Playoffs) {
            return back()->withErrors(['playoff' => 'Match results can only be rolled back when the league is in the Playoffs phase.']);
        }

        $playoff = $league->playoff;
        if ($playoff === null || $playoff->status !== PlayoffStatus::Active) {
            return back()->withErrors(['playoff' => 'Playoffs are not active.']);
        }

        $match = $request->playoffMatch();
        if ($match === null || $match->winner_team_id === null) {
            return back()->withErrors(['playoff_match_id' => 'This match has no result to roll back.']);
        }

        $playoffBracketService->rollbackMatch($match);

        return back()->with('success', 'Playoff result rolled back.');
    }

    public function close(ClosePlayoffsRequest $request, League $league, PlayoffBracketService $playoffBracketService): RedirectResponse
    {
        $playoff = $league->playoff;
        if ($playoff === null) {
            return back()->withErrors(['playoff' => 'No playoff found for this league.']);
        }

        try {
            $playoffBracketService->closePlayoffs($playoff);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['playoff' => $e->getMessage()]);
        }

        return back()->with('success', 'Playoffs closed. League champion and medals are set.');
    }

    public function reset(League $league, PlayoffBracketService $playoffBracketService): RedirectResponse
    {
        $this->authorize('admin', $league);

        $playoff = $league->playoff;
        if ($playoff === null) {
            return back()->withErrors(['playoff' => 'No playoff found for this league.']);
        }

        $playoffBracketService->resetBracketAndReopenLeague($playoff);

        return back()->with('success', 'Playoff bracket cleared, league medals and champion reset. You can adjust seeds and generate again.');
    }

    /**
     * @return array<string, mixed>
     */
    public function playoffPayload(Playoff $playoff): array
    {
        return [
            'id' => $playoff->id,
            'format' => $playoff->format->value,
            'bracket_size' => $playoff->bracket_size,
            'status' => $playoff->status->value,
            'seed_order' => $playoff->seed_order ?? [],
            'matches' => $playoff->matches->map(fn (PlayoffMatch $m): array => [
                'id' => $m->id,
                'slot' => $m->slot,
                'round_index' => $m->round_index,
                'sort_order' => $m->sort_order,
                'is_bronze' => $m->is_bronze,
                'team1_id' => $m->team1_id,
                'team2_id' => $m->team2_id,
                'team1_name' => $m->team1?->name,
                'team2_name' => $m->team2?->name,
                'team1_score' => $m->team1_score,
                'team2_score' => $m->team2_score,
                'winner_team_id' => $m->winner_team_id,
                'completed_at' => $m->completed_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function playoffPayloadWithPokepaste(Playoff $playoff, League $league, ?\App\Models\User $viewer): array
    {
        $payload = $this->playoffPayload($playoff);
        $isAdmin = $viewer !== null && $viewer->can('admin', $league);
        /** @var ReadPlayoffMatchPokepasteSideSummariesAction $summariesAction */
        $summariesAction = app(ReadPlayoffMatchPokepasteSideSummariesAction::class);

        $matches = [];
        foreach ($payload['matches'] as $row) {
            $m = $playoff->matches->firstWhere('id', $row['id']);
            if ($m instanceof PlayoffMatch) {
                $row['pokepaste_sides'] = $summariesAction($m, $viewer, $isAdmin);
            }
            $matches[] = $row;
        }
        $payload['matches'] = $matches;
        $payload['require_team_match_pokepaste_before_results'] = (bool) ($league->matchConfig?->require_team_match_pokepaste_before_results ?? false);

        return $payload;
    }
}
