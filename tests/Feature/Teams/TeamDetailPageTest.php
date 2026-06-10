<?php

use App\Models\User;
use App\Modules\League\Models\League;
use App\Modules\Teams\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('redirects to the league dashboard when visiting a team detail page', function () {
    $user = User::factory()->create();

    $league = League::create([
        'name' => 'Test League',
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
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

    $response->assertRedirect(route('leagues.dashboard', ['league' => $league->id, 'team' => $team->id]));
});
