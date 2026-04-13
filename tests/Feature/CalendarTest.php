<?php

use App\Models\User;
use App\Modules\Draft\Models\Draft;
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
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
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
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
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
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
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

it('shows per-round match week events when draft is complete', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $startDate = now()->addDays(3)->toDateString();

    $league = League::create([
        'name' => 'Round League',
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
        'draft_points' => 100,
        'league_owner' => $otherUser->id,
        'set_start_date' => $startDate,
    ]);

    DraftConfig::create([
        'league_id' => $league->id,
        'draft_date' => now()->subDay()->toDateString(),
        'draft_points' => 100,
        'minimum_drafts' => 0,
        'ban_enabled' => false,
    ]);

    $matchConfig = MatchConfig::create([
        'league_id' => $league->id,
        'number_of_pools' => 1,
        'frequency_type' => 2,
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
        'draft_points' => 0,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
    ]);

    $opponentTeam = Team::create([
        'name' => 'Opp Team',
        'league_id' => $league->id,
        'user_id' => $otherUser->id,
        'pick_position' => 2,
        'seed' => 2,
        'pool_id' => $pool->id,
        'draft_points' => 0,
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

    Set::create([
        'league_id' => $league->id,
        'pool_id' => $pool->id,
        'round' => 1,
        'team1_id' => $myTeam->id,
        'team2_id' => $opponentTeam->id,
        'status' => 1,
    ]);

    Set::create([
        'league_id' => $league->id,
        'pool_id' => $pool->id,
        'round' => 2,
        'team1_id' => $myTeam->id,
        'team2_id' => $opponentTeam->id,
        'status' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('calendar.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('calendar/CalendarIndex')
            ->where('matchWeekStarts.0.round_label', 'Round 1')
            ->where('matchWeekStarts.0.date', $startDate)
            ->where('matchWeekStarts.1.round_label', 'Round 2')
            ->where('matchWeekStarts.1.date', now()->addDays(3)->addWeek()->toDateString())
        );
});

it('does not show match round events when draft is not complete', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $league = League::create([
        'name' => 'Incomplete Draft League',
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
        'draft_points' => 100,
        'league_owner' => $otherUser->id,
        'set_start_date' => now()->addDays(5)->toDateString(),
    ]);

    $matchConfig = MatchConfig::create([
        'league_id' => $league->id,
        'number_of_pools' => 1,
        'frequency_type' => 2,
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

    Draft::create([
        'league_id' => $league->id,
        'status' => 1,
        'round_number' => 1,
        'pick_number' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('calendar.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('calendar/CalendarIndex')
            ->where('matchWeekStarts', [])
        );
});
