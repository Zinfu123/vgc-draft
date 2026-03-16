<?php

use App\Models\User;
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
