<?php

use App\Jobs\EnforceTradeDeadlineJob;
use App\Models\User;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Enums\LeagueStatus;
use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Teams\Models\Team;
use App\Modules\Trade\Models\Trade;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeDeadlineLeague(?Carbon $deadline = null): array
{
    $owner = User::factory()->create(['discord_id' => '111111111111111111']);
    $member = User::factory()->create(['discord_id' => '222222222222222222']);

    $league = League::create([
        'name' => 'Deadline League',
        'status' => LeagueStatus::RegularSeason->value,
        'league_owner' => $owner->id,
        'free_trade_window_hours' => 24,
        'maximum_teams' => 10,
        'trade_deadline_at' => $deadline,
    ]);

    DraftConfig::create([
        'league_id' => $league->id,
        'draft_date' => now()->subDays(10),
        'draft_points' => 80,
        'ban_enabled' => false,
        'minimum_drafts' => 1,
    ]);

    $ownerTeam = Team::create([
        'name' => 'Owner Team',
        'league_id' => $league->id,
        'user_id' => $owner->id,
        'admin_flag' => 1,
        'pick_position' => 1,
        'draft_points' => 80,
        'trades' => 5,
    ]);

    $memberTeam = Team::create([
        'name' => 'Member Team',
        'league_id' => $league->id,
        'user_id' => $member->id,
        'admin_flag' => 0,
        'pick_position' => 2,
        'draft_points' => 80,
        'trades' => 5,
    ]);

    $pd1 = Pokedex::create(['nationaldex_id' => 800, 'name' => 'Necrozma', 'type1' => 'Psychic']);
    $pd2 = Pokedex::create(['nationaldex_id' => 801, 'name' => 'Magearna', 'type1' => 'Steel']);

    $poke1 = LeaguePokemon::create(['league_id' => $league->id, 'pokedex_id' => $pd1->id, 'name' => 'Necrozma', 'cost' => 10, 'drafted_by' => $ownerTeam->id, 'is_drafted' => true]);
    $poke2 = LeaguePokemon::create(['league_id' => $league->id, 'pokedex_id' => $pd2->id, 'name' => 'Magearna', 'cost' => 10, 'drafted_by' => $memberTeam->id, 'is_drafted' => true]);

    return [$league, $owner, $member, $ownerTeam, $memberTeam, $poke1, $poke2];
}

// ── Enforcement: block trades after deadline ──────────────────────────────────

it('allows a trade when no deadline is set', function () {
    [$league, $owner, , , $memberTeam, $poke1, $poke2] = makeDeadlineLeague(null);

    $this->actingAs($owner)->post(route('leagues.trades.create', $league), [
        'target_team_id' => $memberTeam->id,
        'offered_pokemon_ids' => [$poke1->id],
        'requested_pokemon_ids' => [$poke2->id],
    ])->assertSessionHasNoErrors();
});

it('allows a trade before the deadline', function () {
    [$league, $owner, , , $memberTeam, $poke1, $poke2] = makeDeadlineLeague(Carbon::now()->addDays(7));

    $this->actingAs($owner)->post(route('leagues.trades.create', $league), [
        'target_team_id' => $memberTeam->id,
        'offered_pokemon_ids' => [$poke1->id],
        'requested_pokemon_ids' => [$poke2->id],
    ])->assertSessionHasNoErrors();
});

it('blocks a trade after the deadline has passed', function () {
    [$league, $owner, , , $memberTeam, $poke1, $poke2] = makeDeadlineLeague(Carbon::now()->subHour());

    $this->actingAs($owner)->post(route('leagues.trades.create', $league), [
        'target_team_id' => $memberTeam->id,
        'offered_pokemon_ids' => [$poke1->id],
        'requested_pokemon_ids' => [$poke2->id],
    ])->assertSessionHasErrors('league_id');
});

it('blocks accepting a trade after the deadline has passed', function () {
    [$league, $owner, $member, $ownerTeam, $memberTeam, $poke1, $poke2] = makeDeadlineLeague(Carbon::now()->addDays(7));

    // Create trade while deadline is in the future
    $trade = Trade::create([
        'league_id' => $league->id,
        'requesting_team_id' => $ownerTeam->id,
        'target_team_id' => $memberTeam->id,
        'status' => 'pending',
        'counterparty' => 'team',
    ]);

    // Now expire the deadline
    $league->trade_deadline_at = Carbon::now()->subMinute();
    $league->save();

    $this->actingAs($member)->put(route('leagues.trades.respond', ['league' => $league, 'trade' => $trade]), [
        'response' => 'accepted',
    ])->assertSessionHasErrors('trade');
});

// ── Scheduled job dispatch ────────────────────────────────────────────────────

