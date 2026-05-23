<?php

use App\Enums\PokemonGame;
use App\Enums\PokemonNature;
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
use App\Modules\Pokedex\Models\VersionGroupHeldItem;
use App\Modules\Pokepaste\Models\SetTeamPokepaste;
use App\Modules\Pokepaste\Models\SetTeamPokepasteSlot;
use App\Modules\Pokepaste\Services\EnsureSetTeamPokepasteSlotRows;
use App\Modules\Pokepaste\Services\ShowdownPasteParser;
use App\Modules\Pokepaste\Services\ShowdownTeamExporter;
use App\Modules\Pokepaste\Support\PokepasteSlotDefaults;
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Collection;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * @return array{
 *     league: League,
 *     coach: User,
 *     team: Team,
 *     opponent: User,
 *     opponentTeam: Team,
 *     set: Set,
 *     leaguePokemon: array<int, LeaguePokemon>,
 *     versionGroup: VersionGroup,
 *     heldItems: array<int, VersionGroupHeldItem>
 * }
 */
function createLeagueTeamWithSixDraftedPokemonAndMatch(): array
{
    $owner = User::factory()->create();
    $coach = User::factory()->create();
    $opponent = User::factory()->create();

    $league = League::create([
        'name' => 'Pokepaste League',
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

    $team = Team::create([
        'name' => 'Paste Squad',
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
        'name' => 'Opponent',
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

    $versionGroup = VersionGroup::query()->where('slug', 'scarlet-violet')->first();
    expect($versionGroup)->not->toBeNull();

    $heldItems = [];
    foreach (range(1, 6) as $i) {
        $heldItems[] = VersionGroupHeldItem::create([
            'version_group_id' => $versionGroup->id,
            'pokeapi_item_id' => 2000 + $i,
            'name' => 'test-item-'.$i,
            'display_name_en' => 'Leftovers '.$i,
        ]);
    }

    $learnset = [
        ['move_id' => 33, 'move_name' => 'tackle', 'method' => 'level-up', 'level' => 1],
        ['move_id' => 45, 'move_name' => 'growl', 'method' => 'level-up', 'level' => 1],
        ['move_id' => 10, 'move_name' => 'scratch', 'method' => 'level-up', 'level' => 1],
        ['move_id' => 52, 'move_name' => 'ember', 'method' => 'level-up', 'level' => 1],
    ];

    $leaguePokemon = [];
    for ($i = 1; $i <= 6; $i++) {
        $pd = Pokedex::create([
            'nationaldex_id' => $i,
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

        $leaguePokemon[] = LeaguePokemon::create([
            'league_id' => $league->id,
            'pokedex_id' => $pd->id,
            'name' => "PasteMon{$i}",
            'cost' => 10,
            'drafted_by' => $team->id,
        ]);
    }

    return [
        'league' => $league,
        'coach' => $coach,
        'team' => $team,
        'opponent' => $opponent,
        'opponentTeam' => $opponentTeam,
        'set' => $set,
        'leaguePokemon' => $leaguePokemon,
        'versionGroup' => $versionGroup,
        'heldItems' => $heldItems,
    ];
}

/**
 * @param  array<int, LeaguePokemon>  $leaguePokemon
 * @param  array<int, VersionGroupHeldItem>  $heldItems
 * @return array<int, array<string, mixed>>
 */
function buildValidSlots(array $leaguePokemon, array $heldItems): array
{
    $slots = [];
    foreach ($leaguePokemon as $idx => $lp) {
        $slots[] = [
            'league_pokemon_id' => $lp->id,
            'ability' => 'Keen Eye',
            'moves' => ['tackle', 'growl', 'scratch', 'ember'],
            'version_group_held_item_id' => $heldItems[$idx]->id,
            'nature' => PokemonNature::Timid->value,
            'tera_type' => 'Fire',
            'evs' => null,
        ];
    }

    return $slots;
}

function coachPokepasteRecord(array $data): SetTeamPokepaste
{
    $paste = SetTeamPokepaste::query()->firstOrCreate(
        [
            'matchable_type' => Set::class,
            'matchable_id' => $data['set']->id,
            'team_id' => $data['team']->id,
        ],
    );
    app(EnsureSetTeamPokepasteSlotRows::class)($paste);

    return $paste;
}

it('rejects guests from pokepaste parse', function () {
    $data = createLeagueTeamWithSixDraftedPokemonAndMatch();
    $pokepaste = coachPokepasteRecord($data);

    $this->postJson(route('pokepaste.parse', ['pokepaste' => $pokepaste->public_id]), ['paste' => 'x'])
        ->assertUnauthorized();
});

it('returns forbidden when user is not a participant in the match', function () {
    $data = createLeagueTeamWithSixDraftedPokemonAndMatch();
    $stranger = User::factory()->create();
    Team::create([
        'name' => 'Other',
        'league_id' => $data['league']->id,
        'user_id' => $stranger->id,
        'admin_flag' => 0,
        'pick_position' => 3,
        'seed' => 3,
        'pool_id' => $data['team']->pool_id,
        'draft_points' => 80,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
        'trades' => 3,
    ]);

    $pokepaste = coachPokepasteRecord($data);

    $this->actingAs($stranger)
        ->put(route('pokepaste.update', ['pokepaste' => $pokepaste->public_id]), ['slots' => buildValidSlots($data['leaguePokemon'], $data['heldItems'])])
        ->assertForbidden();
});

it('includes showdown export text on public paste view after save', function () {
    $data = createLeagueTeamWithSixDraftedPokemonAndMatch();
    $slots = buildValidSlots($data['leaguePokemon'], $data['heldItems']);
    $pokepaste = coachPokepasteRecord($data);

    $this->actingAs($data['coach'])
        ->put(route('pokepaste.update', ['pokepaste' => $pokepaste->public_id]), ['slots' => $slots])
        ->assertRedirect();

    $this->post('/logout');

    $this->get(route('pokepaste.show', ['pokepaste' => $pokepaste->public_id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pokepaste/PokepasteShow')
            ->where('is_owner', false)
            ->where('edit_mode', false)
            ->where('showdown_export', fn ($text) => is_string($text)
                && str_contains($text, 'Pastemon1')
                && str_contains($text, 'Ability: Keen Eye')
                && str_contains($text, '- Tackle'))
        );
});

it('allows any signed-in user to view another teams paste in read-only layout', function () {
    $data = createLeagueTeamWithSixDraftedPokemonAndMatch();
    $stranger = User::factory()->create();
    $pokepaste = coachPokepasteRecord($data);

    $this->actingAs($stranger)
        ->get(route('pokepaste.show', ['pokepaste' => $pokepaste->public_id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pokepaste/PokepasteShow')
            ->where('is_owner', false)
            ->where('edit_mode', false)
            ->has('roster', 0)
            ->has('view_cards', 6)
        );
});

it('allows guests to view a public paste', function () {
    $data = createLeagueTeamWithSixDraftedPokemonAndMatch();
    $pokepaste = coachPokepasteRecord($data);

    $this->get(route('pokepaste.show', ['pokepaste' => $pokepaste->public_id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pokepaste/PokepasteShow')
            ->where('is_owner', false)
            ->where('edit_mode', false)
        );
});

it('includes coach showdown usernames on match detail', function () {
    $data = createLeagueTeamWithSixDraftedPokemonAndMatch();
    $data['coach']->update(['showdown_username' => 'CoachShowdown1']);
    $data['opponent']->update(['showdown_username' => 'CoachShowdown2']);

    $this->actingAs($data['coach'])
        ->get(route('sets.show', ['set_id' => $data['set']->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('match/MatchDetail')
            ->where('set.team1.user.showdown_username', 'CoachShowdown1')
            ->where('set.team2.user.showdown_username', 'CoachShowdown2')
        );
});

it('includes match pokepaste public id only for participants on match detail', function () {
    $data = createLeagueTeamWithSixDraftedPokemonAndMatch();

    $this->actingAs($data['coach'])
        ->get(route('sets.show', ['set_id' => $data['set']->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('match/MatchDetail')
            ->has('matchPokepaste')
            ->has('matchPokepasteSides')
            ->where('matchPokepaste.pokepaste_public_id', fn ($id) => is_string($id) && ctype_digit($id))
            ->where('matchPokepasteSides.team1', fn ($s) => $s instanceof Collection
                && $s->has('public_id')
                && $s->get('has_data') === false)
        );

    $this->actingAs($data['opponent'])
        ->get(route('sets.show', ['set_id' => $data['set']->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('match/MatchDetail')
            ->where('matchPokepaste', fn ($m) => $m !== null)
            ->where('matchPokepasteSides.team2', fn ($s) => $s instanceof Collection
                && $s->has('public_id')
                && $s->get('has_data') === false)
        );
});

it('reports match pokepaste side has_data after paste is saved on a completed set', function () {
    $data = createLeagueTeamWithSixDraftedPokemonAndMatch();
    $data['set']->update([
        'status' => 0,
        'team1_score' => 2,
        'team2_score' => 0,
    ]);
    $pokepaste = coachPokepasteRecord($data);
    $slots = buildValidSlots($data['leaguePokemon'], $data['heldItems']);

    $this->actingAs($data['coach'])
        ->put(route('pokepaste.update', ['pokepaste' => $pokepaste->public_id]), ['slots' => $slots])
        ->assertRedirect();

    $this->actingAs($data['opponent'])
        ->get(route('sets.show', ['set_id' => $data['set']->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('match/MatchDetail')
            ->where('matchPokepasteSides.team1', fn ($s) => $s instanceof Collection
                && $s->get('has_data') === true)
        );
});

it('defaults owner to edit mode when the paste has no pokemon yet', function () {
    $data = createLeagueTeamWithSixDraftedPokemonAndMatch();
    $pokepaste = coachPokepasteRecord($data);

    $this->actingAs($data['coach'])
        ->get(route('pokepaste.show', ['pokepaste' => $pokepaste->public_id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pokepaste/PokepasteShow')
            ->where('is_owner', true)
            ->where('edit_mode', true)
            ->where('paste_has_data', false)
            ->has('roster', 6)
            ->has('slots', 6)
            ->has('held_items')
            ->has('all_tera_types')
            ->has('natures')
        );
});

it('defaults owner to view mode when the paste already has pokemon', function () {
    $data = createLeagueTeamWithSixDraftedPokemonAndMatch();
    $slots = buildValidSlots($data['leaguePokemon'], $data['heldItems']);
    $pokepaste = coachPokepasteRecord($data);

    $this->actingAs($data['coach'])
        ->put(route('pokepaste.update', ['pokepaste' => $pokepaste->public_id]), ['slots' => $slots])
        ->assertRedirect();

    $this->actingAs($data['coach'])
        ->get(route('pokepaste.show', ['pokepaste' => $pokepaste->public_id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pokepaste/PokepasteShow')
            ->where('is_owner', true)
            ->where('edit_mode', false)
            ->where('paste_has_data', true)
        );
});

it('stays in edit mode with showdown export after save', function () {
    $data = createLeagueTeamWithSixDraftedPokemonAndMatch();
    $slots = buildValidSlots($data['leaguePokemon'], $data['heldItems']);
    $pokepaste = coachPokepasteRecord($data);

    $this->actingAs($data['coach'])
        ->put(route('pokepaste.update', ['pokepaste' => $pokepaste->public_id]), ['slots' => $slots])
        ->assertRedirect(route('pokepaste.show', ['pokepaste' => $pokepaste->public_id, 'edit' => 1]));

    $this->actingAs($data['coach'])
        ->get(route('pokepaste.show', ['pokepaste' => $pokepaste->public_id, 'edit' => 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pokepaste/PokepasteShow')
            ->where('edit_mode', true)
            ->where('showdown_export', fn ($text) => is_string($text)
                && str_contains($text, 'Pastemon1')
                && str_contains($text, 'Ability: Keen Eye')
                && str_contains($text, '- Tackle'))
        );
});

it('lets the owner open edit mode with a query flag', function () {
    $data = createLeagueTeamWithSixDraftedPokemonAndMatch();
    $slots = buildValidSlots($data['leaguePokemon'], $data['heldItems']);
    $pokepaste = coachPokepasteRecord($data);

    $this->actingAs($data['coach'])
        ->put(route('pokepaste.update', ['pokepaste' => $pokepaste->public_id]), ['slots' => $slots])
        ->assertRedirect();

    $this->actingAs($data['coach'])
        ->get(route('pokepaste.show', ['pokepaste' => $pokepaste->public_id, 'edit' => 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pokepaste/PokepasteShow')
            ->where('edit_mode', true)
            ->where('paste_has_data', true)
        );
});

it('accepts showdown ability labels that differ only in title casing from imported game data', function () {
    $data = createLeagueTeamWithSixDraftedPokemonAndMatch();
    $firstLp = $data['leaguePokemon'][0];
    AbilityGenerationData::query()
        ->where('pokedex_id', $firstLp->pokedex_id)
        ->where('version_group_id', $data['versionGroup']->id)
        ->delete();

    AbilityGenerationData::query()->create([
        'pokedex_id' => $firstLp->pokedex_id,
        'version_group_id' => $data['versionGroup']->id,
        'pokeapi_ability_id' => 297,
        'ability_name' => 'sword-of-ruin',
        'slot' => 1,
        'is_hidden' => false,
    ]);

    PokemonGenerationData::query()
        ->where('pokedex_id', $firstLp->pokedex_id)
        ->where('version_group_id', $data['versionGroup']->id)
        ->update([
            'ability_primary_pokeapi_id' => 297,
            'ability_secondary_pokeapi_id' => null,
            'ability_hidden_pokeapi_id' => null,
        ]);

    $slots = buildValidSlots($data['leaguePokemon'], $data['heldItems']);
    $slots[0]['ability'] = 'Sword of Ruin';

    $pokepaste = coachPokepasteRecord($data);

    $this->actingAs($data['coach'])
        ->put(route('pokepaste.update', ['pokepaste' => $pokepaste->public_id]), ['slots' => $slots])
        ->assertRedirect(route('pokepaste.show', ['pokepaste' => $pokepaste->public_id, 'edit' => 1]));

    $row = SetTeamPokepasteSlot::query()
        ->where('set_team_pokepaste_id', $pokepaste->id)
        ->where('slot_index', 0)
        ->first();
    expect($row)->not->toBeNull();
    expect($row->ability)->toBe('Sword Of Ruin');
});

it('saves a valid six-mon paste for the match and team', function () {
    $data = createLeagueTeamWithSixDraftedPokemonAndMatch();
    $slots = buildValidSlots($data['leaguePokemon'], $data['heldItems']);
    $pokepaste = coachPokepasteRecord($data);

    $this->actingAs($data['coach'])
        ->put(route('pokepaste.update', ['pokepaste' => $pokepaste->public_id]), ['slots' => $slots])
        ->assertRedirect(route('pokepaste.show', ['pokepaste' => $pokepaste->public_id, 'edit' => 1]));

    $saved = SetTeamPokepaste::query()
        ->where('matchable_type', Set::class)
        ->where('matchable_id', $data['set']->id)
        ->where('team_id', $data['team']->id)
        ->first();
    expect($saved)->not->toBeNull();
    $saved->load('pasteSlots');
    expect($saved->pasteSlots)->toHaveCount(6);
    expect($saved->pasteSlots->first()->league_pokemon_id)->not->toBeNull();
});

it('saves a partial paste with empty slots', function () {
    $data = createLeagueTeamWithSixDraftedPokemonAndMatch();
    $pokepaste = coachPokepasteRecord($data);
    $slots = PokepasteSlotDefaults::sixEmptySlots();

    $this->actingAs($data['coach'])
        ->put(route('pokepaste.update', ['pokepaste' => $pokepaste->public_id]), ['slots' => $slots])
        ->assertRedirect(route('pokepaste.show', ['pokepaste' => $pokepaste->public_id, 'edit' => 1]));

    $rows = SetTeamPokepasteSlot::query()
        ->where('set_team_pokepaste_id', $pokepaste->id)
        ->orderBy('slot_index')
        ->get();
    expect($rows)->toHaveCount(6);
    expect($rows->every(fn ($r) => $r->league_pokemon_id === null))->toBeTrue();
});

it('saves a partial paste with unfinished moves on one pokemon', function () {
    $data = createLeagueTeamWithSixDraftedPokemonAndMatch();
    $pokepaste = coachPokepasteRecord($data);
    $lp = $data['leaguePokemon'][0];
    $slots = PokepasteSlotDefaults::sixEmptySlots();
    $slots[0] = [
        'league_pokemon_id' => $lp->id,
        'ability' => 'Keen Eye',
        'moves' => ['tackle', '', '', ''],
        'version_group_held_item_id' => null,
        'nature' => null,
        'tera_type' => null,
        'evs' => null,
    ];

    $this->actingAs($data['coach'])
        ->put(route('pokepaste.update', ['pokepaste' => $pokepaste->public_id]), ['slots' => $slots])
        ->assertRedirect();

    $row = SetTeamPokepasteSlot::query()
        ->where('set_team_pokepaste_id', $pokepaste->id)
        ->where('slot_index', 0)
        ->first();
    expect($row)->not->toBeNull();
    expect($row->league_pokemon_id)->toBe($lp->id);
    expect($row->moves)->toBe(['tackle', '', '', '']);
});

it('persists effort values on slot rows', function () {
    $data = createLeagueTeamWithSixDraftedPokemonAndMatch();
    $slots = buildValidSlots($data['leaguePokemon'], $data['heldItems']);
    $slots[0]['evs'] = ['hp' => 252, 'spe' => 252, 'def' => 4];
    $pokepaste = coachPokepasteRecord($data);

    $this->actingAs($data['coach'])
        ->put(route('pokepaste.update', ['pokepaste' => $pokepaste->public_id]), ['slots' => $slots])
        ->assertRedirect();

    $slot0 = SetTeamPokepasteSlot::query()
        ->where('set_team_pokepaste_id', $pokepaste->id)
        ->where('slot_index', 0)
        ->first();
    expect($slot0)->not->toBeNull();
    expect($slot0->ev_hp)->toBe(252);
    expect($slot0->ev_spe)->toBe(252);
    expect($slot0->ev_def)->toBe(4);
    expect($slot0->ev_atk)->toBe(0);
});

it('rejects duplicate roster pokemon in slots', function () {
    $data = createLeagueTeamWithSixDraftedPokemonAndMatch();
    $lp = $data['leaguePokemon'][0];
    $pokepaste = coachPokepasteRecord($data);
    $slots = [];
    for ($i = 0; $i < 6; $i++) {
        $slots[] = [
            'league_pokemon_id' => $lp->id,
            'ability' => 'Keen Eye',
            'moves' => ['tackle', 'growl', 'scratch', 'ember'],
            'version_group_held_item_id' => $data['heldItems'][$i]->id,
            'nature' => null,
            'tera_type' => null,
            'evs' => null,
        ];
    }

    $this->actingAs($data['coach'])
        ->put(route('pokepaste.update', ['pokepaste' => $pokepaste->public_id]), ['slots' => $slots])
        ->assertSessionHasErrors();
});

it('rejects duplicate moves within one pokemon slot', function () {
    $data = createLeagueTeamWithSixDraftedPokemonAndMatch();
    $pokepaste = coachPokepasteRecord($data);
    $slots = buildValidSlots($data['leaguePokemon'], $data['heldItems']);
    $slots[0]['moves'] = ['tackle', 'tackle', 'growl', 'scratch'];

    $this->actingAs($data['coach'])
        ->put(route('pokepaste.update', ['pokepaste' => $pokepaste->public_id]), ['slots' => $slots])
        ->assertSessionHasErrors();
});

it('rejects duplicate held items in slots', function () {
    $data = createLeagueTeamWithSixDraftedPokemonAndMatch();
    $item = $data['heldItems'][0];
    $pokepaste = coachPokepasteRecord($data);
    $slots = [];
    foreach ($data['leaguePokemon'] as $lp) {
        $slots[] = [
            'league_pokemon_id' => $lp->id,
            'ability' => 'Keen Eye',
            'moves' => ['tackle', 'growl', 'scratch', 'ember'],
            'version_group_held_item_id' => $item->id,
            'nature' => null,
            'tera_type' => null,
            'evs' => null,
        ];
    }

    $this->actingAs($data['coach'])
        ->put(route('pokepaste.update', ['pokepaste' => $pokepaste->public_id]), ['slots' => $slots])
        ->assertSessionHasErrors();
});

it('parses showdown paste and returns normalized slots', function () {
    $data = createLeagueTeamWithSixDraftedPokemonAndMatch();
    $slots = buildValidSlots($data['leaguePokemon'], $data['heldItems']);
    $exporter = new ShowdownTeamExporter;
    $paste = $exporter->export($slots, $data['versionGroup']);
    $pokepaste = coachPokepasteRecord($data);

    $response = $this->actingAs($data['coach'])
        ->postJson(route('pokepaste.parse', ['pokepaste' => $pokepaste->public_id]), ['paste' => $paste]);

    $response->assertOk();
    expect($response->json('ok'))->toBeTrue();
    expect($response->json('slots'))->toHaveCount(6);
});

it('rejects parse when a species is not drafted by this team in the league', function () {
    $data = createLeagueTeamWithSixDraftedPokemonAndMatch();
    $slots = buildValidSlots($data['leaguePokemon'], $data['heldItems']);
    $paste = (new ShowdownTeamExporter)->export($slots, $data['versionGroup']);
    $pokepaste = coachPokepasteRecord($data);

    $data['leaguePokemon'][0]->update(['drafted_by' => $data['opponentTeam']->id]);

    $response = $this->actingAs($data['coach'])
        ->postJson(route('pokepaste.parse', ['pokepaste' => $pokepaste->public_id]), ['paste' => $paste]);

    $response->assertStatus(422);
    expect($response->json('ok'))->toBeFalse();
    $errors = $response->json('errors');
    expect($errors)->toBeArray();
    expect(implode(' ', $errors))->toContain('Species not on your roster');
});

it('rejects parse when roster pokemon belongs to another league', function () {
    $data = createLeagueTeamWithSixDraftedPokemonAndMatch();
    $slots = buildValidSlots($data['leaguePokemon'], $data['heldItems']);
    $paste = (new ShowdownTeamExporter)->export($slots, $data['versionGroup']);
    $pokepaste = coachPokepasteRecord($data);

    $otherOwner = User::factory()->create();
    $otherLeague = League::create([
        'name' => 'Other League',
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
        'draft_points' => 100,
        'league_owner' => $otherOwner->id,
    ]);

    $data['leaguePokemon'][0]->update(['league_id' => $otherLeague->id]);

    $response = $this->actingAs($data['coach'])
        ->postJson(route('pokepaste.parse', ['pokepaste' => $pokepaste->public_id]), ['paste' => $paste]);

    $response->assertStatus(422);
    expect($response->json('ok'))->toBeFalse();
    $errors = $response->json('errors');
    expect($errors)->toBeArray();
    expect(implode(' ', $errors))->toContain('Species not on your roster');
});

it('rejects parse with wrong block count', function () {
    $data = createLeagueTeamWithSixDraftedPokemonAndMatch();
    $pokepaste = coachPokepasteRecord($data);

    $response = $this->actingAs($data['coach'])
        ->postJson(route('pokepaste.parse', ['pokepaste' => $pokepaste->public_id]), [
            'paste' => "PasteMon1\nAbility: Keen Eye\n- Tackle\n- Growl\n- Scratch\n- Ember",
        ]);

    $response->assertStatus(422);
    expect($response->json('ok'))->toBeFalse();
});

it('exports showdown text containing species names', function () {
    $data = createLeagueTeamWithSixDraftedPokemonAndMatch();
    $slots = buildValidSlots($data['leaguePokemon'], $data['heldItems']);
    $text = (new ShowdownTeamExporter)->export($slots, $data['versionGroup']);

    expect($text)->toContain('Pastemon1');
    expect($text)->toContain('Ability: Keen Eye');
    expect($text)->toContain('- Tackle');
    expect($text)->toContain('Leftovers 1');
    expect($text)->toContain('Timid Nature');
});

it('parser reports error for invalid paste structure', function () {
    $parser = new ShowdownPasteParser;
    $result = $parser->parse("only one block\nAbility: X\n- A\n- B\n- C\n- D");

    expect($result['errors'])->not->toBeEmpty();
});

// ─── Champions (Mega mechanic) ───────────────────────────────────────────────

/**
 * @return array{
 *     league: League,
 *     coach: User,
 *     team: Team,
 *     set: Set,
 *     leaguePokemon: array<int, LeaguePokemon>,
 *     versionGroup: VersionGroup,
 * }
 */
function createChampionsLeagueTeamWithMatch(): array
{
    $owner = User::factory()->create();
    $coach = User::factory()->create();
    $opponent = User::factory()->create();

    $league = League::create([
        'name' => 'Champions League',
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
        'draft_points' => 100,
        'league_owner' => $owner->id,
        'pokemon_generation' => 9,
        'pokemon_game' => PokemonGame::Champions->value,
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
        'name' => 'Mega Squad',
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
        'name' => 'Opponent',
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

    $versionGroup = VersionGroup::query()->where('slug', 'champions-reg-ma')->firstOrFail();

    $learnset = [
        ['move_id' => 33, 'move_name' => 'tackle', 'method' => 'level-up', 'level' => 1],
        ['move_id' => 45, 'move_name' => 'growl', 'method' => 'level-up', 'level' => 1],
        ['move_id' => 10, 'move_name' => 'scratch', 'method' => 'level-up', 'level' => 1],
        ['move_id' => 52, 'move_name' => 'ember', 'method' => 'level-up', 'level' => 1],
    ];

    $leaguePokemon = [];
    for ($i = 1; $i <= 6; $i++) {
        $pd = Pokedex::create([
            'nationaldex_id' => 800 + $i,
            'name' => "ChampsMon{$i}",
            'type1' => 'Normal',
        ]);

        PokemonGenerationData::factory()->create([
            'pokedex_id' => $pd->id,
            'version_group_id' => $versionGroup->id,
            'learnset' => $learnset,
            'mechanics' => [
                'tera_capable' => false,
                'mega' => true,
                'z_move' => false,
                'dynamax' => false,
                'gmax' => false,
            ],
        ]);

        AbilityGenerationData::query()->create([
            'pokedex_id' => $pd->id,
            'version_group_id' => $versionGroup->id,
            'pokeapi_ability_id' => 51,
            'ability_name' => 'keen-eye',
            'slot' => 1,
            'is_hidden' => false,
        ]);

        $leaguePokemon[] = LeaguePokemon::create([
            'league_id' => $league->id,
            'pokedex_id' => $pd->id,
            'name' => "ChampsMon{$i}",
            'cost' => 10,
            'drafted_by' => $team->id,
        ]);
    }

    return [
        'league' => $league,
        'coach' => $coach,
        'team' => $team,
        'set' => $set,
        'leaguePokemon' => $leaguePokemon,
        'versionGroup' => $versionGroup,
    ];
}

it('returns empty all_tera_types for a champions league pokepaste', function () {
    $data = createChampionsLeagueTeamWithMatch();
    $paste = SetTeamPokepaste::query()->firstOrCreate([
        'matchable_type' => Set::class,
        'matchable_id' => $data['set']->id,
        'team_id' => $data['team']->id,
    ]);
    app(EnsureSetTeamPokepasteSlotRows::class)($paste);

    $this->actingAs($data['coach'])
        ->get(route('pokepaste.show', ['pokepaste' => $paste->public_id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pokepaste/PokepasteShow')
            ->where('all_tera_types', [])
        );
});

it('rejects tera_type submissions for a champions league slot', function () {
    $data = createChampionsLeagueTeamWithMatch();
    $paste = SetTeamPokepaste::query()->firstOrCreate([
        'matchable_type' => Set::class,
        'matchable_id' => $data['set']->id,
        'team_id' => $data['team']->id,
    ]);
    app(EnsureSetTeamPokepasteSlotRows::class)($paste);

    $slots = [];
    foreach ($data['leaguePokemon'] as $idx => $lp) {
        $slots[] = [
            'league_pokemon_id' => $lp->id,
            'ability' => 'Keen Eye',
            'moves' => ['tackle', 'growl', 'scratch', 'ember'],
            'version_group_held_item_id' => null,
            'nature' => PokemonNature::Timid->value,
            'tera_type' => 'Fire',
            'evs' => null,
        ];
    }

    $this->actingAs($data['coach'])
        ->put(route('pokepaste.update', ['pokepaste' => $paste->public_id]), ['slots' => $slots])
        ->assertSessionHasErrors();
});

it('accepts champions league slots without a tera_type', function () {
    $data = createChampionsLeagueTeamWithMatch();
    $paste = SetTeamPokepaste::query()->firstOrCreate([
        'matchable_type' => Set::class,
        'matchable_id' => $data['set']->id,
        'team_id' => $data['team']->id,
    ]);
    app(EnsureSetTeamPokepasteSlotRows::class)($paste);

    $slots = [];
    foreach ($data['leaguePokemon'] as $lp) {
        $slots[] = [
            'league_pokemon_id' => $lp->id,
            'ability' => 'Keen Eye',
            'moves' => ['tackle', 'growl', 'scratch', 'ember'],
            'version_group_held_item_id' => null,
            'nature' => PokemonNature::Timid->value,
            'tera_type' => null,
            'evs' => null,
        ];
    }

    $this->actingAs($data['coach'])
        ->put(route('pokepaste.update', ['pokepaste' => $paste->public_id]), ['slots' => $slots])
        ->assertRedirect(route('pokepaste.show', ['pokepaste' => $paste->public_id, 'edit' => 1]));

    $saved = SetTeamPokepasteSlot::query()
        ->where('set_team_pokepaste_id', $paste->id)
        ->where('slot_index', 0)
        ->first();
    expect($saved)->not->toBeNull();
    expect($saved->tera_type)->toBeNull();
});
