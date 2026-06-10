<?php

use App\Models\User;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Models\League;
use App\Modules\Matches\Actions\CreateEditPoolAction;
use App\Modules\Matches\Actions\TeamsToPoolsAction;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\Pool;
use App\Modules\Teams\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createLeagueWithMatchConfig(int $numberOfPools = 1): array
{
    $owner = User::factory()->create();

    $league = League::create([
        'name' => 'Test League',
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
        'draft_points' => 80,
        'league_owner' => $owner->id,
        'maximum_teams' => 10,
    ]);

    DraftConfig::create([
        'league_id' => $league->id,
        'draft_date' => '2026-04-01',
        'draft_points' => 80,
        'ban_enabled' => false,
    ]);

    $matchConfig = MatchConfig::create([
        'league_id' => $league->id,
        'number_of_pools' => $numberOfPools,
        'enforce_round_count' => false,
    ]);

    $initialPool = Pool::create([
        'league_id' => $league->id,
        'match_config_id' => $matchConfig->id,
    ]);

    return [$league, $matchConfig, $initialPool];
}

// CreateEditPoolAction tests

it('creates additional pools up to the configured number_of_pools', function () {
    [$league, $matchConfig] = createLeagueWithMatchConfig(numberOfPools: 3);

    (new CreateEditPoolAction)(['league_id' => $league->id, 'command' => 'create']);

    expect(Pool::where('league_id', $league->id)->count())->toBe(3);
});

it('names new pools sequentially based on the existing pool count for the league', function () {
    [$league] = createLeagueWithMatchConfig(numberOfPools: 3);

    (new CreateEditPoolAction)(['league_id' => $league->id, 'command' => 'create']);

    $newNames = Pool::where('league_id', $league->id)
        ->whereNotNull('name')
        ->orderBy('id')
        ->pluck('name')
        ->all();

    expect($newNames)->toBe(['Pool 2', 'Pool 3']);
});

it('throws when all pools are already created', function () {
    [$league] = createLeagueWithMatchConfig(numberOfPools: 1);

    expect(fn () => (new CreateEditPoolAction)(['league_id' => $league->id, 'command' => 'create']))
        ->toThrow(\Exception::class, 'All pools already created for this league.');
});

it('throws when pool count exceeds number_of_pools', function () {
    [$league, $matchConfig] = createLeagueWithMatchConfig(numberOfPools: 1);

    Pool::create(['league_id' => $league->id, 'match_config_id' => $matchConfig->id]);

    expect(fn () => (new CreateEditPoolAction)(['league_id' => $league->id, 'command' => 'create']))
        ->toThrow(\Exception::class);
});

it('throws when match config does not exist', function () {
    $owner = User::factory()->create();

    $league = League::create([
        'name' => 'No Config League',
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
        'draft_points' => 80,
        'league_owner' => $owner->id,
        'maximum_teams' => 10,
    ]);

    expect(fn () => (new CreateEditPoolAction)(['league_id' => $league->id, 'command' => 'create']))
        ->toThrow(\Exception::class, 'Match config not found for this league.');
});

// TeamsToPoolsAction tests

it('assigns all teams to the single pool when only one pool exists', function () {
    [$league, $matchConfig, $pool] = createLeagueWithMatchConfig(numberOfPools: 1);

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    Team::create(['league_id' => $league->id, 'user_id' => $user1->id, 'name' => 'Team A', 'pick_position' => 1, 'draft_points' => 80, 'seed' => 0]);
    Team::create(['league_id' => $league->id, 'user_id' => $user2->id, 'name' => 'Team B', 'pick_position' => 2, 'draft_points' => 80, 'seed' => 1]);

    (new TeamsToPoolsAction)(['league_id' => $league->id]);

    $teams = Team::where('league_id', $league->id)->get();
    foreach ($teams as $team) {
        expect($team->pool_id)->toBe($pool->id);
    }
});

it('distributes teams across multiple pools by seed', function () {
    [$league, $matchConfig, $pool1] = createLeagueWithMatchConfig(numberOfPools: 2);

    $pool2 = Pool::create(['league_id' => $league->id, 'match_config_id' => $matchConfig->id]);

    $users = User::factory()->count(4)->create();

    foreach ($users as $i => $user) {
        Team::create([
            'league_id' => $league->id,
            'user_id' => $user->id,
            'name' => "Team {$i}",
            'pick_position' => $i + 1,
            'draft_points' => 80,
            'seed' => $i,
            'pool_id' => null,
        ]);
    }

    (new TeamsToPoolsAction)(['league_id' => $league->id]);

    $teams = Team::where('league_id', $league->id)->orderBy('seed')->get();

    expect($teams[0]->pool_id)->toBe($pool1->id);
    expect($teams[1]->pool_id)->toBe($pool2->id);
    expect($teams[2]->pool_id)->toBe($pool1->id);
    expect($teams[3]->pool_id)->toBe($pool2->id);
});

it('throws when no active pools exist for the league', function () {
    [$league, $matchConfig, $pool] = createLeagueWithMatchConfig(numberOfPools: 1);

    $pool->status = 0;
    $pool->save();

    expect(fn () => (new TeamsToPoolsAction)(['league_id' => $league->id]))
        ->toThrow(\Exception::class, 'No active pools found for this league.');
});

it('assigns a team with seed of zero to the first pool', function () {
    [$league, $matchConfig, $pool1] = createLeagueWithMatchConfig(numberOfPools: 2);

    $pool2 = Pool::create(['league_id' => $league->id, 'match_config_id' => $matchConfig->id]);

    $user = User::factory()->create();

    Team::create([
        'league_id' => $league->id,
        'user_id' => $user->id,
        'name' => 'Zero Seed Team',
        'pick_position' => 1,
        'draft_points' => 80,
        'seed' => 0,
        'pool_id' => null,
    ]);

    (new TeamsToPoolsAction)(['league_id' => $league->id]);

    $team = Team::where('league_id', $league->id)->first();
    expect($team->pool_id)->toBe($pool1->id);
});
