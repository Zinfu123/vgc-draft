<?php

use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Services\PokeApiPokemonGameDataImporter;

uses(Tests\TestCase::class);

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

function reminderMethod(): ReflectionMethod
{
    $m = new ReflectionMethod(PokeApiPokemonGameDataImporter::class, 'mergeReminderMovesFromPriorGenerations');
    $m->setAccessible(true);

    return $m;
}

function makePokemonWithMoves(array $moves): array
{
    return ['moves' => $moves];
}

function makeMoveEntry(string $name, string $url, array $vgDetails): array
{
    return [
        'move' => ['name' => $name, 'url' => $url],
        'version_group_details' => $vgDetails,
    ];
}

function makeVgDetail(string $vgName, string $method, int $level = 0): array
{
    return [
        'version_group' => ['name' => $vgName],
        'move_learn_method' => ['name' => $method],
        'level_learned_at' => $level,
    ];
}

it('adds a prior-generation level-up move as reminder when missing from current learnset', function () {
    $importer = new PokeApiPokemonGameDataImporter;

    $pokemon = makePokemonWithMoves([
        makeMoveEntry('aurora-veil', 'https://pokeapi.co/api/v2/move/694/', [
            makeVgDetail('sword-shield', 'level-up', 1),
        ]),
    ]);

    $result = reminderMethod()->invoke($importer, [], $pokemon, 'scarlet-violet');

    expect($result)->toHaveCount(1)
        ->and($result[0]['move_name'])->toBe('aurora-veil')
        ->and($result[0]['method'])->toBe('reminder');
});

it('does not duplicate a move already present in the current learnset', function () {
    $importer = new PokeApiPokemonGameDataImporter;

    $existingLearnset = [
        ['move_id' => 97, 'move_name' => 'aurora-veil', 'method' => 'level-up', 'level' => 1],
    ];

    $pokemon = makePokemonWithMoves([
        makeMoveEntry('aurora-veil', 'https://pokeapi.co/api/v2/move/694/', [
            makeVgDetail('sword-shield', 'level-up', 1),
            makeVgDetail('scarlet-violet', 'level-up', 1),
        ]),
    ]);

    $result = reminderMethod()->invoke($importer, $existingLearnset, $pokemon, 'scarlet-violet');

    expect($result)->toHaveCount(1);
});

it('ignores moves only learnable via machine or egg in prior generations', function () {
    $importer = new PokeApiPokemonGameDataImporter;

    $pokemon = makePokemonWithMoves([
        makeMoveEntry('blizzard', 'https://pokeapi.co/api/v2/move/59/', [
            makeVgDetail('sword-shield', 'machine'),
        ]),
        makeMoveEntry('powder-snow', 'https://pokeapi.co/api/v2/move/181/', [
            makeVgDetail('sword-shield', 'egg'),
        ]),
    ]);

    $result = reminderMethod()->invoke($importer, [], $pokemon, 'scarlet-violet');

    expect($result)->toBeEmpty();
});

it('does not add moves already in the current version group', function () {
    $importer = new PokeApiPokemonGameDataImporter;

    $pokemon = makePokemonWithMoves([
        makeMoveEntry('icy-wind', 'https://pokeapi.co/api/v2/move/196/', [
            makeVgDetail('scarlet-violet', 'level-up', 1),
        ]),
    ]);

    $result = reminderMethod()->invoke($importer, [], $pokemon, 'scarlet-violet');

    expect($result)->toBeEmpty();
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
