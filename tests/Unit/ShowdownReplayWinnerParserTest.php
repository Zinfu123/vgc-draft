<?php

use App\Modules\Pokepaste\Services\ShowdownReplayWinnerParser;

it('reads the winner name from a pipe-terminated win line', function () {
    $parser = new ShowdownReplayWinnerParser;
    $result = $parser->parse("|player|p1|a|\n|win|CoachA|\n");

    expect($result['errors'])->toBe([])
        ->and($result['is_tie'])->toBeFalse()
        ->and($result['winner'])->toBe('CoachA');
});

it('reads the winner name from a win line without a trailing pipe', function () {
    $parser = new ShowdownReplayWinnerParser;
    $result = $parser->parse("|win|SomePlayer\n");

    expect($result['winner'])->toBe('SomePlayer');
});

it('uses the last win line when multiple appear', function () {
    $parser = new ShowdownReplayWinnerParser;
    $result = $parser->parse("|win|First|\n|win|Last|\n");

    expect($result['winner'])->toBe('Last');
});
