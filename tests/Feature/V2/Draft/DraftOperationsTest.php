<?php

use App\Models\User;
use App\Modules\Draft\Models\DraftWishlistItem;
use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Teams\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createV2DraftLeague(User $user): array
{
    $league = League::create([
        'name' => 'V2 Draft League',
        'status' => \App\Modules\League\Enums\LeagueStatus::Staging->value,
        'league_owner' => $user->id,
        'maximum_teams' => 10,
    ]);

    $team = Team::create([
        'name' => 'Squad',
        'league_id' => $league->id,
        'user_id' => $user->id,
        'admin_flag' => 0,
        'pick_position' => 1,
        'draft_points' => 100,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
    ]);

    $dex = Pokedex::query()->create([
        'nationaldex_id' => 1,
        'name' => 'Bulbasaur',
        'type1' => 'Grass',
        'type2' => 'Poison',
        'sprite_url' => null,
    ]);

    $leaguePokemon = LeaguePokemon::query()->create([
        'league_id' => $league->id,
        'pokedex_id' => $dex->id,
        'name' => $dex->name,
        'cost' => 10,
    ]);

    return [$league, $team, $leaguePokemon];
}

it('renders v2 draft detail page before the draft starts', function () {
    $user = User::factory()->create();
    [$league] = createV2DraftLeague($user);

    $this->actingAs($user)
        ->get("/v2/draft/{$league->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('draft/DraftDetail')
            ->where('draft', null)
            ->where('wishlist_league_pokemon_ids', [])
            ->has('pokemon'));
});

it('adds a wishlist item via v2 preview route', function () {
    $user = User::factory()->create();
    [$league, $team, $leaguePokemon] = createV2DraftLeague($user);

    $this->actingAs($user)
        ->post('/v2/draft/wishlist/toggle', [
            'league_id' => $league->id,
            'league_pokemon_id' => $leaguePokemon->id,
        ])
        ->assertRedirect(route('v2.draft.detail', ['league_id' => $league->id]));

    expect(DraftWishlistItem::query()->where('team_id', $team->id)->count())->toBe(1);
});

it('registers draft module auditor', function () {
    $this->artisan('module:audit Draft')
        ->expectsOutputToContain('Draft')
        ->assertSuccessful();
});

it('requires auth for v2 draft routes', function () {
    $this->get('/v2/draft/1')->assertRedirect(route('login'));
});
