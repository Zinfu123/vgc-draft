<?php

use App\Models\User;
use App\Modules\League\Actions\ReadLeagueKillLeadersAction;
use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\Pool;
use App\Modules\Matches\Models\Set;
use App\Modules\Matches\Models\SetGameResult;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Facades\Cache;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * @return array{league: League, team1: Team, team2: Team, set: Set, pokedex1: Pokedex, pokedex2: Pokedex}
 */
function createLeagueWithGameResults(): array
{
    $owner = User::factory()->create();
    $coach1 = User::factory()->create();
    $coach2 = User::factory()->create();

    $league = League::create([
        'name' => 'Kill Leaders League',
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
        'user_id' => $coach1->id,
        'admin_flag' => 0,
        'pick_position' => 1,
        'seed' => 1,
        'draft_points' => 80,
        'victory_points' => 0,
        'set_wins' => 1,
        'set_losses' => 0,
        'game_wins' => 2,
        'game_losses' => 0,
    ]);

    $team2 = Team::create([
        'name' => 'Team Beta',
        'league_id' => $league->id,
        'user_id' => $coach2->id,
        'admin_flag' => 0,
        'pick_position' => 2,
        'seed' => 2,
        'draft_points' => 80,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 1,
        'game_wins' => 0,
        'game_losses' => 2,
    ]);

    $set = Set::create([
        'league_id' => $league->id,
        'pool_id' => $pool->id,
        'round' => 1,
        'team1_id' => $team1->id,
        'team2_id' => $team2->id,
        'team1_score' => 2,
        'team2_score' => 0,
        'winner_id' => $team1->id,
        'status' => 0,
    ]);

    $pokedex1 = Pokedex::create([
        'nationaldex_id' => 25,
        'name' => 'Pikachu',
        'sprite_url' => 'https://example.com/pikachu.png',
        'type1' => 'Electric',
        'type2' => null,
    ]);

    $pokedex2 = Pokedex::create([
        'nationaldex_id' => 6,
        'name' => 'Charizard',
        'sprite_url' => 'https://example.com/charizard.png',
        'type1' => 'Fire',
        'type2' => 'Flying',
    ]);

    LeaguePokemon::create([
        'league_id' => $league->id,
        'pokedex_id' => $pokedex1->id,
        'drafted_by' => $team1->id,
        'name' => 'Pikachu',
        'cost' => 10,
    ]);

    LeaguePokemon::create([
        'league_id' => $league->id,
        'pokedex_id' => $pokedex2->id,
        'drafted_by' => $team2->id,
        'name' => 'Charizard',
        'cost' => 15,
    ]);

    SetGameResult::create([
        'set_id' => $set->id,
        'game_number' => 1,
        'p1_team_id' => $team1->id,
        'p2_team_id' => $team2->id,
        'winner_team_id' => $team1->id,
        'p1_pokemon' => [$pokedex1->id],
        'p2_pokemon' => [$pokedex2->id],
        'p1_knockouts' => [$pokedex1->id],
        'p2_knockouts' => [],
        'p1_deaths' => [],
        'p2_deaths' => [$pokedex2->id],
        'p1_damage' => [$pokedex1->id => 500],
        'p2_damage' => [$pokedex2->id => 200],
    ]);

    return compact('league', 'team1', 'team2', 'set', 'pokedex1', 'pokedex2');
}

it('returns kill leaders for a league', function (): void {
    ['league' => $league, 'pokedex1' => $pokedex1, 'pokedex2' => $pokedex2] = createLeagueWithGameResults();

    $action = app(ReadLeagueKillLeadersAction::class);
    $result = $action($league);

    expect($result)->toBeArray()->not->toBeEmpty();

    $pikachu = collect($result)->firstWhere('pokedex_id', $pokedex1->id);
    $charizard = collect($result)->firstWhere('pokedex_id', $pokedex2->id);

    expect($pikachu)->not->toBeNull()
        ->and($pikachu['kills'])->toBe(1)
        ->and($pikachu['deaths'])->toBe(0)
        ->and($pikachu['differential'])->toBe(1)
        ->and($pikachu['gp'])->toBe(1)
        ->and($pikachu['name'])->toBe('Pikachu');

    expect($charizard)->not->toBeNull()
        ->and($charizard['kills'])->toBe(0)
        ->and($charizard['deaths'])->toBe(1)
        ->and($charizard['differential'])->toBe(-1)
        ->and($charizard['gp'])->toBe(1)
        ->and($charizard['name'])->toBe('Charizard');
});

it('caches kill leaders and returns the cached result on subsequent calls', function (): void {
    ['league' => $league] = createLeagueWithGameResults();

    $cacheKey = "league:{$league->id}:kill_leaders";
    expect(Cache::has($cacheKey))->toBeFalse();

    $action = app(ReadLeagueKillLeadersAction::class);
    $firstResult = $action($league);

    expect(Cache::has($cacheKey))->toBeTrue();

    $cachedResult = $action($league);
    expect($cachedResult)->toBe($firstResult);
});

it('returns an empty array when no game results exist', function (): void {
    $owner = User::factory()->create();
    $league = League::create([
        'name' => 'Empty League',
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
        'league_owner' => $owner->id,
    ]);

    $result = app(ReadLeagueKillLeadersAction::class)($league);

    expect($result)->toBe([]);
});

it('caches an empty result when no game results exist', function (): void {
    $owner = User::factory()->create();
    $league = League::create([
        'name' => 'Empty Cache League',
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
        'league_owner' => $owner->id,
    ]);

    $cacheKey = "league:{$league->id}:kill_leaders";

    app(ReadLeagueKillLeadersAction::class)($league);

    expect(Cache::has($cacheKey))->toBeTrue();
});

it('recomputes fresh data after the cache is busted', function (): void {
    ['league' => $league, 'pokedex1' => $pokedex1] = createLeagueWithGameResults();

    $cacheKey = "league:{$league->id}:kill_leaders";

    Cache::put($cacheKey, ['stale' => true], now()->addMinutes(30));
    expect(Cache::get($cacheKey))->toBe(['stale' => true]);

    Cache::forget($cacheKey);
    expect(Cache::has($cacheKey))->toBeFalse();

    $freshResult = app(ReadLeagueKillLeadersAction::class)($league);
    expect($freshResult)->toBeArray();
    expect(collect($freshResult)->firstWhere('pokedex_id', $pokedex1->id))->not->toBeNull();
});

it('isolates cache between different leagues', function (): void {
    $owner = User::factory()->create();

    $league1 = League::create([
        'name' => 'League One',
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
        'league_owner' => $owner->id,
    ]);

    $league2 = League::create([
        'name' => 'League Two',
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
        'league_owner' => $owner->id,
    ]);

    $action = app(ReadLeagueKillLeadersAction::class);
    $action($league1);
    $action($league2);

    Cache::forget("league:{$league1->id}:kill_leaders");

    expect(Cache::has("league:{$league1->id}:kill_leaders"))->toBeFalse()
        ->and(Cache::has("league:{$league2->id}:kill_leaders"))->toBeTrue();
});
