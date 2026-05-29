<?php

namespace App\Modules\Stats\Services;

use App\Modules\League\Models\League;
use App\Modules\Matches\Actions\ParseSetGameResultsFromReplaysAction;
use App\Modules\Matches\Models\Set;
use App\Modules\Matches\Models\SetGameResult;
use Illuminate\Support\Facades\Cache;

class RecalcLeaguePokemonStatsService
{
    public function __construct(
        private ParseSetGameResultsFromReplaysAction $parseSetGameResults,
    ) {}

    /**
     * Re-parse every replay-backed set in a league and rebuild set_game_results.
     *
     * @return array{sets_processed: int, sets_skipped: int, games_recorded: int}
     */
    public function __invoke(League $league): array
    {
        $setIds = Set::query()
            ->where('league_id', $league->id)
            ->pluck('id');

        SetGameResult::query()->whereIn('set_id', $setIds)->delete();

        $setsWithReplays = Set::query()
            ->where('league_id', $league->id)
            ->where(function ($query): void {
                foreach (['replay1', 'replay2', 'replay3'] as $column) {
                    $query->orWhere(function ($subQuery) use ($column): void {
                        $subQuery->whereNotNull($column)->where($column, '!=', '');
                    });
                }
            })
            ->orderBy('round')
            ->orderBy('id')
            ->get();

        $setsSkipped = Set::query()
            ->where('league_id', $league->id)
            ->where(function ($query): void {
                $query->whereNull('replay1')->orWhere('replay1', '=', '');
            })
            ->where(function ($query): void {
                $query->whereNull('replay2')->orWhere('replay2', '=', '');
            })
            ->where(function ($query): void {
                $query->whereNull('replay3')->orWhere('replay3', '=', '');
            })
            ->count();

        foreach ($setsWithReplays as $set) {
            ($this->parseSetGameResults)($set);
        }

        Cache::forget("league:{$league->id}:kill_leaders");

        $gamesRecorded = SetGameResult::query()
            ->whereIn('set_id', $setIds)
            ->count();

        return [
            'sets_processed' => $setsWithReplays->count(),
            'sets_skipped' => $setsSkipped,
            'games_recorded' => $gamesRecorded,
        ];
    }
}
