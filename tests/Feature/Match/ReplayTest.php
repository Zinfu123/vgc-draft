<?php

use App\Models\User;
use App\Modules\League\Models\League;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\Pool;
use App\Modules\Matches\Models\Set;
use App\Modules\Teams\Models\Team;
use App\Notifications\MatchReplaysNotification;
use Illuminate\Support\Facades\Http;
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

/**
 * @return array{0: User, 1: User, 2: League, 3: Set}
 */
function createOpenSetForReplayPolicyTests(): array
{
    $owner = User::factory()->create();
    $user1 = User::factory()->create(['showdown_username' => 'CoachA']);
    $user2 = User::factory()->create(['showdown_username' => 'CoachB']);

    $league = League::create([
        'name' => 'Open Replay League',
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
        'league_owner' => $owner->id,
        'discord_webhook_url' => 'https://discord.com/api/webhooks/main/token',
    ]);

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
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
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
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
    ]);

    $set = Set::create([
        'league_id' => $league->id,
        'pool_id' => $pool->id,
        'round' => 1,
        'team1_id' => $team1->id,
        'team2_id' => $team2->id,
        'team1_score' => null,
        'team2_score' => null,
        'winner_id' => null,
        'status' => 1,
    ]);

    return [$user1, $user2, $league, $set];
}

