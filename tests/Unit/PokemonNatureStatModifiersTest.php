<?php

use App\Enums\PokemonNature;

it('adds stat modifiers to frontend nature labels', function () {
    $opts = PokemonNature::optionsForFrontend();
    $jolly = collect($opts)->firstWhere('value', PokemonNature::Jolly->value);
    expect($jolly)->not->toBeNull();
    expect($jolly['label'])->toBe('Jolly (+Spe, -SpA)');
    expect($jolly['export_label'])->toBe('Jolly');
});

it('leaves neutral nature labels without modifiers', function () {
    expect(PokemonNature::Hardy->labelWithStatModifiers())->toBe('Hardy');
});
