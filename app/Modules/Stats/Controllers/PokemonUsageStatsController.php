<?php

namespace App\Modules\Stats\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matches\Models\SetGameResult;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Stats\Models\PokemonUsageStat;
use App\Modules\Stats\Models\PokemonUsageStatsMeta;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PokemonUsageStatsController extends Controller
{
    public function index(Request $request): Response
    {
        $meta = PokemonUsageStatsMeta::query()->find(1);
        $totalPicks = (int) ($meta?->total_picks ?? 0);
        $totalBans = (int) ($meta?->total_bans ?? 0);
        $totalBringUnits = (int) ($meta?->total_bring_units ?? 0);

        $stats = PokemonUsageStat::query()
            ->with('pokedex:id,name,nationaldex_id,sprite_url')
            ->orderByDesc('draft_pick_count')
            ->limit(500)
            ->get();

        $rows = $stats->map(function (PokemonUsageStat $row) use ($totalPicks, $totalBans, $totalBringUnits) {
            $pickRate = $totalPicks > 0 ? $row->draft_pick_count / $totalPicks : 0.0;
            $banRate = $totalBans > 0 ? $row->draft_ban_count / $totalBans : 0.0;
            $bringRate = $totalBringUnits > 0 ? $row->match_bring_count / $totalBringUnits : 0.0;
            $gamePlayed = $row->game_wins + $row->game_losses;
            $gameWinRate = $gamePlayed > 0 ? $row->game_wins / $gamePlayed : null;

            return [
                'pokedex_id' => $row->pokedex_id,
                'name' => $row->pokedex?->name,
                'nationaldex_id' => $row->pokedex?->nationaldex_id,
                'sprite_url' => $row->pokedex?->sprite_url,
                'draft_pick_count' => $row->draft_pick_count,
                'draft_ban_count' => $row->draft_ban_count,
                'match_bring_count' => $row->match_bring_count,
                'game_bring_count' => $row->game_bring_count,
                'game_wins' => $row->game_wins,
                'game_losses' => $row->game_losses,
                'ko_count' => (int) $row->ko_count,
                'avg_ko_per_game' => $row->avg_ko_per_game !== null ? round((float) $row->avg_ko_per_game, 4) : null,
                'pick_rate' => round($pickRate, 6),
                'ban_rate' => round($banRate, 6),
                'bring_rate' => round($bringRate, 6),
                'game_win_rate' => $gameWinRate !== null ? round($gameWinRate, 6) : null,
            ];
        })->values()->all();

        $topPick = collect($rows)->sortByDesc('draft_pick_count')->take(12)->values()->all();
        $topBan = collect($rows)->sortByDesc('draft_ban_count')->take(12)->values()->all();
        $topBring = collect($rows)->sortByDesc('match_bring_count')->take(12)->values()->all();
        $topGameWin = collect($rows)
            ->filter(fn (array $r) => ($r['game_wins'] + $r['game_losses']) >= 5)
            ->sortByDesc('game_win_rate')
            ->take(12)
            ->values()
            ->all();
        $topKo = collect($rows)->sortByDesc('ko_count')->filter(fn (array $r) => $r['ko_count'] > 0)->take(12)->values()->all();

        return Inertia::render('usage-stats/Index', [
            'meta' => [
                'total_picks' => $totalPicks,
                'total_bans' => $totalBans,
                'total_bring_units' => $totalBringUnits,
                'rebuilt_at' => $meta?->rebuilt_at?->toIso8601String(),
            ],
            'rows' => $rows,
            'charts' => [
                'top_pick_labels' => collect($topPick)->pluck('name')->all(),
                'top_pick_values' => collect($topPick)->pluck('pick_rate')->map(fn ($v) => round((float) $v * 100, 3))->all(),
                'top_ban_labels' => collect($topBan)->pluck('name')->all(),
                'top_ban_values' => collect($topBan)->pluck('ban_rate')->map(fn ($v) => round((float) $v * 100, 3))->all(),
                'top_bring_labels' => collect($topBring)->pluck('name')->all(),
                'top_bring_values' => collect($topBring)->pluck('bring_rate')->map(fn ($v) => round((float) $v * 100, 3))->all(),
                'top_win_labels' => collect($topGameWin)->pluck('name')->all(),
                'top_win_values' => collect($topGameWin)->pluck('game_win_rate')->map(fn ($v) => $v !== null ? round((float) $v * 100, 2) : 0)->all(),
                'top_ko_labels' => collect($topKo)->pluck('name')->all(),
                'top_ko_values' => collect($topKo)->pluck('ko_count')->map(fn ($v) => (int) $v)->all(),
            ],
        ]);
    }

    public function show(int $pokedex_id): Response|\Illuminate\Http\Response
    {
        $pokedex = Pokedex::query()->find($pokedex_id);

        if ($pokedex === null) {
            abort(404);
        }

        $stat = PokemonUsageStat::query()
            ->where('pokedex_id', $pokedex_id)
            ->first();

        $gameResults = SetGameResult::query()
            ->where(function ($query) use ($pokedex_id) {
                $query->whereJsonContains('p1_pokemon', $pokedex_id)
                    ->orWhereJsonContains('p2_pokemon', $pokedex_id);
            })
            ->with(['set.team1', 'set.team2'])
            ->get();

        $games = $gameResults->map(function (SetGameResult $result) use ($pokedex_id): array {
            $set = $result->set;
            $isP1 = in_array($pokedex_id, (array) $result->p1_pokemon, true);
            $myTeamId = $isP1 ? $result->p1_team_id : $result->p2_team_id;
            $wonGame = $result->winner_team_id === $myTeamId;
            $knockouts = $isP1 ? (array) $result->p1_knockouts : (array) $result->p2_knockouts;
            $koCount = count(array_filter($knockouts, fn ($id) => $id == $pokedex_id));
            $replayUrl = $set ? ($set->{'replay'.$result->game_number} ?? null) : null;

            return [
                'set_id' => $result->set_id,
                'round' => $set?->round,
                'game_number' => $result->game_number,
                'team1_name' => $set?->team1?->name,
                'team2_name' => $set?->team2?->name,
                'team1_score' => $set?->team1_score,
                'team2_score' => $set?->team2_score,
                'won_game' => $wonGame,
                'ko_count' => $koCount,
                'replay_url' => $replayUrl,
            ];
        })->values()->all();

        return Inertia::render('usage-stats/Show', [
            'pokemon' => [
                'pokedex_id' => $pokedex->id,
                'name' => $pokedex->name,
                'sprite_url' => $pokedex->sprite_url,
                'type1' => $pokedex->type1,
                'type2' => $pokedex->type2,
            ],
            'stat' => $stat ? [
                'draft_pick_count' => $stat->draft_pick_count,
                'draft_ban_count' => $stat->draft_ban_count,
                'match_bring_count' => $stat->match_bring_count,
                'game_bring_count' => $stat->game_bring_count,
                'game_wins' => $stat->game_wins,
                'game_losses' => $stat->game_losses,
                'ko_count' => (int) $stat->ko_count,
                'avg_ko_per_game' => $stat->avg_ko_per_game !== null ? round((float) $stat->avg_ko_per_game, 4) : null,
            ] : null,
            'games' => $games,
        ]);
    }
}
