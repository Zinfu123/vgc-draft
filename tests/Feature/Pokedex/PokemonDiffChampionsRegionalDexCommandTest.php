<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('reports species only in pokeapi vs local champions-reg-ma generation data', function (): void {
    $vgId = (int) DB::table('version_groups')->where('slug', 'champions-reg-ma')->value('id');
    expect($vgId)->toBeGreaterThan(0);

    DB::table('pokedex')->insert([
        'id' => 99001,
        'nationaldex_id' => 1,
        'name' => 'bulbasaur',
        'type1' => 'Grass',
        'type2' => 'Poison',
        'sprite_url' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('pokemon_generation_data')->insert([
        'pokedex_id' => 99001,
        'version_group_id' => $vgId,
        'pokeapi_pokemon_id' => null,
        'hp' => 1,
        'atk' => 1,
        'def' => 1,
        'spa' => 1,
        'spd' => 1,
        'spe' => 1,
        'type1' => 'Grass',
        'type2' => 'Poison',
        'ability_primary_pokeapi_id' => null,
        'ability_secondary_pokeapi_id' => null,
        'ability_hidden_pokeapi_id' => null,
        'learnset' => json_encode([]),
        'mechanics' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Http::fake(function ($request) {
        $url = $request->url();
        if (str_contains($url, '/pokedex/champions')) {
            return Http::response([
                'pokemon_entries' => [
                    ['pokemon_species' => ['name' => 'bulbasaur']],
                    ['pokemon_species' => ['name' => 'chikorita']],
                ],
            ], 200);
        }
        if (preg_match('#/pokemon-species/1/#', $url) === 1) {
            return Http::response(['name' => 'bulbasaur'], 200);
        }

        return Http::response([], 404);
    });

    Artisan::call('pokemon:diff-champions-regional-dex', [
        '--slug' => 'champions-reg-ma',
        '--pokeapi-pokedex' => 'champions',
    ]);

    $out = Artisan::output();
    expect($out)->toContain('Only in PokéAPI regional dex');
    expect($out)->toContain('chikorita');
});
