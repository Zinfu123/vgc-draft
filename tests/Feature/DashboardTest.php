<?php

use App\Models\User;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Models\League;
use App\Modules\Teams\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/dashboard');
    $response->assertOk();
});

test('dashboard includes user stats with correct totals', function () {
    $user = User::factory()->create();

    $league = League::create([
        'name' => 'Past League',
        'status' => 0,
        'open' => false,
        'draft_points' => 100,
        'league_owner' => $user->id,
    ]);

    Team::create([
        'name' => 'My Team',
        'league_id' => $league->id,
        'user_id' => $user->id,
        'admin_flag' => 1,
        'pick_position' => 1,
        'draft_points' => 100,
        'victory_points' => 0,
        'set_wins' => 5,
        'set_losses' => 2,
        'game_wins' => 12,
        'game_losses' => 4,
        'medal_placement' => 1,
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->where('userName', $user->name)
            ->where('userStats.gold_medals', 1)
            ->where('userStats.silver_medals', 0)
            ->where('userStats.bronze_medals', 0)
            ->where('userStats.game_wins', 12)
            ->where('userStats.game_losses', 4)
            ->where('userStats.set_wins', 5)
            ->where('userStats.set_losses', 2)
            ->where('userStats.playoff_game_wins', 0)
            ->where('userStats.playoff_game_losses', 0)
            ->where('userStats.playoff_set_wins', 0)
            ->where('userStats.playoff_set_losses', 0)
    );
});

test('dashboard includes leagues marked as open that the user has not joined', function () {
    $user = User::factory()->create();

    League::create([
        'name' => 'Open League',
        'status' => 1,
        'open' => true,
        'draft_points' => 100,
        'league_owner' => $user->id,
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page->has('openLeagues', 1)
            ->where('openLeagues.0.name', 'Open League')
    );
});

test('dashboard excludes open leagues the user has already joined', function () {
    $user = User::factory()->create();

    $league = League::create([
        'name' => 'My Active League',
        'status' => 1,
        'open' => true,
        'draft_points' => 100,
        'league_owner' => $user->id,
    ]);

    Team::create([
        'name' => 'My Team',
        'league_id' => $league->id,
        'user_id' => $user->id,
        'admin_flag' => 1,
        'pick_position' => 1,
        'draft_points' => 100,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page->has('openLeagues', 0)
    );
});

test('dashboard active leagues expose draft_date as Y-m-d', function () {
    $user = User::factory()->create();

    $league = League::create([
        'name' => 'Draft Format League',
        'status' => 1,
        'open' => true,
        'draft_points' => 100,
        'league_owner' => $user->id,
        'set_start_date' => '2026-04-10',
        'maximum_teams' => 10,
    ]);

    DraftConfig::create([
        'league_id' => $league->id,
        'draft_date' => '2026-04-03',
        'draft_points' => 100,
        'ban_enabled' => false,
    ]);

    Team::create([
        'name' => 'My Team',
        'league_id' => $league->id,
        'user_id' => $user->id,
        'admin_flag' => 1,
        'pick_position' => 1,
        'draft_points' => 100,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->where('usersActiveLeagues.0.draft_date', '2026-04-03')
            ->where('usersActiveLeagues.0.set_start_date', '2026-04-10')
    );
});

test('dashboard open leagues expose draft_date as Y-m-d', function () {
    $owner = User::factory()->create();
    $visitor = User::factory()->create();

    $league = League::create([
        'name' => 'Open Draft League',
        'status' => 1,
        'open' => true,
        'draft_points' => 100,
        'league_owner' => $owner->id,
        'set_start_date' => '2026-04-15',
        'maximum_teams' => 10,
    ]);

    DraftConfig::create([
        'league_id' => $league->id,
        'draft_date' => '2026-04-05',
        'draft_points' => 100,
        'ban_enabled' => false,
    ]);

    $response = $this->actingAs($visitor)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->has('openLeagues', 1)
            ->where('openLeagues.0.draft_date', '2026-04-05')
            ->where('openLeagues.0.set_start_date', '2026-04-15')
    );
});

test('dashboard past leagues include podium and winner from eager-loaded data', function () {
    $owner = User::factory()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();
    $third = User::factory()->create();

    $league = League::create([
        'name' => 'Finished League',
        'status' => 0,
        'open' => false,
        'draft_points' => 100,
        'league_owner' => $owner->id,
        'winner' => $first->id,
    ]);

    Team::create([
        'name' => 'Owner Team',
        'league_id' => $league->id,
        'user_id' => $owner->id,
        'admin_flag' => 1,
        'pick_position' => 1,
        'draft_points' => 100,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
    ]);

    Team::create([
        'name' => 'First Place',
        'league_id' => $league->id,
        'user_id' => $first->id,
        'admin_flag' => 0,
        'pick_position' => 2,
        'draft_points' => 100,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
        'medal_placement' => 1,
    ]);

    Team::create([
        'name' => 'Second Place',
        'league_id' => $league->id,
        'user_id' => $second->id,
        'admin_flag' => 0,
        'pick_position' => 3,
        'draft_points' => 100,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
        'medal_placement' => 2,
    ]);

    Team::create([
        'name' => 'Third Place',
        'league_id' => $league->id,
        'user_id' => $third->id,
        'admin_flag' => 0,
        'pick_position' => 4,
        'draft_points' => 100,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
        'medal_placement' => 3,
    ]);

    $response = $this->actingAs($owner)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->has('usersPastLeagues', 1)
            ->where('usersPastLeagues.0.winner', $first->name)
            ->where('usersPastLeagues.0.podium.first', $first->name)
            ->where('usersPastLeagues.0.podium.second', $second->name)
            ->where('usersPastLeagues.0.podium.third', $third->name)
    );
});

test('dashboard excludes leagues marked as not open', function () {
    $user = User::factory()->create();

    League::create([
        'name' => 'Closed League',
        'status' => 1,
        'open' => false,
        'draft_points' => 100,
        'league_owner' => $user->id,
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page->has('openLeagues', 0)
    );
});
