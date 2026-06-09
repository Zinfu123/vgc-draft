<?php

use App\Modules\Pokedex\Models\AbilityGenerationData;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Models\PokemonGenerationData;
use App\Modules\Pokedex\Models\VersionGroup;
use App\Modules\Pokedex\Services\PokedexFilterService;
use Illuminate\Support\Facades\DB;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('filters by generation using pokemon_generation_data and version_groups', function () {
    $versionGroup = VersionGroup::query()->firstOrFail();

    $withData = Pokedex::query()->create([
        'nationaldex_id' => 25,
        'name' => 'Pikachu',
        'type1' => 'Electric',
        'type2' => null,
        'sprite_url' => null,
    ]);

    $withoutData = Pokedex::query()->create([
        'nationaldex_id' => 1,
        'name' => 'Bulbasaur',
        'type1' => 'Grass',
        'type2' => 'Poison',
        'sprite_url' => null,
    ]);

    PokemonGenerationData::factory()->create([
        'pokedex_id' => $withData->id,
        'version_group_id' => $versionGroup->id,
    ]);

    $service = new PokedexFilterService;

    $page = $service->paginate(50, ['generation' => $versionGroup->generation], null);
    expect($page->pluck('id')->all())->toContain($withData->id)->not->toContain($withoutData->id);
});

it('lists distinct generations from version groups for filter options', function () {
    $options = PokedexFilterService::generationFilterOptionInts();
    $expected = (int) VersionGroup::query()->orderBy('generation')->value('generation');

    expect($options)->toContain($expected);
});

it('returns results with a prefix name search', function () {
    Pokedex::query()->create(['nationaldex_id' => 4, 'name' => 'Charmander', 'type1' => 'Fire', 'type2' => null, 'sprite_url' => null]);
    Pokedex::query()->create(['nationaldex_id' => 1, 'name' => 'Bulbasaur', 'type1' => 'Grass', 'type2' => 'Poison', 'sprite_url' => null]);

    $service = new PokedexFilterService;
    $results = $service->paginate(50, ['search' => 'Char']);

    expect($results->pluck('name')->all())->toContain('Charmander')
        ->not->toContain('Bulbasaur');
});

it('returns results with a substring name search via Scout', function () {
    Pokedex::query()->create(['nationaldex_id' => 4, 'name' => 'Charmander', 'type1' => 'Fire', 'type2' => null, 'sprite_url' => null]);
    Pokedex::query()->create(['nationaldex_id' => 5, 'name' => 'Charmeleon', 'type1' => 'Fire', 'type2' => null, 'sprite_url' => null]);
    Pokedex::query()->create(['nationaldex_id' => 1, 'name' => 'Bulbasaur', 'type1' => 'Grass', 'type2' => 'Poison', 'sprite_url' => null]);

    $service = new PokedexFilterService;
    $results = $service->paginate(50, ['search' => 'mander']);

    expect($results->pluck('name')->all())
        ->toContain('Charmander')
        ->not->toContain('Charmeleon')
        ->not->toContain('Bulbasaur');
});

it('returns results matching by type column via Scout search', function () {
    Pokedex::query()->create(['nationaldex_id' => 4, 'name' => 'Charmander', 'type1' => 'Fire', 'type2' => null, 'sprite_url' => null]);
    Pokedex::query()->create(['nationaldex_id' => 1, 'name' => 'Bulbasaur', 'type1' => 'Grass', 'type2' => 'Poison', 'sprite_url' => null]);
    Pokedex::query()->create(['nationaldex_id' => 6, 'name' => 'Charizard', 'type1' => 'Fire', 'type2' => 'Flying', 'sprite_url' => null]);

    $service = new PokedexFilterService;
    $results = $service->paginate(50, ['search' => 'Fire']);

    $names = $results->pluck('name')->all();
    expect($names)->toContain('Charmander')
        ->toContain('Charizard')
        ->not->toContain('Bulbasaur');
});

it('combines Scout search with type filter', function () {
    Pokedex::query()->create(['nationaldex_id' => 4, 'name' => 'Charmander', 'type1' => 'Fire', 'type2' => null, 'sprite_url' => null]);
    Pokedex::query()->create(['nationaldex_id' => 6, 'name' => 'Charizard', 'type1' => 'Fire', 'type2' => 'Flying', 'sprite_url' => null]);
    Pokedex::query()->create(['nationaldex_id' => 10, 'name' => 'Caterpie', 'type1' => 'Bug', 'type2' => null, 'sprite_url' => null]);

    $service = new PokedexFilterService;
    $results = $service->paginate(50, ['search' => 'Char', 'type1' => 'Flying']);

    expect($results->pluck('name')->all())
        ->toContain('Charizard')
        ->not->toContain('Charmander')
        ->not->toContain('Caterpie');
});

