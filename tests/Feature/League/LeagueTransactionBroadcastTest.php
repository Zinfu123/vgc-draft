<?php

use App\Enums\Trade\TradeCounterparty;
use App\Events\LeagueTransactionEvent;
use App\Events\TradePendingEvent;
use App\Models\User;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Models\League;
use App\Modules\Teams\Models\Team;
use App\Modules\Trade\Models\Trade;
use Illuminate\Support\Facades\Event;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createLeagueForBroadcastTests(): array
{
    $owner = User::factory()->create();

    $league = League::create([
        'name' => 'Broadcast League',
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
        'league_owner' => $owner->id,
    ]);

    DraftConfig::create([
        'league_id' => $league->id,
        'draft_date' => now()->addDay(),
        'draft_points' => 80,
        'ban_enabled' => false,
        'minimum_drafts' => 0,
    ]);

    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $teamA = Team::create([
        'name' => 'Team Alpha',
        'league_id' => $league->id,
        'user_id' => $userA->id,
        'pick_position' => 1,
        'trades' => 3,
    ]);

    $teamB = Team::create([
        'name' => 'Team Beta',
        'league_id' => $league->id,
        'user_id' => $userB->id,
        'pick_position' => 2,
        'trades' => 3,
    ]);

    return compact('league', 'teamA', 'teamB', 'userA', 'userB');
}

it('broadcasts LeagueTransactionEvent when a trade status is updated to accepted', function () {
    Event::fake([LeagueTransactionEvent::class]);

    ['league' => $league, 'teamA' => $teamA, 'teamB' => $teamB] = createLeagueForBroadcastTests();

    $trade = Trade::create([
        'league_id' => $league->id,
        'requesting_team_id' => $teamA->id,
        'target_team_id' => $teamB->id,
        'counterparty' => TradeCounterparty::Team,
        'status' => 'pending',
    ]);

    $trade->update(['status' => 'accepted']);

    Event::assertDispatched(LeagueTransactionEvent::class, function (LeagueTransactionEvent $event) use ($league) {
        return $event->leagueId === $league->id;
    });
});

it('does not broadcast LeagueTransactionEvent when a trade is declined', function () {
    Event::fake([LeagueTransactionEvent::class]);

    ['league' => $league, 'teamA' => $teamA, 'teamB' => $teamB] = createLeagueForBroadcastTests();

    $trade = Trade::create([
        'league_id' => $league->id,
        'requesting_team_id' => $teamA->id,
        'target_team_id' => $teamB->id,
        'counterparty' => TradeCounterparty::Team,
        'status' => 'pending',
    ]);

    $trade->update(['status' => 'declined']);

    Event::assertNotDispatched(LeagueTransactionEvent::class);
});

it('does not broadcast LeagueTransactionEvent when a trade is cancelled', function () {
    Event::fake([LeagueTransactionEvent::class]);

    ['league' => $league, 'teamA' => $teamA, 'teamB' => $teamB] = createLeagueForBroadcastTests();

    $trade = Trade::create([
        'league_id' => $league->id,
        'requesting_team_id' => $teamA->id,
        'target_team_id' => $teamB->id,
        'counterparty' => TradeCounterparty::Team,
        'status' => 'pending',
    ]);

    $trade->update(['status' => 'cancelled']);

    Event::assertNotDispatched(LeagueTransactionEvent::class);
});

it('broadcasts LeagueTransactionEvent on the correct channel', function () {
    ['league' => $league, 'teamA' => $teamA] = createLeagueForBroadcastTests();

    $trade = Trade::create([
        'league_id' => $league->id,
        'requesting_team_id' => $teamA->id,
        'counterparty' => TradeCounterparty::FreeAgency,
        'status' => 'pending',
    ]);

    $event = new LeagueTransactionEvent($league->id);

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0]->name)->toBe('league.transactions.'.$league->id);
});

it('broadcasts TradePendingEvent when a team trade is created targeting another team', function () {
    Event::fake([TradePendingEvent::class]);

    ['teamA' => $teamA, 'teamB' => $teamB, 'league' => $league] = createLeagueForBroadcastTests();

    Trade::create([
        'league_id' => $league->id,
        'requesting_team_id' => $teamA->id,
        'target_team_id' => $teamB->id,
        'counterparty' => TradeCounterparty::Team,
        'status' => 'pending',
    ]);

    Event::assertDispatched(TradePendingEvent::class, function (TradePendingEvent $event) use ($teamB) {
        return $event->targetTeamId === $teamB->id;
    });
});

it('does not broadcast TradePendingEvent for free agency trades', function () {
    Event::fake([TradePendingEvent::class]);

    ['teamA' => $teamA, 'league' => $league] = createLeagueForBroadcastTests();

    Trade::create([
        'league_id' => $league->id,
        'requesting_team_id' => $teamA->id,
        'target_team_id' => null,
        'counterparty' => TradeCounterparty::FreeAgency,
        'status' => 'pending',
    ]);

    Event::assertNotDispatched(TradePendingEvent::class);
});

it('broadcasts TradePendingEvent on the correct channel', function () {
    ['teamB' => $teamB] = createLeagueForBroadcastTests();

    $event = new TradePendingEvent($teamB->id);

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0]->name)->toBe('trade.pending.'.$teamB->id);
});
