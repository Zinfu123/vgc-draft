<?php

use App\Models\User;
use App\Modules\League\Enums\LeagueStatus;
use App\Modules\League\Models\League;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\Pool;
use App\Modules\Teams\Actions\ReadTeamAction;
use App\Modules\Teams\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('groups standings by pool name and exposes the pool relation', function () {
    $owner = User::factory()->create();

    $league = League::create([
        'name' => 'Standings League',
        'status' => LeagueStatus::RegularSeason->value,
        'league_owner' => $owner->id,
        'maximum_teams' => 10,
    ]);

    $matchConfig = MatchConfig::create([
        'league_id' => $league->id,
        'number_of_pools' => 2,
        'enforce_round_count' => false,
    ]);

    $poolAlpha = Pool::create([
        'league_id' => $league->id,
        'match_config_id' => $matchConfig->id,
        'name' => 'Pool Alpha',
    ]);

    $poolBeta = Pool::create([
        'league_id' => $league->id,
        'match_config_id' => $matchConfig->id,
        'name' => 'Pool Beta',
    ]);

    $coach1 = User::factory()->create();
    $coach2 = User::factory()->create();

    Team::create([
        'league_id' => $league->id,
        'user_id' => $coach1->id,
        'name' => 'Alpha Team',
        'pick_position' => 1,
        'pool_id' => $poolAlpha->id,
        'victory_points' => 5,
    ]);

    Team::create([
        'league_id' => $league->id,
        'user_id' => $coach2->id,
        'name' => 'Beta Team',
        'pick_position' => 2,
        'pool_id' => $poolBeta->id,
        'victory_points' => 3,
    ]);

    $standings = (new ReadTeamAction)(['league_id' => $league->id, 'command' => 'standings']);

    expect($standings->keys()->all())->toEqualCanonicalizing(['Pool Alpha', 'Pool Beta'])
        ->and($standings->get('Pool Alpha')->first()->name)->toBe('Alpha Team')
        ->and($standings->get('Pool Beta')->first()->name)->toBe('Beta Team');
});

it('keys standings as Unassigned when a team has no pool', function () {
    $owner = User::factory()->create();

    $league = League::create([
        'name' => 'Unassigned League',
        'status' => LeagueStatus::RegularSeason->value,
        'league_owner' => $owner->id,
        'maximum_teams' => 10,
    ]);

    $coach = User::factory()->create();

    Team::create([
        'league_id' => $league->id,
        'user_id' => $coach->id,
        'name' => 'Lonely Team',
        'pick_position' => 1,
        'pool_id' => null,
        'victory_points' => 1,
    ]);

    $standings = (new ReadTeamAction)(['league_id' => $league->id, 'command' => 'standings']);

    expect($standings->keys()->all())->toBe(['Unassigned']);
});
