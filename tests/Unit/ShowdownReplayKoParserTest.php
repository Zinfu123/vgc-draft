<?php

use App\Modules\Pokepaste\Services\ShowdownPasteParser;
use App\Modules\Pokepaste\Services\ShowdownReplayKoParser;

function makeKoParser(): ShowdownReplayKoParser
{
    return new ShowdownReplayKoParser(new ShowdownPasteParser);
}

it('credits a ko to the last direct attacker when a pokemon faints', function () {
    $log = implode("\n", [
        '|switch|p1a: Chien-Pao|Chien-Pao, L50|100/100',
        '|switch|p2a: Garchomp|Garchomp, L50|100/100',
        '|move|p1a: Chien-Pao|Ice Fang|p2a: Garchomp',
        '|-damage|p2a: Garchomp|0 fnt',
        '|faint|p2a: Garchomp',
    ]);

    $result = makeKoParser()->parse($log);

    expect($result['p1'])->toBe(['Chien-Pao'])
        ->and($result['p2'])->toBe([])
        ->and($result['p1Deaths'])->toBe([])
        ->and($result['p2Deaths'])->toBe(['Garchomp']);
});

it('credits a ko on the correct side when p2 gets the faint', function () {
    $log = implode("\n", [
        '|switch|p1a: Amoonguss|Amoonguss, L50|100/100',
        '|switch|p2a: Incineroar|Incineroar, L50|100/100',
        '|move|p2a: Incineroar|Flare Blitz|p1a: Amoonguss',
        '|-damage|p1a: Amoonguss|0 fnt',
        '|faint|p1a: Amoonguss',
    ]);

    $result = makeKoParser()->parse($log);

    expect($result['p1'])->toBe([])
        ->and($result['p2'])->toBe(['Incineroar'])
        ->and($result['p1Deaths'])->toBe(['Amoonguss'])
        ->and($result['p2Deaths'])->toBe([]);
});

it('does not credit a ko when the final damage was indirect (burn)', function () {
    $log = implode("\n", [
        '|switch|p1a: Chien-Pao|Chien-Pao, L50|100/100',
        '|switch|p2a: Garchomp|Garchomp, L50|100/100',
        '|move|p1a: Chien-Pao|Ice Fang|p2a: Garchomp',
        '|-damage|p2a: Garchomp|20/100 brn',
        '|-damage|p2a: Garchomp|0 fnt|[from] brn',
        '|faint|p2a: Garchomp',
    ]);

    $result = makeKoParser()->parse($log);

    expect($result['p1'])->toBe([])
        ->and($result['p2'])->toBe([])
        ->and($result['p2Deaths'])->toBe(['Garchomp']);
});

it('does not credit a ko when the final damage was from recoil', function () {
    $log = implode("\n", [
        '|switch|p1a: Chien-Pao|Chien-Pao, L50|100/100',
        '|switch|p2a: Garchomp|Garchomp, L50|100/100',
        '|move|p2a: Garchomp|Double-Edge|p1a: Chien-Pao',
        '|-damage|p2a: Garchomp|0 fnt|[from] recoil|[of] p1a: Chien-Pao',
        '|faint|p2a: Garchomp',
    ]);

    $result = makeKoParser()->parse($log);

    expect($result['p1'])->toBe([])
        ->and($result['p2'])->toBe([])
        ->and($result['p2Deaths'])->toBe(['Garchomp']);
});

it('tracks multiple kos in the same game', function () {
    $log = implode("\n", [
        '|switch|p1a: Chien-Pao|Chien-Pao, L50|100/100',
        '|switch|p1b: Rillaboom|Rillaboom, L50|100/100',
        '|switch|p2a: Garchomp|Garchomp, L50|100/100',
        '|switch|p2b: Tornadus|Tornadus, L50|100/100',
        '|move|p1a: Chien-Pao|Ice Fang|p2a: Garchomp',
        '|-damage|p2a: Garchomp|0 fnt',
        '|faint|p2a: Garchomp',
        '|move|p2b: Tornadus|Hurricane|p1b: Rillaboom',
        '|-damage|p1b: Rillaboom|0 fnt',
        '|faint|p1b: Rillaboom',
    ]);

    $result = makeKoParser()->parse($log);

    expect($result['p1'])->toBe(['Chien-Pao'])
        ->and($result['p2'])->toBe(['Tornadus'])
        ->and($result['p1Deaths'])->toBe(['Rillaboom'])
        ->and($result['p2Deaths'])->toBe(['Garchomp']);
});

it('handles switches mid-game and credits the current slot occupant', function () {
    $log = implode("\n", [
        '|switch|p1a: Chien-Pao|Chien-Pao, L50|100/100',
        '|switch|p2a: Garchomp|Garchomp, L50|100/100',
        '|faint|p1a: Chien-Pao',
        '|switch|p1a: Flutter Mane|Flutter Mane, L50|100/100',
        '|move|p1a: Flutter Mane|Moonblast|p2a: Garchomp',
        '|-damage|p2a: Garchomp|0 fnt',
        '|faint|p2a: Garchomp',
    ]);

    $result = makeKoParser()->parse($log);

    expect($result['p1'])->toBe(['Flutter Mane'])
        ->and($result['p2'])->toBe([])
        ->and($result['p1Deaths'])->toBe(['Chien-Pao'])
        ->and($result['p2Deaths'])->toBe(['Garchomp']);
});

it('returns empty arrays when no faints occur', function () {
    $log = implode("\n", [
        '|switch|p1a: Chien-Pao|Chien-Pao, L50|100/100',
        '|switch|p2a: Garchomp|Garchomp, L50|100/100',
        '|move|p1a: Chien-Pao|Ice Fang|p2a: Garchomp',
        '|-damage|p2a: Garchomp|50/100',
        '|win|Coach1',
    ]);

    $result = makeKoParser()->parse($log);

    expect($result['p1'])->toBe([])
        ->and($result['p2'])->toBe([])
        ->and($result['p1Deaths'])->toBe([])
        ->and($result['p2Deaths'])->toBe([]);
});

it('handles drag events as slot tracking the same as switch', function () {
    $log = implode("\n", [
        '|switch|p1a: Chien-Pao|Chien-Pao, L50|100/100',
        '|switch|p2a: Garchomp|Garchomp, L50|100/100',
        '|drag|p1a: Rillaboom|Rillaboom, L50|100/100',
        '|move|p1a: Rillaboom|Wood Hammer|p2a: Garchomp',
        '|-damage|p2a: Garchomp|0 fnt',
        '|faint|p2a: Garchomp',
    ]);

    $result = makeKoParser()->parse($log);

    expect($result['p1'])->toBe(['Rillaboom'])
        ->and($result['p2'])->toBe([])
        ->and($result['p2Deaths'])->toBe(['Garchomp']);
});
