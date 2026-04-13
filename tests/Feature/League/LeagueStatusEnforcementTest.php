<?php

use App\Models\User;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Enums\LeagueStagingStatus;
use App\Modules\League\Enums\LeagueStatus;
use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\Pool;
use App\Modules\Matches\Models\Set;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Teams\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// ── Helpers ──────────────────────────────────────────────────────────────────

function makeEnforcementLeague(LeagueStatus $status, ?LeagueStagingStatus $stagingSubStatus = null): array
{
    $owner = User::factory()->create(['discord_id' => '999999999999999999']);

    $league = League::create([
        'name' => 'Enforcement League',
        'status' => $status->value,
        'staging_sub_status' => $stagingSubStatus?->value,
        'league_owner' => $owner->id,
        'free_trade_window_hours' => 24,
        'maximum_teams' => 10,
    ]);

    DraftConfig::create([
        'league_id' => $league->id,
        'draft_date' => now()->addDay(),
        'draft_points' => 80,
        'ban_enabled' => false,
        'minimum_drafts' => 1,
    ]);

    $member = User::factory()->create(['discord_id' => '111111111111111111']);

    $team = Team::create([
        'name' => 'Alpha',
        'league_id' => $league->id,
        'user_id' => $owner->id,
        'admin_flag' => 1,
        'pick_position' => 1,
        'seed' => 1,
        'draft_points' => 80,
        'trades' => 5,
    ]);

    $targetTeam = Team::create([
        'name' => 'Beta',
        'league_id' => $league->id,
        'user_id' => $member->id,
        'admin_flag' => 0,
        'pick_position' => 2,
        'seed' => 2,
        'draft_points' => 80,
        'trades' => 5,
    ]);

    $pd1 = Pokedex::create(['nationaldex_id' => 1, 'name' => 'Bulbasaur', 'type1' => 'Grass']);
    $pd2 = Pokedex::create(['nationaldex_id' => 4, 'name' => 'Charmander', 'type1' => 'Fire']);

    $poke1 = LeaguePokemon::create(['league_id' => $league->id, 'pokedex_id' => $pd1->id, 'name' => 'Bulbasaur', 'cost' => 10, 'drafted_by' => $team->id, 'is_drafted' => true]);
    $poke2 = LeaguePokemon::create(['league_id' => $league->id, 'pokedex_id' => $pd2->id, 'name' => 'Charmander', 'cost' => 10, 'drafted_by' => $targetTeam->id, 'is_drafted' => true]);

    return [$league, $owner, $member, $team, $targetTeam, $poke1, $poke2];
}

// ── Join League (Registration only) ──────────────────────────────────────────

it('allows joining a league during Registration', function () {
    $owner = User::factory()->create();
    $newUser = User::factory()->create(['showdown_username' => 'NewCoach']);

    $league = League::create([
        'name' => 'Join Test',
        'status' => LeagueStatus::Registration->value,
        'league_owner' => $owner->id,
        'maximum_teams' => 10,
    ]);
    DraftConfig::create(['league_id' => $league->id, 'draft_points' => 80, 'ban_enabled' => false]);

    Team::create([
        'name' => 'Owner Team',
        'league_id' => $league->id,
        'user_id' => $owner->id,
        'admin_flag' => 1,
        'pick_position' => 1,
        'draft_points' => 80,
    ]);

    $this->actingAs($newUser)->post('/teams', [
        'name' => 'New Team',
        'league_id' => $league->id,
        'user_id' => $newUser->id,
        'pick_position' => 2,
    ])->assertRedirect();

    expect(Team::where('league_id', $league->id)->where('user_id', $newUser->id)->exists())->toBeTrue();
});

it('blocks joining a league that is past Registration', function (LeagueStatus $status) {
    $owner = User::factory()->create();
    $newUser = User::factory()->create(['showdown_username' => 'Blocked']);

    $league = League::create([
        'name' => 'Active League',
        'status' => $status->value,
        'league_owner' => $owner->id,
        'maximum_teams' => 10,
    ]);
    DraftConfig::create(['league_id' => $league->id, 'draft_points' => 80, 'ban_enabled' => false]);

    $this->actingAs($newUser)->post('/teams', [
        'name' => 'Late Team',
        'league_id' => $league->id,
        'user_id' => $newUser->id,
        'pick_position' => 1,
    ])->assertSessionHasErrors('league_id');
})->with([
    'Staging' => [LeagueStatus::Staging],
    'RegularSeason' => [LeagueStatus::RegularSeason],
    'Playoffs' => [LeagueStatus::Playoffs],
    'Completed' => [LeagueStatus::Completed],
]);