it('returns all results when search is empty without using Scout', function () {
    Pokedex::query()->create(['nationaldex_id' => 25, 'name' => 'Pikachu', 'type1' => 'Electric', 'type2' => null, 'sprite_url' => null]);
    Pokedex::query()->create(['nationaldex_id' => 1, 'name' => 'Bulbasaur', 'type1' => 'Grass', 'type2' => 'Poison', 'sprite_url' => null]);

    $service = new PokedexFilterService;
    $results = $service->paginate(50, ['search' => '']);

    expect($results->total())->toBeGreaterThanOrEqual(2);
});

it('filters by ability for the selected game version', function () {
    $versionGroup = VersionGroup::query()->where('slug', 'scarlet-violet')->firstOrFail();

    $withAbility = Pokedex::query()->create([
        'nationaldex_id' => 1,
        'name' => 'Bulbasaur',
        'type1' => 'Grass',
        'type2' => 'Poison',
        'sprite_url' => null,
    ]);

    $withoutAbility = Pokedex::query()->create([
        'nationaldex_id' => 4,
        'name' => 'Charmander',
        'type1' => 'Fire',
        'type2' => null,
        'sprite_url' => null,
    ]);

    AbilityGenerationData::query()->create([
        'pokedex_id' => $withAbility->id,
        'version_group_id' => $versionGroup->id,
        'pokeapi_ability_id' => 65,
        'ability_name' => 'overgrow',
        'slot' => 1,
        'is_hidden' => false,
    ]);

    $service = new PokedexFilterService;
    $results = $service->paginate(50, [
        'game' => 'scarlet-violet',
        'ability' => 'overgrow',
    ]);

    expect($results->pluck('name')->all())
        ->toContain('Bulbasaur')
        ->not->toContain('Charmander');
});

it('filters by move in learnset for the selected game version', function () {
    $versionGroup = VersionGroup::query()->where('slug', 'scarlet-violet')->firstOrFail();

    $withMove = Pokedex::query()->create([
        'nationaldex_id' => 1,
        'name' => 'Bulbasaur',
        'type1' => 'Grass',
        'type2' => 'Poison',
        'sprite_url' => null,
    ]);

    $withoutMove = Pokedex::query()->create([
        'nationaldex_id' => 4,
        'name' => 'Charmander',
        'type1' => 'Fire',
        'type2' => null,
        'sprite_url' => null,
    ]);

    PokemonGenerationData::factory()->create([
        'pokedex_id' => $withMove->id,
        'version_group_id' => $versionGroup->id,
        'learnset' => [
            ['move_id' => 89, 'move_name' => 'earthquake', 'method' => 'machine', 'level' => 0],
        ],
    ]);

    DB::table('pokeapi_move_cache')->insert([
        'id' => 89,
        'name' => 'earthquake',
        'type_slug' => 'ground',
        'damage_class' => 'physical',
        'power' => 100,
        'accuracy' => 100,
        'ailment_name' => null,
        'short_effect_en' => null,
        'updated_at' => now(),
    ]);

    $service = new PokedexFilterService;
    $results = $service->paginate(50, [
        'game' => 'scarlet-violet',
        'move' => 'earthquake',
    ]);

    expect($results->pluck('name')->all())
        ->toContain('Bulbasaur')
        ->not->toContain('Charmander');
});

it('lists ability options for a version group', function () {
    $versionGroup = VersionGroup::query()->where('slug', 'scarlet-violet')->firstOrFail();
    $pokedex = Pokedex::query()->create([
        'nationaldex_id' => 1,
        'name' => 'Bulbasaur',
        'type1' => 'Grass',
        'type2' => 'Poison',
        'sprite_url' => null,
    ]);

    AbilityGenerationData::query()->create([
        'pokedex_id' => $pokedex->id,
        'version_group_id' => $versionGroup->id,
        'pokeapi_ability_id' => 65,
        'ability_name' => 'overgrow',
        'slot' => 1,
        'is_hidden' => false,
    ]);

    expect(PokedexFilterService::abilityFilterOptionsForVersionGroup($versionGroup->id))
        ->toContain('overgrow');
});
