<?php

use App\Modules\Pokepaste\Services\ShowdownFormatHelper;

it('collapses showdown tatsugiri forms to a single match key', function (string $raw, string $expectedKey) {
    expect(ShowdownFormatHelper::speciesToMatchKey($raw))->toBe($expectedKey);
})->with([
    'base name' => ['Tatsugiri', 'tatsugiri'],
    'stretchy form' => ['Tatsugiri-Stretchy', 'tatsugiri'],
    'curly form' => ['Tatsugiri-Curly', 'tatsugiri'],
    'droopy form' => ['Tatsugiri-Droopy', 'tatsugiri'],
    'lowercase stretchy' => ['tatsugiri-stretchy', 'tatsugiri'],
]);

it('does not change unrelated species keys', function () {
    expect(ShowdownFormatHelper::speciesToMatchKey('Pikachu'))->toBe('pikachu');
    expect(ShowdownFormatHelper::speciesToMatchKey('Chien-Pao'))->toBe('chien-pao');
});
