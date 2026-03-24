<?php

use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Models\PokemonGameData;
use App\Modules\Pokedex\Models\VersionGroup;
use App\Modules\Pokedex\Services\PokeApiPokemonGameDataImporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function minimalPokemonPayloadForImporter(): array
{
    $stats = [];
    foreach (['hp', 'attack', 'defense', 'special-attack', 'special-defense', 'speed'] as $i => $n) {
        $stats[] = ['base_stat' => 50 + $i, 'stat' => ['name' => $n]];
    }

    return [
        'stats' => $stats,
        'types' => [
            ['slot' => 1, 'type' => ['name' => 'bug']],
        ],
        'abilities' => [
            ['ability' => ['name' => 'swarm'], 'is_hidden' => false, 'slot' => 1],
        ],
    ];
}

it('merges egg moves from evolves_from ancestor into the imported learnset', function () {
    $versionGroup = VersionGroup::query()->where('slug', 'scarlet-violet')->firstOrFail();
    $pokedexId = DB::table('pokedex')->insertGetId([
        'nationaldex_id' => 900,
        'name' => 'kleavor',
        'type1' => 'Bug',
        'type2' => 'Rock',
        'sprite_url' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $pokedex = Pokedex::query()->findOrFail($pokedexId);

    $species900 = [
        'evolves_from_species' => [
            'name' => 'scyther',
            'url' => 'https://pokeapi.co/api/v2/pokemon-species/123/',
        ],
        'varieties' => [
            [
                'is_default' => true,
                'pokemon' => [
                    'name' => 'kleavor',
                    'url' => 'https://pokeapi.co/api/v2/pokemon/900/',
                ],
            ],
        ],
    ];

    $species123 = [
        'evolves_from_species' => null,
        'varieties' => [
            [
                'is_default' => true,
                'pokemon' => [
                    'name' => 'scyther',
                    'url' => 'https://pokeapi.co/api/v2/pokemon/123/',
                ],
            ],
        ],
    ];

    $pokemon900 = array_merge(minimalPokemonPayloadForImporter(), [
        'moves' => [
            [
                'move' => ['name' => 'tackle', 'url' => 'https://pokeapi.co/api/v2/move/33/'],
                'version_group_details' => [
                    [
                        'version_group' => ['name' => 'scarlet-violet'],
                        'move_learn_method' => ['name' => 'level-up'],
                        'level_learned_at' => 1,
                    ],
                ],
            ],
        ],
    ]);

    $pokemon123 = array_merge(minimalPokemonPayloadForImporter(), [
        'moves' => [
            [
                'move' => ['name' => 'night-slash', 'url' => 'https://pokeapi.co/api/v2/move/400/'],
                'version_group_details' => [
                    [
                        'version_group' => ['name' => 'scarlet-violet'],
                        'move_learn_method' => ['name' => 'egg'],
                        'level_learned_at' => 0,
                    ],
                ],
            ],
        ],
    ]);

    Http::fake(function (\Illuminate\Http\Client\Request $request) use ($species900, $species123, $pokemon900, $pokemon123) {
        $path = (string) (parse_url($request->url(), PHP_URL_PATH) ?? '');

        if (str_contains($path, '/pokemon-species/900')) {
            return Http::response($species900, 200);
        }
        if (str_contains($path, '/pokemon-species/123')) {
            return Http::response($species123, 200);
        }
        if (str_contains($path, '/pokemon/900')) {
            return Http::response($pokemon900, 200);
        }
        if (str_contains($path, '/pokemon/123')) {
            return Http::response($pokemon123, 200);
        }

        return Http::response(['error' => 'unexpected '.$path], 404);
    });

    $ok = (new PokeApiPokemonGameDataImporter)->import($pokedex, $versionGroup);

    expect($ok)->toBeTrue();

    $row = PokemonGameData::query()
        ->where('pokedex_id', $pokedex->id)
        ->where('version_group_id', $versionGroup->id)
        ->first();
    expect($row)->not->toBeNull();

    $learnset = $row->learnset;
    expect(is_array($learnset))->toBeTrue();
    $names = collect($learnset)->pluck('move_name')->map(fn (string $n) => strtolower($n))->all();
    expect($names)->toContain('night-slash');

    $eggNightSlash = collect($learnset)->first(fn (array $m) => ($m['move_name'] ?? '') === 'night-slash' && ($m['method'] ?? '') === 'egg');
    expect($eggNightSlash)->not->toBeNull();
});
