<?php

use App\Enums\PokemonNature;

it('applies adamant atk up and spa down', function () {
    $n = PokemonNature::Adamant;
    expect($n->statMultiplier('atk'))->toBe(1.1)
        ->and($n->statMultiplier('spa'))->toBe(0.9)
        ->and($n->statMultiplier('def'))->toBe(1.0);
});

it('has neutral hardy stats', function () {
    $n = PokemonNature::Hardy;
    expect($n->statMultiplier('atk'))->toBe(1.0)
        ->and($n->statMultiplier('spa'))->toBe(1.0);
});
