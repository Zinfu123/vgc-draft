<?php

use App\Models\User;
use App\Modules\Draft\Actions\CreateEditDraftAction;
use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\Draft\Models\DraftPick;
use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\Pool;
use App\Modules\Matches\Models\Set;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Teams\Models\Team;
use App\Modules\Trade\Models\Trade;
use Spatie\Activitylog\Models\Activity;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// Shared helper to build a minimal league with one team and one league pokemon.
function makeLeagueWithTeamAndPokemon(): array
{
    $owner = User::factory()->create();
    $league = League::create(['name' => 'Activity Log League', 'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value, 'open' => true, 'league_owner' => $owner->id]);
    DraftConfig::create(['league_id' => $league->id, 'draft_points' => 80, 'minimum_drafts' => 0, 'enforce_round_count' => false, 'ban_enabled' => false]);
    $team = Team::create(['name' => 'Team A', 'league_id' => $league->id, 'user_id' => $owner->id, 'pick_position' => 1, 'draft_points' => 80, 'victory_points' => 0, 'set_wins' => 0, 'set_losses' => 0, 'game_wins' => 0, 'game_losses' => 0]);
    $pokedex = Pokedex::create(['nationaldex_id' => 25, 'name' => 'Pikachu', 'type1' => 'Electric', 'type2' => null, 'sprite_url' => null]);
    $leaguePokemon = LeaguePokemon::create(['league_id' => $league->id, 'pokedex_id' => $pokedex->id, 'name' => 'Pikachu', 'cost' => 10, 'banned' => false, 'is_drafted' => false]);

    return compact('owner', 'league', 'team', 'pokedex', 'leaguePokemon');
}

// --- Draft Pick ---

it('logs a created activity when a draft pick is created', function () {
    ['owner' => $user, 'league' => $league, 'team' => $team, 'leaguePokemon' => $leaguePokemon] = makeLeagueWithTeamAndPokemon();
    $this->actingAs($user);

    $draft = Draft::create(['league_id' => $league->id, 'round_number' => 1, 'status' => 1, 'pick_number' => 1]);

    $pick = DraftPick::create([
        'draft_id' => $draft->id,
        'team_id' => $team->id,
        'league_pokemon_id' => $leaguePokemon->id,
        'round_number' => 1,
        'pick_number' => 1,
        'league_id' => $league->id,
    ]);

    $activity = Activity::query()->where('subject_type', DraftPick::class)->first();
    expect($activity)->not->toBeNull();
    expect($activity->event)->toBe('created');
    expect($activity->subject_id)->toBe($pick->id);
    expect($activity->causer_id)->toBe($user->id);
});

it('records the causer as null for anonymous draft pick creation', function () {
    ['league' => $league, 'team' => $team, 'leaguePokemon' => $leaguePokemon] = makeLeagueWithTeamAndPokemon();

    $draft = Draft::create(['league_id' => $league->id, 'round_number' => 1, 'status' => 1, 'pick_number' => 1]);

    DraftPick::create([
        'draft_id' => $draft->id,
        'team_id' => $team->id,
        'league_pokemon_id' => $leaguePokemon->id,
        'round_number' => 1,
        'pick_number' => 1,
        'league_id' => $league->id,
    ]);

    $activity = Activity::query()->where('subject_type', DraftPick::class)->first();
    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBeNull();
});

// --- Trade ---

it('logs a created activity when a trade is created', function () {
    ['owner' => $user, 'league' => $league, 'team' => $teamA] = makeLeagueWithTeamAndPokemon();
    $teamB = Team::create(['name' => 'Team B', 'league_id' => $league->id, 'user_id' => User::factory()->create()->id, 'pick_position' => 2, 'draft_points' => 80, 'victory_points' => 0, 'set_wins' => 0, 'set_losses' => 0, 'game_wins' => 0, 'game_losses' => 0]);
    $this->actingAs($user);

    $trade = Trade::create([
        'league_id' => $league->id,
        'requesting_team_id' => $teamA->id,
        'target_team_id' => $teamB->id,
        'counterparty' => 'team',
        'status' => 'pending',
    ]);

    $activity = Activity::query()->where('subject_type', Trade::class)->where('event', 'created')->first();
    expect($activity)->not->toBeNull();
    expect($activity->subject_id)->toBe($trade->id);
    expect($activity->causer_id)->toBe($user->id);
});

it('logs an updated activity when trade status changes', function () {
    ['owner' => $user, 'league' => $league, 'team' => $teamA] = makeLeagueWithTeamAndPokemon();
    $teamB = Team::create(['name' => 'Team B', 'league_id' => $league->id, 'user_id' => User::factory()->create()->id, 'pick_position' => 2, 'draft_points' => 80, 'victory_points' => 0, 'set_wins' => 0, 'set_losses' => 0, 'game_wins' => 0, 'game_losses' => 0]);
    $this->actingAs($user);

    $trade = Trade::create([
        'league_id' => $league->id,
        'requesting_team_id' => $teamA->id,
        'target_team_id' => $teamB->id,
        'counterparty' => 'team',
        'status' => 'pending',
    ]);

    $trade->update(['status' => 'accepted']);

    $updatedActivity = Activity::query()
        ->where('subject_type', Trade::class)
        ->where('event', 'updated')
        ->first();

    expect($updatedActivity)->not->toBeNull();
    expect($updatedActivity->attribute_changes['attributes']['status'])->toBe('accepted');
    expect($updatedActivity->attribute_changes['old']['status'])->toBe('pending');
});

it('does not log a trade update when non-tracked attributes change', function () {
    ['owner' => $user, 'league' => $league, 'team' => $teamA] = makeLeagueWithTeamAndPokemon();
    $teamB = Team::create(['name' => 'Team B', 'league_id' => $league->id, 'user_id' => User::factory()->create()->id, 'pick_position' => 2, 'draft_points' => 80, 'victory_points' => 0, 'set_wins' => 0, 'set_losses' => 0, 'game_wins' => 0, 'game_losses' => 0]);
    $this->actingAs($user);

    $trade = Trade::create([
        'league_id' => $league->id,
        'requesting_team_id' => $teamA->id,
        'target_team_id' => $teamB->id,
        'counterparty' => 'team',
        'status' => 'pending',
    ]);

    $countBefore = Activity::query()->where('subject_type', Trade::class)->where('event', 'updated')->count();

    $trade->update(['requesting_team_id' => $teamA->id]);

    $countAfter = Activity::query()->where('subject_type', Trade::class)->where('event', 'updated')->count();
    expect($countAfter)->toBe($countBefore);
});

// --- Set (match) ---

it('logs an updated activity when a set score is updated', function () {
    ['owner' => $user, 'league' => $league, 'team' => $team1] = makeLeagueWithTeamAndPokemon();
    $matchConfig = MatchConfig::create(['league_id' => $league->id, 'number_of_pools' => 1, 'status' => 1]);
    $pool = Pool::create(['league_id' => $league->id, 'match_config_id' => $matchConfig->id, 'status' => 1]);
    $team2 = Team::create(['name' => 'T2', 'league_id' => $league->id, 'user_id' => User::factory()->create()->id, 'pick_position' => 2, 'draft_points' => 80, 'victory_points' => 0, 'set_wins' => 0, 'set_losses' => 0, 'game_wins' => 0, 'game_losses' => 0, 'pool_id' => $pool->id]);
    $this->actingAs($user);

    $set = Set::create([
        'pool_id' => $pool->id,
        'league_id' => $league->id,
        'team1_id' => $team1->id,
        'team2_id' => $team2->id,
        'round' => 1,
        'status' => 0,
        'is_bye' => false,
    ]);

    $set->update([
        'team1_score' => 2,
        'team2_score' => 0,
        'winner_id' => $team1->id,
        'status' => 1,
    ]);

    $activity = Activity::query()
        ->where('subject_type', Set::class)
        ->where('event', 'updated')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->attribute_changes['attributes']['team1_score'])->toBe(2);
    expect($activity->attribute_changes['attributes']['winner_id'])->toBe($team1->id);
    expect($activity->causer_id)->toBe($user->id);
});

// --- Draft lifecycle (CreateEditDraftAction) ---

it('logs a draft started activity when a draft is created via CreateEditDraftAction', function () {
    ['owner' => $user, 'league' => $league] = makeLeagueWithTeamAndPokemon();
    $this->actingAs($user);

    (new CreateEditDraftAction)(['command' => 'create', 'league_id' => $league->id]);

    $activity = Activity::query()
        ->where('subject_type', Draft::class)
        ->where('description', 'Draft started')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['league_id'])->toBe($league->id);
    expect($activity->causer_id)->toBe($user->id);
});

