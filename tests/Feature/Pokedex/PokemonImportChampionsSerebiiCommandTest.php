<?php

use App\Modules\Pokedex\Models\PokemonGenerationData;
use App\Modules\Pokedex\Models\VersionGroup;
use App\Modules\Pokedex\Services\ChampionsSerebiiImportResult;
use App\Modules\Pokedex\Services\SerebiiChampionsImporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function championsVersionGroup(): VersionGroup
{
    return VersionGroup::query()->where('slug', 'champions-reg-ma')->firstOrFail();
}

function championsPokedexRow(int $nationalDexId = 15): int
{
    return DB::table('pokedex')->insertGetId([
        'nationaldex_id' => $nationalDexId,
        'name' => 'test-pokemon-'.$nationalDexId,
        'type1' => 'Bug',
        'type2' => 'Poison',
        'sprite_url' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('exits with failure when champions-reg-ma version group is missing', function () {
    DB::table('version_groups')->where('slug', 'champions-reg-ma')->delete();

    $this->artisan('pokemon:import-champions-serebii')
        ->expectsOutputToContain('Version group [champions-reg-ma] not found')
        ->assertExitCode(1);
});

it('reports nothing to import when no pokedex rows exist and db is empty', function () {
    DB::table('pokemon_generation_data')->delete();
    DB::table('pokedex')->delete();

    $this->artisan('pokemon:import-champions-serebii')
        ->expectsOutputToContain('Nothing to import')
        ->assertExitCode(0);
});

it('skips rows that already have data when only-missing flag is set', function () {
    $versionGroup = championsVersionGroup();
    $pokedexId = championsPokedexRow(901);

    PokemonGenerationData::factory()->create([
        'pokedex_id' => $pokedexId,
        'version_group_id' => $versionGroup->id,
    ]);

    $this->artisan('pokemon:import-champions-serebii', ['--only-missing' => true, '--id' => $pokedexId])
        ->expectsOutputToContain('Nothing to import')
        ->assertExitCode(0);
});

it('calls the importer service for each pokedex row', function () {
    $versionGroup = championsVersionGroup();
    $pokedexId = championsPokedexRow(902);

    $this->mock(SerebiiChampionsImporter::class, function ($mock) {
        $mock->shouldReceive('import')->once()->andReturn(ChampionsSerebiiImportResult::ok());
    });

    $this->artisan('pokemon:import-champions-serebii', ['--id' => $pokedexId])
        ->expectsOutputToContain('Importing 1 species')
        ->assertExitCode(0);
});

it('respects the --chunk option to limit the number of species processed', function () {
    $versionGroup = championsVersionGroup();

    $pokedexId1 = championsPokedexRow(903);
    $pokedexId2 = championsPokedexRow(904);

    $mock = $this->mock(SerebiiChampionsImporter::class, function ($mock) {
        $mock->shouldReceive('import')->once()->andReturn(ChampionsSerebiiImportResult::ok());
    });

    $this->artisan('pokemon:import-champions-serebii', ['--chunk' => 1])
        ->expectsOutputToContain('max 1 this run')
        ->assertExitCode(0);
});

it('shows finished message after successful run', function () {
    $versionGroup = championsVersionGroup();
    $pokedexId = championsPokedexRow(905);

    $this->mock(SerebiiChampionsImporter::class, function ($mock) {
        $mock->shouldReceive('import')->once()->andReturn(ChampionsSerebiiImportResult::ok());
    });

    $this->artisan('pokemon:import-champions-serebii', ['--id' => $pokedexId])
        ->expectsOutputToContain('Import finished')
        ->assertExitCode(0);
});

it('imports only champions roster species when --roster-only is set', function () {
    championsVersionGroup();

    $html = file_get_contents(base_path('tests/Fixtures/Serebii/champions_available_roster_min.html'));
    Http::fake(function (\Illuminate\Http\Client\Request $request) use ($html) {
        return str_contains($request->url(), 'pokemonchampions/pokemon.shtml')
            ? Http::response($html, 200, ['Content-Type' => 'text/html'])
            : Http::response('unmocked', 404);
    });

    $partial = \Mockery::mock(SerebiiChampionsImporter::class)->makePartial();
    $partial->shouldReceive('import')->twice()->andReturn(ChampionsSerebiiImportResult::ok());
    $this->instance(SerebiiChampionsImporter::class, $partial);

    DB::table('pokedex')->insert([
        [
            'nationaldex_id' => 3,
            'name' => 'venusaur',
            'type1' => 'Grass',
            'type2' => 'Poison',
            'sprite_url' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'nationaldex_id' => 3.001,
            'name' => 'venusaur-mega',
            'type1' => 'Grass',
            'type2' => 'Poison',
            'sprite_url' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $exitCode = \Illuminate\Support\Facades\Artisan::call('pokemon:import-champions-serebii', [
        '--roster-only' => true,
    ]);
    $output = \Illuminate\Support\Facades\Artisan::output();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('Importing 2 species');
    expect($output)->toContain('Champions available roster');
});

it('rejects combining --roster-only with --id', function () {
    championsVersionGroup();

    $this->artisan('pokemon:import-champions-serebii', [
        '--roster-only' => true,
        '--id' => '1',
    ])
        ->expectsOutputToContain('cannot be used together')
        ->assertExitCode(1);
});

it('appends failed imports to the failure log and prints a summary table', function () {
    championsVersionGroup();
    $pokedexId = championsPokedexRow(906);
    $logPath = storage_path('framework/testing/champions-serebii-failures-'.uniqid('', true).'.log');
    if (is_file($logPath)) {
        unlink($logPath);
    }

    $this->mock(SerebiiChampionsImporter::class, function ($mock) {
        $mock->shouldReceive('import')->once()->andReturn(
            ChampionsSerebiiImportResult::failed('Test failure reason', 'https://www.serebii.net/pokedex-champions/foo/')
        );
    });

    $this->artisan('pokemon:import-champions-serebii', [
        '--id' => $pokedexId,
        '--failure-log' => $logPath,
    ])
        ->expectsOutputToContain('Failed or skipped imports')
        ->expectsOutputToContain('Test failure reason')
        ->expectsOutputToContain('Failure log appended to')
        ->assertExitCode(0);

    expect(file_get_contents($logPath))->toContain('Test failure reason');
    expect(file_get_contents($logPath))->toContain('pokedex-champions/foo');
});
