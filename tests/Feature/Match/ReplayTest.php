<?php

use App\Models\User;
use App\Modules\League\Models\League;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\Pool;
use App\Modules\Matches\Models\Set;
use App\Modules\Teams\Models\Team;
use App\Notifications\MatchReplaysNotification;
use Illuminate\Support\Facades\Notification;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createSetForReplayTests(array $leagueOverrides = []): array
{
    $owner = User::factory()->create();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $league = League::create(array_merge([
        'name' => 'Replay League',
        'status' => 1,
        'league_owner' => $owner->id,
        'discord_webhook_url' => 'https://discord.com/api/webhooks/main/token',
    ], $leagueOverrides));

    $matchConfig = MatchConfig::create([
        'league_id' => $league->id,
        'enforce_round_count' => false,
    ]);

    $pool = Pool::create([
        'league_id' => $league->id,
        'match_config_id' => $matchConfig->id,
        'status' => 1,
    ]);

    $team1 = Team::create([
        'name' => 'Team Rocket',
        'league_id' => $league->id,
        'user_id' => $user1->id,
        'admin_flag' => 1,
        'pick_position' => 1,
        'seed' => 1,
        'draft_points' => 80,
        'victory_points' => 0,
        'set_wins' => 1,
        'set_losses' => 0,
        'game_wins' => 2,
        'game_losses' => 0,
    ]);

    $team2 = Team::create([
        'name' => 'Team Aqua',
        'league_id' => $league->id,
        'user_id' => $user2->id,
        'admin_flag' => 0,
        'pick_position' => 2,
        'seed' => 2,
        'draft_points' => 80,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 1,
        'game_wins' => 0,
        'game_losses' => 2,
    ]);

    $set = Set::create([
        'league_id' => $league->id,
        'pool_id' => $pool->id,
        'round' => 1,
        'team1_id' => $team1->id,
        'team2_id' => $team2->id,
        'team1_score' => 2,
        'team2_score' => 0,
        'winner_id' => $team1->id,
        'status' => 0,
    ]);

    return [$user1, $user2, $league, $set];
}

// ── Saving replays ───────────────────────────────────────────────────────────

it('saves up to three replay urls on a set', function () {
    Notification::fake();

    [$user1, , , $set] = createSetForReplayTests();

    $response = $this->actingAs($user1)->put(route('sets.update-replays'), [
        'set_id' => $set->id,
        'replay1' => 'https://replay.pokemonshowdown.com/gen9vgc2025-123',
        'replay2' => 'https://replay.pokemonshowdown.com/gen9vgc2025-456',
        'replay3' => 'https://replay.pokemonshowdown.com/gen9vgc2025-789',
    ]);

    $response->assertRedirect(route('sets.show', ['set_id' => $set->id]));

    $set->refresh();
    expect($set->replay1)->toBe('https://replay.pokemonshowdown.com/gen9vgc2025-123')
        ->and($set->replay2)->toBe('https://replay.pokemonshowdown.com/gen9vgc2025-456')
        ->and($set->replay3)->toBe('https://replay.pokemonshowdown.com/gen9vgc2025-789');
});

it('saves a single replay url', function () {
    Notification::fake();

    [$user1, , , $set] = createSetForReplayTests();

    $this->actingAs($user1)->put(route('sets.update-replays'), [
        'set_id' => $set->id,
        'replay1' => 'https://replay.pokemonshowdown.com/gen9vgc2025-111',
    ]);

    $set->refresh();
    expect($set->replay1)->toBe('https://replay.pokemonshowdown.com/gen9vgc2025-111')
        ->and($set->replay2)->toBeNull()
        ->and($set->replay3)->toBeNull();
});

it('clears replays when empty values are submitted', function () {
    Notification::fake();

    [$user1, , , $set] = createSetForReplayTests();
    $set->update(['replay1' => 'https://replay.pokemonshowdown.com/gen9vgc2025-111']);

    $this->actingAs($user1)->put(route('sets.update-replays'), [
        'set_id' => $set->id,
        'replay1' => '',
    ]);

    $set->refresh();
    expect($set->replay1)->toBeNull();
});

