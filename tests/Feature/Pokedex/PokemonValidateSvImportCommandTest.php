<?php

use App\Modules\Pokedex\Models\VersionGroup;
use Illuminate\Support\Facades\Http;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('prints PokeAPI regional dex counts and compares to the database', function () {
    VersionGroup::query()->where('slug', 'scarlet-violet')->firstOrFail();

    Http::fake([
        'https://pokeapi.co/api/v2/pokedex/31/' => Http::response([
            'pokemon_entries' => [
                ['pokemon_species' => ['url' => 'https://pokeapi.co/api/v2/pokemon-species/10/']],
                ['pokemon_species' => ['url' => 'https://pokeapi.co/api/v2/pokemon-species/11/']],
            ],
        ], 200),
        'https://pokeapi.co/api/v2/pokedex/32/' => Http::response([
            'pokemon_entries' => [
                ['pokemon_species' => ['url' => 'https://pokeapi.co/api/v2/pokemon-species/11/']],
            ],
        ], 200),
        'https://pokeapi.co/api/v2/pokedex/33/' => Http::response([
            'pokemon_entries' => [],
        ], 200),
        'https://pokeapi.co/api/v2/generation/9/' => Http::response([
            'pokemon_species' => [
                ['url' => 'https://pokeapi.co/api/v2/pokemon-species/906/'],
            ],
        ], 200),
    ]);

    config(['pokemon.pokeapi_url' => 'https://pokeapi.co/api/v2']);

    $this->artisan('pokemon:validate-sv-import')
        ->expectsOutputToContain('Unique species in Paldea')
        ->assertExitCode(0);

    Http::assertSentCount(4);
});
