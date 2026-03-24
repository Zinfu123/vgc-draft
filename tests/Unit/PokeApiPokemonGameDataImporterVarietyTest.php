<?php

use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Services\PokeApiPokemonGameDataImporter;

it('picks the variety whose pokemon name matches the pokedex row name slug', function () {
    $pokedex = new Pokedex(['name' => 'slowbro-galar']);
    $species = [
        'varieties' => [
            [
                'is_default' => true,
                'pokemon' => [
                    'name' => 'slowbro',
                    'url' => 'https://pokeapi.co/api/v2/pokemon/80/',
                ],
            ],
            [
                'is_default' => false,
                'pokemon' => [
                    'name' => 'slowbro-galar',
                    'url' => 'https://pokeapi.co/api/v2/pokemon/10172/',
                ],
            ],
        ],
    ];

    $importer = new PokeApiPokemonGameDataImporter;
    $method = new ReflectionMethod(PokeApiPokemonGameDataImporter::class, 'resolveVarietyPokemonUrl');
    $method->setAccessible(true);
    $url = $method->invoke($importer, $species, $pokedex);

    expect($url)->toEndWith('/10172/');
});

it('falls back to the default variety when the name matches the default form', function () {
    $pokedex = new Pokedex(['name' => 'slowbro']);
    $species = [
        'varieties' => [
            [
                'is_default' => true,
                'pokemon' => [
                    'name' => 'slowbro',
                    'url' => 'https://pokeapi.co/api/v2/pokemon/80/',
                ],
            ],
            [
                'is_default' => false,
                'pokemon' => [
                    'name' => 'slowbro-galar',
                    'url' => 'https://pokeapi.co/api/v2/pokemon/10172/',
                ],
            ],
        ],
    ];

    $importer = new PokeApiPokemonGameDataImporter;
    $method = new ReflectionMethod(PokeApiPokemonGameDataImporter::class, 'resolveVarietyPokemonUrl');
    $method->setAccessible(true);
    $url = $method->invoke($importer, $species, $pokedex);

    expect($url)->toEndWith('/80/');
});
