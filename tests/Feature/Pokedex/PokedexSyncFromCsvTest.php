<?php

use App\Actions\SyncPokedexFromCsvAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('upserts existing pokedex rows and inserts new ones from csv', function () {
    $dir = storage_path('framework/testing');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $path = $dir.'/'.uniqid('pokedex_', true).'.csv';

    $csv = <<<'CSV'
id,nationaldex_id,name,type1,type2,sprite_url,created_at,updated_at
95001,1,bulbasaur-was-wrong,Fire,-,https://example.com/a.png,2025-01-01 00:00:00,2025-01-01 00:00:00
95002,2,ivysaur,Grass,Poison,,2025-01-01 00:00:00,2025-01-01 00:00:00
CSV;
    file_put_contents($path, $csv);

    DB::table('pokedex')->insert([
        'id' => 95001,
        'nationaldex_id' => 1,
        'name' => 'bulbasaur-old',
        'type1' => 'Grass',
        'type2' => 'Poison',
        'sprite_url' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $count = app(SyncPokedexFromCsvAction::class)->handle($path);
    expect($count)->toBe(2);

    $row1 = DB::table('pokedex')->where('id', 95001)->first();
    expect($row1->name)->toBe('bulbasaur-was-wrong');
    expect((float) $row1->nationaldex_id)->toBe(1.0);
    expect($row1->type1)->toBe('Fire');
    expect($row1->type2)->toBeNull();
    expect($row1->sprite_url)->toBe('https://example.com/a.png');

    $row2 = DB::table('pokedex')->where('id', 95002)->first();
    expect($row2)->not->toBeNull();
    expect($row2->name)->toBe('ivysaur');

    @unlink($path);
});

it('does not delete pokedex rows missing from csv', function () {
    $dir = storage_path('framework/testing');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $path = $dir.'/'.uniqid('pokedex_', true).'.csv';

    file_put_contents($path, "id,nationaldex_id,name,type1,type2,sprite_url,created_at,updated_at\n3,3,venusaur,Grass,Poison,,2025-01-01 00:00:00,2025-01-01 00:00:00\n");

    DB::table('pokedex')->insert([
        'id' => 99,
        'nationaldex_id' => 99,
        'name' => 'orphan',
        'type1' => 'Normal',
        'type2' => null,
        'sprite_url' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(SyncPokedexFromCsvAction::class)->handle($path);

    expect(DB::table('pokedex')->where('id', 99)->exists())->toBeTrue();

    @unlink($path);
});

it('runs pokedex:sync-from-csv successfully', function () {
    $dir = storage_path('framework/testing');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $path = $dir.'/'.uniqid('pokedex_', true).'.csv';
    file_put_contents($path, "id,nationaldex_id,name,type1,type2,sprite_url,created_at,updated_at\n5,5,charmeleon,Fire,-,,2025-01-01 00:00:00,2025-01-01 00:00:00\n");

    Artisan::call('pokedex:sync-from-csv', ['--path' => $path]);
    expect(Artisan::output())->toContain('Synced 1');

    @unlink($path);
});
