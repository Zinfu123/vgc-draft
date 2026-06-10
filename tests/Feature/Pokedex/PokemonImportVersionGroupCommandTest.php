<?php

use App\Modules\Pokedex\Models\PokemonGenerationData;
use App\Modules\Pokedex\Models\VersionGroup;
use Illuminate\Support\Facades\DB;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('does nothing when only-missing and the only pokedex row already has game data', function () {
    $versionGroup = VersionGroup::query()->where('slug', 'scarlet-violet')->firstOrFail();
    $pokedexId = DB::table('pokedex')->insertGetId([
        'nationaldex_id' => 1,
        'name' => 'bulbasaur',
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

    $this->artisan('pokemon:import-version-group', [
        'slug' => 'scarlet-violet',
        '--only-missing' => true,
        '--id' => $pokedexId,
    ])
        ->expectsOutputToContain('Nothing to import')
        ->assertExitCode(0);
});

it('still imports a row when only-missing and game data is absent', function () {
    $pokedexId = DB::table('pokedex')->insertGetId([
        'nationaldex_id' => 999,
        'name' => 'bulbasaur',
        'type1' => 'Grass',
        'type2' => 'Poison',
        'sprite_url' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->partialMock(\App\Modules\Pokedex\Services\PokeApiPokemonGameDataImporter::class, function ($mock) {
        $mock->shouldReceive('import')->once()->andReturn(true);
    });

    $this->artisan('pokemon:import-version-group', [
        'slug' => 'scarlet-violet',
        '--only-missing' => true,
        '--id' => $pokedexId,
    ])
        ->expectsOutputToContain('Importing 1 species')
        ->assertExitCode(0);
});

it('re-imports only ogerpon mask form pokedex rows when ogerpon-mask-forms is set', function () {
    $maskNames = \App\Console\Commands\PokemonImportVersionGroupCommand::OGERPON_MASK_POKEDEX_NAMES;

    foreach ($maskNames as $name) {
        DB::table('pokedex')->insert([
            'nationaldex_id' => 1017,
            'name' => $name,
            'type1' => 'Grass',
            'type2' => null,
            'sprite_url' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    DB::table('pokedex')->insert([
        'nationaldex_id' => 1017,
        'name' => 'ogerpon',
        'type1' => 'Grass',
        'type2' => null,
        'sprite_url' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->partialMock(\App\Modules\Pokedex\Services\PokeApiPokemonGameDataImporter::class, function ($mock) {
        $mock->shouldReceive('import')->times(3)->andReturn(true);
    });

    $this->artisan('pokemon:import-version-group', [
        'slug' => 'scarlet-violet',
        '--ogerpon-mask-forms' => true,
    ])
        ->expectsOutputToContain('Importing 3 species')
        ->assertExitCode(0);
});