// ── Trades ────────────────────────────────────────────────────────────────────

it('allows trades during RegularSeason', function () {
    [$league, $owner, , $team, $targetTeam, $poke1, $poke2] = makeEnforcementLeague(LeagueStatus::RegularSeason);

    $this->actingAs($owner)->post("/leagues/{$league->id}/trades", [
        'target_team_id' => $targetTeam->id,
        'offered_pokemon_ids' => [$poke1->id],
        'requested_pokemon_ids' => [$poke2->id],
    ])->assertSessionHasNoErrors();
});

it('blocks trades during Registration', function () {
    [$league, $owner, , $team, $targetTeam, $poke1, $poke2] = makeEnforcementLeague(LeagueStatus::Registration);

    $this->actingAs($owner)->post("/leagues/{$league->id}/trades", [
        'target_team_id' => $targetTeam->id,
        'offered_pokemon_ids' => [$poke1->id],
        'requested_pokemon_ids' => [$poke2->id],
    ])->assertSessionHasErrors('league_id');
});

it('blocks trades during Staging outside of free trade window', function () {
    [$league, $owner, , $team, $targetTeam, $poke1, $poke2] = makeEnforcementLeague(
        LeagueStatus::Staging,
        LeagueStagingStatus::PreDraft
    );

    $this->actingAs($owner)->post("/leagues/{$league->id}/trades", [
        'target_team_id' => $targetTeam->id,
        'offered_pokemon_ids' => [$poke1->id],
        'requested_pokemon_ids' => [$poke2->id],
    ])->assertSessionHasErrors('league_id');
});

it('blocks trades during Completed leagues', function () {
    [$league, $owner, , $team, $targetTeam, $poke1, $poke2] = makeEnforcementLeague(LeagueStatus::Completed);

    $this->actingAs($owner)->post("/leagues/{$league->id}/trades", [
        'target_team_id' => $targetTeam->id,
        'offered_pokemon_ids' => [$poke1->id],
        'requested_pokemon_ids' => [$poke2->id],
    ])->assertSessionHasErrors('league_id');
});

// ── Match Score Submission ────────────────────────────────────────────────────

function makeSetForLeague(League $league, Team $team1, Team $team2): Set
{
    $matchConfig = MatchConfig::firstOrCreate(['league_id' => $league->id], ['number_of_pools' => 1]);
    $pool = Pool::firstOrCreate(['league_id' => $league->id], ['match_config_id' => $matchConfig->id]);

    return Set::create([
        'league_id' => $league->id,
        'pool_id' => $pool->id,
        'round' => 1,
        'team1_id' => $team1->id,
        'team2_id' => $team2->id,
        'status' => 1,
    ]);
}

it('allows score submission during RegularSeason', function () {
    [$league, $owner, , $team, $targetTeam] = makeEnforcementLeague(LeagueStatus::RegularSeason);
    $set = makeSetForLeague($league, $team, $targetTeam);

    $this->actingAs($owner)->put('/match', [
        'set_id' => $set->id,
        'team1_id' => $team->id,
        'team2_id' => $targetTeam->id,
        'team1_score' => 2,
        'team2_score' => 0,
        'command' => 'update',
    ])->assertRedirect();

    expect($set->fresh()->status)->toBe(0);
});

it('allows score submission during Playoffs', function () {
    [$league, $owner, , $team, $targetTeam] = makeEnforcementLeague(LeagueStatus::Playoffs);
    $set = makeSetForLeague($league, $team, $targetTeam);

    $this->actingAs($owner)->put('/match', [
        'set_id' => $set->id,
        'team1_id' => $team->id,
        'team2_id' => $targetTeam->id,
        'team1_score' => 2,
        'team2_score' => 0,
        'command' => 'update',
    ])->assertRedirect();

    expect($set->fresh()->status)->toBe(0);
});

