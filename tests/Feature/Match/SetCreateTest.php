<?php

use App\Models\User;
use App\Modules\League\Models\League;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\Pool;
use App\Modules\Matches\Models\Set;
use App\Modules\Teams\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createLeagueWithTeams(int $teamCount, ?int $roundCount = null): array
{
    $owner = User::factory()->create();

    $league = League::create([
        'name' => 'Test League',
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
        'draft_points' => 100,
        'league_owner' => $owner->id,
    ]);

    $matchConfig = MatchConfig::create([
        'league_id' => $league->id,
        'number_of_pools' => 1,
        'status' => 1,
        'round_count' => $roundCount,
    ]);

    $pool = Pool::create([
        'league_id' => $league->id,
        'match_config_id' => $matchConfig->id,
        'status' => 1,
    ]);

    $users = User::factory()->count($teamCount)->create();
    foreach ($users as $index => $user) {
        Team::create([
            'name' => "Team {$index}",
            'league_id' => $league->id,
            'user_id' => $user->id,
            'pick_position' => $index + 1,
            'seed' => $index + 1,
            'pool_id' => $pool->id,
            'draft_points' => 100,
            'victory_points' => 0,
            'set_wins' => 0,
            'set_losses' => 0,
            'game_wins' => 0,
            'game_losses' => 0,
        ]);
    }

    return [$owner, $league, $pool];
}

test('creating sets with no round_count defaults to round-robin (n-1 rounds)', function () {
    [$owner, $league, $pool] = createLeagueWithTeams(teamCount: 4, roundCount: null);

    $response = $this->actingAs($owner)->post("/match/{$league->id}/create", [
        'league_id' => $league->id,
    ]);

    $response->assertRedirect(route('leagues.detail', ['league' => $league->id]));

    // 4 teams → 3 rounds of round-robin, 2 matchups per round = 6 sets total
    $rounds = Set::where('league_id', $league->id)->distinct()->pluck('round');
    expect($rounds->count())->toBe(3);
    expect(Set::where('league_id', $league->id)->count())->toBe(6);
});

test('creating sets with round_count set enforces that number of rounds', function () {
    [$owner, $league, $pool] = createLeagueWithTeams(teamCount: 4, roundCount: 2);

    $response = $this->actingAs($owner)->post("/match/{$league->id}/create", [
        'league_id' => $league->id,
    ]);

    $response->assertRedirect(route('leagues.detail', ['league' => $league->id]));

    // round_count = 2, so only 2 rounds should be created
    $rounds = Set::where('league_id', $league->id)->distinct()->pluck('round');
    expect($rounds->count())->toBe(2);
    expect(Set::where('league_id', $league->id)->count())->toBe(4);
});

test('creating sets with round_count of 0 falls back to default round-robin', function () {
    [$owner, $league, $pool] = createLeagueWithTeams(teamCount: 4, roundCount: 0);

    $response = $this->actingAs($owner)->post("/match/{$league->id}/create", [
        'league_id' => $league->id,
    ]);

    $response->assertRedirect(route('leagues.detail', ['league' => $league->id]));

    // round_count = 0 → falls back to teamsCount - 1 = 3 rounds
    $rounds = Set::where('league_id', $league->id)->distinct()->pluck('round');
    expect($rounds->count())->toBe(3);
});

test('creating sets with round_count larger than round-robin generates more rounds', function () {
    [$owner, $league, $pool] = createLeagueWithTeams(teamCount: 4, roundCount: 5);

    $response = $this->actingAs($owner)->post("/match/{$league->id}/create", [
        'league_id' => $league->id,
    ]);

    $response->assertRedirect(route('leagues.detail', ['league' => $league->id]));

    $rounds = Set::where('league_id', $league->id)->distinct()->pluck('round');
    expect($rounds->count())->toBe(5);
});

test('two-team league with more rounds than round-robin never produces a team vs itself', function () {
    [$owner, $league, $pool] = createLeagueWithTeams(teamCount: 2, roundCount: 8);

    $this->actingAs($owner)->post("/match/{$league->id}/create", [
        'league_id' => $league->id,
    ])->assertRedirect();

    $sets = Set::where('league_id', $league->id)->get();

    // 8 rounds × 1 matchup each = 8 sets
    expect($sets->count())->toBe(8);

    // No set should ever pit a team against itself
    foreach ($sets as $set) {
        expect($set->team1_id)->not->toBe($set->team2_id);
    }
});
