<?php

namespace App\Console\Commands;

use App\Modules\League\Actions\ReadLeagueKillLeadersAction;
use App\Modules\League\Models\League;
use App\Modules\Stats\Services\RecalcLeaguePokemonStatsService;
use Illuminate\Console\Command;
use Throwable;

class RecalcLeaguePokemonStatsCommand extends Command
{
    protected $signature = 'league:recalc-pokemon-stats
                            {league : The league ID}
                            {--pokedex= : Optional pokedex ID to display stats for after recalc}';

    protected $description = 'Re-parse replays for every set in a league and rebuild Pokémon battle stats.';

    public function handle(
        RecalcLeaguePokemonStatsService $service,
        ReadLeagueKillLeadersAction $readLeagueKillLeadersAction,
    ): int {
        $league = League::query()->find($this->argument('league'));

        if ($league === null) {
            $this->error('League not found.');

            return self::FAILURE;
        }

        $this->info("Recalculating Pokémon stats for league: {$league->name} (ID {$league->id})");

        try {
            $summary = $service($league);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Processed {$summary['sets_processed']} set(s) with replays.");
        $this->info("Skipped {$summary['sets_skipped']} set(s) with no replays.");
        $this->info("Recorded {$summary['games_recorded']} game result(s).");

        $pokedexId = $this->option('pokedex');

        if ($pokedexId !== null) {
            $this->displayPokemonStats($readLeagueKillLeadersAction($league), (int) $pokedexId);
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<array{
     *     pokedex_id: int,
     *     name: string|null,
     *     coach: string|null,
     *     kills: int,
     *     deaths: int,
     *     differential: int,
     *     games_brought: int,
     *     avg_ko_per_game: float|null,
     *     damage: int,
     * }>  $killLeaders
     */
    private function displayPokemonStats(array $killLeaders, int $pokedexId): void
    {
        $stat = collect($killLeaders)->firstWhere('pokedex_id', $pokedexId);

        if ($stat === null) {
            $this->warn("No stats found for pokedex ID {$pokedexId} in this league.");

            return;
        }

        $this->newLine();
        $this->info('Pokémon stats:');
        $this->table(
            ['Name', 'Coach', 'Kills', 'Deaths', '+/-', 'Brought', 'KO/G', 'Damage'],
            [[
                $stat['name'] ?? '?',
                $stat['coach'] ?? '—',
                $stat['kills'],
                $stat['deaths'],
                ($stat['differential'] > 0 ? '+' : '').$stat['differential'],
                $stat['games_brought'],
                $stat['avg_ko_per_game'] !== null ? number_format($stat['avg_ko_per_game'], 2) : '—',
                $stat['damage'],
            ]],
        );
    }
}
