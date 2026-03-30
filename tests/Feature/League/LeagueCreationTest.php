<?php

use App\Enums\PokemonGame;
use App\Models\User;
use App\Modules\Matches\Models\Pool;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('creates a default pool when a league is created', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/leagues', [
        'command' => 'create',
        'league_id' => 0,
        'name' => 'Test League',
        'draft_date' => '2026-04-01',
        'set_start_date' => '2026-04-15',
        'set_frequency' => 7,
        'draft_points' => 80,
        'enforce_round_count' => false,
        'round_count' => 1,
        'ban_enabled' => false,
    ])->assertRedirect();

    $league = \App\Modules\League\Models\League::where('name', 'Test League')->first();
    expect($league)->not->toBeNull();
    expect($league->pokemon_generation)->toBe(9);
    expect($league->pokemon_game)->toBe(PokemonGame::ScarletViolet);

    $pool = Pool::where('league_id', $league->id)->first();
    expect($pool)->not->toBeNull();
    expect($pool->match_config_id)->toBe($league->matchConfig->id);
});

it('rejects a league when the game does not match the generation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/leagues', [
        'command' => 'create',
        'name' => 'Bad League',
        'draft_date' => '2026-04-01',
        'set_start_date' => '2026-04-15',
        'set_frequency' => 7,
        'draft_points' => 80,
        'enforce_round_count' => false,
        'round_count' => 1,
        'ban_enabled' => false,
        'pokemon_generation' => 1,
        'pokemon_game' => PokemonGame::ScarletViolet->value,
    ])->assertSessionHasErrors('pokemon_game');
});
