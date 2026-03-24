<?php

namespace App\Modules\Stats\Services;

use App\Modules\Draft\Models\Bans;
use App\Modules\Draft\Models\DraftPick;
use App\Modules\Matches\Models\Set;
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

            $bringPool = DB::selectOne('
                SELECT COUNT(*) as cnt FROM (
                    SELECT DISTINCT stp.matchable_type, stp.matchable_id, stp.team_id, lp.pokedex_id
                    FROM sets s
                    INNER JOIN set_team_pokepastes stp ON stp.matchable_type = ? AND stp.matchable_id = s.id
                    INNER JOIN set_team_pokepaste_slots sl ON sl.set_team_pokepaste_id = stp.id AND sl.league_pokemon_id IS NOT NULL
                    INNER JOIN league_pokemon lp ON lp.id = sl.league_pokemon_id
                    WHERE s.status = 0 AND s.team1_score IS NOT NULL AND s.team2_score IS NOT NULL
                ) x
            ', [$setClass]);

            $bringPlayoff = DB::selectOne('
                SELECT COUNT(*) as cnt FROM (
                    SELECT DISTINCT stp.matchable_type, stp.matchable_id, stp.team_id, lp.pokedex_id
                    FROM playoff_matches pm
                    INNER JOIN set_team_pokepastes stp ON stp.matchable_type = ? AND stp.matchable_id = pm.id
                    INNER JOIN set_team_pokepaste_slots sl ON sl.set_team_pokepaste_id = stp.id AND sl.league_pokemon_id IS NOT NULL
                    INNER JOIN league_pokemon lp ON lp.id = sl.league_pokemon_id
                    WHERE pm.winner_team_id IS NOT NULL AND pm.completed_at IS NOT NULL
                      AND pm.team1_score IS NOT NULL AND pm.team2_score IS NOT NULL
                ) x
            ', [$playoffClass]);

            $totalBringUnits = (int) (($bringPool->cnt ?? 0) + ($bringPlayoff->cnt ?? 0));

            $bringByDex = [];

            $poolBringGrouped = DB::select('
                SELECT pokedex_id, COUNT(*) as c FROM (
                    SELECT DISTINCT stp.matchable_type, stp.matchable_id, stp.team_id, lp.pokedex_id AS pokedex_id
                    FROM sets s
                    INNER JOIN set_team_pokepastes stp ON stp.matchable_type = ? AND stp.matchable_id = s.id
                    INNER JOIN set_team_pokepaste_slots sl ON sl.set_team_pokepaste_id = stp.id AND sl.league_pokemon_id IS NOT NULL
                    INNER JOIN league_pokemon lp ON lp.id = sl.league_pokemon_id
                    WHERE s.status = 0 AND s.team1_score IS NOT NULL AND s.team2_score IS NOT NULL
                ) sub
                GROUP BY pokedex_id
            ', [$setClass]);

            foreach ($poolBringGrouped as $row) {
                $pid = (int) $row->pokedex_id;
                $bringByDex[$pid] = ($bringByDex[$pid] ?? 0) + (int) $row->c;
            }

            $playoffBringGrouped = DB::select('
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

            foreach ($playoffBringGrouped as $row) {
                $pid = (int) $row->pokedex_id;
                $bringByDex[$pid] = ($bringByDex[$pid] ?? 0) + (int) $row->c;
            }

            $gameWins = [];
            $gameLosses = [];

            $this->mergeGameStatsFromSets($setClass, $gameWins, $gameLosses);
            $this->mergeGameStatsFromPlayoffs($playoffClass, $gameWins, $gameLosses);

            $allIds = array_unique(array_merge(
                array_keys($bringByDex),
                array_keys($gameWins),
                array_keys($gameLosses),
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
     * @param  array<int, int>  $gameWins
     * @param  array<int, int>  $gameLosses
     */
    private function mergeGameStatsFromSets(string $setClass, array &$gameWins, array &$gameLosses): void
    {
        $sets = Set::query()
            ->where('status', 0)
            ->whereNotNull('team1_score')
            ->whereNotNull('team2_score')
            ->get(['id', 'team1_id', 'team2_id', 'team1_score', 'team2_score']);

        foreach ($sets as $set) {
            $this->addGameStatsForTeamSide(
                $setClass,
                (int) $set->id,
                (int) $set->team1_id,
                (int) $set->team1_score,
                (int) $set->team2_score,
                $gameWins,
                $gameLosses
            );
            $this->addGameStatsForTeamSide(
                $setClass,
                (int) $set->id,
                (int) $set->team2_id,
                (int) $set->team2_score,
                (int) $set->team1_score,
                $gameWins,
                $gameLosses
            );
        }
    }

    /**
     * @param  array<int, int>  $gameWins
     * @param  array<int, int>  $gameLosses
     */
    private function mergeGameStatsFromPlayoffs(string $playoffClass, array &$gameWins, array &$gameLosses): void
    {
        $matches = PlayoffMatch::query()
            ->whereNotNull('winner_team_id')
            ->whereNotNull('completed_at')
            ->whereNotNull('team1_score')
            ->whereNotNull('team2_score')
            ->get(['id', 'team1_id', 'team2_id', 'team1_score', 'team2_score']);

        foreach ($matches as $m) {
            if ($m->team1_id === null || $m->team2_id === null) {
                continue;
            }
            $this->addGameStatsForTeamSide(
                $playoffClass,
                (int) $m->id,
                (int) $m->team1_id,
                (int) $m->team1_score,
                (int) $m->team2_score,
                $gameWins,
                $gameLosses
            );
            $this->addGameStatsForTeamSide(
                $playoffClass,
                (int) $m->id,
                (int) $m->team2_id,
                (int) $m->team2_score,
                (int) $m->team1_score,
                $gameWins,
                $gameLosses
            );
        }
    }

    /**
     * @param  array<int, int>  $gameWins
     * @param  array<int, int>  $gameLosses
     */
    private function addGameStatsForTeamSide(
        string $matchableType,
        int $matchableId,
        int $teamId,
        int $winsToCredit,
        int $lossesToCredit,
        array &$gameWins,
        array &$gameLosses,
    ): void {
        $pokedexIds = DB::table('set_team_pokepastes as stp')
            ->join('set_team_pokepaste_slots as sl', 'sl.set_team_pokepaste_id', '=', 'stp.id')
            ->join('league_pokemon as lp', 'lp.id', '=', 'sl.league_pokemon_id')
            ->where('stp.matchable_type', $matchableType)
            ->where('stp.matchable_id', $matchableId)
            ->where('stp.team_id', $teamId)
            ->whereNotNull('sl.league_pokemon_id')
            ->distinct()
            ->pluck('lp.pokedex_id');

        foreach ($pokedexIds as $dexId) {
            $dexId = (int) $dexId;
            $gameWins[$dexId] = ($gameWins[$dexId] ?? 0) + $winsToCredit;
            $gameLosses[$dexId] = ($gameLosses[$dexId] ?? 0) + $lossesToCredit;
        }
    }
}
