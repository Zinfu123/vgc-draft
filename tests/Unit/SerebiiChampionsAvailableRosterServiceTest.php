<?php

use App\Modules\Pokedex\Services\SerebiiChampionsAvailableRosterService;
use App\Modules\Pokedex\Services\SerebiiChampionsImporter;

it('parses roster rows and resolves regional, mega, and special slugs', function () {
    $html = file_get_contents(__DIR__.'/../Fixtures/Serebii/champions_available_roster_snippet.html');
    $service = new SerebiiChampionsAvailableRosterService;
    $importer = new SerebiiChampionsImporter;

    $rows = $service->parseRosterRows($html);
    expect($rows)->toHaveCount(9);

    $names = $service->resolveUniquePokedexNamesFromHtml($html, $importer);
    expect($names)->toBe([
        'venusaur',
        'venusaur-mega',
        'charizard-mega-x',
        'charizard-mega-y',
        'raichu-alola',
        'floette-eternal',
        'floette-eternal-mega',
        'tauros-paldea',
        'mr-rime',
    ]);
});
