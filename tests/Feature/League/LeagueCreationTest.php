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
        'playoffs_enabled' => true,
        'free_trade_window_hours' => 24,
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

it('stores draft_start_at when provided during league creation', function () {
    $user = User::factory()->create();
    $startAt = '2026-05-01T18:00:00.000Z';

    $this->actingAs($user)->post('/leagues', baseLeagueCreatePayload([
        'name' => 'Scheduled Draft League',
        'draft_start_at' => $startAt,
    ]))->assertRedirect();

    $league = \App\Modules\League\Models\League::where('name', 'Scheduled Draft League')->first();
    expect($league)->not->toBeNull();
    expect($league->draftConfig->draft_start_at)->not->toBeNull();
});

it('stores null draft_start_at when not provided during league creation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/leagues', baseLeagueCreatePayload([
        'name' => 'No Schedule League',
    ]))->assertRedirect();

    $league = \App\Modules\League\Models\League::where('name', 'No Schedule League')->first();
    expect($league)->not->toBeNull();
    expect($league->draftConfig->draft_start_at)->toBeNull();
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
            ->where('require_showdown_username', false)
            ->has('playoff_format_options')
            ->has('playoff_bracket_size_options'));
});

it('stores require_showdown_username when creating a league', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/leagues', baseLeagueCreatePayload([
        'name' => 'Showdown Required League',
        'require_showdown_username' => true,
    ]))->assertRedirect();

    $league = \App\Modules\League\Models\League::where('name', 'Showdown Required League')->first();
    expect($league)->not->toBeNull();
    expect($league->require_showdown_username)->toBeTrue();
});

it('stores require_showdown_username as false by default when creating a league', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/leagues', baseLeagueCreatePayload([
        'name' => 'Showdown Optional League',
    ]))->assertRedirect();

    $league = \App\Modules\League\Models\League::where('name', 'Showdown Optional League')->first();
    expect($league)->not->toBeNull();
    expect($league->require_showdown_username)->toBeFalse();
});

it('includes the league status in the leagues index page data', function () {
    $user = User::factory()->create();

    $activeLeague = \App\Modules\League\Models\League::create([
        'name' => 'Active League',
        'league_owner' => $user->id,
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
        'set_frequency' => 7,
    ]);

    $pastLeague = \App\Modules\League\Models\League::create([
        'name' => 'Past League',
        'league_owner' => $user->id,
        'status' => \App\Modules\League\Enums\LeagueStatus::Completed->value,
        'set_frequency' => 7,
    ]);

    \App\Modules\Draft\Models\DraftConfig::create([
        'league_id' => $activeLeague->id,
        'draft_date' => '2026-05-01',
        'draft_start_at' => '2026-05-01 18:00:00',
        'draft_points' => 80,
        'ban_enabled' => false,
    ]);

    $this->actingAs($user)
        ->get(route('leagues.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('league/LeagueIndex')
            ->has('currentLeagues', 1, fn ($league) => $league
                ->where('id', $activeLeague->id)
                ->where('status', \App\Modules\League\Enums\LeagueStatus::RegularSeason->value)
                ->has('draft_config')
                ->where('draft_config.draft_date', '2026-05-01T00:00:00.000000Z')
                ->whereNot('draft_config.draft_start_at', null)
                ->etc()
            )
            ->has('pastLeagues', 1, fn ($league) => $league
                ->where('id', $pastLeague->id)
                ->where('status', \App\Modules\League\Enums\LeagueStatus::Completed->value)
                ->etc()
            )
        );
});
