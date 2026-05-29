<?php

use App\Models\User;
use App\Modules\League\Actions\ReadLeagueKillLeadersAction;
use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Matches\Actions\ParseSetGameResultsFromReplaysAction;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\Pool;
use App\Modules\Matches\Models\Set;
use App\Modules\Matches\Models\SetGameResult;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\mock;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * @return array{league: League, set: Set, pokedex: Pokedex}
 */
function createLeagueSetWithReplay(): array
{
    $owner = User::factory()->create();

    $league = League::create([
        'name' => 'Recalc Stats League',
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
        'league_owner' => $owner->id,
    ]);

    $matchConfig = MatchConfig::create([
        'league_id' => $league->id,
        'number_of_pools' => 1,
        'status' => 1,
    ]);

    $pool = Pool::create([
        'league_id' => $league->id,
        'match_config_id' => $matchConfig->id,
        'status' => 1,
    ]);

    $team1 = Team::create([
        'name' => 'Team Alpha',
        'league_id' => $league->id,
        'user_id' => User::factory()->create()->id,
        'admin_flag' => 0,
        'pick_position' => 1,
        'seed' => 1,
        'draft_points' => 80,
    ]);

    $team2 = Team::create([
        'name' => 'Team Beta',
        'league_id' => $league->id,
        'user_id' => User::factory()->create()->id,
        'admin_flag' => 0,
        'pick_position' => 2,
        'seed' => 2,
        'draft_points' => 80,
    ]);

    $set = Set::create([
        'league_id' => $league->id,
        'pool_id' => $pool->id,
        'round' => 1,
        'team1_id' => $team1->id,
        'team2_id' => $team2->id,
        'team1_score' => 1,
        'team2_score' => 0,
        'winner_id' => $team1->id,
        'replay1' => 'https://replay.example/game-1',
        'status' => 0,
    ]);

    Set::create([
        'league_id' => $league->id,
        'pool_id' => $pool->id,
        'round' => 2,
        'team1_id' => $team1->id,
        'team2_id' => $team2->id,
        'team1_score' => null,
        'team2_score' => null,
        'winner_id' => null,
        'status' => 0,
    ]);

    $pokedex = Pokedex::create([
        'nationaldex_id' => 25,
        'name' => 'Pikachu',
        'type1' => 'Electric',
    ]);

    LeaguePokemon::create([
        'league_id' => $league->id,
        'pokedex_id' => $pokedex->id,
        'drafted_by' => $team1->id,
        'name' => 'Pikachu',
        'cost' => 10,
    ]);

    SetGameResult::create([
        'set_id' => $set->id,
        'game_number' => 1,
        'p1_team_id' => $team1->id,
        'p2_team_id' => $team2->id,
        'winner_team_id' => $team1->id,
        'p1_pokemon' => [$pokedex->id],
        'p2_pokemon' => [],
        'p1_knockouts' => [],
        'p2_knockouts' => [],
    ]);

    return compact('league', 'set', 'pokedex');
}

it('fails when the league does not exist', function (): void {
    $this->artisan('league:recalc-pokemon-stats', ['league' => 99999])
        ->assertFailed()
        ->expectsOutputToContain('League not found');
});

it('clears stale game results and re-parses sets with replays', function (): void {
    ['league' => $league, 'set' => $set, 'pokedex' => $pokedex] = createLeagueSetWithReplay();

    expect(SetGameResult::query()->count())->toBe(1);

    mock(ParseSetGameResultsFromReplaysAction::class)
        ->shouldReceive('__invoke')
        ->once()
        ->withArgs(fn (Set $parsedSet): bool => $parsedSet->is($set))
        ->andReturnUsing(function (Set $parsedSet) use ($pokedex, $set): void {
            SetGameResult::create([
                'set_id' => $set->id,
                'game_number' => 1,
                'p1_team_id' => $set->team1_id,
                'p2_team_id' => $set->team2_id,
                'winner_team_id' => $set->team1_id,
                'p1_pokemon' => [$pokedex->id],
                'p2_pokemon' => [],
                'p1_knockouts' => [$pokedex->id],
                'p2_knockouts' => [],
            ]);
        });

    $this->artisan('league:recalc-pokemon-stats', ['league' => $league->id])
        ->assertSuccessful()
        ->expectsOutputToContain('Processed 1 set(s) with replays')
        ->expectsOutputToContain('Skipped 1 set(s) with no replays')
        ->expectsOutputToContain('Recorded 1 game result(s)');

    expect(SetGameResult::query()->count())->toBe(1)
        ->and(SetGameResult::query()->first()->p1_knockouts)->toBe([$pokedex->id]);
});

it('busts the league kill leaders cache after recalc', function (): void {
    ['league' => $league, 'set' => $set] = createLeagueSetWithReplay();

    Cache::put("league:{$league->id}:kill_leaders", ['stale' => true], now()->addMinutes(30));

    mock(ParseSetGameResultsFromReplaysAction::class)
        ->shouldReceive('__invoke')
        ->once()
        ->withArgs(fn (Set $parsedSet): bool => $parsedSet->is($set));

    $this->artisan('league:recalc-pokemon-stats', ['league' => $league->id])
        ->assertSuccessful();

    expect(Cache::has("league:{$league->id}:kill_leaders"))->toBeFalse();
});

it('can display stats for a specific pokemon after recalc', function (): void {
    ['league' => $league, 'set' => $set, 'pokedex' => $pokedex] = createLeagueSetWithReplay();

    mock(ParseSetGameResultsFromReplaysAction::class)
        ->shouldReceive('__invoke')
        ->once()
        ->andReturnUsing(function () use ($set, $pokedex): void {
            SetGameResult::create([
                'set_id' => $set->id,
                'game_number' => 1,
                'p1_team_id' => $set->team1_id,
                'p2_team_id' => $set->team2_id,
                'winner_team_id' => $set->team1_id,
                'p1_pokemon' => [$pokedex->id],
                'p2_pokemon' => [],
                'p1_knockouts' => [$pokedex->id, $pokedex->id],
                'p2_knockouts' => [],
            ]);
        });

    $this->artisan('league:recalc-pokemon-stats', [
        'league' => $league->id,
        '--pokedex' => $pokedex->id,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('Pikachu');

    $stats = app(ReadLeagueKillLeadersAction::class)($league);
    $pikachu = collect($stats)->firstWhere('pokedex_id', $pokedex->id);

    expect($pikachu)->not->toBeNull()
        ->and($pikachu['kills'])->toBe(2)
        ->and($pikachu['games_brought'])->toBe(1)
        ->and($pikachu['avg_ko_per_game'])->toBe(2.0);
});

it('warns when the requested pokemon has no stats in the league', function (): void {
    ['league' => $league, 'set' => $set] = createLeagueSetWithReplay();

    mock(ParseSetGameResultsFromReplaysAction::class)
        ->shouldReceive('__invoke')
        ->once()
        ->withArgs(fn (Set $parsedSet): bool => $parsedSet->is($set));

    $this->artisan('league:recalc-pokemon-stats', [
        'league' => $league->id,
        '--pokedex' => 99999,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('No stats found for pokedex ID 99999');
});
