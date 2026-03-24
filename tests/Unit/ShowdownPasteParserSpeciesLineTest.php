<?php

use App\Modules\Pokepaste\Services\ShowdownFormatHelper;
use App\Modules\Pokepaste\Services\ShowdownPasteParser;

function showdownBlock(string $speciesLine): string
{
    return <<<TXT
{$speciesLine}
Ability: Keen Eye
Level: 50
- Tackle
- Growl
- Scratch
- Ember
TXT;
}

it('uses species inside parentheses for unquoted nicknames', function () {
    $parser = new ShowdownPasteParser;
    $paste = implode("\n\n", array_map(
        fn (int $i) => showdownBlock("Nick Name (PasteMon{$i}) @ Leftovers {$i}"),
        range(1, 6),
    ));

    $result = $parser->parse($paste);
    expect($result['errors'])->toBeEmpty();
    expect($result['blocks'])->toHaveCount(6);
    expect($result['blocks'][0]['species_raw'])->toBe('PasteMon1');
    expect($result['blocks'][2]['species_raw'])->toBe('PasteMon3');
});

it('parses tatsugiri form in parentheses for roster matching', function () {
    $parser = new ShowdownPasteParser;
    $paste = implode("\n\n", array_map(
        fn (int $i) => showdownBlock($i === 1
            ? 'Dinner (Tatsugiri-Stretchy) @ Sitrus Berry'
            : "Nick (PasteMon{$i}) @ Leftovers {$i}"),
        range(1, 6),
    ));

    $result = $parser->parse($paste);
    expect($result['errors'])->toBeEmpty();
    expect($result['blocks'][0]['species_raw'])->toBe('Tatsugiri-Stretchy');
    expect(ShowdownFormatHelper::speciesToMatchKey($result['blocks'][0]['species_raw']))->toBe('tatsugiri');
});

it('parses hyphenated species in parentheses', function () {
    $parser = new ShowdownPasteParser;
    $paste = implode("\n\n", array_map(
        fn (int $i) => showdownBlock($i === 1
            ? 'Sham Wow (Chien-Pao) @ Focus Sash'
            : "Nick (PasteMon{$i}) @ Leftovers {$i}"),
        range(1, 6),
    ));

    $result = $parser->parse($paste);
    expect($result['errors'])->toBeEmpty();
    expect($result['blocks'][0]['species_raw'])->toBe('Chien-Pao');
    expect($result['blocks'][0]['item'])->toBe('Focus Sash');
});

it('parses species with space inside parentheses', function () {
    $parser = new ShowdownPasteParser;
    $paste = implode("\n\n", array_map(
        fn (int $i) => showdownBlock($i === 1
            ? 'Flutta Mayne (Flutter Mane) @ Booster Energy'
            : "Nick (PasteMon{$i}) @ Leftovers {$i}"),
        range(1, 6),
    ));

    $result = $parser->parse($paste);
    expect($result['errors'])->toBeEmpty();
    expect($result['blocks'][0]['species_raw'])->toBe('Flutter Mane');
});

it('still parses plain species without parentheses', function () {
    $parser = new ShowdownPasteParser;
    $paste = implode("\n\n", array_map(
        fn (int $i) => showdownBlock("PasteMon{$i} @ Leftovers {$i}"),
        range(1, 6),
    ));

    $result = $parser->parse($paste);
    expect($result['errors'])->toBeEmpty();
    expect($result['blocks'][0]['species_raw'])->toBe('PasteMon1');
});

it('still parses quoted nickname with parentheses', function () {
    $parser = new ShowdownPasteParser;
    $paste = implode("\n\n", array_map(
        fn (int $i) => showdownBlock($i === 1
            ? '"Quoted Nick" (PasteMon1) @ Leftovers 1'
            : "PasteMon{$i} @ Leftovers {$i}"),
        range(1, 6),
    ));

    $result = $parser->parse($paste);
    expect($result['errors'])->toBeEmpty();
    expect($result['blocks'][0]['species_raw'])->toBe('PasteMon1');
});

it('treats trailing (M) and (F) as gender, not the species name', function () {
    $parser = new ShowdownPasteParser;
    $paste = implode("\n\n", [
        showdownBlock('Tinkaton (F) @ Leftovers 1'),
        showdownBlock('Indeedee (M) @ Leftovers 2'),
        showdownBlock('Indeedee-F (F) @ Leftovers 3'),
        showdownBlock('PasteMon4 @ Leftovers 4'),
        showdownBlock('PasteMon5 @ Leftovers 5'),
        showdownBlock('PasteMon6 @ Leftovers 6'),
    ]);

    $result = $parser->parse($paste);
    expect($result['errors'])->toBeEmpty();
    expect($result['blocks'][0]['species_raw'])->toBe('Tinkaton');
    expect($result['blocks'][1]['species_raw'])->toBe('Indeedee');
    expect($result['blocks'][2]['species_raw'])->toBe('Indeedee-F');
});

it('strips gender suffix from plain species line with optional spaces', function () {
    $parser = new ShowdownPasteParser;
    $paste = implode("\n\n", array_map(
        fn (int $i) => showdownBlock($i === 1
            ? 'Charizard  ( m ) @ Leftovers 1'
            : "PasteMon{$i} @ Leftovers {$i}"),
        range(1, 6),
    ));

    $result = $parser->parse($paste);
    expect($result['errors'])->toBeEmpty();
    expect($result['blocks'][0]['species_raw'])->toBe('Charizard');
});
