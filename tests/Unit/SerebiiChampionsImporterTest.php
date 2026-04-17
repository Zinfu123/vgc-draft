<?php

use App\Modules\Pokedex\Services\SerebiiChampionsImporter;

function beedrillHtml(): string
{
    return (string) file_get_contents(__DIR__.'/../Fixtures/Serebii/beedrill.html');
}

function venusaurHtml(): string
{
    return (string) file_get_contents(__DIR__.'/../Fixtures/Serebii/venusaur.html');
}

// -----------------------------------------------------------------
// URL slug derivation
// -----------------------------------------------------------------

it('converts a simple base name to a serebii slug', function () {
    $importer = new SerebiiChampionsImporter;

    expect($importer->toSerebiiSlug('chandelure'))->toBe('chandelure');
});

it('strips -mega suffix for a mega form', function () {
    $importer = new SerebiiChampionsImporter;

    expect($importer->toSerebiiSlug('chandelure-mega'))->toBe('chandelure');
    expect($importer->toSerebiiSlug('venusaur-mega'))->toBe('venusaur');
    expect($importer->toSerebiiSlug('greninja-mega'))->toBe('greninja');
});

it('strips -mega-x and -mega-y suffixes for charizard forms', function () {
    $importer = new SerebiiChampionsImporter;

    expect($importer->toSerebiiSlug('charizard-mega-x'))->toBe('charizard');
    expect($importer->toSerebiiSlug('charizard-mega-y'))->toBe('charizard');
});

it('strips -eternal-mega suffix for floette', function () {
    $importer = new SerebiiChampionsImporter;

    expect($importer->toSerebiiSlug('floette-eternal-mega'))->toBe('floette');
});

it('converts mr-mime to mr.mime serebii slug', function () {
    $importer = new SerebiiChampionsImporter;

    expect($importer->toSerebiiSlug('mr-mime'))->toBe('mr.mime');
});

it('converts mr-rime to mr.rime serebii slug', function () {
    $importer = new SerebiiChampionsImporter;

    expect($importer->toSerebiiSlug('mr-rime'))->toBe('mr.rime');
});

it('preserves kommo-o slug with the dash', function () {
    $importer = new SerebiiChampionsImporter;

    expect($importer->toSerebiiSlug('kommo-o'))->toBe('kommo-o');
});

it('maps regional form names to the base species Serebii path', function () {
    $importer = new SerebiiChampionsImporter;

    expect($importer->toSerebiiSlug('typhlosion-hisui'))->toBe('typhlosion');
    expect($importer->toSerebiiSlug('arcanine-hisui'))->toBe('arcanine');
    expect($importer->toSerebiiSlug('raichu-alola'))->toBe('raichu');
    expect($importer->toSerebiiSlug('slowbro-galar'))->toBe('slowbro');
    expect($importer->toSerebiiSlug('tauros-paldea'))->toBe('tauros');
});

it('detects regional form suffixes after stripping mega from the name', function () {
    $importer = new SerebiiChampionsImporter;

    expect($importer->regionalFormFromPokedexName('typhlosion-hisui'))->toBe('hisui');
    expect($importer->regionalFormFromPokedexName('raichu-alola'))->toBe('alola');
    expect($importer->regionalFormFromPokedexName('venusaur'))->toBeNull();
});

// -----------------------------------------------------------------
// Move parsing
// -----------------------------------------------------------------

it('parses move names from beedrill fixture', function () {
    $importer = new SerebiiChampionsImporter;
    $moves = $importer->parseMoves(beedrillHtml());

    expect($moves)->toContain('Poison Jab');
    expect($moves)->toContain('X-Scissor');
    expect($moves)->toContain('Pin Missile');
    expect($moves)->toHaveCount(3);
});

it('deduplicates move names', function () {
    $html = '<html><body>'
        .'<a href="/attackdex-champions/tackle.shtml">Tackle</a>'
        .'<a href="/attackdex-champions/tackle.shtml">Tackle</a>'
        .'</body></html>';

    $importer = new SerebiiChampionsImporter;
    $moves = $importer->parseMoves($html);

    expect($moves)->toHaveCount(1);
    expect($moves[0])->toBe('Tackle');
});

it('returns empty array when no move links exist', function () {
    $importer = new SerebiiChampionsImporter;

    expect($importer->parseMoves('<html><body><p>No moves here</p></body></html>'))->toBe([]);
});

it('ignores attackdex-champions index and directory links without a move file', function () {
    $html = '<html><body>'
        .'<a href="https://www.serebii.net/attackdex-champions/">Champions Attackdex</a>'
        .'<a href="/attackdex-champions/">Attackdex</a>'
        .'<a href="https://www.serebii.net/attackdex-champions/tackle.shtml">Tackle</a>'
        .'</body></html>';

    $importer = new SerebiiChampionsImporter;
    $moves = $importer->parseMoves($html);

    expect($moves)->toHaveCount(1);
    expect($moves[0])->toBe('Tackle');
});