it('blocks score submission during Registration', function () {
    [$league, $owner, , $team, $targetTeam] = makeEnforcementLeague(LeagueStatus::Registration);
    $set = makeSetForLeague($league, $team, $targetTeam);

    $this->actingAs($owner)->put('/match', [
        'set_id' => $set->id,
        'team1_id' => $team->id,
        'team2_id' => $targetTeam->id,
        'team1_score' => 2,
        'team2_score' => 0,
        'command' => 'update',
    ])->assertRedirect();

    expect($set->fresh()->status)->toBe(1);
});

// ── Pokémon Pool Editing ──────────────────────────────────────────────────────

it('allows pool editing during Registration', function () {
    [$league, $owner] = makeEnforcementLeague(LeagueStatus::Registration);
    $pd = Pokedex::create(['nationaldex_id' => 999, 'name' => 'Newmon', 'type1' => 'Normal']);

    $this->actingAs($owner)
        ->post(route('leagues.admin.pokemon-pool.store', $league), ['pokedex_id' => $pd->id, 'cost' => 5])
        ->assertRedirect();

    expect(LeaguePokemon::where('league_id', $league->id)->where('pokedex_id', $pd->id)->exists())->toBeTrue();
});

it('allows pool editing during Staging PreDraft', function () {
    [$league, $owner] = makeEnforcementLeague(LeagueStatus::Staging, LeagueStagingStatus::PreDraft);
    $pd = Pokedex::create(['nationaldex_id' => 998, 'name' => 'Premon', 'type1' => 'Normal']);

    $this->actingAs($owner)
        ->post(route('leagues.admin.pokemon-pool.store', $league), ['pokedex_id' => $pd->id, 'cost' => 5])
        ->assertRedirect();

    expect(LeaguePokemon::where('league_id', $league->id)->where('pokedex_id', $pd->id)->exists())->toBeTrue();
});

it('blocks pool editing during RegularSeason', function () {
    [$league, $owner] = makeEnforcementLeague(LeagueStatus::RegularSeason);
    $pd = Pokedex::create(['nationaldex_id' => 997, 'name' => 'Latemon', 'type1' => 'Normal']);

    $this->actingAs($owner)
        ->post(route('leagues.admin.pokemon-pool.store', $league), ['pokedex_id' => $pd->id, 'cost' => 5])
        ->assertForbidden();
});

it('blocks pool editing during Staging DraftInProgress', function () {
    [$league, $owner] = makeEnforcementLeague(LeagueStatus::Staging, LeagueStagingStatus::DraftInProgress);
    $pd = Pokedex::create(['nationaldex_id' => 996, 'name' => 'Draftmon', 'type1' => 'Normal']);

    $this->actingAs($owner)
        ->post(route('leagues.admin.pokemon-pool.store', $league), ['pokedex_id' => $pd->id, 'cost' => 5])
        ->assertForbidden();
});

// ── Playoff Bracket Generation ────────────────────────────────────────────────

it('passes the Playoffs phase check when generating (may fail on bracket config, not phase)', function () {
    [$league, $owner, , $team, $targetTeam] = makeEnforcementLeague(LeagueStatus::Playoffs);

    $this->actingAs($owner)->get(route('leagues.admin.playoffs', $league));

    $response = $this->actingAs($owner)
        ->post(route('leagues.admin.playoffs.generate', $league));

    $errors = session('errors')?->all() ?? [];
    $phaseError = 'The playoff bracket can only be generated when the league is in the Playoffs phase.';
    expect($errors)->not->toContain($phaseError);
});

it('blocks playoff generation when league is not in Playoffs phase', function (LeagueStatus $status) {
    [$league, $owner, , $team, $targetTeam] = makeEnforcementLeague($status);

    $this->actingAs($owner)->get(route('leagues.admin.playoffs', $league));

    $this->actingAs($owner)
        ->post(route('leagues.admin.playoffs.generate', $league))
        ->assertSessionHasErrors();
})->with([
    'Registration' => [LeagueStatus::Registration],
    'Staging' => [LeagueStatus::Staging],
    'RegularSeason' => [LeagueStatus::RegularSeason],
]);
