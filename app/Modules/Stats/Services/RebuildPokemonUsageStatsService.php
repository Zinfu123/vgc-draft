<?php

namespace App\Modules\Stats\Services;

use App\Modules\Draft\Models\Bans;
use App\Modules\Draft\Models\DraftPick;
use App\Modules\Matches\Models\Set;
use App\Modules\Matches\Models\SetGameResult;
use App\Modules\Playoffs\Models\PlayoffMatch;
use App\Modules\Stats\Models\PokemonUsageStat;
use App\Modules\Stats\Models\PokemonUsageStatsMeta;
use Illuminate\Support\Facades\DB;

class RebuildPokemonUsageStatsService
{
    public function __invoke(): void
    {
        $setClass = Set::class;
        $playoffClass = PlayoffMatch::class;

        DB::transaction(function () use ($setClass, $playoffClass): void {
            PokemonUsageStat::query()->delete();

            $totalPicks = (int) DraftPick::query()->count();
            $totalBans = (int) Bans::query()->count();

            $pickRows = DB::table('draft_picks as dp')
                ->join('league_pokemon as lp', 'lp.id', '=', 'dp.league_pokemon_id')
                ->selectRaw('lp.pokedex_id as pokedex_id, COUNT(*) as c')
                ->groupBy('lp.pokedex_id')
                ->get()
                ->keyBy('pokedex_id');

            $banRows = DB::table('draft_bans as db')
                ->selectRaw('db.pokedex_id as pokedex_id, COUNT(*) as c')
                ->groupBy('db.pokedex_id')
                ->get()
                ->keyBy('pokedex_id');

            // Bring counts and game win/loss come from per-game replay data (set_game_results).
            // Each entry represents a single game; p1_pokemon / p2_pokemon are JSON arrays of pokedex_ids
            // for the 4 pokemon each side actually selected.
            $bringByDex = [];
            $gameWins = [];
            $gameLosses = [];
            $totalBringUnits = 0;

            $koByDex = [];

            $gameResults = SetGameResult::query()
                ->whereNotNull('p1_pokemon')
                ->whereNotNull('p2_pokemon')
                ->get(['p1_team_id', 'p2_team_id', 'winner_team_id', 'p1_pokemon', 'p2_pokemon', 'p1_knockouts', 'p2_knockouts']);

            foreach ($gameResults as $result) {
                $p1Ids = $result->p1_pokemon ?? [];
                $p2Ids = $result->p2_pokemon ?? [];

                foreach ($p1Ids as $dexId) {
                    $dexId = (int) $dexId;
                    $bringByDex[$dexId] = ($bringByDex[$dexId] ?? 0) + 1;
                    $totalBringUnits++;
                }
                foreach ($p2Ids as $dexId) {
                    $dexId = (int) $dexId;
                    $bringByDex[$dexId] = ($bringByDex[$dexId] ?? 0) + 1;
                    $totalBringUnits++;
                }

                if ($result->winner_team_id !== null) {
                    $winnerIds = (int) $result->winner_team_id === (int) $result->p1_team_id ? $p1Ids : $p2Ids;
                    $loserIds = (int) $result->winner_team_id === (int) $result->p1_team_id ? $p2Ids : $p1Ids;

                    foreach ($winnerIds as $dexId) {
                        $dexId = (int) $dexId;
                        $gameWins[$dexId] = ($gameWins[$dexId] ?? 0) + 1;
                    }
                    foreach ($loserIds as $dexId) {
                        $dexId = (int) $dexId;
                        $gameLosses[$dexId] = ($gameLosses[$dexId] ?? 0) + 1;
                    }
                }

                foreach (array_merge($result->p1_knockouts ?? [], $result->p2_knockouts ?? []) as $dexId) {
                    $dexId = (int) $dexId;
                    $koByDex[$dexId] = ($koByDex[$dexId] ?? 0) + 1;
                }
            }

            // Fallback: sets/playoffs that have pokepaste data but no game results contribute
            // bring counts from their match pokepastes (6-pokemon team preview level).
            $this->mergeFallbackBringFromSets($setClass, $bringByDex, $totalBringUnits);
            $this->mergeFallbackBringFromPlayoffs($playoffClass, $bringByDex, $totalBringUnits);

            $allIds = array_unique(array_merge(
                array_keys($bringByDex),
                array_keys($gameWins),
                array_keys($gameLosses),
                array_keys($koByDex),
                $pickRows->keys()->all(),
                $banRows->keys()->all(),
            ));

            foreach ($allIds as $pokedexId) {
                PokemonUsageStat::query()->create([
                    'pokedex_id' => $pokedexId,
                    'draft_pick_count' => (int) ($pickRows->get($pokedexId)?->c ?? 0),
                    'draft_ban_count' => (int) ($banRows->get($pokedexId)?->c ?? 0),
                    'match_bring_count' => (int) ($bringByDex[$pokedexId] ?? 0),
                    'game_wins' => (int) ($gameWins[$pokedexId] ?? 0),
                    'game_losses' => (int) ($gameLosses[$pokedexId] ?? 0),
                    'ko_count' => (int) ($koByDex[$pokedexId] ?? 0),
                ]);
            }

            PokemonUsageStatsMeta::query()->updateOrCreate(
                ['id' => 1],
                [
                    'total_picks' => $totalPicks,
                    'total_bans' => $totalBans,
                    'total_bring_units' => $totalBringUnits,
                    'rebuilt_at' => now(),
                ]
            );
        });
    }

