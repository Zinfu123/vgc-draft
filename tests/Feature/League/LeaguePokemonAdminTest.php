<?php

use App\Models\User;
use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\League\Models\LeaguePokemonTemplate;
use App\Modules\League\Models\LeaguePokemonTemplateRow;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Models\VersionGroup;
use App\Modules\Teams\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createLeagueWithAdmin(): array
{
    $owner = User::factory()->create();
    $league = League::query()->create([
        'name' => 'LP Test',
        'status' => \App\Modules\League\Enums\LeagueStatus::Registration->value,
        'league_owner' => $owner->id,
        'maximum_teams' => 8,
    ]);

    return [$league, $owner];
}

function seedDexAndTemplate(): LeaguePokemonTemplate
{
    $a = Pokedex::query()->create([
        'nationaldex_id' => 10,
        'name' => 'Caterpie',
        'type1' => 'Bug',
        'type2' => null,
        'sprite_url' => null,
    ]);
    $b = Pokedex::query()->create([
        'nationaldex_id' => 11,
        'name' => 'Metapod',
        'type1' => 'Bug',
        'type2' => null,
        'sprite_url' => null,
    ]);

    $tpl = LeaguePokemonTemplate::query()->create([
        'name' => 'Bug duo',
        'slug' => 'bug-duo',
        'description' => null,
        'version_group_id' => VersionGroup::query()->firstOrFail()->id,
    ]);
    LeaguePokemonTemplateRow::query()->create([
        'league_pokemon_template_id' => $tpl->id,
        'pokedex_id' => $a->id,
        'cost' => 1,
    ]);
    LeaguePokemonTemplateRow::query()->create([
        'league_pokemon_template_id' => $tpl->id,
        'pokedex_id' => $b->id,
        'cost' => 2,
    ]);

    return $tpl;
}

it('forbids guests from opening the pokemon pool admin page', function () {
    [$league] = createLeagueWithAdmin();

    $this->get(route('leagues.admin.pokemon-pool', ['league' => $league->id]))
        ->assertRedirect();
});

it('allows a team coach with admin_flag to open the pokemon pool admin page', function () {
    [$league] = createLeagueWithAdmin();
    $adminCoach = User::factory()->create();
    Team::query()->create([
        'league_id' => $league->id,
        'user_id' => $adminCoach->id,
        'name' => 'Admins',
        'pick_position' => 1,
        'draft_points' => 50,
        'seed' => 0,
        'pool_id' => null,
        'admin_flag' => 1,
    ]);

    $this->actingAs($adminCoach)
        ->get(route('leagues.admin.pokemon-pool', ['league' => $league->id]))
        ->assertOk();
});

it('forbids coaches without admin flag from opening the pokemon pool admin page', function () {
    [$league] = createLeagueWithAdmin();
    $coach = User::factory()->create();
    Team::query()->create([
        'league_id' => $league->id,
        'user_id' => $coach->id,
        'name' => 'T',
        'pick_position' => 1,
        'draft_points' => 50,
        'seed' => 0,
        'pool_id' => null,
        'admin_flag' => 0,
    ]);

    $this->actingAs($coach)
        ->get(route('leagues.admin.pokemon-pool', ['league' => $league->id]))
        ->assertForbidden();
});

it('allows the league owner to apply a template to an empty pool', function () {
    [$league, $owner] = createLeagueWithAdmin();
    $tpl = seedDexAndTemplate();

    $this->actingAs($owner)
        ->post(route('leagues.admin.pokemon-templates.apply', ['league' => $league->id, 'template' => $tpl->id]), [])
        ->assertRedirect(route('leagues.admin.pokemon-pool', ['league' => $league->id]));

    expect(LeaguePokemon::query()->where('league_id', $league->id)->count())->toBe(2);
});

it('requires confirmation when replacing an existing pool', function () {
    [$league, $owner] = createLeagueWithAdmin();
    $tpl = seedDexAndTemplate();
    $dex = Pokedex::query()->where('nationaldex_id', 10)->firstOrFail();

    LeaguePokemon::query()->create([
        'league_id' => $league->id,
        'pokedex_id' => $dex->id,
        'name' => $dex->name,
        'cost' => 99,
    ]);

    $this->actingAs($owner)
        ->post(route('leagues.admin.pokemon-templates.apply', ['league' => $league->id, 'template' => $tpl->id]), [])
        ->assertSessionHasErrors('confirm_replace');
});

it('blocks template swap when a pool row is drafted', function () {
    [$league, $owner] = createLeagueWithAdmin();
    $tpl = seedDexAndTemplate();
    $dex = Pokedex::query()->where('nationaldex_id', 10)->firstOrFail();

    LeaguePokemon::query()->create([
        'league_id' => $league->id,
        'pokedex_id' => $dex->id,
        'name' => $dex->name,
        'cost' => 99,
        'is_drafted' => true,
    ]);

    $this->actingAs($owner)
        ->post(route('leagues.admin.pokemon-templates.apply', ['league' => $league->id, 'template' => $tpl->id]), [
            'confirm_replace' => true,
        ])
        ->assertSessionHasErrors('template');
});

it('returns template preview json for admins', function () {
    [$league, $owner] = createLeagueWithAdmin();
    $tpl = seedDexAndTemplate();

    $this->actingAs($owner)
        ->getJson(route('leagues.admin.pokemon-templates.preview', ['league' => $league->id, 'template' => $tpl->id]))
        ->assertOk()
        ->assertJsonPath('total', 2);
});

it('updates league pokemon cost for an admin', function () {
    [$league, $owner] = createLeagueWithAdmin();
    seedDexAndTemplate();
    $dex = Pokedex::query()->where('nationaldex_id', 10)->firstOrFail();
    $lp = LeaguePokemon::query()->create([
        'league_id' => $league->id,
        'pokedex_id' => $dex->id,
        'name' => $dex->name,
        'cost' => 5,
    ]);

    $this->actingAs($owner)
        ->patch(route('leagues.admin.pokemon-pool.update', ['league' => $league->id, 'leaguePokemon' => $lp->id]), [
            'cost' => 42,
        ])
        ->assertRedirect();

    expect((int) $lp->fresh()->cost)->toBe(42);
});

it('forbids csv import for non-admins', function () {
    [$league] = createLeagueWithAdmin();
    $coach = User::factory()->create();
    Team::query()->create([
        'league_id' => $league->id,
        'user_id' => $coach->id,
        'name' => 'T',
        'pick_position' => 1,
        'draft_points' => 50,
        'seed' => 0,
        'pool_id' => null,
        'admin_flag' => 0,
    ]);

    $csv = tmpfile();
    fwrite($csv, "10,1\n");
    $meta = stream_get_meta_data($csv);
    $path = $meta['uri'];

    $file = new \Illuminate\Http\UploadedFile($path, 't.csv', 'text/csv', null, true);

    $this->actingAs($coach)
        ->post(route('leagues.admin.pokemon-pool.import-csv', ['league' => $league->id]), [
            'csv_file' => $file,
        ])
        ->assertForbidden();
});

it('forbids legacy league pokemon create for non-admins', function () {
    [$league] = createLeagueWithAdmin();
    $coach = User::factory()->create();

    $csv = tmpfile();
    fwrite($csv, "10,1\n");
    $meta = stream_get_meta_data($csv);
    $path = $meta['uri'];
    $file = new \Illuminate\Http\UploadedFile($path, 't.csv', 'text/csv', null, true);

    $this->actingAs($coach)
        ->post(route('leagues.pokemon.create'), [
            'league_id' => $league->id,
            'csv_file' => $file,
        ])
        ->assertForbidden();
});
