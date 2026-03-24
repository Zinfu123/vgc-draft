<?php

use App\Models\User;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Models\League;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\Pool;
use App\Modules\Teams\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createLeagueWithPool(): array
{
    $league = League::create([
        'name' => 'Test League',
        'status' => 1,
        'draft_points' => 80,
        'league_owner' => 1,
        'maximum_teams' => 10,
    ]);

    DraftConfig::create([
        'league_id' => $league->id,
        'draft_date' => '2026-04-01',
        'draft_points' => 80,
        'ban_enabled' => false,
    ]);

    $matchConfig = MatchConfig::create([
        'league_id' => $league->id,
        'enforce_round_count' => false,
    ]);

    $pool = Pool::create([
        'league_id' => $league->id,
        'match_config_id' => $matchConfig->id,
    ]);

    return [$league, $pool];
}

it('assigns a new team to the default pool', function () {
    $user = User::factory()->create();
    [$league, $pool] = createLeagueWithPool();

    $this->actingAs($user)->post('/teams', [
        'name' => 'My Team',
        'league_id' => $league->id,
        'user_id' => $user->id,
        'pick_position' => 1,
    ])->assertRedirect();

    $team = Team::where('league_id', $league->id)->where('user_id', $user->id)->first();
    expect($team)->not->toBeNull();
    expect($team->pool_id)->toBe($pool->id);
});

it('does not fail team creation when no pool exists for the league', function () {
    $user = User::factory()->create();

    $league = League::create([
        'name' => 'No Pool League',
        'status' => 1,
        'draft_points' => 80,
        'league_owner' => $user->id,
        'maximum_teams' => 10,
    ]);

    DraftConfig::create([
        'league_id' => $league->id,
        'draft_date' => '2026-04-01',
        'draft_points' => 80,
        'ban_enabled' => false,
    ]);

    $this->actingAs($user)->post('/teams', [
        'name' => 'My Team',
        'league_id' => $league->id,
        'user_id' => $user->id,
        'pick_position' => 1,
    ])->assertRedirect();

    $team = Team::where('league_id', $league->id)->where('user_id', $user->id)->first();
    expect($team)->not->toBeNull();
    expect($team->pool_id)->toBeNull();
});
