<?php

use App\Events\MatchScheduleRequestUpdatedEvent;
use App\Models\User;
use App\Modules\League\Models\League;
use App\Modules\Matches\Enums\ScheduleRequestStatus;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\MatchScheduleRequest;
use App\Modules\Matches\Models\Pool;
use App\Modules\Matches\Models\Set;
use App\Modules\Teams\Models\Team;
use App\Notifications\MatchScheduleRequestNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeScheduleSetup(): array
{
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $league = League::create([
        'name' => 'Test League',
        'status' => 1,
        'draft_points' => 100,
        'league_owner' => $user1->id,
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
        'name' => 'Team 1',
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
        'name' => 'Team 2',
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

    return compact('user1', 'user2', 'team1', 'team2', 'set');
}

it('allows a participant to propose a match time', function () {
    Event::fake();

    ['user1' => $user1, 'set' => $set] = makeScheduleSetup();
    $proposedAt = now()->addDays(3)->format('Y-m-d H:i:s');

    $this->actingAs($user1)
        ->post(route('sets.schedule-requests.store', ['set' => $set->id]), [
            'proposed_at' => $proposedAt,
        ])->assertRedirect();

    expect(MatchScheduleRequest::query()->where('set_id', $set->id)->count())->toBe(1);
    $request = MatchScheduleRequest::query()->where('set_id', $set->id)->first();
    expect($request->status)->toBe(ScheduleRequestStatus::Pending);
    expect($request->proposed_by_user_id)->toBe($user1->id);

    Event::assertDispatched(MatchScheduleRequestUpdatedEvent::class);
});

it('requires a future proposed_at date', function () {
    ['user1' => $user1, 'set' => $set] = makeScheduleSetup();

    $this->actingAs($user1)
        ->post(route('sets.schedule-requests.store', ['set' => $set->id]), [
            'proposed_at' => now()->subDay()->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('proposed_at');
});

it('blocks a non-participant from proposing a match time', function () {
    Event::fake();

    ['set' => $set] = makeScheduleSetup();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->post(route('sets.schedule-requests.store', ['set' => $set->id]), [
            'proposed_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
        ])->assertForbidden();

    Event::assertNotDispatched(MatchScheduleRequestUpdatedEvent::class);
});

it('allows the opponent to accept a schedule request and sets scheduled_at on the set', function () {
    Event::fake();

    ['user1' => $user1, 'user2' => $user2, 'set' => $set] = makeScheduleSetup();
    $proposedAt = now()->addDays(3);

    $scheduleRequest = MatchScheduleRequest::create([
        'set_id' => $set->id,
        'proposed_by_user_id' => $user1->id,
        'proposed_at' => $proposedAt,
        'status' => ScheduleRequestStatus::Pending->value,
    ]);

    $this->actingAs($user2)
        ->patch(route('sets.schedule-requests.update', ['set' => $set->id, 'scheduleRequest' => $scheduleRequest->id]), [
            'status' => 'accepted',
        ])->assertRedirect();

    $scheduleRequest->refresh();
    expect($scheduleRequest->status)->toBe(ScheduleRequestStatus::Accepted);

    $set->refresh();
    expect($set->scheduled_at)->not->toBeNull();

    Event::assertDispatched(MatchScheduleRequestUpdatedEvent::class);
});

it('allows the opponent to decline a schedule request', function () {
    Event::fake();

    ['user1' => $user1, 'user2' => $user2, 'set' => $set] = makeScheduleSetup();

    $scheduleRequest = MatchScheduleRequest::create([
        'set_id' => $set->id,
        'proposed_by_user_id' => $user1->id,
        'proposed_at' => now()->addDays(3),
        'status' => ScheduleRequestStatus::Pending->value,
    ]);

    $this->actingAs($user2)
        ->patch(route('sets.schedule-requests.update', ['set' => $set->id, 'scheduleRequest' => $scheduleRequest->id]), [
            'status' => 'declined',
        ])->assertRedirect();

    $scheduleRequest->refresh();
    expect($scheduleRequest->status)->toBe(ScheduleRequestStatus::Declined);

    $set->refresh();
    expect($set->scheduled_at)->toBeNull();
});

it('blocks the proposer from responding to their own request', function () {
    Event::fake();

    ['user1' => $user1, 'set' => $set] = makeScheduleSetup();

    $scheduleRequest = MatchScheduleRequest::create([
        'set_id' => $set->id,
        'proposed_by_user_id' => $user1->id,
        'proposed_at' => now()->addDays(3),
        'status' => ScheduleRequestStatus::Pending->value,
    ]);

    $this->actingAs($user1)
        ->patch(route('sets.schedule-requests.update', ['set' => $set->id, 'scheduleRequest' => $scheduleRequest->id]), [
            'status' => 'accepted',
        ])->assertForbidden();
});

it('sends a discord notification when a league webhook is configured', function () {
    Event::fake();
    Notification::fake();

    ['user1' => $user1, 'set' => $set] = makeScheduleSetup();

    $league = League::query()->find($set->league_id);
    $league->update(['discord_webhook_url' => 'https://discord.com/api/webhooks/fake/url']);

    $this->actingAs($user1)
        ->post(route('sets.schedule-requests.store', ['set' => $set->id]), [
            'proposed_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
        ])->assertRedirect();

    Notification::assertSentTo($league, MatchScheduleRequestNotification::class);
});

it('does not send a discord notification when no webhook is configured', function () {
    Event::fake();
    Notification::fake();

    ['user1' => $user1, 'set' => $set] = makeScheduleSetup();

    $this->actingAs($user1)
        ->post(route('sets.schedule-requests.store', ['set' => $set->id]), [
            'proposed_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
        ])->assertRedirect();

    Notification::assertNothingSent();
});

it('cancels any pending request when a new one is proposed', function () {
    Event::fake();

    ['user1' => $user1, 'user2' => $user2, 'set' => $set] = makeScheduleSetup();

    $firstRequest = MatchScheduleRequest::create([
        'set_id' => $set->id,
        'proposed_by_user_id' => $user1->id,
        'proposed_at' => now()->addDays(3),
        'status' => ScheduleRequestStatus::Pending->value,
    ]);

    $this->actingAs($user2)
        ->post(route('sets.schedule-requests.store', ['set' => $set->id]), [
            'proposed_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
        ])->assertRedirect();

    $firstRequest->refresh();
    expect($firstRequest->status)->toBe(ScheduleRequestStatus::Declined);

    $latestRequest = MatchScheduleRequest::query()
        ->where('set_id', $set->id)
        ->where('status', ScheduleRequestStatus::Pending->value)
        ->latest()
        ->first();

    expect($latestRequest)->not->toBeNull();
    expect($latestRequest->proposed_by_user_id)->toBe($user2->id);
});
