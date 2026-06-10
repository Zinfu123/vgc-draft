<?php

use App\Modules\Pokepaste\Services\ShowdownPasteParser;
use App\Modules\Pokepaste\Services\ShowdownReplayDamageParser;

function makeDamageParser(): ShowdownReplayDamageParser
{
    return new ShowdownReplayDamageParser(new ShowdownPasteParser);
}

it('credits direct move damage to the active attacker', function () {
    $log = implode("\n", [
        '|switch|p1a: Chien-Pao|Chien-Pao, L50|270/270',
        '|switch|p2a: Garchomp|Garchomp, L50|300/300',
        '|move|p1a: Chien-Pao|Ice Fang|p2a: Garchomp',
        '|-damage|p2a: Garchomp|150/300',
    ]);

    $result = makeDamageParser()->parse($log);

    expect($result['p1'])->toBe(['Chien-Pao' => 150])
        ->and($result['p2'])->toBe([]);
});

it('credits damage to p2 attacker when p2 deals damage', function () {
    $log = implode("\n", [
        '|switch|p1a: Amoonguss|Amoonguss, L50|200/200',
        '|switch|p2a: Incineroar|Incineroar, L50|250/250',
        '|move|p2a: Incineroar|Flare Blitz|p1a: Amoonguss',
        '|-damage|p1a: Amoonguss|60/200',
    ]);

    $result = makeDamageParser()->parse($log);

    expect($result['p1'])->toBe([])
        ->and($result['p2'])->toBe(['Incineroar' => 140]);
});

it('excludes indirect residual damage from from-tagged events', function () {
    $log = implode("\n", [
        '|switch|p1a: Chien-Pao|Chien-Pao, L50|270/270',
        '|switch|p2a: Garchomp|Garchomp, L50|300/300',
        '|move|p1a: Chien-Pao|Ice Fang|p2a: Garchomp',
        '|-damage|p2a: Garchomp|200/300',
        '|-damage|p2a: Garchomp|188/300|[from] brn',
    ]);

    $result = makeDamageParser()->parse($log);

    // Only the direct 100 damage is credited; the 12 burn damage is not
    expect($result['p1'])->toBe(['Chien-Pao' => 100])
        ->and($result['p2'])->toBe([]);
});

it('accumulates damage from multiple moves by the same attacker', function () {
    $log = implode("\n", [
        '|switch|p1a: Chien-Pao|Chien-Pao, L50|270/270',
        '|switch|p2a: Garchomp|Garchomp, L50|300/300',
        '|move|p1a: Chien-Pao|Ice Fang|p2a: Garchomp',
        '|-damage|p2a: Garchomp|200/300',
        '|move|p1a: Chien-Pao|Ice Fang|p2a: Garchomp',
        '|-damage|p2a: Garchomp|100/300',
    ]);

    $result = makeDamageParser()->parse($log);

    expect($result['p1'])->toBe(['Chien-Pao' => 200]);
});

it('tracks damage from multiple pokemon on p1 side', function () {
    $log = implode("\n", [
        '|switch|p1a: Chien-Pao|Chien-Pao, L50|270/270',
        '|switch|p1b: Rillaboom|Rillaboom, L50|300/300',
        '|switch|p2a: Garchomp|Garchomp, L50|400/400',
        '|move|p1a: Chien-Pao|Ice Fang|p2a: Garchomp',
        '|-damage|p2a: Garchomp|300/400',
        '|move|p1b: Rillaboom|Wood Hammer|p2a: Garchomp',
        '|-damage|p2a: Garchomp|150/400',
    ]);

    $result = makeDamageParser()->parse($log);

    expect($result['p1'])->toBe(['Chien-Pao' => 100, 'Rillaboom' => 150])
        ->and($result['p2'])->toBe([]);
});

it('updates hp correctly after a heal event', function () {
    $log = implode("\n", [
        '|switch|p1a: Chien-Pao|Chien-Pao, L50|270/270',
        '|switch|p2a: Blissey|Blissey, L50|400/400',
        '|move|p1a: Chien-Pao|Ice Fang|p2a: Blissey',
        '|-damage|p2a: Blissey|200/400',
        '|-heal|p2a: Blissey|300/400',
        '|move|p1a: Chien-Pao|Ice Fang|p2a: Blissey',
        '|-damage|p2a: Blissey|100/400',
    ]);

    $result = makeDamageParser()->parse($log);

    // First hit: 400-200=200, second hit after heal (300 hp): 300-100=200
    expect($result['p1'])->toBe(['Chien-Pao' => 400]);
});

it('returns empty arrays when no damage events occur', function () {
    $log = implode("\n", [
        '|switch|p1a: Chien-Pao|Chien-Pao, L50|270/270',
        '|switch|p2a: Garchomp|Garchomp, L50|300/300',
        '|win|Coach1',
    ]);

    $result = makeDamageParser()->parse($log);

    expect($result['p1'])->toBe([])
        ->and($result['p2'])->toBe([]);
});

it('handles slot switches mid-game crediting new slot occupant for damage', function () {
    $log = implode("\n", [
        '|switch|p1a: Chien-Pao|Chien-Pao, L50|270/270',
        '|switch|p2a: Garchomp|Garchomp, L50|300/300',
        '|switch|p1a: Rillaboom|Rillaboom, L50|300/300',
        '|move|p1a: Rillaboom|Wood Hammer|p2a: Garchomp',
        '|-damage|p2a: Garchomp|150/300',
    ]);

    $result = makeDamageParser()->parse($log);

    expect($result['p1'])->toBe(['Rillaboom' => 150])
        ->and($result['p1'])->not->toHaveKey('Chien-Pao');
});
