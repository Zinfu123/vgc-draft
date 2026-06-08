<?php

use App\Actions\AuditPokemonGenerationDataVarietiesAction;
use App\Modules\Pokedex\Models\VersionGroup;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
});

function fakeIndeedeeSpecies(): void
{
    Http::fake([
        'https://pokeapi.co/api/v2/pokemon-species/876*' => Http::response([
            'id' => 876,
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
        ], 200),
    ]);
}

it('reports a variety mismatch when stored pokeapi id does not match the resolved form', function (): void {
    fakeIndeedeeSpecies();

    $versionGroup = VersionGroup::query()->where('slug', 'scarlet-violet')->firstOrFail();

    DB::table('pokedex')->insert([
        'id' => 99101,
        'nationaldex_id' => 876.002,
        'name' => 'indeedee-f',
        'type1' => 'Psychic',
        'type2' => 'Normal',
        'sprite_url' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('pokemon_generation_data')->insert([
        'pokedex_id' => 99101,
        'version_group_id' => $versionGroup->id,
        'pokeapi_pokemon_id' => 876,
        'hp' => 70,
        'atk' => 55,
        'def' => 65,
        'spa' => 95,
        'spd' => 105,
        'spe' => 85,
        'type1' => 'Psychic',
        'type2' => 'Normal',
        'ability_primary_pokeapi_id' => null,
        'ability_secondary_pokeapi_id' => null,
        'ability_hidden_pokeapi_id' => null,
        'learnset' => json_encode([]),
        'mechanics' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $result = app(AuditPokemonGenerationDataVarietiesAction::class)->handle('scarlet-violet');

    expect($result['rows_checked'])->toBe(1);
    expect($result['rows_with_issues'])->toBe(1);
    expect($result['issues'][0]['name'])->toBe('indeedee-f');
    expect($result['issues'][0]['issue'])->toBe('variety_mismatch');
    expect($result['issues'][0]['stored_pokeapi_pokemon_id'])->toBe(876);
    expect($result['issues'][0]['expected_pokeapi_pokemon_id'])->toBe(10186);
    expect($result['issues'][0]['expected_variety_name'])->toBe('indeedee-female');
});

it('passes when stored pokeapi id matches the resolved form', function (): void {
    fakeIndeedeeSpecies();

    $versionGroup = VersionGroup::query()->where('slug', 'scarlet-violet')->firstOrFail();

    DB::table('pokedex')->insert([
        'id' => 99102,
        'nationaldex_id' => 876.002,
        'name' => 'indeedee-f',
        'type1' => 'Psychic',
        'type2' => 'Normal',
        'sprite_url' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('pokemon_generation_data')->insert([
        'pokedex_id' => 99102,
        'version_group_id' => $versionGroup->id,
        'pokeapi_pokemon_id' => 10186,
        'hp' => 70,
        'atk' => 55,
        'def' => 65,
        'spa' => 95,
        'spd' => 105,
        'spe' => 85,
        'type1' => 'Psychic',
        'type2' => 'Normal',
        'ability_primary_pokeapi_id' => null,
        'ability_secondary_pokeapi_id' => null,
        'ability_hidden_pokeapi_id' => null,
        'learnset' => json_encode([]),
        'mechanics' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $result = app(AuditPokemonGenerationDataVarietiesAction::class)->handle('scarlet-violet');

    expect($result['rows_checked'])->toBe(1);
    expect($result['rows_with_issues'])->toBe(0);
    expect($result['rows_ok'])->toBe(1);
});

it('runs pokemon:audit-generation-data with json output', function (): void {
    fakeIndeedeeSpecies();

    $versionGroup = VersionGroup::query()->where('slug', 'scarlet-violet')->firstOrFail();

    DB::table('pokedex')->insert([
        'id' => 99103,
        'nationaldex_id' => 876.002,
        'name' => 'indeedee-f',
        'type1' => 'Psychic',
        'type2' => 'Normal',
        'sprite_url' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('pokemon_generation_data')->insert([
        'pokedex_id' => 99103,
        'version_group_id' => $versionGroup->id,
        'pokeapi_pokemon_id' => 876,
        'hp' => 70,
        'atk' => 55,
        'def' => 65,
        'spa' => 95,
        'spd' => 105,
        'spe' => 85,
        'type1' => 'Psychic',
        'type2' => 'Normal',
        'ability_primary_pokeapi_id' => null,
        'ability_secondary_pokeapi_id' => null,
        'ability_hidden_pokeapi_id' => null,
        'learnset' => json_encode([]),
        'mechanics' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $exitCode = Artisan::call('pokemon:audit-generation-data', [
        '--slug' => 'scarlet-violet',
        '--json' => true,
    ]);

    expect($exitCode)->toBe(1);

    $decoded = json_decode(Artisan::output(), true);
    expect($decoded)->toBeArray();
    expect($decoded['rows_with_issues'])->toBe(1);
    expect($decoded['issues'][0]['issue'])->toBe('variety_mismatch');
});

it('re-imports mismatched rows when --fix is passed', function (): void {
    fakeIndeedeeSpecies();

    $versionGroup = VersionGroup::query()->where('slug', 'scarlet-violet')->firstOrFail();

    DB::table('pokedex')->insert([
        'id' => 99104,
        'nationaldex_id' => 876.002,
        'name' => 'indeedee-f',
        'type1' => 'Psychic',
        'type2' => 'Normal',
        'sprite_url' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('pokemon_generation_data')->insert([
        'pokedex_id' => 99104,
        'version_group_id' => $versionGroup->id,
        'pokeapi_pokemon_id' => 876,
        'hp' => 70,
        'atk' => 55,
        'def' => 65,
        'spa' => 95,
        'spd' => 105,
        'spe' => 85,
        'type1' => 'Psychic',
        'type2' => 'Normal',
        'ability_primary_pokeapi_id' => null,
        'ability_secondary_pokeapi_id' => null,
        'ability_hidden_pokeapi_id' => null,
        'learnset' => json_encode([]),
        'mechanics' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->partialMock(\App\Modules\Pokedex\Services\PokeApiPokemonGameDataImporter::class, function ($mock) use ($versionGroup): void {
        $mock->shouldReceive('import')->once()->andReturnUsing(function ($pokedex) use ($versionGroup): bool {
            DB::table('pokemon_generation_data')
                ->where('pokedex_id', $pokedex->id)
                ->where('version_group_id', $versionGroup->id)
                ->update(['pokeapi_pokemon_id' => 10186]);

            return true;
        });
    });

    $this->artisan('pokemon:audit-generation-data', [
        '--slug' => 'scarlet-violet',
        '--fix' => true,
    ])->assertExitCode(0);
});
