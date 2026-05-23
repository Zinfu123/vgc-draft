<?php

use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Services\DraftPoolPokedexResolver;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('resolves greninja-ash to the base greninja pokedex row', function () {
    $base = Pokedex::query()->create([
        'nationaldex_id' => 658,
        'name' => 'greninja',
        'type1' => 'Water',
        'type2' => 'Dark',
        'sprite_url' => null,
    ]);

    $ash = Pokedex::query()->create([
        'nationaldex_id' => 658.001,
        'name' => 'greninja-ash',
        'type1' => 'Water',
        'type2' => 'Dark',
        'sprite_url' => null,
    ]);

    $resolver = new DraftPoolPokedexResolver;

    expect($resolver->canonicalName('greninja-ash'))->toBe('greninja')
        ->and($resolver->resolveByName('greninja-ash')?->id)->toBe($base->id)
        ->and($resolver->resolvePokedex($ash)?->id)->toBe($base->id)
        ->and($resolver->resolveByNationaldexId(658.001)?->id)->toBe($base->id);
});

it('prefers the whole-number nationaldex row when duplicate base names exist', function () {
    $base = Pokedex::query()->create([
        'nationaldex_id' => 658,
        'name' => 'greninja',
        'type1' => 'Water',
        'type2' => 'Dark',
        'sprite_url' => null,
    ]);

    Pokedex::query()->create([
        'nationaldex_id' => 658.002,
        'name' => 'greninja',
        'type1' => 'Water',
        'type2' => 'Dark',
        'sprite_url' => null,
    ]);

    $resolver = new DraftPoolPokedexResolver;

    expect($resolver->resolveByName('greninja')?->id)->toBe($base->id);
});

it('treats mixed-case base species names as canonical', function () {
    $pikachu = Pokedex::query()->create([
        'nationaldex_id' => 25,
        'name' => 'Pikachu',
        'type1' => 'Electric',
        'type2' => null,
        'sprite_url' => null,
    ]);

    $resolver = new DraftPoolPokedexResolver;

    expect($resolver->resolveByNationaldexId(25)?->id)->toBe($pikachu->id);
});
