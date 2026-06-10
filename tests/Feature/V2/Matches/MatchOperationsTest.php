<?php

use App\Models\User;
use App\Modules\League\Models\League;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\Pool;
use App\Modules\Matches\Models\Set;
use App\Modules\Teams\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createV2MatchSet(User $user1, User $user2): array
{
    $league = League::create([
        'name' => 'V2 Match League',
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
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
    ]);

    $team2 = Team::create([
        'name' => 'Team 2',
        'league_id' => $league->id,
        'user_id' => $user2->id,
        'pick_position' => 2,
        'seed' => 2,
        'pool_id' => $pool->id,
        'draft_points' => 100,
    ]);

    $set = Set::create([
        'league_id' => $league->id,
        'pool_id' => $pool->id,
        'round' => 1,
        'team1_id' => $team1->id,
        'team2_id' => $team2->id,
        'status' => 1,
    ]);

    return [$league, $pool, $set, $team1, $team2];
}

it('renders v2 match detail page', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    [, , $set] = createV2MatchSet($user1, $user2);

    $this->actingAs($user1)
        ->get("/v2/match/set/{$set->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('match/MatchDetail')
            ->where('set.id', $set->id));
});

it('stores a match message via v2 preview route', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    [, , $set] = createV2MatchSet($user1, $user2);

    $this->actingAs($user1)
        ->post("/v2/match/set/{$set->id}/messages", ['body' => 'Hello from v2'])
        ->assertRedirect();

    $this->actingAs($user2)
        ->getJson("/v2/match/set/{$set->id}/messages")
        ->assertOk()
        ->assertJsonCount(1);
});

it('registers matches module auditor', function () {
    $this->artisan('module:audit Matches')
        ->expectsOutputToContain('Matches')
        ->assertSuccessful();
});

it('requires auth for v2 match routes', function () {
    $this->get('/v2/match/set/1')->assertRedirect(route('login'));
});