function replayLogMinimalWithPlayersAndWinner(string $p1, string $p2, string $winner): string
{
    return implode("\n", [
        "|player|p1|{$p1}|",
        "|player|p2|{$p2}|",
        "|win|{$winner}|",
    ]);
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

it('forbids league members who are not in the set from saving replays', function () {
    [$user1, $user2, $league, $set] = createSetForReplayTests();

    $stranger = User::factory()->create();
    Team::create([
        'name' => 'Team Stranger',
        'league_id' => $league->id,
        'user_id' => $stranger->id,
        'admin_flag' => 0,
        'pick_position' => 3,
        'seed' => 3,
        'draft_points' => 80,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
    ]);

    $response = $this->actingAs($stranger)->put(route('sets.update-replays'), [
        'set_id' => $set->id,
        'replay1' => 'https://replay.pokemonshowdown.com/gen9vgc2025-111',
    ]);

    $response->assertForbidden();
});

it('rejects the same showdown replay used twice on one set', function () {
    Notification::fake();

    [$user1, , , $set] = createSetForReplayTests();
    $url = 'https://replay.pokemonshowdown.com/gen9vgc2025-dup';

    $response = $this->actingAs($user1)->from(route('sets.show', ['set_id' => $set->id]))->put(route('sets.update-replays'), [
        'set_id' => $set->id,
        'replay1' => $url,
        'replay2' => $url,
    ]);

    $response->assertSessionHasErrors('replay1');
});

it('rejects a replay that is already saved on another set in the league', function () {
    Notification::fake();

    [$user1, , $league, $firstLeagueSet] = createSetForReplayTests();
    $firstLeagueSet->update(['replay1' => 'https://replay.pokemonshowdown.com/gen9vgc2025-shared']);

    $otherSet = Set::create([
        'league_id' => $league->id,
        'pool_id' => $firstLeagueSet->pool_id,
        'round' => 2,
        'team1_id' => $firstLeagueSet->team1_id,
        'team2_id' => $firstLeagueSet->team2_id,
        'status' => 1,
    ]);

    $response = $this->actingAs($user1)->from(route('sets.show', ['set_id' => $otherSet->id]))->put(route('sets.update-replays'), [
        'set_id' => $otherSet->id,
        'replay1' => 'https://replay.pokemonshowdown.com/gen9vgc2025-shared',
    ]);

    $response->assertSessionHasErrors('replay1');
});

// ── Replay preview (p1 / p2 names) ───────────────────────────────────────────

it('returns showdown player names and suggests p1 team when profile usernames match the replay', function () {
    [$user1, $user2, , $set] = createOpenSetForReplayPolicyTests();
    $set->update(['replay1' => 'https://replay.pokemonshowdown.com/gen9preview-match']);

    Http::fake([
        'https://replay.pokemonshowdown.com/gen9preview-match.log' => Http::response(
            replayLogMinimalWithPlayersAndWinner('CoachA', 'CoachB', 'CoachA'),
            200
        ),
    ]);

    $response = $this->actingAs($user1)->postJson(route('sets.preview-replay-players'), [
        'set_id' => $set->id,
        'replay_slot' => 1,
    ]);

    $response->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('p1_name', 'CoachA')
        ->assertJsonPath('p2_name', 'CoachB')
        ->assertJsonPath('needs_manual_p1_map', false)
        ->assertJsonPath('suggested_p1_team_id', $set->team1_id);
});

it('suggests p1 when only the second coach has a showdown username and it matches replay player two', function () {
    [$user1, $user2, , $set] = createOpenSetForReplayPolicyTests();
    $user1->update(['showdown_username' => null]);
    $user2->update(['showdown_username' => 'CoachB']);
    $set->refresh();
    $set->update(['replay1' => 'https://replay.pokemonshowdown.com/gen9preview-p2-only']);

    Http::fake([
        'https://replay.pokemonshowdown.com/gen9preview-p2-only.log' => Http::response(
            replayLogMinimalWithPlayersAndWinner('CoachA', 'CoachB', 'CoachA'),
            200
        ),
    ]);

    $response = $this->actingAs($user2)->postJson(route('sets.preview-replay-players'), [
        'set_id' => $set->id,
        'replay_slot' => 1,
    ]);

    $response->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('needs_manual_p1_map', false)
        ->assertJsonPath('suggested_p1_team_id', $set->team1_id);
});

it('suggests p1 from replay when only one coach has a showdown username and it matches a replay slot', function () {
    [$user1, $user2, , $set] = createOpenSetForReplayPolicyTests();
    $user2->update(['showdown_username' => null]);
    $set->refresh();
    $set->update(['replay1' => 'https://replay.pokemonshowdown.com/gen9preview-partial']);

    Http::fake([
        'https://replay.pokemonshowdown.com/gen9preview-partial.log' => Http::response(
            replayLogMinimalWithPlayersAndWinner('CoachA', 'CoachB', 'CoachA'),
            200
        ),
    ]);

    $response = $this->actingAs($user1)->postJson(route('sets.preview-replay-players'), [
        'set_id' => $set->id,
        'replay_slot' => 1,
    ]);

    $response->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('needs_manual_p1_map', false)
        ->assertJsonPath('suggested_p1_team_id', $set->team1_id);
});

// ── Require replays before results ──────────────────────────────────────────

it('blocks submitting set results when the league requires replays and none are saved', function () {
    [$user1, , $league, $set] = createOpenSetForReplayPolicyTests();
    MatchConfig::query()->where('league_id', $league->id)->update(['require_replays_before_results' => true]);

    $response = $this->actingAs($user1)->from(route('sets.show', ['set_id' => $set->id]))->put(route('sets.update'), [
        'command' => 'update',
        'set_id' => $set->id,
        'team1_id' => $set->team1_id,
        'team2_id' => $set->team2_id,
        'team1_score' => 2,
        'team2_score' => 0,
    ]);

    $response->assertSessionHasErrors('set_result');
});

it('allows set results when require replays is on and at least one replay exists', function () {
    [$user1, , $league, $set] = createOpenSetForReplayPolicyTests();
    MatchConfig::query()->where('league_id', $league->id)->update(['require_replays_before_results' => true]);
    $set->update(['replay1' => 'https://replay.pokemonshowdown.com/gen9vgc2025-has-replay']);

    Notification::fake();

    $response = $this->actingAs($user1)->put(route('sets.update'), [
        'command' => 'update',
        'set_id' => $set->id,
        'team1_id' => $set->team1_id,
        'team2_id' => $set->team2_id,
        'team1_score' => 2,
        'team2_score' => 0,
    ]);

    $response->assertRedirect(route('sets.show', ['set_id' => $set->id]));
    $set->refresh();
    expect($set->status)->toBe(0)
        ->and($set->team1_score)->toBe(2)
        ->and($set->team2_score)->toBe(0);
});

// ── Auto-complete set from replays ─────────────────────────────────────────

it('aggregates best-of-three scores from replay logs when coach showdown names match winners', function () {
    [, , , $set] = createOpenSetForReplayPolicyTests();
    $set->update([
        'replay1' => 'https://replay.pokemonshowdown.com/gen9-auto-a',
        'replay2' => 'https://replay.pokemonshowdown.com/gen9-auto-b',
    ]);

    Http::fake([
        'https://replay.pokemonshowdown.com/*' => Http::response(
            replayLogMinimalWithPlayersAndWinner('CoachA', 'CoachB', 'CoachA'),
            200
        ),
    ]);

    $scores = app(\App\Modules\Pokepaste\Services\ReplaySetOutcomeAggregator::class)
        ->aggregateScoresFromSetReplays($set->fresh(['team1.user', 'team2.user']));

    expect($scores)->toBe(['team1_score' => 2, 'team2_score' => 0]);
});

it('auto-completes an open set from two replay logs when league config enables it', function () {
    Notification::fake();

    [$user1, , $league, $set] = createOpenSetForReplayPolicyTests();
    MatchConfig::query()->where('league_id', $league->id)->update(['auto_complete_set_from_replays' => true]);

    $winLog = replayLogMinimalWithPlayersAndWinner('CoachA', 'CoachB', 'CoachA');
    Http::fake([
        'https://replay.pokemonshowdown.com/gen9-auto-a.log' => Http::response($winLog, 200),
        'https://replay.pokemonshowdown.com/gen9-auto-b.log' => Http::response($winLog, 200),
    ]);

    $this->actingAs($user1)->put(route('sets.update-replays'), [
        'set_id' => $set->id,
        'replay1' => 'https://replay.pokemonshowdown.com/gen9-auto-a',
        'replay2' => 'https://replay.pokemonshowdown.com/gen9-auto-b',
    ]);

    $set->refresh();
    expect($set->status)->toBe(0)
        ->and($set->team1_score)->toBe(2)
        ->and($set->team2_score)->toBe(0)
        ->and($set->winner_id)->toBe($set->team1_id);
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
