<?php

use App\Models\User;
use App\Modules\Draft\Models\Draft;
use App\Modules\League\Models\League;
use App\Modules\Teams\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('redirects draft detail to league draft recap when the draft is completed', function () {
    $user = User::factory()->create();
    $league = League::create([
        'name' => 'Recap League',
        'status' => \App\Modules\League\Enums\LeagueStatus::Staging->value,
        'league_owner' => $user->id,
        'maximum_teams' => 10,
    ]);
    Team::create([
        'name' => 'Squad',
        'league_id' => $league->id,
        'user_id' => $user->id,
        'admin_flag' => 0,
        'pick_position' => 1,
        'draft_points' => 100,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
    ]);
    Draft::create([
        'league_id' => $league->id,
        'status' => 0,
        'round_number' => 1,
        'pick_number' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('draft.detail', ['league_id' => $league->id]))
        ->assertRedirect(route('leagues.draft', ['league' => $league->id]));
});
