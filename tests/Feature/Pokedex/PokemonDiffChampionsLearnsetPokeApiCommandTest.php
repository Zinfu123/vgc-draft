<?php

use App\Actions\DiffChampionsLearnsetVsPokeApiAction;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('reports moves present in DB learnset but not in the PokéAPI reference version group', function (): void {
    $vgId = (int) DB::table('version_groups')->where('slug', 'champions-reg-ma')->value('id');
    expect($vgId)->toBeGreaterThan(0);

    DB::table('pokedex')->insert([
        'id' => 99101,
        'nationaldex_id' => 1,
        'name' => 'bulbasaur',
        'type1' => 'Grass',
        'type2' => 'Poison',
        'sprite_url' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('pokemon_generation_data')->insert([
        'pokedex_id' => 99101,
        'version_group_id' => $vgId,
        'pokeapi_pokemon_id' => null,
        'hp' => 45,
        'atk' => 49,
        'def' => 49,
        'spa' => 65,
        'spd' => 65,
        'spe' => 45,
        'type1' => 'Grass',
        'type2' => 'Poison',
        'ability_primary_pokeapi_id' => null,
        'ability_secondary_pokeapi_id' => null,
        'ability_hidden_pokeapi_id' => null,
        'learnset' => json_encode([
            ['move_id' => 33, 'move_name' => 'tackle', 'method' => 'level-up', 'level' => 1],
            ['move_id' => 99999, 'move_name' => 'fake-move-for-test', 'method' => 'level-up', 'level' => 1],
        ]),
        'mechanics' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Http::fake([
        'https://pokeapi.co/api/v2/pokemon-species/1*' => Http::response([
            'id' => 1,
            'evolves_from_species' => null,
            'varieties' => [[
                'is_default' => true,
                'pokemon' => [
                    'name' => 'bulbasaur',
                    'url' => 'https://pokeapi.co/api/v2/pokemon/1/',
                ],
            ]],
        ], 200),
        'https://pokeapi.co/api/v2/pokemon/1*' => Http::response([
            'id' => 1,
            'moves' => [[
                'move' => ['name' => 'tackle', 'url' => 'https://pokeapi.co/api/v2/move/33/'],
                'version_group_details' => [[
                    'version_group' => ['name' => 'scarlet-violet'],
                    'move_learn_method' => ['name' => 'level-up'],
                    'level_learned_at' => 1,
                ]],
            ]],
        ], 200),
    ]);

    $result = app(DiffChampionsLearnsetVsPokeApiAction::class)->handle(
        'champions-reg-ma',
        'scarlet-violet',
        false,
        null,
    );

    expect($result['rows_compared'])->toBe(1);
    expect($result['differences'])->toHaveCount(1);
    expect($result['differences'][0]['name'])->toBe('bulbasaur');
    expect($result['differences'][0]['only_in_db'])->toContain('fake-move-for-test (99999)');
    expect($result['differences'][0]['only_in_pokeapi'])->toBe([]);
});

it('runs pokemon:diff-champions-learnsets-pokeapi with --json', function (): void {
    $vgId = (int) DB::table('version_groups')->where('slug', 'champions-reg-ma')->value('id');

    DB::table('pokedex')->insert([
        'id' => 99102,
        'nationaldex_id' => 1,
        'name' => 'bulbasaur',
        'type1' => 'Grass',
        'type2' => 'Poison',
        'sprite_url' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('pokemon_generation_data')->insert([
        'pokedex_id' => 99102,
        'version_group_id' => $vgId,
        'pokeapi_pokemon_id' => null,
        'hp' => 45,
        'atk' => 49,
        'def' => 49,
        'spa' => 65,
        'spd' => 65,
        'spe' => 45,
        'type1' => 'Grass',
        'type2' => 'Poison',
        'ability_primary_pokeapi_id' => null,
        'ability_secondary_pokeapi_id' => null,
        'ability_hidden_pokeapi_id' => null,
        'learnset' => json_encode([
            ['move_id' => 33, 'move_name' => 'tackle', 'method' => 'level-up', 'level' => 1],
        ]),
        'mechanics' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Http::fake([
        'https://pokeapi.co/api/v2/pokemon-species/1*' => Http::response([
            'id' => 1,
            'evolves_from_species' => null,
            'varieties' => [[
                'is_default' => true,
                'pokemon' => [
                    'name' => 'bulbasaur',
                    'url' => 'https://pokeapi.co/api/v2/pokemon/1/',
                ],
            ]],
        ], 200),
        'https://pokeapi.co/api/v2/pokemon/1*' => Http::response([
            'id' => 1,
            'moves' => [[
                'move' => ['name' => 'tackle', 'url' => 'https://pokeapi.co/api/v2/move/33/'],
                'version_group_details' => [[
                    'version_group' => ['name' => 'scarlet-violet'],
                    'move_learn_method' => ['name' => 'level-up'],
                    'level_learned_at' => 1,
                ]],
            ]],
        ], 200),
    ]);

    Artisan::call('pokemon:diff-champions-learnsets-pokeapi', [
        '--all' => true,
        '--json' => true,
    ]);

    $decoded = json_decode(Artisan::output(), true);
    expect($decoded)->toBeArray();
    expect($decoded['rows_compared'] ?? null)->toBe(1);
});