it('rejects invalid replay urls', function () {
    [$user1, , , $set] = createSetForReplayTests();

    $response = $this->actingAs($user1)->put(route('sets.update-replays'), [
        'set_id' => $set->id,
        'replay1' => 'not-a-url',
    ]);

    $response->assertSessionHasErrors('replay1');
});

it('requires authentication to save replays', function () {
    [, , , $set] = createSetForReplayTests();

    $response = $this->put(route('sets.update-replays'), [
        'set_id' => $set->id,
        'replay1' => 'https://replay.pokemonshowdown.com/gen9vgc2025-111',
    ]);

    $response->assertRedirect('/login');
});

// ── Discord notification ─────────────────────────────────────────────────────

it('sends a MatchReplaysNotification to the league when replays are saved', function () {
    Notification::fake();

    [$user1, , $league, $set] = createSetForReplayTests();

    $this->actingAs($user1)->put(route('sets.update-replays'), [
        'set_id' => $set->id,
        'replay1' => 'https://replay.pokemonshowdown.com/gen9vgc2025-111',
    ]);

    Notification::assertSentTo($league, MatchReplaysNotification::class);
});

it('does not send a MatchReplaysNotification when no replays are provided', function () {
    Notification::fake();

    [$user1, , $league, $set] = createSetForReplayTests();

    $this->actingAs($user1)->put(route('sets.update-replays'), [
        'set_id' => $set->id,
    ]);

    Notification::assertNotSentTo($league, MatchReplaysNotification::class);
});

// ── Replay webhook fallback ──────────────────────────────────────────────────

it('routes replay notifications to the replay webhook when set', function () {
    $league = new League([
        'discord_webhook_url' => 'https://discord.com/api/webhooks/main/token',
        'discord_replay_webhook_url' => 'https://discord.com/api/webhooks/replays/token',
    ]);

    expect($league->routeNotificationForDiscordReplay())->toBe('https://discord.com/api/webhooks/replays/token');
});

it('falls back to main webhook when replay webhook is not set', function () {
    $league = new League([
        'discord_webhook_url' => 'https://discord.com/api/webhooks/main/token',
        'discord_replay_webhook_url' => null,
    ]);

    expect($league->routeNotificationForDiscordReplay())->toBe('https://discord.com/api/webhooks/main/token');
});

it('returns null when neither webhook is set', function () {
    $league = new League([
        'discord_webhook_url' => null,
        'discord_replay_webhook_url' => null,
    ]);

    expect($league->routeNotificationForDiscordReplay())->toBeNull();
});

// ── Notification content ─────────────────────────────────────────────────────

it('MatchReplaysNotification toDiscord contains team names and replay links', function () {
    $set = new Set([
        'team1_id' => 1,
        'team2_id' => 2,
        'replay1' => 'https://replay.pokemonshowdown.com/game1',
        'replay2' => 'https://replay.pokemonshowdown.com/game2',
        'replay3' => null,
        'round' => 2,
    ]);

    $team1 = new Team(['name' => 'Team Rocket']);
    $team2 = new Team(['name' => 'Team Aqua']);
    $set->setRelation('team1', $team1);
    $set->setRelation('team2', $team2);

    $notification = new MatchReplaysNotification($set);
    $payload = $notification->toDiscord(new League(['name' => 'Test']));

    expect($payload['embeds'][0]['title'])
        ->toContain('Team Rocket')
        ->toContain('Team Aqua');

    expect($payload['embeds'][0]['description'])
        ->toContain('https://replay.pokemonshowdown.com/game1')
        ->toContain('https://replay.pokemonshowdown.com/game2')
        ->not->toContain('Game 3');

    expect($payload['embeds'][0]['footer']['text'])->toContain('Round 2');
});
