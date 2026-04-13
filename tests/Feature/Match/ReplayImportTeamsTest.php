<?php

use App\Models\User;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\Pool;
use App\Modules\Matches\Models\Set;
use App\Modules\Pokedex\Models\AbilityGenerationData;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Models\PokemonGenerationData;
use App\Modules\Pokedex\Models\VersionGroup;
use App\Modules\Pokepaste\Models\SetTeamPokepaste;
use App\Modules\Pokepaste\Models\SetTeamPokepasteSlot;
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Facades\Http;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * @return array{
 *     coach1: User,
 *     coach2: User,
 *     team1: Team,
 *     team2: Team,
 *     set: Set,
 *     team1PokemonIdsOrdered: list<int>,
 *     team2PokemonIdsOrdered: list<int>,
 * }
 */
function createSetWithTwoSixPokemonRosters(): array
{
    $owner = User::factory()->create();
    $coach1 = User::factory()->create();
    $coach2 = User::factory()->create();

    $league = League::create([
        'name' => 'Replay Import League',
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
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

    $team1 = Team::create([
        'name' => 'Team Alpha',
        'league_id' => $league->id,
        'user_id' => $coach1->id,
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

    $team2 = Team::create([
        'name' => 'Team Beta',
        'league_id' => $league->id,
        'user_id' => $coach2->id,
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
        'team1_id' => $team1->id,
        'team2_id' => $team2->id,
        'status' => 1,
        'replay1' => 'https://replay.pokemonshowdown.com/gen9replay-import-test-abc',
    ]);

    $versionGroup = VersionGroup::query()->where('slug', 'scarlet-violet')->first();
    expect($versionGroup)->not->toBeNull();

    $learnset = [
        ['move_id' => 33, 'move_name' => 'tackle', 'method' => 'level-up', 'level' => 1],
        ['move_id' => 45, 'move_name' => 'growl', 'method' => 'level-up', 'level' => 1],
        ['move_id' => 10, 'move_name' => 'scratch', 'method' => 'level-up', 'level' => 1],
        ['move_id' => 52, 'move_name' => 'ember', 'method' => 'level-up', 'level' => 1],
    ];

    $team1PokemonIdsOrdered = [];
    for ($i = 1; $i <= 6; $i++) {
        $pd = Pokedex::create([
            'nationaldex_id' => 500 + $i,
            'name' => "PasteMon{$i}",
            'type1' => 'Normal',
        ]);

        PokemonGenerationData::factory()->create([
            'pokedex_id' => $pd->id,
            'version_group_id' => $versionGroup->id,
            'ability_primary_pokeapi_id' => 51,
            'ability_secondary_pokeapi_id' => null,
            'ability_hidden_pokeapi_id' => null,
            'learnset' => $learnset,
        ]);

        AbilityGenerationData::query()->create([
            'pokedex_id' => $pd->id,
            'version_group_id' => $versionGroup->id,
            'pokeapi_ability_id' => 51,
            'ability_name' => 'keen-eye',
            'slot' => 1,
            'is_hidden' => false,
        ]);

        $lp = LeaguePokemon::create([
            'league_id' => $league->id,
            'pokedex_id' => $pd->id,
            'name' => "PasteMon{$i}",
            'cost' => 10,
            'drafted_by' => $team1->id,
        ]);
        $team1PokemonIdsOrdered[] = $lp->id;
    }

    $team2PokemonIdsOrdered = [];
    for ($i = 1; $i <= 6; $i++) {
        $pd = Pokedex::create([
            'nationaldex_id' => 600 + $i,
            'name' => "OppMon{$i}",
            'type1' => 'Normal',
        ]);

        PokemonGenerationData::factory()->create([
            'pokedex_id' => $pd->id,
            'version_group_id' => $versionGroup->id,
            'ability_primary_pokeapi_id' => 51,
            'ability_secondary_pokeapi_id' => null,
            'ability_hidden_pokeapi_id' => null,
            'learnset' => $learnset,
        ]);

        AbilityGenerationData::query()->create([
            'pokedex_id' => $pd->id,
            'version_group_id' => $versionGroup->id,
            'pokeapi_ability_id' => 51,
            'ability_name' => 'keen-eye',
            'slot' => 1,
            'is_hidden' => false,
        ]);

        $lp = LeaguePokemon::create([
            'league_id' => $league->id,
            'pokedex_id' => $pd->id,
            'name' => "OppMon{$i}",
            'cost' => 10,
            'drafted_by' => $team2->id,
        ]);
        $team2PokemonIdsOrdered[] = $lp->id;
    }

    return [
        'coach1' => $coach1,
        'coach2' => $coach2,
        'team1' => $team1,
        'team2' => $team2,
        'set' => $set,
        'team1PokemonIdsOrdered' => $team1PokemonIdsOrdered,
        'team2PokemonIdsOrdered' => $team2PokemonIdsOrdered,
    ];
}

function replayLogFixturePasteMonVsOppMon(): string
{
    $lines = ['|player|p1|a|', '|player|p2|b|'];
    foreach (range(1, 6) as $i) {
        $lines[] = "|poke|p1|PasteMon{$i}, L50, M|";
    }
    foreach (range(1, 6) as $i) {
        $lines[] = "|poke|p2|OppMon{$i}, L50, M|";
    }

    return implode("\n", $lines);
}

/** p1 uses team2 species (OppMon); p2 uses team1 species (PasteMon) — for testing flipped p1 mapping. */
function replayLogFixtureOppMonVsPasteMon(): string
{
    $lines = ['|player|p1|a|', '|player|p2|b|'];
    foreach (range(1, 6) as $i) {
        $lines[] = "|poke|p1|OppMon{$i}, L50, M|";
    }
    foreach (range(1, 6) as $i) {
        $lines[] = "|poke|p2|PasteMon{$i}, L50, M|";
    }

    return implode("\n", $lines);
}

/** Same species multiset as replayLogFixturePasteMonVsOppMon; p1 line order reversed (PasteMon 6→1). */
function replayLogFixturePasteMonReversedVsOppMon(): string
{
    $lines = ['|player|p1|a|', '|player|p2|b|'];
    foreach (range(6, 1) as $i) {
        $lines[] = "|poke|p1|PasteMon{$i}, L50, M|";
    }
    foreach (range(1, 6) as $i) {
        $lines[] = "|poke|p2|OppMon{$i}, L50, M|";
    }

    return implode("\n", $lines);
}

it('imports both teams match pokepaste species from a saved showdown replay log', function () {
    $data = createSetWithTwoSixPokemonRosters();

    Http::fake([
        'https://replay.pokemonshowdown.com/gen9replay-import-test-abc.log' => Http::response(replayLogFixturePasteMonVsOppMon(), 200),
    ]);

    $response = $this->actingAs($data['coach1'])->post(route('sets.import-replay-teams'), [
        'set_id' => $data['set']->id,
        'replay_slot' => 1,
        'p1_team_id' => $data['team1']->id,
    ]);

    $response->assertRedirect(route('sets.show', ['set_id' => $data['set']->id]))
        ->assertSessionHas('success');

    $paste1 = SetTeamPokepaste::query()
        ->where('matchable_type', Set::class)
        ->where('matchable_id', $data['set']->id)
        ->where('team_id', $data['team1']->id)
        ->first();
    expect($paste1)->not->toBeNull();

    $ids1 = SetTeamPokepasteSlot::query()
        ->where('set_team_pokepaste_id', $paste1->id)
        ->orderBy('slot_index')
        ->pluck('league_pokemon_id')
        ->all();

    expect($ids1)->toBe($data['team1PokemonIdsOrdered']);

    $paste2 = SetTeamPokepaste::query()
        ->where('matchable_type', Set::class)
        ->where('matchable_id', $data['set']->id)
        ->where('team_id', $data['team2']->id)
        ->first();
    expect($paste2)->not->toBeNull();

    $ids2 = SetTeamPokepasteSlot::query()
        ->where('set_team_pokepaste_id', $paste2->id)
        ->orderBy('slot_index')
        ->pluck('league_pokemon_id')
        ->all();

    expect($ids2)->toBe($data['team2PokemonIdsOrdered']);
});

it('preserves saved match pokepaste details when re-importing the same six species in replay order', function () {
    $data = createSetWithTwoSixPokemonRosters();

    $replayLogUrl = 'https://replay.pokemonshowdown.com/gen9replay-import-test-abc.log';
    Http::fake([
        $replayLogUrl => Http::sequence()
            ->push(replayLogFixturePasteMonVsOppMon(), 200)
            ->push(replayLogFixturePasteMonReversedVsOppMon(), 200),
    ]);

    $this->actingAs($data['coach1'])->post(route('sets.import-replay-teams'), [
        'set_id' => $data['set']->id,
        'replay_slot' => 1,
        'p1_team_id' => $data['team1']->id,
    ])->assertRedirect(route('sets.show', ['set_id' => $data['set']->id]));

    $paste1 = SetTeamPokepaste::query()
        ->where('matchable_type', Set::class)
        ->where('matchable_id', $data['set']->id)
        ->where('team_id', $data['team1']->id)
        ->first();
    expect($paste1)->not->toBeNull();

    $slots = [];
    foreach ($data['team1PokemonIdsOrdered'] as $i => $id) {
        $slots[] = [
            'league_pokemon_id' => $id,
            'ability' => $i === 0 ? 'Keen Eye' : '',
            'moves' => ['', '', '', ''],
            'version_group_held_item_id' => null,
            'nature' => null,
            'tera_type' => null,
            'evs' => null,
        ];
    }

    $this->actingAs($data['coach1'])
        ->put(route('pokepaste.update', ['pokepaste' => $paste1->public_id]), ['slots' => $slots])
        ->assertRedirect(route('pokepaste.show', ['pokepaste' => $paste1->public_id]));

    $this->actingAs($data['coach1'])->post(route('sets.import-replay-teams'), [
        'set_id' => $data['set']->id,
        'replay_slot' => 1,
        'p1_team_id' => $data['team1']->id,
    ])->assertRedirect(route('sets.show', ['set_id' => $data['set']->id]));

    $firstMonId = $data['team1PokemonIdsOrdered'][0];
    $slotRow = SetTeamPokepasteSlot::query()
        ->where('set_team_pokepaste_id', $paste1->id)
        ->where('league_pokemon_id', $firstMonId)
        ->first();

    expect($slotRow)->not->toBeNull()
        ->and($slotRow->ability)->toBe('Keen Eye')
        ->and($slotRow->slot_index)->toBe(5);
});

it('maps p2 roster when p1 team is team two', function () {
    $data = createSetWithTwoSixPokemonRosters();

    Http::fake([
        'https://replay.pokemonshowdown.com/gen9replay-import-test-abc.log' => Http::response(replayLogFixtureOppMonVsPasteMon(), 200),
    ]);

    $this->actingAs($data['coach1'])->post(route('sets.import-replay-teams'), [
        'set_id' => $data['set']->id,
        'replay_slot' => 1,
        'p1_team_id' => $data['team2']->id,
    ])->assertRedirect(route('sets.show', ['set_id' => $data['set']->id]));

    $pasteA = SetTeamPokepaste::query()
        ->where('matchable_type', Set::class)
        ->where('matchable_id', $data['set']->id)
        ->where('team_id', $data['team1']->id)
        ->first();
    expect($pasteA)->not->toBeNull();

    $idsTeam1 = SetTeamPokepasteSlot::query()
        ->where('set_team_pokepaste_id', $pasteA->id)
        ->orderBy('slot_index')
        ->pluck('league_pokemon_id')
        ->all();

    expect($idsTeam1)->toBe($data['team1PokemonIdsOrdered']);

    $pasteB = SetTeamPokepaste::query()
        ->where('matchable_type', Set::class)
        ->where('matchable_id', $data['set']->id)
        ->where('team_id', $data['team2']->id)
        ->first();
    $idsTeam2 = SetTeamPokepasteSlot::query()
        ->where('set_team_pokepaste_id', $pasteB->id)
        ->orderBy('slot_index')
        ->pluck('league_pokemon_id')
        ->all();

    expect($idsTeam2)->toBe($data['team2PokemonIdsOrdered']);
});

it('rejects import when replay slot has no saved url', function () {
    $data = createSetWithTwoSixPokemonRosters();
    $data['set']->update(['replay1' => null]);

    $this->actingAs($data['coach1'])->post(route('sets.import-replay-teams'), [
        'set_id' => $data['set']->id,
        'replay_slot' => 1,
        'p1_team_id' => $data['team1']->id,
    ])->assertSessionHasErrors('replay_slot');
});

it('rejects non show replay host when importing', function () {
    $data = createSetWithTwoSixPokemonRosters();
    $data['set']->update(['replay1' => 'https://example.com/fake-replay']);

    $this->actingAs($data['coach1'])->post(route('sets.import-replay-teams'), [
        'set_id' => $data['set']->id,
        'replay_slot' => 1,
        'p1_team_id' => $data['team1']->id,
    ])
        ->assertRedirect(route('sets.show', ['set_id' => $data['set']->id]))
        ->assertSessionHasErrors('replay_import');
});

it('shows friendly error when replay log download fails', function () {
    $data = createSetWithTwoSixPokemonRosters();

    Http::fake([
        'https://replay.pokemonshowdown.com/gen9replay-import-test-abc.log' => Http::response('', 503),
    ]);

    $this->actingAs($data['coach1'])->post(route('sets.import-replay-teams'), [
        'set_id' => $data['set']->id,
        'replay_slot' => 1,
        'p1_team_id' => $data['team1']->id,
    ])
        ->assertRedirect(route('sets.show', ['set_id' => $data['set']->id]))
        ->assertSessionHasErrors('replay_import');
});

it('requires authentication to import replay teams', function () {
    $data = createSetWithTwoSixPokemonRosters();

    $this->post(route('sets.import-replay-teams'), [
        'set_id' => $data['set']->id,
        'replay_slot' => 1,
        'p1_team_id' => $data['team1']->id,
    ])->assertRedirect('/login');
});

it('forbids import when user is not a participant in the set', function () {
    $data = createSetWithTwoSixPokemonRosters();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->post(route('sets.import-replay-teams'), [
        'set_id' => $data['set']->id,
        'replay_slot' => 1,
        'p1_team_id' => $data['team1']->id,
    ])->assertForbidden();
});

it('rejects p1 team id that is not part of the set', function () {
    $data = createSetWithTwoSixPokemonRosters();
    $otherLeague = League::create([
        'name' => 'Other',
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
        'draft_points' => 100,
        'league_owner' => User::factory()->create()->id,
    ]);
    $otherTeam = Team::create([
        'name' => 'Other T',
        'league_id' => $otherLeague->id,
        'user_id' => $data['coach1']->id,
        'admin_flag' => 0,
        'pick_position' => 1,
        'seed' => 1,
        'draft_points' => 80,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
        'trades' => 3,
    ]);

    Http::fake([
        'https://replay.pokemonshowdown.com/gen9replay-import-test-abc.log' => Http::response(replayLogFixturePasteMonVsOppMon(), 200),
    ]);

    $this->actingAs($data['coach1'])->post(route('sets.import-replay-teams'), [
        'set_id' => $data['set']->id,
        'replay_slot' => 1,
        'p1_team_id' => $otherTeam->id,
    ])->assertSessionHasErrors('p1_team_id');
});
