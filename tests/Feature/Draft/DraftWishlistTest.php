<?php

use App\Models\User;
use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftWishlistItem;
use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Teams\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createPokedexAndLeaguePokemonForWishlist(League $league, string $name, int $nationaldexId, int $cost): LeaguePokemon
{
    $dex = Pokedex::query()->create([
        'nationaldex_id' => $nationaldexId,
        'name' => $name,
        'type1' => 'Grass',
        'type2' => 'Poison',
        'sprite_url' => null,
    ]);

    return LeaguePokemon::query()->create([
        'league_id' => $league->id,
        'pokedex_id' => $dex->id,
        'name' => $dex->name,
        'cost' => $cost,
    ]);
}

function createLeagueTeamPoolForWishlist(User $user): array
{
    $league = League::create([
        'name' => 'Wishlist League',
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

    $leaguePokemon = createPokedexAndLeaguePokemonForWishlist($league, 'Bulbasaur', 1, 10);

    return [$league, $team, $leaguePokemon];
}

it('returns draft detail before the draft starts with empty wishlist ids', function () {
    $user = User::factory()->create();
    [$league, , $leaguePokemon] = createLeagueTeamPoolForWishlist($user);

    $this->actingAs($user)
        ->get(route('draft.detail', ['league_id' => $league->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('draft/DraftDetail')
            ->where('draft', null)
            ->where('wishlist_league_pokemon_ids', [])
            ->has('pokemon'));
});

it('adds a wishlist item before the draft starts', function () {
    $user = User::factory()->create();
    [$league, $team, $leaguePokemon] = createLeagueTeamPoolForWishlist($user);

    $this->actingAs($user)
        ->post(route('draft.wishlist.toggle'), [
            'league_id' => $league->id,
            'league_pokemon_id' => $leaguePokemon->id,
        ])
        ->assertRedirect(route('draft.detail', ['league_id' => $league->id]));

    expect(DraftWishlistItem::query()->where('team_id', $team->id)->where('league_pokemon_id', $leaguePokemon->id)->exists())->toBeTrue();
});

it('removes a wishlist item when toggled again', function () {
    $user = User::factory()->create();
    [$league, $team, $leaguePokemon] = createLeagueTeamPoolForWishlist($user);

    DraftWishlistItem::query()->create([
        'team_id' => $team->id,
        'league_pokemon_id' => $leaguePokemon->id,
    ]);

    $this->actingAs($user)
        ->post(route('draft.wishlist.toggle'), [
            'league_id' => $league->id,
            'league_pokemon_id' => $leaguePokemon->id,
        ])
        ->assertRedirect(route('draft.detail', ['league_id' => $league->id]));

    expect(DraftWishlistItem::query()->where('team_id', $team->id)->count())->toBe(0);
});

it('rejects wishlist changes when the draft has ended', function () {
    $user = User::factory()->create();
    [$league, $team, $leaguePokemon] = createLeagueTeamPoolForWishlist($user);

    Draft::create([
        'league_id' => $league->id,
        'status' => 0,
        'round_number' => 1,
        'pick_number' => 1,
    ]);

    $this->actingAs($user)
        ->post(route('draft.wishlist.toggle'), [
            'league_id' => $league->id,
            'league_pokemon_id' => $leaguePokemon->id,
        ])
        ->assertSessionHasErrors('league_id');

    expect(DraftWishlistItem::query()->count())->toBe(0);
});

it('allows wishlist toggles while the draft is in progress', function () {
    $user = User::factory()->create();
    [$league, $team, $leaguePokemon] = createLeagueTeamPoolForWishlist($user);

    Draft::create([
        'league_id' => $league->id,
        'status' => 1,
        'round_number' => 1,
        'pick_number' => 1,
    ]);

    $this->actingAs($user)
        ->post(route('draft.wishlist.toggle'), [
            'league_id' => $league->id,
            'league_pokemon_id' => $leaguePokemon->id,
        ])
        ->assertRedirect(route('draft.detail', ['league_id' => $league->id]));

    expect(DraftWishlistItem::query()->where('team_id', $team->id)->exists())->toBeTrue();
});

it('rejects wishlist when the user has no team in the league', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    [$league, $team, $leaguePokemon] = createLeagueTeamPoolForWishlist($other);

    $this->actingAs($user)
        ->post(route('draft.wishlist.toggle'), [
            'league_id' => $league->id,
            'league_pokemon_id' => $leaguePokemon->id,
        ])
        ->assertSessionHasErrors('league_id');

    expect(DraftWishlistItem::query()->count())->toBe(0);
});

it('reorders wishlist items by league pokemon id order', function () {
    $user = User::factory()->create();
    [$league, $team, $first] = createLeagueTeamPoolForWishlist($user);
    $second = createPokedexAndLeaguePokemonForWishlist($league, 'Ivysaur', 2, 8);
    $third = createPokedexAndLeaguePokemonForWishlist($league, 'Venusaur', 3, 6);

    foreach ([$first, $second, $third] as $index => $lp) {
        DraftWishlistItem::query()->create([
            'team_id' => $team->id,
            'league_pokemon_id' => $lp->id,
            'sort_order' => $index,
        ]);
    }

    $reordered = [$third->id, $first->id, $second->id];

    $this->actingAs($user)
        ->post(route('draft.wishlist.reorder'), [
            'league_id' => $league->id,
            'league_pokemon_ids' => $reordered,
        ])
        ->assertRedirect(route('draft.detail', ['league_id' => $league->id]));

    expect(
        DraftWishlistItem::query()
            ->where('team_id', $team->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('league_pokemon_id')
            ->all(),
    )->toBe($reordered);

    $this->actingAs($user)
        ->get(route('draft.detail', ['league_id' => $league->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('wishlist_league_pokemon_ids', $reordered));
});

it('rejects wishlist reorder when the draft has ended', function () {
    $user = User::factory()->create();
    [$league, $team, $first] = createLeagueTeamPoolForWishlist($user);
    $second = createPokedexAndLeaguePokemonForWishlist($league, 'Ivysaur', 2, 8);

    DraftWishlistItem::query()->create([
        'team_id' => $team->id,
        'league_pokemon_id' => $first->id,
        'sort_order' => 0,
    ]);
    DraftWishlistItem::query()->create([
        'team_id' => $team->id,
        'league_pokemon_id' => $second->id,
        'sort_order' => 1,
    ]);

    Draft::create([
        'league_id' => $league->id,
        'status' => 0,
        'round_number' => 1,
        'pick_number' => 1,
    ]);

    $this->actingAs($user)
        ->post(route('draft.wishlist.reorder'), [
            'league_id' => $league->id,
            'league_pokemon_ids' => [$second->id, $first->id],
        ])
        ->assertSessionHasErrors('league_id');

    expect(
        DraftWishlistItem::query()
            ->where('team_id', $team->id)
            ->orderBy('sort_order')
            ->pluck('league_pokemon_id')
            ->all(),
    )->toBe([$first->id, $second->id]);
});

it('rejects wishlist reorder when the Pokémon list does not match the wishlist', function () {
    $user = User::factory()->create();
    [$league, $team, $first] = createLeagueTeamPoolForWishlist($user);
    $second = createPokedexAndLeaguePokemonForWishlist($league, 'Ivysaur', 2, 8);
    $third = createPokedexAndLeaguePokemonForWishlist($league, 'Venusaur', 3, 6);

    DraftWishlistItem::query()->create([
        'team_id' => $team->id,
        'league_pokemon_id' => $first->id,
        'sort_order' => 0,
    ]);
    DraftWishlistItem::query()->create([
        'team_id' => $team->id,
        'league_pokemon_id' => $second->id,
        'sort_order' => 1,
    ]);

    $this->actingAs($user)
        ->post(route('draft.wishlist.reorder'), [
            'league_id' => $league->id,
            'league_pokemon_ids' => [$third->id, $first->id, $second->id],
        ])
        ->assertSessionHasErrors('league_pokemon_ids');
});
