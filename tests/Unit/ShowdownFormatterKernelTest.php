<?php

use App\Kernel\Contracts\ShowdownFormatter;
use App\Kernel\Support\ShowdownFormatHelper;
use App\Kernel\Support\ShowdownFormatterService;

uses(Tests\TestCase::class);

it('resolves ShowdownFormatter from the container', function () {
    $formatter = app(ShowdownFormatter::class);

    expect($formatter)->toBeInstanceOf(ShowdownFormatterService::class)
        ->and($formatter->speciesToMatchKey('Tatsugiri-Stretchy'))->toBe('tatsugiri');
});

it('keeps ShowdownFormatHelper static API in Kernel', function () {
    expect(ShowdownFormatHelper::speciesToMatchKey('Pikachu'))->toBe('pikachu');
});

it('does not import ShowdownFormatHelper from Pokepaste in Pokedex', function () {
    $path = app_path('Modules/Pokedex/Services/PokemonAbilityListResolver.php');
    $contents = file_get_contents($path);

    expect($contents)->not->toContain('App\Modules\Pokepaste\Services\ShowdownFormatHelper');
});