it('parses move names from venusaur fixture', function () {
    $importer = new SerebiiChampionsImporter;
    $moves = $importer->parseMoves(venusaurHtml());

    expect($moves)->toContain('Solar Beam');
    expect($moves)->toContain('Earth Power');
    expect($moves)->toContain('Sludge Bomb');
    expect($moves)->toContain('Energy Ball');
});

it('scopes moves to the Hisuian standard moves table when the anchor is present', function () {
    $html = <<<'HTML'
<!DOCTYPE html><html><body>
<table class="dextable"><tr><td class="fooevo"><h3><a name="standardlevel"></a>Standard Moves</h3></td></tr>
<tr><td><a href="/attackdex-champions/tackle.shtml">Tackle</a></td></tr>
</table>
<table class="dextable"><tr><td class="fooevo"><h3><a name="hisuianlevel"></a>Hisuian Form Standard Moves</h3></td></tr>
<tr><td><a href="/attackdex-champions/ember.shtml">Ember</a></td></tr>
</table>
</body></html>
HTML;

    $importer = new SerebiiChampionsImporter;

    expect($importer->parseMoves($html, 'standardlevel'))->toBe(['Tackle']);
    expect($importer->parseMoves($html, 'hisuianlevel'))->toBe(['Ember']);
});

it('parses Hisuian stats and abilities from the regional sections', function () {
    $html = <<<'HTML'
<!DOCTYPE html><html><body>
<table class="dextable"><tr><td colspan="8" class="fooevo"><h2>Stats</h2></td></tr>
<tr><td class="fooinfo">Base Stats - Total: 534</td>
<td class="fooinfo">78</td><td class="fooinfo">84</td><td class="fooinfo">78</td>
<td class="fooinfo">109</td><td class="fooinfo">85</td><td class="fooinfo">100</td></tr>
</table>
<table class="dextable"><tr><td colspan="8" class="fooevo"><h2>Stats - Hisuian Typhlosion</h2></td></tr>
<tr><td class="fooinfo">Base Stats - Total: 534</td>
<td class="fooinfo">73</td><td class="fooinfo">84</td><td class="fooinfo">78</td>
<td class="fooinfo">119</td><td class="fooinfo">85</td><td class="fooinfo">95</td></tr>
</table>
<table class="dextable"><tr><td class="fooinfo" colspan="5">
<b>Hisuian Form Abilities</b>:<br />
<a href="/abilitydex/blaze.shtml"><b>Blaze</b></a>: desc<br />
<a href="/abilitydex/frisk.shtml"><b>Frisk</b></a>: desc
</td></tr></table>
</body></html>
HTML;

    $importer = new SerebiiChampionsImporter;

    expect($importer->parseBaseStats($html, 'hisui'))->toBe([
        'hp' => 73,
        'atk' => 84,
        'def' => 78,
        'spa' => 119,
        'spd' => 85,
        'spe' => 95,
    ]);

    expect($importer->parseRegionalFormAbilities($html, 'hisui'))->toBe(['Blaze', 'Frisk']);
});

// -----------------------------------------------------------------
// Base stats parsing
// -----------------------------------------------------------------

it('parses base stats from beedrill fixture', function () {
    $importer = new SerebiiChampionsImporter;
    $stats = $importer->parseBaseStats(beedrillHtml());

    expect($stats)->not->toBeNull();
    expect($stats['hp'])->toBe(65);
    expect($stats['atk'])->toBe(90);
    expect($stats['def'])->toBe(40);
    expect($stats['spa'])->toBe(45);
    expect($stats['spd'])->toBe(80);
    expect($stats['spe'])->toBe(75);
});

it('parses base stats from venusaur fixture', function () {
    $importer = new SerebiiChampionsImporter;
    $stats = $importer->parseBaseStats(venusaurHtml());

    expect($stats)->not->toBeNull();
    expect($stats['hp'])->toBe(80);
    expect($stats['atk'])->toBe(82);
    expect($stats['def'])->toBe(83);
    expect($stats['spa'])->toBe(100);
    expect($stats['spd'])->toBe(100);
    expect($stats['spe'])->toBe(80);
});

it('returns null when no stats table is present', function () {
    $importer = new SerebiiChampionsImporter;

    expect($importer->parseBaseStats('<html><body><p>No stats</p></body></html>'))->toBeNull();
});

// -----------------------------------------------------------------
// Mega stats parsing
// -----------------------------------------------------------------

it('parses mega beedrill stats (last stats block)', function () {
    $importer = new SerebiiChampionsImporter;
    $stats = $importer->parseMegaStats(beedrillHtml(), 'beedrill-mega');

    expect($stats)->not->toBeNull();
    expect($stats['hp'])->toBe(65);
    expect($stats['atk'])->toBe(150);
    expect($stats['def'])->toBe(40);
    expect($stats['spa'])->toBe(15);
    expect($stats['spd'])->toBe(80);
    expect($stats['spe'])->toBe(145);
});

