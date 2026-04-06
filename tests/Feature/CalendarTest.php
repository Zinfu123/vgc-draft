<?php

use App\Models\User;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Models\League;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\Pool;
use App\Modules\Matches\Models\Set;
use App\Modules\Teams\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('requires authentication to view the calendar', function () {
    $this->get(route('calendar.index'))->assertRedirect(route('login'));
});

it('returns the calendar page for an authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('calendar.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('calendar/CalendarIndex'));
});

it('includes draft days for leagues the user is in', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $league = League::create([
        'name' => 'Draft League',
        'status' => 1,
        'draft_points' => 100,
        'league_owner' => $otherUser->id,
        'set_start_date' => now()->addMonths(1)->toDateString(),
    ]);

    DraftConfig::create([
        'league_id' => $league->id,
        'draft_date' => now()->addWeeks(2)->toDateString(),
        'draft_points' => 100,
        'minimum_drafts' => 0,
        'ban_enabled' => false,
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

    Team::create([
        'name' => 'My Team',
        'league_id' => $league->id,
        'user_id' => $user->id,
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

    $this->actingAs($user)
        ->get(route('calendar.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('calendar/CalendarIndex')
            ->where('draftDays.0.league_name', 'Draft League')
        );
});

it('includes scheduled matches for the user', function () {
    $user = User::factory()->create();
    $opponent = User::factory()->create();

    $league = League::create([
        'name' => 'Match League',
        'status' => 1,
        'draft_points' => 100,
        'league_owner' => $opponent->id,
        'set_start_date' => now()->addMonths(1)->toDateString(),
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

    $myTeam = Team::create([
        'name' => 'My Team',
        'league_id' => $league->id,
        'user_id' => $user->id,
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

    $opponentTeam = Team::create([
        'name' => 'Opponent Team',
        'league_id' => $league->id,
        'user_id' => $opponent->id,
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

    Set::create([
        'league_id' => $league->id,
        'pool_id' => $pool->id,
        'round' => 1,
        'team1_id' => $myTeam->id,
        'team2_id' => $opponentTeam->id,
        'status' => 1,
        'scheduled_at' => now()->addDays(5),
    ]);

    $this->actingAs($user)
        ->get(route('calendar.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('calendar/CalendarIndex')
            ->where('scheduledMatches.0.opponent_team_name', 'Opponent Team')
        );
});

it('does not include events from leagues the user is not in', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $league = League::create([
        'name' => 'Other League',
        'status' => 1,
        'draft_points' => 100,
        'league_owner' => $otherUser->id,
        'set_start_date' => now()->addMonths(1)->toDateString(),
    ]);

    DraftConfig::create([
        'league_id' => $league->id,
        'draft_date' => now()->addWeeks(2)->toDateString(),
        'draft_points' => 100,
        'minimum_drafts' => 0,
        'ban_enabled' => false,
    ]);

    $this->actingAs($user)
        ->get(route('calendar.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('calendar/CalendarIndex')
            ->where('draftDays', [])
        );
});
