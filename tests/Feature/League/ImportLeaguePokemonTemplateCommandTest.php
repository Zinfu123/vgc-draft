<?php

use App\Modules\League\Models\LeaguePokemonTemplate;
use App\Modules\League\Models\LeaguePokemonTemplateRow;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Models\VersionGroup;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('imports a template from csv with display name', function () {
    $dex = Pokedex::query()->create([
        'nationaldex_id' => 25,
        'name' => 'Pikachu',
        'type1' => 'Electric',
        'type2' => null,
        'sprite_url' => null,
    ]);

    $path = tempnam(sys_get_temp_dir(), 'tplcsv');
    file_put_contents($path, "nationaldex_id,cost\n25,500\n");

    $this->artisan('league:pokemon-template-import', [
        'path' => $path,
        'name' => 'VGC Sample',
    ])->assertSuccessful();

    $template = LeaguePokemonTemplate::query()->where('slug', 'vgc-sample')->first();
    expect($template)->not->toBeNull()
        ->and($template->name)->toBe('VGC Sample')
        ->and($template->version_group_id)->toBe(VersionGroup::query()->firstOrFail()->id);

    expect(LeaguePokemonTemplateRow::query()->where('league_pokemon_template_id', $template->id)->count())->toBe(1);
    $row = LeaguePokemonTemplateRow::query()->where('league_pokemon_template_id', $template->id)->first();
    expect((int) $row->pokedex_id)->toBe((int) $dex->id)
        ->and((int) $row->cost)->toBe(500);
});

it('refuses duplicate slug without replace', function () {
    Pokedex::query()->create([
        'nationaldex_id' => 1,
        'name' => 'Bulb',
        'type1' => 'Grass',
        'type2' => 'Poison',
        'sprite_url' => null,
    ]);

    $path = tempnam(sys_get_temp_dir(), 'tplcsv');
    file_put_contents($path, "1,10\n");

    $this->artisan('league:pokemon-template-import', [
        'path' => $path,
        'name' => 'First',
        '--slug' => 'same',
    ])->assertSuccessful();

    $this->artisan('league:pokemon-template-import', [
        'path' => $path,
        'name' => 'Second',
        '--slug' => 'same',
    ])->assertFailed();
});

it('replaces rows with --replace', function () {
    Pokedex::query()->create([
        'nationaldex_id' => 2,
        'name' => 'Ivysaur',
        'type1' => 'Grass',
        'type2' => 'Poison',
        'sprite_url' => null,
    ]);
    Pokedex::query()->create([
        'nationaldex_id' => 3,
        'name' => 'Venusaur',
        'type1' => 'Grass',
        'type2' => 'Poison',
        'sprite_url' => null,
    ]);

    $pathA = tempnam(sys_get_temp_dir(), 'tplcsv');
    file_put_contents($pathA, "2,20\n");

    $this->artisan('league:pokemon-template-import', [
        'path' => $pathA,
        'name' => 'T',
        '--slug' => 'rep',
    ])->assertSuccessful();

    $pathB = tempnam(sys_get_temp_dir(), 'tplcsv');
    file_put_contents($pathB, "3,30\n");

    $this->artisan('league:pokemon-template-import', [
        'path' => $pathB,
        'name' => 'T2',
        '--slug' => 'rep',
        '--replace' => true,
    ])->assertSuccessful();

    $template = LeaguePokemonTemplate::query()->where('slug', 'rep')->first();
    expect(LeaguePokemonTemplateRow::query()->where('league_pokemon_template_id', $template->id)->count())->toBe(1)
        ->and(LeaguePokemonTemplateRow::query()->where('league_pokemon_template_id', $template->id)->value('cost'))->toBe(30);
});
