<?php

use App\Models\User;
use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\League\Models\LeaguePokemonTemplate;
use App\Modules\League\Models\LeaguePokemonTemplateRow;
use App\Modules\Pokedex\Models\Pokedex;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function insertGreninjaRows(): array
{
    $base = Pokedex::query()->create([
        'nationaldex_id' => 658,
        'name' => 'greninja',
        'type1' => 'Water',
        'type2' => 'Dark',
        'sprite_url' => null,
    ]);

    $ash = Pokedex::query()->create([
        'nationaldex_id' => 658.001,
        'name' => 'greninja-ash',
        'type1' => 'Water',
        'type2' => 'Dark',
        'sprite_url' => null,
    ]);

    return ['base' => $base, 'ash' => $ash];
}

it('normalizes template rows from greninja-ash to greninja', function () {
    ['base' => $base, 'ash' => $ash] = insertGreninjaRows();

    $template = LeaguePokemonTemplate::query()->create([
        'name' => 'Test Template',
        'slug' => 'test-template',
        'description' => null,
        'version_group_id' => null,
        'is_published' => true,
    ]);

    LeaguePokemonTemplateRow::query()->create([
        'league_pokemon_template_id' => $template->id,
        'pokedex_id' => $ash->id,
        'cost' => 10,
    ]);

    $this->artisan('league:normalize-draft-pool-pokemon')
        ->assertExitCode(0);

    $row = LeaguePokemonTemplateRow::query()
        ->where('league_pokemon_template_id', $template->id)
        ->firstOrFail();

    expect((int) $row->pokedex_id)->toBe($base->id)
        ->and($row->cost)->toBe(10);
});

it('normalizes league pool rows from greninja-ash to greninja', function () {
    ['base' => $base, 'ash' => $ash] = insertGreninjaRows();

    $owner = User::factory()->create();
    $league = League::query()->create([
        'name' => 'Normalize Test League',
        'status' => \App\Modules\League\Enums\LeagueStatus::Registration->value,
        'league_owner' => $owner->id,
        'maximum_teams' => 8,
    ]);

    LeaguePokemon::query()->create([
        'league_id' => $league->id,
        'pokedex_id' => $ash->id,
        'name' => 'greninja-ash',
        'cost' => 10,
    ]);

    $this->artisan('league:normalize-draft-pool-pokemon')
        ->assertExitCode(0);

    $poolRow = LeaguePokemon::query()
        ->where('league_id', $league->id)
        ->firstOrFail();

    expect((int) $poolRow->pokedex_id)->toBe($base->id)
        ->and($poolRow->name)->toBe('greninja')
        ->and($poolRow->cost)->toBe(10);
});

it('merges duplicate template rows onto the canonical base species', function () {
    ['base' => $base, 'ash' => $ash] = insertGreninjaRows();

    $template = LeaguePokemonTemplate::query()->create([
        'name' => 'Test Template',
        'slug' => 'merge-template',
        'description' => null,
        'version_group_id' => null,
        'is_published' => true,
    ]);

    LeaguePokemonTemplateRow::query()->create([
        'league_pokemon_template_id' => $template->id,
        'pokedex_id' => $base->id,
        'cost' => 3,
    ]);

    LeaguePokemonTemplateRow::query()->create([
        'league_pokemon_template_id' => $template->id,
        'pokedex_id' => $ash->id,
        'cost' => 10,
    ]);

    $this->artisan('league:normalize-draft-pool-pokemon')
        ->assertExitCode(0);

    $rows = LeaguePokemonTemplateRow::query()
        ->where('league_pokemon_template_id', $template->id)
        ->get();

    expect($rows)->toHaveCount(1)
        ->and((int) $rows->first()->pokedex_id)->toBe($base->id)
        ->and($rows->first()->cost)->toBe(10);
});