it('dispatches EnforceTradeDeadlineJob when deadline is set via admin', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $league = League::create([
        'name' => 'Dispatch Test',
        'status' => LeagueStatus::RegularSeason->value,
        'league_owner' => $owner->id,
        'maximum_teams' => 10,
    ]);

    $deadline = Carbon::now()->addDays(3);

    $this->actingAs($owner)
        ->patch(route('leagues.trade-deadline.update', $league), [
            'trade_deadline_at' => $deadline->toISOString(),
        ])
        ->assertRedirect();

    Queue::assertPushed(EnforceTradeDeadlineJob::class, function ($job) use ($league, $deadline) {
        return $job->leagueId === $league->id
            && $job->scheduledDeadline->eq($deadline);
    });
});

it('dispatches a new EnforceTradeDeadlineJob when deadline is updated', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $league = League::create([
        'name' => 'Update Dispatch Test',
        'status' => LeagueStatus::RegularSeason->value,
        'league_owner' => $owner->id,
        'maximum_teams' => 10,
    ]);

    $firstDeadline = Carbon::now()->addDays(3);
    $secondDeadline = Carbon::now()->addDays(5);

    $this->actingAs($owner)
        ->patch(route('leagues.trade-deadline.update', $league), [
            'trade_deadline_at' => $firstDeadline->toISOString(),
        ]);

    $this->actingAs($owner)
        ->patch(route('leagues.trade-deadline.update', $league), [
            'trade_deadline_at' => $secondDeadline->toISOString(),
        ]);

    Queue::assertPushed(EnforceTradeDeadlineJob::class, 2);
});

it('does not dispatch a job when deadline is cleared', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $league = League::create([
        'name' => 'Clear Deadline Test',
        'status' => LeagueStatus::RegularSeason->value,
        'league_owner' => $owner->id,
        'maximum_teams' => 10,
        'trade_deadline_at' => Carbon::now()->subHour(),
    ]);

    $this->actingAs($owner)
        ->patch(route('leagues.trade-deadline.update', $league), [
            'trade_deadline_at' => null,
        ]);

    Queue::assertNotPushed(EnforceTradeDeadlineJob::class);
});

it('does not dispatch a job when the deadline is already in the past', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $league = League::create([
        'name' => 'Past Deadline Test',
        'status' => LeagueStatus::RegularSeason->value,
        'league_owner' => $owner->id,
        'maximum_teams' => 10,
    ]);

    $this->actingAs($owner)
        ->patch(route('leagues.trade-deadline.update', $league), [
            'trade_deadline_at' => Carbon::now()->subHour()->toISOString(),
        ]);

    Queue::assertNotPushed(EnforceTradeDeadlineJob::class);
});

// ── EnforceTradeDeadlineJob cancels pending trades ────────────────────────────

it('cancels pending trades when the job runs at deadline', function () {
    [$league, , , $ownerTeam, $memberTeam] = makeDeadlineLeague(Carbon::now()->subMinute());

    $trade = Trade::create([
        'league_id' => $league->id,
        'requesting_team_id' => $ownerTeam->id,
        'target_team_id' => $memberTeam->id,
        'status' => 'pending',
        'counterparty' => 'team',
    ]);

    $job = new EnforceTradeDeadlineJob($league->id, $league->trade_deadline_at);
    $job->handle();

    expect($trade->fresh()->status)->toBe('cancelled');
});

it('skips cancellation if deadline was changed after job was dispatched', function () {
    $originalDeadline = Carbon::now()->subMinute();
    [$league, , , $ownerTeam, $memberTeam] = makeDeadlineLeague($originalDeadline);

    $trade = Trade::create([
        'league_id' => $league->id,
        'requesting_team_id' => $ownerTeam->id,
        'target_team_id' => $memberTeam->id,
        'status' => 'pending',
        'counterparty' => 'team',
    ]);

    // Simulate commissioner updating the deadline after job was dispatched
    $newDeadline = Carbon::now()->addDays(2);
    $league->trade_deadline_at = $newDeadline;
    $league->save();

    // Old job still runs with the original deadline
    $job = new EnforceTradeDeadlineJob($league->id, $originalDeadline);
    $job->handle();

    // Trade should NOT be cancelled because the deadline changed
    expect($trade->fresh()->status)->toBe('pending');
});

it('non-admins cannot update the trade deadline', function () {
    $owner = User::factory()->create();
    $nonAdmin = User::factory()->create();

    $league = League::create([
        'name' => 'Auth Test',
        'status' => LeagueStatus::RegularSeason->value,
        'league_owner' => $owner->id,
        'maximum_teams' => 10,
    ]);

    $this->actingAs($nonAdmin)
        ->patch(route('leagues.trade-deadline.update', $league), [
            'trade_deadline_at' => Carbon::now()->addDays(3)->toISOString(),
        ])
        ->assertForbidden();
});
