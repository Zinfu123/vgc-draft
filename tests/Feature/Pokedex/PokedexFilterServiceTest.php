<?php

use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Models\PokemonGameData;
use App\Modules\Pokedex\Models\VersionGroup;
use App\Modules\Pokedex\Services\PokedexFilterService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('filters by generation using pokemon_game_data and version_groups', function () {
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

    PokemonGameData::factory()->create([
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
