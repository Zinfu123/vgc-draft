<?php

use App\Models\User;
use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\League\Models\LeaguePokemonTemplate;
use App\Modules\League\Models\LeaguePokemonTemplateRow;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Models\VersionGroup;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('creates a template from a league pokemon pool', function () {
    $owner = User::factory()->create();
    $league = League::query()->create([
        'name' => 'Source League',
        'status' => \App\Modules\League\Enums\LeagueStatus::Registration->value,
        'league_owner' => $owner->id,
        'maximum_teams' => 8,
    ]);

    $dex = Pokedex::query()->create([
        'nationaldex_id' => 25,
        'name' => 'Pikachu',
        'type1' => 'Electric',
        'type2' => null,
        'sprite_url' => null,
    ]);

    LeaguePokemon::query()->create([
        'league_id' => $league->id,
        'pokedex_id' => $dex->id,
        'name' => $dex->name,
        'cost' => 450,
    ]);

    $this->artisan('league:pokemon-template-from-league', [
        'league' => (string) $league->id,
        'name' => 'Copied pool',
        '--slug' => 'copied-pool',
    ])->assertSuccessful();

    $template = LeaguePokemonTemplate::query()->where('slug', 'copied-pool')->firstOrFail();
    expect($template->version_group_id)->toBe(VersionGroup::query()->firstOrFail()->id);
    expect(LeaguePokemonTemplateRow::query()->where('league_pokemon_template_id', $template->id)->count())->toBe(1);
    $row = LeaguePokemonTemplateRow::query()->where('league_pokemon_template_id', $template->id)->firstOrFail();
    expect((int) $row->pokedex_id)->toBe((int) $dex->id)
        ->and((int) $row->cost)->toBe(450);
});
