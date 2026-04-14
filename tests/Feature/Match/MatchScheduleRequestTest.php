<?php

use App\Models\User;
use App\Modules\League\Enums\LeagueStatus;
use App\Modules\League\Models\League;
use App\Modules\Matches\Enums\ScheduleRequestStatus;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\MatchScheduleRequest;
use App\Modules\Matches\Models\Pool;
use App\Modules\Matches\Models\Set;
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Facades\Http;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeScheduleRequestFixture(): array
{
    $user1 = User::factory()->create(['discord_id' => '111111111111111111']);
    $user2 = User::factory()->create(['discord_id' => '222222222222222222']);

    $league = League::create([
        'name' => 'Schedule Test League',
        'status' => LeagueStatus::RegularSeason->value,
        'league_owner' => $user1->id,
        'discord_webhook_url' => 'https://discord.com/api/webhooks/test/webhook',
    ]);

    $matchConfig = MatchConfig::create([
        'league_id' => $league->id,
        'number_of_pools' => 1,
        'status' => 1,
    ]);

    $pool = Pool::create([
        'league_id' => $league->id,
        'match_config_id' => $matchConfig->id,
        'status' => 1,
    ]);

    $team1 = Team::create([
        'name' => 'Team A',
        'league_id' => $league->id,
        'user_id' => $user1->id,
        'pick_position' => 1,
        'seed' => 1,
        'pool_id' => $pool->id,
        'draft_points' => 100,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
    ]);

    $team2 = Team::create([
        'name' => 'Team B',
        'league_id' => $league->id,
        'user_id' => $user2->id,
        'pick_position' => 2,
        'seed' => 2,
        'pool_id' => $pool->id,
        'draft_points' => 100,
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
        'status' => 1,
    ]);

    return compact('user1', 'user2', 'team1', 'team2', 'set', 'league');
}

it('allows a participant to request a match time', function () {
    Http::fake();

    ['user1' => $user1, 'set' => $set] = makeScheduleRequestFixture();

    $proposedAt = now()->addDays(2)->format('Y-m-d\TH:i');

    $response = $this->actingAs($user1)
        ->post(route('sets.schedule-request.store', ['set' => $set->id]), [
            'proposed_at' => $proposedAt,
        ]);

    $response->assertRedirect(route('sets.show', ['set_id' => $set->id]));

    expect(MatchScheduleRequest::query()->where('set_id', $set->id)->count())->toBe(1);

    $request = MatchScheduleRequest::query()->where('set_id', $set->id)->first();
    expect($request->status)->toBe(ScheduleRequestStatus::Pending)
        ->and($request->proposed_by_user_id)->toBe($user1->id);
});

it('blocks a non-participant from requesting a match time', function () {
    Http::fake();

    ['set' => $set] = makeScheduleRequestFixture();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->post(route('sets.schedule-request.store', ['set' => $set->id]), [
            'proposed_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
        ])->assertForbidden();

    expect(MatchScheduleRequest::query()->where('set_id', $set->id)->count())->toBe(0);
});

it('blocks unauthenticated users from requesting a match time', function () {
    ['set' => $set] = makeScheduleRequestFixture();

    $this->post(route('sets.schedule-request.store', ['set' => $set->id]), [
        'proposed_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
    ])->assertRedirect(route('login'));
});

