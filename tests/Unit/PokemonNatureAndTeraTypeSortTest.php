<?php

use App\Enums\PokemonNature;
use App\Enums\PokemonTeraType;

it('sorts nature options alphabetically by label', function () {
    $opts = PokemonNature::optionsForFrontend();
    $labels = array_column($opts, 'label');
    $sorted = $labels;
    sort($sorted, SORT_STRING);
    expect($labels)->toBe($sorted);
});

it('sorts tera type values alphabetically for generation 8', function () {
    $types = PokemonTeraType::allValuesForGeneration(8);
    $sorted = $types;
    sort($sorted, SORT_STRING);
    expect($types)->toBe($sorted);
});

it('sorts tera type values alphabetically for generation 9 including stellar', function () {
    $types = PokemonTeraType::allValuesForGeneration(9);
    $sorted = $types;
    sort($sorted, SORT_STRING);
    expect($types)->toBe($sorted);
    expect($types)->toContain('Stellar');
});