    /**
     * For completed sets that have no set_game_results entries, fall back to the
     * match pokepaste (6-pokemon team preview) for bring counts only.
     * These are typically older matches completed before replay parsing was introduced.
     *
     * @param  array<int, int>  $bringByDex
     */
    private function mergeFallbackBringFromSets(string $setClass, array &$bringByDex, int &$totalBringUnits): void
    {
        $setsWithGameResults = SetGameResult::query()
            ->pluck('set_id')
            ->unique()
            ->all();

        $rows = DB::select('
            SELECT pokedex_id, COUNT(*) as c FROM (
                SELECT DISTINCT stp.matchable_type, stp.matchable_id, stp.team_id, lp.pokedex_id AS pokedex_id
                FROM sets s
                INNER JOIN set_team_pokepastes stp ON stp.matchable_type = ? AND stp.matchable_id = s.id
                INNER JOIN set_team_pokepaste_slots sl ON sl.set_team_pokepaste_id = stp.id AND sl.league_pokemon_id IS NOT NULL
                INNER JOIN league_pokemon lp ON lp.id = sl.league_pokemon_id
                WHERE s.status = 0 AND s.team1_score IS NOT NULL AND s.team2_score IS NOT NULL
                  AND s.id NOT IN ('.implode(',', count($setsWithGameResults) > 0 ? $setsWithGameResults : [0]).')
            ) sub
            GROUP BY pokedex_id
        ', [$setClass]);

        foreach ($rows as $row) {
            $pid = (int) $row->pokedex_id;
            $bringByDex[$pid] = ($bringByDex[$pid] ?? 0) + (int) $row->c;
            $totalBringUnits += (int) $row->c;
        }
    }

    /**
     * @param  array<int, int>  $bringByDex
     */
    private function mergeFallbackBringFromPlayoffs(string $playoffClass, array &$bringByDex, int &$totalBringUnits): void
    {
        $rows = DB::select('
            SELECT pokedex_id, COUNT(*) as c FROM (
                SELECT DISTINCT stp.matchable_type, stp.matchable_id, stp.team_id, lp.pokedex_id AS pokedex_id
                FROM playoff_matches pm
                INNER JOIN set_team_pokepastes stp ON stp.matchable_type = ? AND stp.matchable_id = pm.id
                INNER JOIN set_team_pokepaste_slots sl ON sl.set_team_pokepaste_id = stp.id AND sl.league_pokemon_id IS NOT NULL
                INNER JOIN league_pokemon lp ON lp.id = sl.league_pokemon_id
                WHERE pm.winner_team_id IS NOT NULL AND pm.completed_at IS NOT NULL
                  AND pm.team1_score IS NOT NULL AND pm.team2_score IS NOT NULL
            ) sub
            GROUP BY pokedex_id
        ', [$playoffClass]);

        foreach ($rows as $row) {
            $pid = (int) $row->pokedex_id;
            $bringByDex[$pid] = ($bringByDex[$pid] ?? 0) + (int) $row->c;
            $totalBringUnits += (int) $row->c;
        }
    }
}
