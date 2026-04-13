<?php

use App\Models\User;
use App\Modules\Pokedex\Models\AbilityGenerationData;
use App\Modules\Pokedex\Models\PokemonGenerationData;
use App\Modules\Pokedex\Models\VersionGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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

    PokemonGenerationData::factory()->create([
        'pokedex_id' => $pokedexId,
        'version_group_id' => $versionGroup->id,
    ]);
    AbilityGenerationData::query()->create([
        'pokedex_id' => $pokedexId,
        'version_group_id' => $versionGroup->id,
        'pokeapi_ability_id' => 65,
        'ability_name' => 'overgrow',
        'slot' => 1,
        'is_hidden' => false,
    ]);

    $response = $this->actingAs($user)->get(route('pokedex.show', $pokedexId).'?'.http_build_query(['game' => 'scarlet-violet']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('pokedex/PokedexShow')
        ->where('selectedVersionSlug', 'scarlet-violet')
        ->has('gameData')
        ->where('gameData.hp', 50)
        ->has('gameData.abilities')
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

    PokemonGenerationData::factory()->create([
        'pokedex_id' => $pokedexId,
        'version_group_id' => $sv->id,
        'hp' => 39,
    ]);
    AbilityGenerationData::query()->create([
        'pokedex_id' => $pokedexId,
        'version_group_id' => $sv->id,
        'pokeapi_ability_id' => 66,
        'ability_name' => 'blaze',
        'slot' => 1,
        'is_hidden' => false,
    ]);

    PokemonGenerationData::factory()->create([
        'pokedex_id' => $pokedexId,
        'version_group_id' => $swsh->id,
        'hp' => 40,
    ]);
    AbilityGenerationData::query()->create([
        'pokedex_id' => $pokedexId,
        'version_group_id' => $swsh->id,
        'pokeapi_ability_id' => 66,
        'ability_name' => 'blaze',
        'slot' => 1,
        'is_hidden' => false,
    ]);

    $this->actingAs($user)
        ->get(route('pokedex.show', $pokedexId).'?'.http_build_query(['game' => 'sword-shield']))
        ->assertInertia(fn ($page) => $page
            ->where('selectedVersionSlug', 'sword-shield')
            ->where('gameData.hp', 40)
        );
});

it('renders pokedex ability detail from PokéAPI', function () {
    $user = User::factory()->create();
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        $path = (string) (parse_url($request->url(), PHP_URL_PATH) ?? '');
        if (str_contains($path, '/ability/65')) {
            return Http::response([
                'id' => 65,
                'name' => 'overgrow',
                'effect_entries' => [
                    ['effect' => 'Test effect.', 'short_effect' => 'Short.', 'language' => ['name' => 'en']],
                ],
                'generation' => ['name' => 'generation-iii'],
                'flavor_text_entries' => [],
            ], 200);
        }

        return Http::response(['error' => 'unexpected'], 404);
    });

    $this->actingAs($user)
        ->get(route('pokedex.abilities.show', 65))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pokedex/PokedexAbilityShow')
            ->where('name_display', 'Overgrow'));
});

it('renders pokedex item detail from PokéAPI', function () {
    $user = User::factory()->create();
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        $path = (string) (parse_url($request->url(), PHP_URL_PATH) ?? '');
        if (str_contains($path, '/item/211')) {
            return Http::response([
                'id' => 211,
                'name' => 'leftovers',
                'cost' => 200,
                'category' => ['name' => 'medicine'],
                'effect_entries' => [
                    ['effect' => 'Restores HP.', 'short_effect' => 'Restores HP each turn.', 'language' => ['name' => 'en']],
                ],
                'names' => [['name' => 'Leftovers', 'language' => ['name' => 'en']]],
                'sprites' => ['default' => 'https://example.com/leftovers.png'],
                'flavor_text_entries' => [],
            ], 200);
        }

        return Http::response(['error' => 'unexpected'], 404);
    });

    $this->actingAs($user)
        ->get(route('pokedex.items.show', 211))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pokedex/PokedexItemShow')
            ->where('name_display', 'Leftovers'));
});
