<?php

use App\Models\User;
use App\Modules\Pokedex\Models\PokemonGameData;
use App\Modules\Pokedex\Models\VersionGroup;
use Illuminate\Support\Facades\DB;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('renders the pokedex index for authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('pokedex.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('pokedex/PokedexIndex')
        ->has('pokemon')
        ->has('filters')
        ->has('typeOptions')
        ->has('generationFilterOptions')
    );
});

it('renders pokemon detail with scarlet-violet game data', function () {
    $user = User::factory()->create();
    $versionGroup = VersionGroup::query()->where('slug', 'scarlet-violet')->firstOrFail();

    $pokedexId = DB::table('pokedex')->insertGetId([
        'nationaldex_id' => 1,
        'name' => 'Bulbasaur',
        'type1' => 'Grass',
        'type2' => 'Poison',
        'sprite_url' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    PokemonGameData::factory()->create([
        'pokedex_id' => $pokedexId,
        'version_group_id' => $versionGroup->id,
    ]);

    $response = $this->actingAs($user)->get(route('pokedex.show', $pokedexId));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('pokedex/PokedexShow')
        ->where('selectedVersionSlug', 'scarlet-violet')
        ->has('gameData')
        ->where('gameData.hp', 50)
    );
});

it('switches game data when game query matches another version group', function () {
    $user = User::factory()->create();

    DB::table('version_groups')->insert([
        'slug' => 'sword-shield',
        'generation' => 8,
        'sort_order' => 50,
        'name' => 'Sword & Shield',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $sv = VersionGroup::query()->where('slug', 'scarlet-violet')->firstOrFail();
    $swsh = VersionGroup::query()->where('slug', 'sword-shield')->firstOrFail();

    $pokedexId = DB::table('pokedex')->insertGetId([
        'nationaldex_id' => 4,
        'name' => 'Charmander',
        'type1' => 'Fire',
        'type2' => null,
        'sprite_url' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    PokemonGameData::factory()->create([
        'pokedex_id' => $pokedexId,
        'version_group_id' => $sv->id,
        'hp' => 39,
    ]);

    PokemonGameData::factory()->create([
        'pokedex_id' => $pokedexId,
        'version_group_id' => $swsh->id,
        'hp' => 40,
    ]);

    $this->actingAs($user)
        ->get(route('pokedex.show', $pokedexId).'?'.http_build_query(['game' => 'sword-shield']))
        ->assertInertia(fn ($page) => $page
            ->where('selectedVersionSlug', 'sword-shield')
            ->where('gameData.hp', 40)
        );
});
