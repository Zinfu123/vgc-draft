<?php

use App\Models\User;
use App\Modules\League\Models\League;
use App\Modules\Teams\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('renders the team detail inertia page with team and league props', function () {
    $user = User::factory()->create();

    $league = League::create([
        'name' => 'Test League',
        'status' => 1,
        'league_owner' => $user->id,
        'maximum_teams' => 10,
    ]);

    $team = Team::create([
        'name' => 'Alpha Squad',
        'league_id' => $league->id,
        'user_id' => $user->id,
        'pick_position' => 1,
        'set_wins' => 3,
        'set_losses' => 1,
        'victory_points' => 9,
    ]);

    $response = $this->actingAs($user)->get(route('teams.detail', ['team_id' => $team->id]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('teams/TeamDetail')
        ->has('team', fn ($t) => $t
            ->where('name', 'Alpha Squad')
            ->where('set_wins', 3)
            ->where('set_losses', 1)
            ->where('victory_points', 9)
            ->etc()
        )
        ->has('league', fn ($l) => $l
            ->where('name', 'Test League')
            ->where('id', $league->id)
            ->etc()
        )
    );
});
