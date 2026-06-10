<?php

use App\Modules\Pokepaste\Services\ShowdownPasteParser;
use App\Modules\Pokepaste\Services\ShowdownReplayTeamPreviewParser;

function minimalReplayLogForPasteMonSpecies(): string
{
    $lines = ['|player|p1|a|', '|player|p2|b|'];
    foreach (range(1, 6) as $i) {
        $lines[] = "|poke|p1|PasteMon{$i}, L50, M|";
    }
    foreach (range(1, 6) as $i) {
        $lines[] = "|poke|p2|OppMon{$i}, L50, M|";
    }

    return implode("\n", $lines);
}

it('parses six species per player from replay log', function () {
    $parser = new ShowdownReplayTeamPreviewParser(new ShowdownPasteParser);
    $result = $parser->parse(minimalReplayLogForPasteMonSpecies());

    expect($result['errors'])->toBeEmpty()
        ->and($result['p1'])->toHaveCount(6)
        ->and($result['p2'])->toHaveCount(6)
        ->and($result['p1'][0])->toBe('PasteMon1')
        ->and($result['p2'][5])->toBe('OppMon6');
});

it('parses nickname parentheses in replay details', function () {
    $log = "|player|p1|x|\n|player|p2|y|\n";
    foreach (range(1, 6) as $i) {
        $log .= "|poke|p1|Nick {$i} (PasteMon{$i}), L50, M|\n";
    }
    foreach (range(1, 6) as $i) {
        $log .= "|poke|p2|OppMon{$i}, L50, F|\n";
    }

    $parser = new ShowdownReplayTeamPreviewParser(new ShowdownPasteParser);
    $result = $parser->parse($log);

    expect($result['errors'])->toBeEmpty()
        ->and($result['p1'][2])->toBe('PasteMon3');
});

it('errors when preview count is wrong', function () {
    $parser = new ShowdownReplayTeamPreviewParser(new ShowdownPasteParser);
    $result = $parser->parse("|player|p1|a|\n|poke|p1|PasteMon1, L50, M|\n");

    expect($result['errors'])->not->toBeEmpty()
        ->and($result['p1'])->toBeEmpty();
});

it('extracts species from replay poke details via ShowdownPasteParser', function () {
    $parser = new ShowdownPasteParser;

    expect($parser->speciesRawFromReplayPokeDetails('Pikachu, L50, M'))->toBe('Pikachu')
        ->and($parser->speciesRawFromReplayPokeDetails('Captain (Pikachu), L50, F'))->toBe('Pikachu');
});
