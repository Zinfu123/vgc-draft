<?php

use App\Enums\Playoffs\PlayoffFormat;
use App\Enums\Playoffs\PlayoffStatus;
use App\Enums\PokemonGame;
use App\Models\User;
use App\Modules\Matches\Models\Pool;
use App\Modules\Teams\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function baseLeagueCreatePayload(array $overrides = []): array
{
    return array_merge([
        'command' => 'create',
        'league_id' => 0,
        'name' => 'Test League',
        'draft_date' => '2026-04-01',
        'set_start_date' => '2026-04-15',
        'set_frequency' => 7,
        'draft_points' => 80,
        'minimum_drafts' => 10,
        'enforce_round_count' => false,
        'round_count' => 1,
        'ban_enabled' => false,
        'playoff_format' => PlayoffFormat::SingleElimination->value,
        'playoff_bracket_size' => 4,
    ], $overrides);
}

it('creates a default pool when a league is created', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/leagues', baseLeagueCreatePayload([
        'name' => 'Test League',
        'minimum_drafts' => 10,
    ]))->assertRedirect();

    $league = \App\Modules\League\Models\League::where('name', 'Test League')->first();
    expect($league)->not->toBeNull();
    expect($league->pokemon_generation)->toBe(9);
    expect($league->pokemon_game)->toBe(PokemonGame::ScarletViolet);
    expect($league->draftConfig->minimum_drafts)->toBe(10);

    $pool = Pool::where('league_id', $league->id)->first();
    expect($pool)->not->toBeNull();
    expect($pool->match_config_id)->toBe($league->matchConfig->id);

    expect($league->playoff)->not->toBeNull();
    expect($league->playoff->format)->toBe(PlayoffFormat::SingleElimination);
    expect($league->playoff->bracket_size)->toBe(4);
    expect($league->playoff->status)->toBe(PlayoffStatus::Draft);

    $ownerTeam = Team::query()->where('league_id', $league->id)->where('user_id', $user->id)->first();
    expect($ownerTeam)->not->toBeNull();
    expect($ownerTeam->name)->toBe(trim((string) $user->name).'\'s Team');
    expect($ownerTeam->admin_flag)->toBe(1);
    expect($ownerTeam->pick_position)->toBe(1);
    expect($ownerTeam->draft_points)->toBe(80);
    expect($ownerTeam->pool_id)->toBe($pool->id);
});

it('names the commissioner team when the owner display name is blank', function () {
    $user = User::factory()->create(['name' => '   ']);

    $this->actingAs($user)->post('/leagues', baseLeagueCreatePayload([
        'name' => 'Blank Name League',
    ]))->assertRedirect();

    $league = \App\Modules\League\Models\League::where('name', 'Blank Name League')->first();
    expect($league)->not->toBeNull();

    $ownerTeam = Team::query()->where('league_id', $league->id)->where('user_id', $user->id)->first();
    expect($ownerTeam?->name)->toBe('Commissioner\'s Team');
});

it('stores optional discord webhooks when creating a league', function () {
    $user = User::factory()->create();
    $main = 'https://discord.com/api/webhooks/111/aaaa';
    $replay = 'https://discord.com/api/webhooks/222/bbbb';

    $this->actingAs($user)->post('/leagues', baseLeagueCreatePayload([
        'name' => 'Discord League',
        'minimum_drafts' => 1,
        'discord_webhook_url' => $main,
        'discord_replay_webhook_url' => $replay,
    ]))->assertRedirect();

    $league = \App\Modules\League\Models\League::where('name', 'Discord League')->first();
    expect($league)->not->toBeNull();
    expect($league->discord_webhook_url)->toBe($main);
    expect($league->discord_replay_webhook_url)->toBe($replay);
});

it('rejects invalid discord webhook urls when creating a league', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/leagues', baseLeagueCreatePayload([
        'name' => 'Bad Webhook League',
        'minimum_drafts' => 1,
        'discord_webhook_url' => 'not-a-valid-url',
    ]))->assertSessionHasErrors('discord_webhook_url');
});

it('rejects a league when the game does not match the generation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/leagues', baseLeagueCreatePayload([
        'name' => 'Bad League',
        'minimum_drafts' => 1,
        'pokemon_generation' => 1,
        'pokemon_game' => PokemonGame::ScarletViolet->value,
    ]))->assertSessionHasErrors('pokemon_game');
});

it('rejects an invalid playoff bracket size when creating a league', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/leagues', baseLeagueCreatePayload([
        'name' => 'Bad Bracket League',
        'playoff_bracket_size' => 99,
    ]))->assertSessionHasErrors('playoff_bracket_size');
});

it('renders the league create wizard page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('leagues.create-edit', ['command' => 'create']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('league/LeagueCreateEdit')
            ->where('command', 'create')
            ->where('discord_webhook_url', '')
            ->where('discord_replay_webhook_url', '')
            ->where('playoff_format', PlayoffFormat::SingleElimination->value)
            ->where('playoff_bracket_size', 4)
            ->has('playoff_format_options')
            ->has('playoff_bracket_size_options'));
});
