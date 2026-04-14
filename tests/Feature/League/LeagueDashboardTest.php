<?php

use App\Models\User;
use App\Modules\League\Enums\LeagueStatus;
use App\Modules\League\Models\League;
use App\Modules\Matches\Enums\ScheduleRequestStatus;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\MatchMessage;
use App\Modules\Matches\Models\MatchScheduleRequest;
use App\Modules\Matches\Models\Pool;
use App\Modules\Matches\Models\Set;
use App\Modules\Teams\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeDashboardFixture(): array
{
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $league = League::create([
        'name' => 'Dashboard Test League',
        'status' => LeagueStatus::RegularSeason->value,
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

    return compact('user1', 'user2', 'team1', 'team2', 'league', 'set');
}

it('includes unread_message_count of 0 on nextSet when no messages', function () {
    ['user1' => $user1, 'league' => $league] = makeDashboardFixture();

    $response = $this->actingAs($user1)->get(route('leagues.dashboard', ['league' => $league->id]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('league/LeagueDashboard')
        ->where('nextSet.unread_message_count', 0)
        ->where('nextSet.pending_schedule_request', null)
    );
});

it('includes unread_message_count on nextSet from opponent messages', function () {
    ['user1' => $user1, 'user2' => $user2, 'set' => $set, 'league' => $league] = makeDashboardFixture();

    MatchMessage::create(['set_id' => $set->id, 'user_id' => $user2->id, 'body' => 'Hey!', 'is_read' => false]);
    MatchMessage::create(['set_id' => $set->id, 'user_id' => $user2->id, 'body' => 'Ready?', 'is_read' => false]);
    // Own message — should not be counted
    MatchMessage::create(['set_id' => $set->id, 'user_id' => $user1->id, 'body' => 'Yes!', 'is_read' => false]);

    $response = $this->actingAs($user1)->get(route('leagues.dashboard', ['league' => $league->id]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('league/LeagueDashboard')
        ->where('nextSet.unread_message_count', 2)
    );
});

it('does not count already-read messages in unread_message_count', function () {
    ['user1' => $user1, 'user2' => $user2, 'set' => $set, 'league' => $league] = makeDashboardFixture();

    MatchMessage::create(['set_id' => $set->id, 'user_id' => $user2->id, 'body' => 'Read this', 'is_read' => true]);
    MatchMessage::create(['set_id' => $set->id, 'user_id' => $user2->id, 'body' => 'Unread!', 'is_read' => false]);

    $response = $this->actingAs($user1)->get(route('leagues.dashboard', ['league' => $league->id]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('league/LeagueDashboard')
        ->where('nextSet.unread_message_count', 1)
    );
});

it('includes a pending schedule request sent by the opponent on nextSet', function () {
    ['user1' => $user1, 'user2' => $user2, 'set' => $set, 'league' => $league] = makeDashboardFixture();

    MatchScheduleRequest::create([
        'set_id' => $set->id,
        'proposed_by_user_id' => $user2->id,
        'proposed_at' => now()->addDays(3),
        'status' => ScheduleRequestStatus::Pending->value,
    ]);

    $response = $this->actingAs($user1)->get(route('leagues.dashboard', ['league' => $league->id]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('league/LeagueDashboard')
        ->where('nextSet.pending_schedule_request.is_mine', false)
        ->has('nextSet.pending_schedule_request.proposed_at')
    );
});

it('includes a pending schedule request sent by the current user on nextSet', function () {
    ['user1' => $user1, 'set' => $set, 'league' => $league] = makeDashboardFixture();

    MatchScheduleRequest::create([
        'set_id' => $set->id,
        'proposed_by_user_id' => $user1->id,
        'proposed_at' => now()->addDays(3),
        'status' => ScheduleRequestStatus::Pending->value,
    ]);

    $response = $this->actingAs($user1)->get(route('leagues.dashboard', ['league' => $league->id]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('league/LeagueDashboard')
        ->where('nextSet.pending_schedule_request.is_mine', true)
    );
});

it('returns null for pending_schedule_request when no pending request exists', function () {
    ['user1' => $user1, 'user2' => $user2, 'set' => $set, 'league' => $league] = makeDashboardFixture();

    // Declined request — should not appear
    MatchScheduleRequest::create([
        'set_id' => $set->id,
        'proposed_by_user_id' => $user2->id,
        'proposed_at' => now()->addDays(3),
        'status' => ScheduleRequestStatus::Declined->value,
    ]);

    $response = $this->actingAs($user1)->get(route('leagues.dashboard', ['league' => $league->id]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('league/LeagueDashboard')
        ->where('nextSet.pending_schedule_request', null)
    );
});
