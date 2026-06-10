<?php

use App\Kernel\Support\ShowdownFormatHelper;

it('title-cases pokemon display labels', function (string $raw, string $expected) {
    expect(ShowdownFormatHelper::pokemonDisplayLabel($raw))->toBe($expected);
})->with([
    'lowercase species' => ['pikachu', 'Pikachu'],
    'uppercase species' => ['PIKACHU', 'Pikachu'],
    'already title' => ['Pikachu', 'Pikachu'],
    'multiword' => ['iron bundle', 'Iron Bundle'],
    'trims whitespace' => ['  eevee  ', 'Eevee'],
    'empty string' => ['', ''],
]);
