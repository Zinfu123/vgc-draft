<?php

namespace App\Modules\League\Actions;

use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Matches\Models\Set;
use App\Modules\Matches\Models\SetGameResult;
use Illuminate\Support\Facades\Cache;

class ReadLeagueKillLeadersAction
{
    /**
     * Aggregate per-Pokemon kill/death/damage/GP stats for a league.
     *
     * Each entry corresponds to one drafted Pokemon in the league and includes
     * the owning team's coach name plus accumulated battle statistics derived
     * from SetGameResult replay data. Results are cached for 30 minutes and
     * invalidated whenever replays are (re)processed for a set in this league.
     *
     * @return list<array{
     *     pokedex_id: int,
     *     name: string|null,
     *     sprite_url: string|null,
     *     type1: string|null,
     *     type2: string|null,
     *     coach: string|null,
     *     kills: int,
     *     deaths: int,
     *     differential: int,
     *     gp: int,
     *     games_brought: int,
     *     avg_ko_per_game: float|null,
     *     damage: int,
     * }>
     */
    public function __invoke(League $league): array
    {
        return Cache::remember(
            "league:{$league->id}:kill_leaders",
            now()->addMinutes(30),
            fn () => $this->compute($league),
        );
    }

    /**
     * @return list<array{
     *     pokedex_id: int,
     *     name: string|null,
     *     sprite_url: string|null,
     *     type1: string|null,
     *     type2: string|null,
     *     coach: string|null,
     *     kills: int,
     *     deaths: int,
     *     differential: int,
     *     gp: int,
     *     games_brought: int,
     *     avg_ko_per_game: float|null,
     *     damage: int,
     * }>
     */
    private function compute(League $league): array
    {
        $setIds = Set::query()
            ->where('league_id', $league->id)
            ->pluck('id');

        $gameResults = SetGameResult::query()
            ->whereIn('set_id', $setIds)
            ->get(['p1_team_id', 'p2_team_id', 'p1_pokemon', 'p2_pokemon', 'p1_knockouts', 'p2_knockouts', 'p1_deaths', 'p2_deaths', 'p1_damage', 'p2_damage']);

        /** @var array<int, int> $killsByDex */
        $killsByDex = [];
        /** @var array<int, int> $deathsByDex */
        $deathsByDex = [];
        /** @var array<int, int> $gpByDex */
        $gpByDex = [];
        /** @var array<int, int> $damageByDex */
        $damageByDex = [];

        foreach ($gameResults as $result) {
            foreach ($result->p1_pokemon ?? [] as $dexId) {
                $gpByDex[(int) $dexId] = ($gpByDex[(int) $dexId] ?? 0) + 1;
            }
            foreach ($result->p2_pokemon ?? [] as $dexId) {
                $gpByDex[(int) $dexId] = ($gpByDex[(int) $dexId] ?? 0) + 1;
            }

            foreach ($result->p1_knockouts ?? [] as $dexId) {
                $killsByDex[(int) $dexId] = ($killsByDex[(int) $dexId] ?? 0) + 1;
            }
            foreach ($result->p2_knockouts ?? [] as $dexId) {
                $killsByDex[(int) $dexId] = ($killsByDex[(int) $dexId] ?? 0) + 1;
            }

            foreach ($result->p1_deaths ?? [] as $dexId) {
                $deathsByDex[(int) $dexId] = ($deathsByDex[(int) $dexId] ?? 0) + 1;
            }
            foreach ($result->p2_deaths ?? [] as $dexId) {
                $deathsByDex[(int) $dexId] = ($deathsByDex[(int) $dexId] ?? 0) + 1;
            }

            foreach ($result->p1_damage ?? [] as $dexId => $dmg) {
                $damageByDex[(int) $dexId] = ($damageByDex[(int) $dexId] ?? 0) + (int) $dmg;
            }
            foreach ($result->p2_damage ?? [] as $dexId => $dmg) {
                $damageByDex[(int) $dexId] = ($damageByDex[(int) $dexId] ?? 0) + (int) $dmg;
            }
        }

        $allDexIds = array_unique(array_merge(
            array_keys($killsByDex),
            array_keys($deathsByDex),
            array_keys($gpByDex),
            array_keys($damageByDex),
        ));

        if ($allDexIds === []) {
            return [];
        }

        $leaguePokemon = LeaguePokemon::query()
            ->where('league_id', $league->id)
            ->whereIn('pokedex_id', $allDexIds)
            ->whereNotNull('drafted_by')
            ->with(['pokemon:id,name,sprite_url,type1,type2', 'draftedBy:id,user_id', 'draftedBy.user:id,name'])
            ->get();

        return $leaguePokemon
            ->map(function (LeaguePokemon $lp) use ($killsByDex, $deathsByDex, $gpByDex, $damageByDex): array {
                $dexId = (int) $lp->pokedex_id;
                $kills = $killsByDex[$dexId] ?? 0;
                $deaths = $deathsByDex[$dexId] ?? 0;
                $gamesBrought = $gpByDex[$dexId] ?? 0;

                return [
                    'pokedex_id' => $dexId,
                    'name' => $lp->pokemon?->name,
                    'sprite_url' => $lp->pokemon?->sprite_url,
                    'type1' => $lp->pokemon?->type1,
                    'type2' => $lp->pokemon?->type2,
                    'coach' => $lp->draftedBy?->user?->name,
                    'kills' => $kills,
                    'deaths' => $deaths,
                    'differential' => $kills - $deaths,
                    'gp' => $gamesBrought,
                    'games_brought' => $gamesBrought,
                    'avg_ko_per_game' => $gamesBrought > 0 ? round($kills / $gamesBrought, 2) : null,
                    'damage' => $damageByDex[$dexId] ?? 0,
                ];
            })
            ->sortByDesc('kills')
            ->values()
            ->all();
    }
}
