<?php

namespace App\Modules\Stats\Controllers;

use App\Http\Controllers\Controller;
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
                'game_wins' => $row->game_wins,
                'game_losses' => $row->game_losses,
                'ko_count' => (int) $row->ko_count,
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
}