it('logs a draft finalized activity when finalize_draft is invoked', function () {
    ['owner' => $user, 'league' => $league] = makeLeagueWithTeamAndPokemon();
    $this->actingAs($user);

    $draft = Draft::create(['league_id' => $league->id, 'round_number' => 1, 'status' => 1, 'pick_number' => 1]);

    (new CreateEditDraftAction)(['command' => 'finalize_draft', 'league_id' => $league->id]);

    $activity = Activity::query()
        ->where('subject_type', Draft::class)
        ->where('description', 'Draft finalized')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->subject_id)->toBe($draft->id);
    expect($activity->causer_id)->toBe($user->id);
});

// --- LeaguePokemon ---

it('logs an updated activity when a league pokemon is drafted', function () {
    ['owner' => $user, 'league' => $league, 'team' => $team, 'leaguePokemon' => $leaguePokemon] = makeLeagueWithTeamAndPokemon();
    $this->actingAs($user);

    $leaguePokemon->update(['is_drafted' => true, 'drafted_by' => $team->id]);

    $activity = Activity::query()
        ->where('subject_type', LeaguePokemon::class)
        ->where('event', 'updated')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->attribute_changes['attributes']['is_drafted'])->toBeTrue();
    expect($activity->attribute_changes['attributes']['drafted_by'])->toBe($team->id);
    expect($activity->attribute_changes['old']['is_drafted'])->toBeFalse();
});

it('does not log a LeaguePokemon update when non-tracked attributes change', function () {
    ['owner' => $user, 'league' => $league, 'leaguePokemon' => $leaguePokemon] = makeLeagueWithTeamAndPokemon();
    $this->actingAs($user);

    $countBefore = Activity::query()->where('subject_type', LeaguePokemon::class)->where('event', 'updated')->count();

    $leaguePokemon->update(['name' => 'Raichu']);

    $countAfter = Activity::query()->where('subject_type', LeaguePokemon::class)->where('event', 'updated')->count();
    expect($countAfter)->toBe($countBefore);
});
