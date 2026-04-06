<?php

use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Models\PokemonGenerationData;
use App\Modules\Pokedex\Models\VersionGroup;
use App\Modules\Pokedex\Services\PokedexFilterService;

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
