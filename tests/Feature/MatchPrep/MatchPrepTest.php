<?php

use App\Models\User;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Enums\LeagueStatus;
use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\Pool;
use App\Modules\Matches\Models\Set;
use App\Modules\MatchPrep\Models\MatchPrepNote;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Teams\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * @return array{
 *     league: League,
 *     coach: User,
 *     team: Team,
 *     opponent: User,
 *     opponentTeam: Team,
 *     set: Set,
 *     coachMonA: LeaguePokemon,
 *     coachMonB: LeaguePokemon,
 *     opponentMon: LeaguePokemon,
 * }
 */
function createMatchPrepScenario(): array
{
    $owner = User::factory()->create();
    $coach = User::factory()->create();
    $opponent = User::factory()->create();

    $league = League::create([
        'name' => 'Prep League',
        'status' => LeagueStatus::RegularSeason->value,
        'draft_points' => 100,
        'league_owner' => $owner->id,
    ]);

    DraftConfig::create([
        'league_id' => $league->id,
        'draft_date' => now()->addDay(),
        'draft_points' => 80,
        'ban_enabled' => false,
        'minimum_drafts' => 2,
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

    $team = Team::create([
        'name' => 'Prep Squad',
        'league_id' => $league->id,
        'user_id' => $coach->id,
        'admin_flag' => 0,
        'pick_position' => 1,
        'seed' => 1,
        'pool_id' => $pool->id,
        'draft_points' => 80,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
        'trades' => 3,
    ]);

    $opponentTeam = Team::create([
        'name' => 'Other',
        'league_id' => $league->id,
        'user_id' => $opponent->id,
        'admin_flag' => 0,
        'pick_position' => 2,
        'seed' => 2,
        'pool_id' => $pool->id,
        'draft_points' => 80,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
        'trades' => 3,
    ]);

    $set = Set::create([
        'league_id' => $league->id,
        'pool_id' => $pool->id,
        'round' => 1,
        'team1_id' => $team->id,
        'team2_id' => $opponentTeam->id,
        'status' => 1,
    ]);

    $pd1 = Pokedex::create([
        'nationaldex_id' => 9001,
        'name' => 'PrepMonA',
        'type1' => 'Fire',
    ]);
    $pd2 = Pokedex::create([
        'nationaldex_id' => 9002,
        'name' => 'PrepMonB',
        'type1' => 'Water',
    ]);
    $pd3 = Pokedex::create([
        'nationaldex_id' => 9003,
        'name' => 'OppMon',
        'type1' => 'Grass',
    ]);

    $coachMonA = LeaguePokemon::create([
        'league_id' => $league->id,
        'pokedex_id' => $pd1->id,
        'name' => 'PrepMonA',
        'cost' => 10,
        'drafted_by' => $team->id,
    ]);
    $coachMonB = LeaguePokemon::create([
        'league_id' => $league->id,
        'pokedex_id' => $pd2->id,
        'name' => 'PrepMonB',
        'cost' => 8,
        'drafted_by' => $team->id,
    ]);
    $opponentMon = LeaguePokemon::create([
        'league_id' => $league->id,
        'pokedex_id' => $pd3->id,
        'name' => 'OppMon',
        'cost' => 9,
        'drafted_by' => $opponentTeam->id,
    ]);

    return compact(
        'league',
        'coach',
        'team',
        'opponent',
        'opponentTeam',
        'set',
        'coachMonA',
        'coachMonB',
        'opponentMon',
    );
}

function emptyBringSix(): array
{
    return [null, null, null, null, null, null];
}

function emptyPlanFour(): array
{
    return [null, null, null, null];
}

function notePayload(array $overrides = []): array
{
    return array_merge([
        'bring_six_slots' => emptyBringSix(),
        'plan_1_slots' => emptyPlanFour(),
        'plan_2_slots' => emptyPlanFour(),
        'plan_3_slots' => emptyPlanFour(),
        'plan_1_notes' => 'Bring core',
        'plan_2_notes' => null,
        'plan_3_notes' => null,
        'calcs' => [],
    ], $overrides);
}

it('redirects guests from match prep index', function () {
    $this->get(route('match-prep.index'))->assertRedirect();
});

it('includes set scores, winner, and replays on match prep index', function () {
    $s = createMatchPrepScenario();
    $s['set']->update([
        'team1_score' => 2,
        'team2_score' => 1,
        'winner_id' => $s['team']->id,
        'replay1' => 'https://replay.pokemonshowdown.com/gen9vgc2025-prep-test',
    ]);

    $this->actingAs($s['coach']);

    $this->get(route('match-prep.index', ['league_id' => $s['league']->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('matches', 1)
            ->where('matches.0.my_team_id', $s['team']->id)
            ->where('matches.0.set.team1_score', 2)
            ->where('matches.0.set.team2_score', 1)
            ->where('matches.0.set.winner_id', $s['team']->id)
            ->where('matches.0.set.replay1', 'https://replay.pokemonshowdown.com/gen9vgc2025-prep-test'));
});

it('lets a coach save prep for their set', function () {
    $s = createMatchPrepScenario();
    $this->actingAs($s['coach']);

    $bring = emptyBringSix();
    $bring[0] = $s['coachMonA']->id;
    $bring[1] = $s['coachMonB']->id;

    $plan1 = emptyPlanFour();
    $plan1[0] = $s['coachMonA']->id;
    $plan1[1] = $s['coachMonB']->id;

    $this->put(route('match-prep.update', $s['set']), notePayload([
        'bring_six_slots' => $bring,
        'plan_1_slots' => $plan1,
        'calcs' => [
            [
                'my_league_pokemon_id' => $s['coachMonA']->id,
                'opponent_league_pokemon_id' => $s['opponentMon']->id,
                'body' => '252+ Atk Choice Band Urshifu Wicked Blow vs. 0 HP / 0 Def Pikachu on a rainy day',
            ],
            [
                'my_league_pokemon_id' => $s['coachMonA']->id,
                'opponent_league_pokemon_id' => $s['opponentMon']->id,
                'body' => 'Alternate spread guess',
            ],
        ],
    ]))->assertRedirect(route('match-prep.index', ['league_id' => $s['league']->id]));

    $this->assertDatabaseHas('match_prep_notes', [
        'user_id' => $s['coach']->id,
        'set_id' => $s['set']->id,
    ]);

    $note = MatchPrepNote::query()->where('user_id', $s['coach']->id)->where('set_id', $s['set']->id)->first();
    expect($note)->not->toBeNull();
    expect($note->bring_six_slots[0])->toBe($s['coachMonA']->id);
    expect($note->plan_1_slots[0])->toBe($s['coachMonA']->id);
    expect($note->calcs)->toHaveCount(2);
});

it('rejects slot ids from another team', function () {
    $s = createMatchPrepScenario();
    $this->actingAs($s['coach']);

    $plan = emptyPlanFour();
    $plan[0] = $s['opponentMon']->id;

    $this->put(route('match-prep.update', $s['set']), notePayload([
        'plan_1_slots' => $plan,
    ]))->assertSessionHasErrors('plan_1_slots.0');
});

it('rejects game plan pokemon not in bring six when bring six is set', function () {
    $s = createMatchPrepScenario();
    $this->actingAs($s['coach']);

    $bring = emptyBringSix();
    $bring[0] = $s['coachMonA']->id;

    $plan = emptyPlanFour();
    $plan[0] = $s['coachMonB']->id;

    $this->put(route('match-prep.update', $s['set']), notePayload([
        'bring_six_slots' => $bring,
        'plan_1_slots' => $plan,
    ]))->assertSessionHasErrors('plan_1_slots.0');
});

it('rejects calcs that use an opponent mon on your team', function () {
    $s = createMatchPrepScenario();
    $this->actingAs($s['coach']);

    $this->put(route('match-prep.update', $s['set']), notePayload([
        'calcs' => [
            [
                'my_league_pokemon_id' => $s['coachMonA']->id,
                'opponent_league_pokemon_id' => $s['coachMonB']->id,
                'body' => 'x',
            ],
        ],
    ]))->assertSessionHasErrors('calcs.0.opponent_league_pokemon_id');
});

it('forbids a user who is not in the set from updating prep', function () {
    $s = createMatchPrepScenario();
    $stranger = User::factory()->create();
    $this->actingAs($stranger);

    $this->put(route('match-prep.update', $s['set']), notePayload())->assertForbidden();
});

it('returns 404 for share when disabled', function () {
    $s = createMatchPrepScenario();
    $this->actingAs($s['coach']);
    $this->put(route('match-prep.update', $s['set']), notePayload());

    $note = MatchPrepNote::query()->where('user_id', $s['coach']->id)->where('set_id', $s['set']->id)->first();
    expect($note)->not->toBeNull();
    $note->share_uuid = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';
    $note->share_enabled = false;
    $note->save();

    $this->get(route('match-prep.share.show', ['share_uuid' => $note->share_uuid]))->assertNotFound();
});

it('shows shared prep when enabled', function () {
    $s = createMatchPrepScenario();
    $this->actingAs($s['coach']);
    $this->put(route('match-prep.update', $s['set']), notePayload());

    $this->post(route('match-prep.share', $s['set']), [
        'share_enabled' => true,
        'regenerate_uuid' => false,
    ])->assertRedirect(route('match-prep.index', ['league_id' => $s['league']->id]));

    $note = MatchPrepNote::query()->where('user_id', $s['coach']->id)->where('set_id', $s['set']->id)->first();
    expect($note->share_enabled)->toBeTrue();
    expect($note->share_uuid)->not->toBeNull();

    $this->get(route('match-prep.share.show', ['share_uuid' => $note->share_uuid]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('match-prep/MatchPrepShareShow')
            ->has('match')
            ->has('league_name'));
});
