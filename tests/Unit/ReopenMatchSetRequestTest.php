<?php

use App\Http\Requests\Match\ReopenMatchSetRequest;

it('parses set id from plain number or match URLs', function (string $input, int $expected) {
    expect(ReopenMatchSetRequest::parseSetIdFromMatchReference($input))->toBe($expected);
})->with([
    'digits' => ['42', 42],
    'digits trimmed' => ['  99  ', 99],
    'match path' => ['https://example.com/match/set/7', 7],
    'relative match path' => ['/match/set/15', 15],
    'set path' => ['https://example.com/set/3', 3],
]);

it('returns null when no set id can be parsed', function (string $input) {
    expect(ReopenMatchSetRequest::parseSetIdFromMatchReference($input))->toBeNull();
})->with([
    'empty' => [''],
    'no id' => ['https://example.com/leagues/1/matches'],
    'letters only' => ['abc'],
]);