it('requires proposed_at to be in the future', function () {
    ['user1' => $user1, 'set' => $set] = makeScheduleRequestFixture();

    $this->actingAs($user1)
        ->post(route('sets.schedule-request.store', ['set' => $set->id]), [
            'proposed_at' => now()->subDay()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('proposed_at');
});

it('cancels an existing pending request when a new one is submitted', function () {
    Http::fake();

    ['user1' => $user1, 'set' => $set] = makeScheduleRequestFixture();

    MatchScheduleRequest::create([
        'set_id' => $set->id,
        'proposed_by_user_id' => $user1->id,
        'proposed_at' => now()->addDay(),
        'status' => ScheduleRequestStatus::Pending->value,
    ]);

    $this->actingAs($user1)
        ->post(route('sets.schedule-request.store', ['set' => $set->id]), [
            'proposed_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
        ]);

    $requests = MatchScheduleRequest::query()->where('set_id', $set->id)->get();

    expect($requests->count())->toBe(2);
    expect($requests->where('status', ScheduleRequestStatus::Declined)->count())->toBe(1);
    expect($requests->where('status', ScheduleRequestStatus::Pending)->count())->toBe(1);
});

it('allows the other participant to accept a time request and sets scheduled_at on the set', function () {
    Http::fake();

    ['user1' => $user1, 'user2' => $user2, 'set' => $set] = makeScheduleRequestFixture();

    $proposedAt = now()->addDays(2);

    $scheduleRequest = MatchScheduleRequest::create([
        'set_id' => $set->id,
        'proposed_by_user_id' => $user1->id,
        'proposed_at' => $proposedAt,
        'status' => ScheduleRequestStatus::Pending->value,
    ]);

    $response = $this->actingAs($user2)
        ->patch(route('sets.schedule-request.respond', ['scheduleRequest' => $scheduleRequest->id]), [
            'action' => 'accept',
        ]);

    $response->assertRedirect(route('sets.show', ['set_id' => $set->id]));

    $scheduleRequest->refresh();
    expect($scheduleRequest->status)->toBe(ScheduleRequestStatus::Accepted);

    $set->refresh();
    expect($set->scheduled_at)->not->toBeNull();
    expect($set->scheduled_at->timestamp)->toBe($proposedAt->timestamp);
});

it('allows the other participant to decline a time request', function () {
    Http::fake();

    ['user1' => $user1, 'user2' => $user2, 'set' => $set] = makeScheduleRequestFixture();

    $scheduleRequest = MatchScheduleRequest::create([
        'set_id' => $set->id,
        'proposed_by_user_id' => $user1->id,
        'proposed_at' => now()->addDays(2),
        'status' => ScheduleRequestStatus::Pending->value,
    ]);

    $this->actingAs($user2)
        ->patch(route('sets.schedule-request.respond', ['scheduleRequest' => $scheduleRequest->id]), [
            'action' => 'decline',
        ])->assertRedirect(route('sets.show', ['set_id' => $set->id]));

    $scheduleRequest->refresh();
    expect($scheduleRequest->status)->toBe(ScheduleRequestStatus::Declined);
});

it('allows the proposer to cancel their own request', function () {
    Http::fake();

    ['user1' => $user1, 'set' => $set] = makeScheduleRequestFixture();

    $scheduleRequest = MatchScheduleRequest::create([
        'set_id' => $set->id,
        'proposed_by_user_id' => $user1->id,
        'proposed_at' => now()->addDays(2),
        'status' => ScheduleRequestStatus::Pending->value,
    ]);

    $this->actingAs($user1)
        ->patch(route('sets.schedule-request.respond', ['scheduleRequest' => $scheduleRequest->id]), [
            'action' => 'cancel',
        ])->assertRedirect(route('sets.show', ['set_id' => $set->id]));

    $scheduleRequest->refresh();
    expect($scheduleRequest->status)->toBe(ScheduleRequestStatus::Declined);
});

it('blocks the proposer from accepting their own request', function () {
    Http::fake();

    ['user1' => $user1, 'set' => $set] = makeScheduleRequestFixture();

    $scheduleRequest = MatchScheduleRequest::create([
        'set_id' => $set->id,
        'proposed_by_user_id' => $user1->id,
        'proposed_at' => now()->addDays(2),
        'status' => ScheduleRequestStatus::Pending->value,
    ]);

    $this->actingAs($user1)
        ->patch(route('sets.schedule-request.respond', ['scheduleRequest' => $scheduleRequest->id]), [
            'action' => 'accept',
        ])->assertForbidden();

    $scheduleRequest->refresh();
    expect($scheduleRequest->status)->toBe(ScheduleRequestStatus::Pending);
});

it('allows the other participant to propose a reschedule creating a new pending request', function () {
    Http::fake();

    ['user1' => $user1, 'user2' => $user2, 'set' => $set] = makeScheduleRequestFixture();

    $scheduleRequest = MatchScheduleRequest::create([
        'set_id' => $set->id,
        'proposed_by_user_id' => $user1->id,
        'proposed_at' => now()->addDays(2),
        'status' => ScheduleRequestStatus::Pending->value,
    ]);

    $newTime = now()->addDays(5)->format('Y-m-d\TH:i');

    $this->actingAs($user2)
        ->patch(route('sets.schedule-request.respond', ['scheduleRequest' => $scheduleRequest->id]), [
            'action' => 'reschedule',
            'proposed_at' => $newTime,
        ])->assertRedirect(route('sets.show', ['set_id' => $set->id]));

    $scheduleRequest->refresh();
    expect($scheduleRequest->status)->toBe(ScheduleRequestStatus::Declined);

    $newRequest = MatchScheduleRequest::query()
        ->where('set_id', $set->id)
        ->where('proposed_by_user_id', $user2->id)
        ->where('status', ScheduleRequestStatus::Pending->value)
        ->first();

    expect($newRequest)->not->toBeNull();
    expect($newRequest->proposed_by_user_id)->toBe($user2->id);
});

it('sends a discord notification when a time is requested', function () {
    Http::fake(['https://discord.com/api/webhooks/*' => Http::response([], 204)]);

    ['user1' => $user1, 'set' => $set] = makeScheduleRequestFixture();

    $this->actingAs($user1)
        ->post(route('sets.schedule-request.store', ['set' => $set->id]), [
            'proposed_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
        ]);

    Http::assertSentCount(1);
});

it('sends a discord notification when a time request is accepted', function () {
    Http::fake(['https://discord.com/api/webhooks/*' => Http::response([], 204)]);

    ['user1' => $user1, 'user2' => $user2, 'set' => $set] = makeScheduleRequestFixture();

    $scheduleRequest = MatchScheduleRequest::create([
        'set_id' => $set->id,
        'proposed_by_user_id' => $user1->id,
        'proposed_at' => now()->addDays(2),
        'status' => ScheduleRequestStatus::Pending->value,
    ]);

    $this->actingAs($user2)
        ->patch(route('sets.schedule-request.respond', ['scheduleRequest' => $scheduleRequest->id]), [
            'action' => 'accept',
        ]);

    Http::assertSentCount(1);
});
