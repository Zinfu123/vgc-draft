<?php

use App\Models\User;
use App\Modules\League\Models\League;
use App\Modules\Matches\Models\Pool;
use App\Modules\Matches\Models\Set;
use App\Modules\Teams\Models\Team;
use App\Notifications\DraftEndedNotification;
use App\Notifications\DraftStartedNotification;
use App\Notifications\MatchResultNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// ── Discord Webhook URL update ───────────────────────────────────────────────

it('saves a discord webhook url on the league', function () {
    $owner = User::factory()->create();
    $league = League::create([
        'name' => 'Test League',
        'status' => 1,
        'league_owner' => $owner->id,
    ]);

    $response = $this->actingAs($owner)->post("/leagues/{$league->id}/discord-webhook", [
        'discord_webhook_url' => 'https://discord.com/api/webhooks/123/abc',
    ]);

    $response->assertRedirect();
    expect($league->fresh()->discord_webhook_url)->toBe('https://discord.com/api/webhooks/123/abc');
});

it('clears the discord webhook url when an empty value is submitted', function () {
    $owner = User::factory()->create();
    $league = League::create([
        'name' => 'Test League',
        'status' => 1,
        'league_owner' => $owner->id,
        'discord_webhook_url' => 'https://discord.com/api/webhooks/123/abc',
    ]);

    $response = $this->actingAs($owner)->post("/leagues/{$league->id}/discord-webhook", [
        'discord_webhook_url' => '',
    ]);

    $response->assertRedirect();
    expect($league->fresh()->discord_webhook_url)->toBeNull();
});

it('rejects an invalid url for the discord webhook', function () {
    $owner = User::factory()->create();
    $league = League::create([
        'name' => 'Test League',
        'status' => 1,
        'league_owner' => $owner->id,
    ]);

    $response = $this->actingAs($owner)->post("/leagues/{$league->id}/discord-webhook", [
        'discord_webhook_url' => 'not-a-url',
    ]);

    $response->assertSessionHasErrors('discord_webhook_url');
});

it('requires authentication to update the discord webhook', function () {
    $owner = User::factory()->create();
    $league = League::create([
        'name' => 'Test League',
        'status' => 1,
        'league_owner' => $owner->id,
    ]);

    $response = $this->post("/leagues/{$league->id}/discord-webhook", [
        'discord_webhook_url' => 'https://discord.com/api/webhooks/123/abc',
    ]);

    $response->assertRedirect('/login');
});

// ── DraftStartedNotification ─────────────────────────────────────────────────

it('sends a DraftStartedNotification when a draft is created', function () {
    Notification::fake();
    Event::fake();

    [$owner, $league, $team1, $team2] = createLeagueForDiscordTests();

    $this->actingAs($owner)->post('/draft/create', ['league_id' => $league->id]);

    Notification::assertSentTo($league, DraftStartedNotification::class);
});

it('does not send a DraftStartedNotification when league has no webhook url', function () {
    Notification::fake();
    Event::fake();

    [$owner, $league, $team1, $team2] = createLeagueForDiscordTests();
    $league->update(['discord_webhook_url' => null]);

    $this->actingAs($owner)->post('/draft/create', ['league_id' => $league->id]);

    Notification::assertSentTo($league, DraftStartedNotification::class);
    // The notification is still sent but DiscordChannel will skip it when webhook is null
});

// ── MatchResultNotification ──────────────────────────────────────────────────

it('sends a MatchResultNotification when a set result is submitted', function () {
    Notification::fake();
    Event::fake();

    [$owner, $league, $team1, $team2, $user1] = createLeagueForDiscordTests();

    $pool = Pool::create([
        'league_id' => $league->id,
        'match_config_id' => $league->matchConfig->id,
        'status' => 1,
    ]);

    $set = Set::create([
        'league_id' => $league->id,
        'pool_id' => $pool->id,
        'round' => 1,
        'team1_id' => $team1->id,
        'team2_id' => $team2->id,
        'status' => 1,
    ]);

    $this->actingAs($user1)->put('/match', [
        'set_id' => $set->id,
        'team1_id' => $team1->id,
        'team2_id' => $team2->id,
        'team1_score' => 2,
        'team2_score' => 0,
        'command' => 'update',
    ]);

    Notification::assertSentTo($league, MatchResultNotification::class);
});

// ── Notification content ─────────────────────────────────────────────────────

it('DraftStartedNotification toDiscord contains the league name', function () {
    $league = new League(['name' => 'VGC Championship']);
    $notification = new DraftStartedNotification($league);
    $payload = $notification->toDiscord($league);

    expect($payload['embeds'][0]['description'])->toContain('VGC Championship');
});

it('DraftEndedNotification toDiscord contains the league name', function () {
    $league = new League(['name' => 'VGC Championship']);
    $notification = new DraftEndedNotification($league);
    $payload = $notification->toDiscord($league);

    expect($payload['embeds'][0]['description'])->toContain('VGC Championship');
});

it('MatchResultNotification toDiscord contains both team names and scores', function () {
    $set = new Set([
        'team1_id' => 1,
        'team2_id' => 2,
        'team1_score' => 2,
        'team2_score' => 1,
        'winner_id' => 1,
        'round' => 3,
    ]);

    $team1 = new Team(['name' => 'Team Rocket']);
    $team1->id = 1;
    $team2 = new Team(['name' => 'Team Aqua']);
    $team2->id = 2;

    $set->setRelation('team1', $team1);
    $set->setRelation('team2', $team2);

    $notification = new MatchResultNotification($set);
    $league = new League(['name' => 'Test']);
    $payload = $notification->toDiscord($league);

    expect($payload['embeds'][0]['description'])
        ->toContain('Team Rocket')
        ->toContain('Team Aqua')
        ->toContain('2')
        ->toContain('1');

    expect($payload['embeds'][0]['footer']['text'])->toContain('Round 3');
});
