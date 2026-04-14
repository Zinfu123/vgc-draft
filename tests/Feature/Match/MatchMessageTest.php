<?php

use App\Events\MatchMessageSentEvent;
use App\Models\User;
use App\Modules\League\Models\League;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\MatchMessage;
use App\Modules\Matches\Models\Pool;
use App\Modules\Matches\Models\Set;
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Facades\Event;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeSetWithTwoUsers(): array
{
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $league = League::create([
        'name' => 'Test League',
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
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

    return compact('user1', 'user2', 'team1', 'team2', 'set', 'league');
}

it('allows a participant to send a message', function () {
    Event::fake();

    ['user1' => $user1, 'set' => $set] = makeSetWithTwoUsers();

    $response = $this->actingAs($user1)
        ->post(route('sets.messages.store', ['set' => $set->id]), [
            'body' => 'Hey, when can you play?',
        ]);

    $response->assertRedirect();

    expect(MatchMessage::query()->where('set_id', $set->id)->count())->toBe(1);
    expect(MatchMessage::query()->where('set_id', $set->id)->first()->body)->toBe('Hey, when can you play?');

    Event::assertDispatched(MatchMessageSentEvent::class);
});

it('requires authentication to send a message', function () {
    ['set' => $set] = makeSetWithTwoUsers();

    $this->post(route('sets.messages.store', ['set' => $set->id]), [
        'body' => 'Hello',
    ])->assertRedirect(route('login'));
});

it('blocks a non-participant from sending a message', function () {
    Event::fake();

    ['set' => $set] = makeSetWithTwoUsers();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->post(route('sets.messages.store', ['set' => $set->id]), [
            'body' => 'I should not be able to send this',
        ])->assertForbidden();

    expect(MatchMessage::query()->where('set_id', $set->id)->count())->toBe(0);

    Event::assertNotDispatched(MatchMessageSentEvent::class);
});

it('validates that message body is required', function () {
    ['user1' => $user1, 'set' => $set] = makeSetWithTwoUsers();

    $this->actingAs($user1)
        ->post(route('sets.messages.store', ['set' => $set->id]), [
            'body' => '',
        ])->assertSessionHasErrors('body');
});

it('validates that message body does not exceed 1000 characters', function () {
    ['user1' => $user1, 'set' => $set] = makeSetWithTwoUsers();

    $this->actingAs($user1)
        ->post(route('sets.messages.store', ['set' => $set->id]), [
            'body' => str_repeat('a', 1001),
        ])->assertSessionHasErrors('body');
});

it('returns messages for a set in order', function () {
    ['user1' => $user1, 'user2' => $user2, 'set' => $set] = makeSetWithTwoUsers();

    MatchMessage::create(['set_id' => $set->id, 'user_id' => $user1->id, 'body' => 'First']);
    MatchMessage::create(['set_id' => $set->id, 'user_id' => $user2->id, 'body' => 'Second']);

    $response = $this->actingAs($user1)
        ->getJson(route('sets.messages.index', ['set' => $set->id]));

    $response->assertSuccessful();
    $data = $response->json();
    expect($data)->toHaveCount(2);
    expect($data[0]['body'])->toBe('First');
    expect($data[1]['body'])->toBe('Second');
});

it('new messages are unread by default', function () {
    Event::fake();

    ['user1' => $user1, 'set' => $set] = makeSetWithTwoUsers();

    $this->actingAs($user1)
        ->post(route('sets.messages.store', ['set' => $set->id]), [
            'body' => 'Hello!',
        ]);

    $message = MatchMessage::query()->where('set_id', $set->id)->first();
    expect($message->is_read)->toBeFalse();
});

it('includes is_read in the message index response', function () {
    ['user1' => $user1, 'user2' => $user2, 'set' => $set] = makeSetWithTwoUsers();

    MatchMessage::create(['set_id' => $set->id, 'user_id' => $user2->id, 'body' => 'Hey!', 'is_read' => false]);

    $response = $this->actingAs($user1)
        ->getJson(route('sets.messages.index', ['set' => $set->id]));

    $response->assertSuccessful();
    expect($response->json(0))->toHaveKey('is_read');
    expect($response->json(0)['is_read'])->toBeFalse();
});

it('marks opponent messages as read when requested', function () {
    ['user1' => $user1, 'user2' => $user2, 'set' => $set] = makeSetWithTwoUsers();

    MatchMessage::create(['set_id' => $set->id, 'user_id' => $user2->id, 'body' => 'Hey!', 'is_read' => false]);
    MatchMessage::create(['set_id' => $set->id, 'user_id' => $user1->id, 'body' => 'Reply', 'is_read' => false]);

    $response = $this->actingAs($user1)
        ->postJson(route('sets.messages.mark-read', ['set' => $set->id]));

    $response->assertSuccessful();

    $messages = MatchMessage::query()->where('set_id', $set->id)->get();

    // user2's message (received by user1) should now be read
    expect($messages->firstWhere('user_id', $user2->id)->is_read)->toBeTrue();
    // user1's own message should remain unread (they sent it)
    expect($messages->firstWhere('user_id', $user1->id)->is_read)->toBeFalse();
});

it('requires authentication to mark messages as read', function () {
    ['set' => $set] = makeSetWithTwoUsers();

    $this->postJson(route('sets.messages.mark-read', ['set' => $set->id]))
        ->assertUnauthorized();
});
