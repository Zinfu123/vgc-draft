<?php

use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Models\VersionGroup;
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

it('matches a variety whose api name has a suffix appended to the pokedex slug', function () {
    $pokedex = new Pokedex(['name' => 'ogerpon-wellspring']);
    $species = [
        'varieties' => [
            [
                'is_default' => true,
                'pokemon' => [
                    'name' => 'ogerpon',
                    'url' => 'https://pokeapi.co/api/v2/pokemon/1017/',
                ],
            ],
            [
                'is_default' => false,
                'pokemon' => [
                    'name' => 'ogerpon-wellspring-mask',
                    'url' => 'https://pokeapi.co/api/v2/pokemon/10274/',
                ],
            ],
        ],
    ];

    $importer = new PokeApiPokemonGameDataImporter;
    $method = new ReflectionMethod(PokeApiPokemonGameDataImporter::class, 'resolveVarietyPokemonUrl');
    $method->setAccessible(true);
    $url = $method->invoke($importer, $species, $pokedex);

    expect($url)->toEndWith('/10274/');
});

it('does not let the prefix fallback steal the default variety from the base form', function () {
    $pokedex = new Pokedex(['name' => 'ogerpon']);
    $species = [
        'varieties' => [
            [
                'is_default' => true,
                'pokemon' => [
                    'name' => 'ogerpon',
                    'url' => 'https://pokeapi.co/api/v2/pokemon/1017/',
                ],
            ],
            [
                'is_default' => false,
                'pokemon' => [
                    'name' => 'ogerpon-wellspring-mask',
                    'url' => 'https://pokeapi.co/api/v2/pokemon/10274/',
                ],
            ],
        ],
    ];

    $importer = new PokeApiPokemonGameDataImporter;
    $method = new ReflectionMethod(PokeApiPokemonGameDataImporter::class, 'resolveVarietyPokemonUrl');
    $method->setAccessible(true);
    $url = $method->invoke($importer, $species, $pokedex);

    expect($url)->toEndWith('/1017/');
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

it('maps showdown-style -f pokedex names to pokeapi -female varieties', function (string $pokedexName, string $expectedApiName) {
    $pokedex = new Pokedex(['name' => $pokedexName]);
    $species = [
        'varieties' => [
            [
                'is_default' => true,
                'pokemon' => [
                    'name' => str_replace('-female', '-male', $expectedApiName),
                    'url' => 'https://pokeapi.co/api/v2/pokemon/1/',
                ],
            ],
            [
                'is_default' => false,
                'pokemon' => [
                    'name' => $expectedApiName,
                    'url' => 'https://pokeapi.co/api/v2/pokemon/10186/',
                ],
            ],
        ],
    ];

    $importer = new PokeApiPokemonGameDataImporter;
    $method = new ReflectionMethod(PokeApiPokemonGameDataImporter::class, 'resolveVarietyPokemonUrl');
    $method->setAccessible(true);
    $url = $method->invoke($importer, $species, $pokedex);

    expect($url)->toEndWith('/10186/');
})->with([
    'indeedee-f' => ['indeedee-f', 'indeedee-female'],
    'meowstic-f' => ['meowstic-f', 'meowstic-female'],
    'basculegion-f' => ['basculegion-f', 'basculegion-female'],
    'oinkologne-f' => ['oinkologne-f', 'oinkologne-female'],
]);

it('maps paldea tauros pokedex names to pokeapi breed varieties', function (string $pokedexName, string $expectedApiName, int $expectedPokemonId) {
    $pokedex = new Pokedex(['name' => $pokedexName]);
    $species = [
        'varieties' => [
            [
                'is_default' => true,
                'pokemon' => [
                    'name' => 'tauros',
                    'url' => 'https://pokeapi.co/api/v2/pokemon/128/',
                ],
            ],
            [
                'is_default' => false,
                'pokemon' => [
                    'name' => 'tauros-paldea-combat-breed',
                    'url' => 'https://pokeapi.co/api/v2/pokemon/10250/',
                ],
            ],
            [
                'is_default' => false,
                'pokemon' => [
                    'name' => 'tauros-paldea-blaze-breed',
                    'url' => 'https://pokeapi.co/api/v2/pokemon/10251/',
                ],
            ],
            [
                'is_default' => false,
                'pokemon' => [
                    'name' => 'tauros-paldea-aqua-breed',
                    'url' => 'https://pokeapi.co/api/v2/pokemon/10252/',
                ],
            ],
        ],
    ];

    $importer = new PokeApiPokemonGameDataImporter;
    $method = new ReflectionMethod(PokeApiPokemonGameDataImporter::class, 'resolveVarietyPokemonUrl');
    $method->setAccessible(true);
    $url = $method->invoke($importer, $species, $pokedex);

    expect($url)->toEndWith("/{$expectedPokemonId}/");
})->with([
    'combat' => ['tauros-paldea', 'tauros-paldea-combat-breed', 10250],
    'blaze' => ['tauros-paldea-fire', 'tauros-paldea-blaze-breed', 10251],
    'aqua' => ['tauros-paldea-water', 'tauros-paldea-aqua-breed', 10252],
]);

it('maps base pokedex names to pokeapi -male varieties via prefix match', function () {
    $pokedex = new Pokedex(['name' => 'indeedee']);
    $species = [
        'varieties' => [
            [
                'is_default' => true,
                'pokemon' => [
                    'name' => 'indeedee-male',
                    'url' => 'https://pokeapi.co/api/v2/pokemon/876/',
                ],
            ],
            [
                'is_default' => false,
                'pokemon' => [
                    'name' => 'indeedee-female',
                    'url' => 'https://pokeapi.co/api/v2/pokemon/10186/',
                ],
            ],
        ],
    ];

    $importer = new PokeApiPokemonGameDataImporter;
    $method = new ReflectionMethod(PokeApiPokemonGameDataImporter::class, 'resolveVarietyPokemonUrl');
    $method->setAccessible(true);
    $url = $method->invoke($importer, $species, $pokedex);

    expect($url)->toEndWith('/876/');
});

it('sets tera_capable true and mega false for tera mechanic version groups', function () {
    $versionGroup = new VersionGroup(['generational_mechanics' => [1]]);
    $importer = new PokeApiPokemonGameDataImporter;
    $method = new ReflectionMethod(PokeApiPokemonGameDataImporter::class, 'defaultMechanicsForVersionGroup');
    $method->setAccessible(true);

    $mechanics = $method->invoke($importer, $versionGroup);

    expect($mechanics['tera_capable'])->toBeTrue();
    expect($mechanics['mega'])->toBeFalse();
});

it('sets mega true and tera_capable false for mega mechanic version groups like champions', function () {
    $versionGroup = new VersionGroup(['generational_mechanics' => [2]]);
    $importer = new PokeApiPokemonGameDataImporter;
    $method = new ReflectionMethod(PokeApiPokemonGameDataImporter::class, 'defaultMechanicsForVersionGroup');
    $method->setAccessible(true);

    $mechanics = $method->invoke($importer, $versionGroup);

    expect($mechanics['tera_capable'])->toBeFalse();
    expect($mechanics['mega'])->toBeTrue();
    expect($mechanics['z_move'])->toBeFalse();
    expect($mechanics['dynamax'])->toBeFalse();
    expect($mechanics['gmax'])->toBeFalse();
});

it('sets all mechanics to false for version groups with no battle mechanic', function () {
    $versionGroup = new VersionGroup(['generational_mechanics' => [0]]);
    $importer = new PokeApiPokemonGameDataImporter;
    $method = new ReflectionMethod(PokeApiPokemonGameDataImporter::class, 'defaultMechanicsForVersionGroup');
    $method->setAccessible(true);

    $mechanics = $method->invoke($importer, $versionGroup);

    expect($mechanics['tera_capable'])->toBeFalse();
    expect($mechanics['mega'])->toBeFalse();
});
