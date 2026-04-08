<?php

use App\Models\User;
use App\Modules\League\Models\League;
use App\Modules\Matches\Actions\CreateEditSetsAction;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\Pool;
use App\Modules\Matches\Models\Set;
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Facades\Event;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * @return array{0: Set, 1: Team, 2: Team}
 */
function setWithTwoTeams(): array
{
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $league = League::create([
        'name' => 'Guard Test League',
        'status' => 1,
        'draft_points' => 100,
        'league_owner' => $user1->id,
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
        'user_id' => $user1->id,
        'pick_position' => 1,
        'seed' => 1,
        'pool_id' => $pool->id,
        'draft_points' => 100,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
    ]);

    $team2 = Team::create([
        'name' => 'Team Beta',
        'league_id' => $league->id,
        'user_id' => $user2->id,
        'pick_position' => 2,
        'seed' => 2,
        'pool_id' => $pool->id,
        'draft_points' => 100,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
    ]);

    $set = Set::create([
        'league_id' => $league->id,
        'pool_id' => $pool->id,
        'round' => 1,
        'team1_id' => $team1->id,
        'team2_id' => $team2->id,
        'status' => 1,
    ]);

    return [$set, $team1, $team2];
}

it('completes a set and applies victory points exactly once on a 2-0 result', function () {
    Event::fake();

    [$set, $team1, $team2] = setWithTwoTeams();

    $result = app(CreateEditSetsAction::class)([
        'command' => 'update',
        'set_id' => $set->id,
        'team1_id' => $team1->id,
        'team2_id' => $team2->id,
        'team1_score' => 2,
        'team2_score' => 0,
    ]);

    expect($result)->toBeTrue();

    $team1->refresh();
    $team2->refresh();

    expect($team1->victory_points)->toBe(3)
        ->and($team1->set_wins)->toBe(1)
        ->and($team1->set_losses)->toBe(0)
        ->and($team2->victory_points)->toBe(0)
        ->and($team2->set_wins)->toBe(0)
        ->and($team2->set_losses)->toBe(1);
});

it('completes a set and applies victory points exactly once on a 2-1 result', function () {
    Event::fake();

    [$set, $team1, $team2] = setWithTwoTeams();

    app(CreateEditSetsAction::class)([
        'command' => 'update',
        'set_id' => $set->id,
        'team1_id' => $team1->id,
        'team2_id' => $team2->id,
        'team1_score' => 2,
        'team2_score' => 1,
    ]);

    $team1->refresh();
    $team2->refresh();

    expect($team1->victory_points)->toBe(2)
        ->and($team2->victory_points)->toBe(1);
});

it('ignores a duplicate submission on an already-completed set', function () {
    Event::fake();

    [$set, $team1, $team2] = setWithTwoTeams();

    $payload = [
        'command' => 'update',
        'set_id' => $set->id,
        'team1_id' => $team1->id,
        'team2_id' => $team2->id,
        'team1_score' => 2,
        'team2_score' => 0,
    ];

    app(CreateEditSetsAction::class)($payload);
    $secondResult = app(CreateEditSetsAction::class)($payload);

    expect($secondResult)->toBeNull();

    $team1->refresh();
    $team2->refresh();

    // Points must only be applied once — total across both teams always equals 3
    expect($team1->victory_points + $team2->victory_points)->toBe(3);
});

it('ensures completed set totals exactly 3 points regardless of score (2-0)', function () {
    Event::fake();

    [$set, $team1, $team2] = setWithTwoTeams();

    app(CreateEditSetsAction::class)([
        'command' => 'update',
        'set_id' => $set->id,
        'team1_id' => $team1->id,
        'team2_id' => $team2->id,
        'team1_score' => 2,
        'team2_score' => 0,
    ]);

    $team1->refresh();
    $team2->refresh();

    expect($team1->victory_points + $team2->victory_points)->toBe(3);
});

it('ensures completed set totals exactly 3 points regardless of score (2-1)', function () {
    Event::fake();

    [$set, $team1, $team2] = setWithTwoTeams();

    app(CreateEditSetsAction::class)([
        'command' => 'update',
        'set_id' => $set->id,
        'team1_id' => $team1->id,
        'team2_id' => $team2->id,
        'team1_score' => 1,
        'team2_score' => 2,
    ]);

    $team1->refresh();
    $team2->refresh();

    expect($team1->victory_points + $team2->victory_points)->toBe(3);
});