it('parses mega venusaur stats (last stats block)', function () {
    $importer = new SerebiiChampionsImporter;
    $stats = $importer->parseMegaStats(venusaurHtml(), 'venusaur-mega');

    expect($stats)->not->toBeNull();
    expect($stats['hp'])->toBe(80);
    expect($stats['atk'])->toBe(100);
    expect($stats['def'])->toBe(123);
    expect($stats['spa'])->toBe(122);
    expect($stats['spd'])->toBe(120);
    expect($stats['spe'])->toBe(80);
});

it('parses mega floette-eternal-mega stats using the Mega Floette block on Serebii', function () {
    $html = <<<'HTML'
<!DOCTYPE html><html><body>
<h3>Mega Floette</h3>
<table class="dextable"><tr><td class="fooinfo">Base Stats - Total: 551</td>
<td class="fooinfo">1</td><td class="fooinfo">2</td><td class="fooinfo">3</td><td class="fooinfo">4</td><td class="fooinfo">5</td><td class="fooinfo">6</td></tr></table>
<table class="dextable"><tr><td class="fooinfo">Base Stats - Total: 651</td>
<td class="fooinfo">74</td><td class="fooinfo">65</td><td class="fooinfo">67</td><td class="fooinfo">92</td><td class="fooinfo">128</td><td class="fooinfo">125</td></tr></table>
</body></html>
HTML;

    $importer = new SerebiiChampionsImporter;

    expect($importer->parseMegaStats($html, 'floette-eternal-mega'))->toBe([
        'hp' => 74,
        'atk' => 65,
        'def' => 67,
        'spa' => 92,
        'spd' => 128,
        'spe' => 125,
    ]);
});

it('scopes Eternal Floette moves by h3 for the floette-eternal-mega import path', function () {
    $html = <<<'HTML'
<!DOCTYPE html><html><body>
<table class="dextable"><tr><td class="fooevo"><h3> Standard Moves - Eternal Floette</h3></td></tr>
<tr><td class="fooinfo"><a href="/attackdex-champions/moonblast.shtml">Moonblast</a></td></tr></table>
<table class="dextable"><tr><td><h3>Other</h3></td></tr>
<tr><td><a href="/attackdex-champions/tackle.shtml">Tackle</a></td></tr></table>
</body></html>
HTML;

    $importer = new SerebiiChampionsImporter;
    $method = new ReflectionMethod(SerebiiChampionsImporter::class, 'parseMovesInDextableH3ContainingAll');
    $moves = $method->invoke($importer, $html, 'Standard Moves', 'Eternal Floette');

    expect($moves)->toBe(['Moonblast']);
});

// -----------------------------------------------------------------
// Ability parsing
// -----------------------------------------------------------------

it('parses base abilities from venusaur fixture', function () {
    $importer = new SerebiiChampionsImporter;
    $abilities = $importer->parseBaseAbilities(venusaurHtml());

    expect($abilities)->toBe(['Overgrow', 'Chlorophyll']);
});

it('parses mega beedrill ability from fixture', function () {
    $importer = new SerebiiChampionsImporter;
    $abilities = $importer->parseMegaAbilities(beedrillHtml(), 'beedrill-mega');

    expect($abilities)->toBe(['Adaptability']);
});

it('parses mega venusaur ability (Thick Fat) from fixture', function () {
    $importer = new SerebiiChampionsImporter;
    $abilities = $importer->parseMegaAbilities(venusaurHtml(), 'venusaur-mega');

    expect($abilities)->toBe(['Thick Fat']);
});

it('ignores abilitydex index links and only reads the first Abilities cell for base', function () {
    $html = <<<'HTML'
<!DOCTYPE html><html><body>
<table><tr><td><b>Abilities:</b>
<a href="https://www.serebii.net/abilitydex/">Abilitydex</a>
<a href="https://www.serebii.net/abilitydex/overgrow.shtml">Overgrow</a>
</td></tr></table>
<table><tr><td><b>Abilities:</b>
<a href="https://www.serebii.net/abilitydex/adaptability.shtml">Adaptability</a>
</td></tr></table>
</body></html>
HTML;

    $importer = new SerebiiChampionsImporter;
    expect($importer->parseBaseAbilities($html))->toBe(['Overgrow']);
});

it('parses mega charizard x vs y from separate sections', function () {
    $html = <<<'HTML'
<!DOCTYPE html><html><body>
<table><tr><td><b>Abilities:</b>
<a href="https://www.serebii.net/abilitydex/blaze.shtml">Blaze</a>
</td></tr></table>
<h2>Mega Charizard X</h2>
<table><tr><td><b>Abilities:</b>
<a href="https://www.serebii.net/abilitydex/toughclaws.shtml">Tough Claws</a>
</td></tr></table>
<h2>Mega Charizard Y</h2>
<table><tr><td><b>Abilities:</b>
<a href="https://www.serebii.net/abilitydex/drought.shtml">Drought</a>
</td></tr></table>
</body></html>
HTML;

    $importer = new SerebiiChampionsImporter;
    expect($importer->parseMegaAbilities($html, 'charizard-mega-x'))->toBe(['Tough Claws']);
    expect($importer->parseMegaAbilities($html, 'charizard-mega-y'))->toBe(['Drought']);
});
