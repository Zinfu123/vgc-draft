<?php

use App\Models\User;
use App\Modules\League\Models\League;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\Pool;
use App\Modules\Matches\Models\Set;
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Facades\Event;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('updating a set updates team statistics correctly when team1 wins 2-0', function () {
    Event::fake();

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $league = League::create([
        'name' => 'Test League',
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
        'name' => 'Team 1',
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
        'name' => 'Team 2',
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

    $response = $this->actingAs($user1)->put('/match', [
        'set_id' => $set->id,
        'team1_id' => $team1->id,
        'team2_id' => $team2->id,
        'team1_score' => 2,
        'team2_score' => 0,
        'command' => 'update',
    ]);

    $response->assertRedirect(route('sets.show', ['set_id' => $set->id]));

    $set->refresh();
    $team1->refresh();
    $team2->refresh();

    expect($set->status)->toBe(0);
    expect($set->winner_id)->toBe($team1->id);
    expect($set->team1_score)->toBe(2);
    expect($set->team2_score)->toBe(0);

    // Team1 won 2-0, so should get 3 victory points
    expect($team1->victory_points)->toBe(3);
    expect($team2->victory_points)->toBe(0);

    // Set wins/losses
    expect($team1->set_wins)->toBe(1);
    expect($team1->set_losses)->toBe(0);
    expect($team2->set_wins)->toBe(0);
    expect($team2->set_losses)->toBe(1);

    // Game wins/losses
    expect($team1->game_wins)->toBe(2);
    expect($team1->game_losses)->toBe(0);
    expect($team2->game_wins)->toBe(0);
    expect($team2->game_losses)->toBe(2);

    Event::assertDispatched(\App\Events\SetUpdatedEvent::class);
});

test('updating a set updates team statistics correctly when team1 wins 2-1', function () {
    Event::fake();

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $league = League::create([
        'name' => 'Test League',
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
        'name' => 'Team 1',
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
        'name' => 'Team 2',
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

    $response = $this->actingAs($user1)->put('/match', [
        'set_id' => $set->id,
        'team1_id' => $team1->id,
        'team2_id' => $team2->id,
        'team1_score' => 2,
        'team2_score' => 1,
        'command' => 'update',
    ]);

    $response->assertRedirect(route('sets.show', ['set_id' => $set->id]));

    $set->refresh();
    $team1->refresh();
    $team2->refresh();

    expect($set->status)->toBe(0);
    expect($set->winner_id)->toBe($team1->id);

    // Team1 won 2-1, so should get 2 victory points, team2 gets 1
    expect($team1->victory_points)->toBe(2);
    expect($team2->victory_points)->toBe(1);

    // Set wins/losses
    expect($team1->set_wins)->toBe(1);
    expect($team1->set_losses)->toBe(0);
    expect($team2->set_wins)->toBe(0);
    expect($team2->set_losses)->toBe(1);

    // Game wins/losses
    expect($team1->game_wins)->toBe(2);
    expect($team1->game_losses)->toBe(1);
    expect($team2->game_wins)->toBe(1);
    expect($team2->game_losses)->toBe(2);
});

test('updating a set updates team statistics correctly when team2 wins 2-1', function () {
    Event::fake();

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $league = League::create([
        'name' => 'Test League',
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
        'name' => 'Team 1',
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
        'name' => 'Team 2',
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

    $response = $this->actingAs($user1)->put('/match', [
        'set_id' => $set->id,
        'team1_id' => $team1->id,
        'team2_id' => $team2->id,
        'team1_score' => 1,
        'team2_score' => 2,
        'command' => 'update',
    ]);

    $response->assertRedirect(route('sets.show', ['set_id' => $set->id]));

    $set->refresh();
    $team1->refresh();
    $team2->refresh();

    expect($set->status)->toBe(0);
    expect($set->winner_id)->toBe($team2->id);

    // Team2 won 2-1, so team1 gets 1 victory point, team2 gets 2
    expect($team1->victory_points)->toBe(1);
    expect($team2->victory_points)->toBe(2);

    // Set wins/losses
    expect($team1->set_wins)->toBe(0);
    expect($team1->set_losses)->toBe(1);
    expect($team2->set_wins)->toBe(1);
    expect($team2->set_losses)->toBe(0);

    // Game wins/losses
    expect($team1->game_wins)->toBe(1);
    expect($team1->game_losses)->toBe(2);
    expect($team2->game_wins)->toBe(2);
    expect($team2->game_losses)->toBe(1);
});

test('updating a completed set does not update team statistics again', function () {
    Event::fake();

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $league = League::create([
        'name' => 'Test League',
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
        'name' => 'Team 1',
        'league_id' => $league->id,
        'user_id' => $user1->id,
        'pick_position' => 1,
        'seed' => 1,
        'pool_id' => $pool->id,
        'draft_points' => 100,
        'victory_points' => 3,
        'set_wins' => 1,
        'set_losses' => 0,
        'game_wins' => 2,
        'game_losses' => 0,
    ]);

    $team2 = Team::create([
        'name' => 'Team 2',
        'league_id' => $league->id,
        'user_id' => $user2->id,
        'pick_position' => 2,
        'seed' => 2,
        'pool_id' => $pool->id,
        'draft_points' => 100,
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
        'status' => 0, // Already completed
    ]);

    $initialTeam1VictoryPoints = $team1->victory_points;
    $initialTeam2VictoryPoints = $team2->victory_points;

    $response = $this->actingAs($user1)->put('/match', [
        'set_id' => $set->id,
        'team1_id' => $team1->id,
        'team2_id' => $team2->id,
        'team1_score' => 2,
        'team2_score' => 1, // Trying to change the score
        'command' => 'update',
    ]);

    $response->assertRedirect(route('sets.show', ['set_id' => $set->id]));

    $team1->refresh();
    $team2->refresh();

    // Statistics should remain unchanged
    expect($team1->victory_points)->toBe($initialTeam1VictoryPoints);
    expect($team2->victory_points)->toBe($initialTeam2VictoryPoints);
    expect($team1->set_wins)->toBe(1);
    expect($team2->set_losses)->toBe(1);

    // Event should not be dispatched
    Event::assertNotDispatched(\App\Events\SetUpdatedEvent::class);
});
