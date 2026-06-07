<?php

use App\Enums\Trade\TradeCounterparty;
use App\Models\User;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Teams\Models\Team;
use App\Modules\Trade\Models\Trade;
use App\Notifications\TradeRequestNotification;
use Illuminate\Support\Facades\Notification;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createLeagueForTradeTests(): array
{
    $owner = User::factory()->create();

    $league = League::create([
        'name' => 'Trade League',
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
        'league_owner' => $owner->id,
        'discord_webhook_url' => 'https://discord.com/api/webhooks/test/token',
    ]);

    DraftConfig::create([
        'league_id' => $league->id,
        'draft_date' => now()->addDay(),
        'draft_points' => 80,
        'ban_enabled' => false,
        'minimum_drafts' => 2,
    ]);

    $userA = User::factory()->create(['discord_id' => '111111111111111111']);
    $userB = User::factory()->create(['discord_id' => '222222222222222222']);

    $teamA = Team::create([
        'name' => 'Team Alpha',
        'league_id' => $league->id,
        'user_id' => $userA->id,
        'admin_flag' => 1,
        'pick_position' => 1,
        'seed' => 1,
        'draft_points' => 80,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
        'trades' => 3,
    ]);

    $teamB = Team::create([
        'name' => 'Team Beta',
        'league_id' => $league->id,
        'user_id' => $userB->id,
        'admin_flag' => 0,
        'pick_position' => 2,
        'seed' => 2,
        'draft_points' => 80,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
        'trades' => 3,
    ]);

    $pdPikachu = Pokedex::create(['nationaldex_id' => 25, 'name' => 'Pikachu', 'type1' => 'Electric']);
    $pdCharizard = Pokedex::create(['nationaldex_id' => 6, 'name' => 'Charizard', 'type1' => 'Fire']);
    $pdGengar = Pokedex::create(['nationaldex_id' => 94, 'name' => 'Gengar', 'type1' => 'Ghost']);
    $pdBlastoise = Pokedex::create(['nationaldex_id' => 9, 'name' => 'Blastoise', 'type1' => 'Water']);
    $pdVenusaur = Pokedex::create(['nationaldex_id' => 3, 'name' => 'Venusaur', 'type1' => 'Grass']);
    $pdMew = Pokedex::create(['nationaldex_id' => 151, 'name' => 'Mew', 'type1' => 'Psychic']);

    $pikachuA = LeaguePokemon::create(['league_id' => $league->id, 'pokedex_id' => $pdPikachu->id, 'name' => 'Pikachu', 'cost' => 10, 'drafted_by' => $teamA->id]);
    $charizardA = LeaguePokemon::create(['league_id' => $league->id, 'pokedex_id' => $pdCharizard->id, 'name' => 'Charizard', 'cost' => 20, 'drafted_by' => $teamA->id]);
    $gengarA = LeaguePokemon::create(['league_id' => $league->id, 'pokedex_id' => $pdGengar->id, 'name' => 'Gengar', 'cost' => 15, 'drafted_by' => $teamA->id]);

    $blastoiseB = LeaguePokemon::create(['league_id' => $league->id, 'pokedex_id' => $pdBlastoise->id, 'name' => 'Blastoise', 'cost' => 20, 'drafted_by' => $teamB->id]);
    $venusaurB = LeaguePokemon::create(['league_id' => $league->id, 'pokedex_id' => $pdVenusaur->id, 'name' => 'Venusaur', 'cost' => 20, 'drafted_by' => $teamB->id]);
    $mewB = LeaguePokemon::create(['league_id' => $league->id, 'pokedex_id' => $pdMew->id, 'name' => 'Mew', 'cost' => 30, 'drafted_by' => $teamB->id]);

    return [$owner, $league, $teamA, $teamB, $userA, $userB, $pikachuA, $charizardA, $gengarA, $blastoiseB, $venusaurB, $mewB];
}

// ── Create Trade ─────────────────────────────────────────────────────────────

it('user can create a trade request', function () {
    Notification::fake();

    [$owner, $league, $teamA, $teamB, $userA, $userB, $pikachuA, $charizardA, $gengarA, $blastoiseB] = createLeagueForTradeTests();

    $response = $this->actingAs($userA)->post("/leagues/{$league->id}/trades", [
        'target_team_id' => $teamB->id,
        'offered_pokemon_ids' => [$pikachuA->id],
        'requested_pokemon_ids' => [$blastoiseB->id],
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $trade = Trade::where('requesting_team_id', $teamA->id)->where('target_team_id', $teamB->id)->first();
    expect($trade)->not->toBeNull();
    expect($trade->status)->toBe('pending');
    expect($trade->offeredPokemon)->toHaveCount(1);
    expect($trade->requestedPokemon)->toHaveCount(1);
});

it('sends a discord notification when a trade is created', function () {
    Notification::fake();

    [$owner, $league, $teamA, $teamB, $userA, $userB, $pikachuA, $charizardA, $gengarA, $blastoiseB] = createLeagueForTradeTests();

    $this->actingAs($userA)->post("/leagues/{$league->id}/trades", [
        'target_team_id' => $teamB->id,
        'offered_pokemon_ids' => [$pikachuA->id],
        'requested_pokemon_ids' => [$blastoiseB->id],
    ]);

    Notification::assertSentTo($league, TradeRequestNotification::class);
});

it('trade notification mentions the target user discord id', function () {
    [$owner, $league, $teamA, $teamB, $userA, $userB, $pikachuA, $charizardA, $gengarA, $blastoiseB] = createLeagueForTradeTests();

    $trade = Trade::create([
        'league_id' => $league->id,
        'requesting_team_id' => $teamA->id,
        'target_team_id' => $teamB->id,
        'status' => 'pending',
    ]);
    $trade->load('league', 'requestingTeam', 'targetTeam', 'offeredPokemon', 'requestedPokemon');

    $notification = new TradeRequestNotification($trade, $userB);
    $payload = $notification->toDiscord($league);

    expect($payload['content'])->toBe("<@{$userB->discord_id}>");
});

it('trade notification falls back to name when discord id is null', function () {
    [$owner, $league, $teamA, $teamB, $userA, $userB, $pikachuA, $charizardA, $gengarA, $blastoiseB] = createLeagueForTradeTests();
    $userB->update(['discord_id' => null]);

    $trade = Trade::create([
        'league_id' => $league->id,
        'requesting_team_id' => $teamA->id,
        'target_team_id' => $teamB->id,
        'status' => 'pending',
    ]);
    $trade->load('league', 'requestingTeam', 'targetTeam', 'offeredPokemon', 'requestedPokemon');

    $notification = new TradeRequestNotification($trade, $userB);
    $payload = $notification->toDiscord($league);

    expect($payload['content'])->toBe($userB->name);
});

it('rejects a trade if the offered pokemon does not belong to the user team', function () {
    [$owner, $league, $teamA, $teamB, $userA, $userB, $pikachuA, $charizardA, $gengarA, $blastoiseB] = createLeagueForTradeTests();

    $response = $this->actingAs($userA)->post("/leagues/{$league->id}/trades", [
        'target_team_id' => $teamB->id,
        'offered_pokemon_ids' => [$blastoiseB->id],
        'requested_pokemon_ids' => [$pikachuA->id],
    ]);

    $response->assertSessionHasErrors('offered_pokemon_ids');
});

it('rejects a trade if the requesting team lacks enough trades', function () {
    [$owner, $league, $teamA, $teamB, $userA, $userB, $pikachuA, $charizardA, $gengarA, $blastoiseB, $venusaurB, $mewB] = createLeagueForTradeTests();
    $teamA->update(['trades' => 1]);

    $response = $this->actingAs($userA)->post("/leagues/{$league->id}/trades", [
        'target_team_id' => $teamB->id,
        'offered_pokemon_ids' => [$pikachuA->id],
        'requested_pokemon_ids' => [$blastoiseB->id, $venusaurB->id],
    ]);

    $response->assertSessionHasErrors('requested_pokemon_ids');
});

it('rejects a trade if the target team lacks enough trades to receive the offered pokemon', function () {
    [$owner, $league, $teamA, $teamB, $userA, $userB, $pikachuA, $charizardA, $gengarA, $blastoiseB, $venusaurB, $mewB] = createLeagueForTradeTests();
    $teamB->update(['trades' => 0]);

    $response = $this->actingAs($userA)->post("/leagues/{$league->id}/trades", [
        'target_team_id' => $teamB->id,
        'offered_pokemon_ids' => [$pikachuA->id],
        'requested_pokemon_ids' => [$blastoiseB->id],
    ]);

    $response->assertSessionHasErrors('offered_pokemon_ids');
});

it('rejects a trade that would drop the offering team below minimum roster size', function () {
    [$owner, $league, $teamA, $teamB, $userA, $userB, $pikachuA, $charizardA, $gengarA, $blastoiseB] = createLeagueForTradeTests();

    $response = $this->actingAs($userA)->post("/leagues/{$league->id}/trades", [
        'target_team_id' => $teamB->id,
        'offered_pokemon_ids' => [$pikachuA->id, $charizardA->id, $gengarA->id],
        'requested_pokemon_ids' => [$blastoiseB->id],
    ]);

    $response->assertSessionHasErrors('offered_pokemon_ids');
});

it('user can create a team trade offering only draft points', function () {
    Notification::fake();

    [$owner, $league, $teamA, $teamB, $userA, $userB, $pikachuA, $charizardA, $gengarA, $blastoiseB] = createLeagueForTradeTests();

    $response = $this->actingAs($userA)->post("/leagues/{$league->id}/trades", [
        'target_team_id' => $teamB->id,
        'offered_pokemon_ids' => [],
        'offered_draft_points' => 25,
        'requested_pokemon_ids' => [$blastoiseB->id],
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $trade = Trade::where('requesting_team_id', $teamA->id)->where('target_team_id', $teamB->id)->first();

    expect($trade)->not->toBeNull()
        ->and($trade->status)->toBe('pending')
        ->and($trade->draft_points_delta)->toBe(-25)
        ->and($trade->offeredPokemon)->toHaveCount(0)
        ->and($trade->requestedPokemon)->toHaveCount(1);
});

it('rejects a team trade when offered draft points exceed the team balance', function () {
    [$owner, $league, $teamA, $teamB, $userA, $userB, $pikachuA, $charizardA, $gengarA, $blastoiseB] = createLeagueForTradeTests();

    $teamA->update(['draft_points' => 10]);

    $response = $this->actingAs($userA)->post("/leagues/{$league->id}/trades", [
        'target_team_id' => $teamB->id,
        'offered_pokemon_ids' => [],
        'offered_draft_points' => 25,
        'requested_pokemon_ids' => [$blastoiseB->id],
    ]);

    $response->assertSessionHasErrors('offered_draft_points');
});

it('rejects a team trade with no pokemon or draft points offered', function () {
    [$owner, $league, $teamA, $teamB, $userA, $userB, $pikachuA, $charizardA, $gengarA, $blastoiseB] = createLeagueForTradeTests();

    $response = $this->actingAs($userA)->post("/leagues/{$league->id}/trades", [
        'target_team_id' => $teamB->id,
        'offered_pokemon_ids' => [],
        'offered_draft_points' => 0,
        'requested_pokemon_ids' => [$blastoiseB->id],
    ]);

    $response->assertSessionHasErrors('offered_pokemon_ids');
});

it('target user can accept a draft-points-only team trade and points are transferred', function () {
    [$owner, $league, $teamA, $teamB, $userA, $userB, $pikachuA, $charizardA, $gengarA, $blastoiseB] = createLeagueForTradeTests();

    $teamAStartingPoints = $teamA->draft_points;
    $teamBStartingPoints = $teamB->draft_points;

    $trade = Trade::create([
        'league_id' => $league->id,
        'requesting_team_id' => $teamA->id,
        'target_team_id' => $teamB->id,
        'status' => 'pending',
        'draft_points_delta' => -25,
    ]);
    $trade->tradePokemon()->create(['league_pokemon_id' => $blastoiseB->id, 'direction' => 'requested']);

    $response = $this->actingAs($userB)->put("/leagues/{$league->id}/trades/{$trade->id}", [
        'response' => 'accepted',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    expect($trade->fresh()->status)->toBe('accepted')
        ->and($blastoiseB->fresh()->drafted_by)->toBe($teamA->id)
        ->and($teamA->fresh()->draft_points)->toBe($teamAStartingPoints - 25)
        ->and($teamB->fresh()->draft_points)->toBe($teamBStartingPoints + 25)
        ->and($teamA->fresh()->trades)->toBe(2)
        ->and($teamB->fresh()->trades)->toBe(3);
});

// ── Respond to Trade ─────────────────────────────────────────────────────────

it('target user can accept a trade and pokemon are transferred', function () {
    [$owner, $league, $teamA, $teamB, $userA, $userB, $pikachuA, $charizardA, $gengarA, $blastoiseB] = createLeagueForTradeTests();

    $trade = Trade::create([
        'league_id' => $league->id,
        'requesting_team_id' => $teamA->id,
        'target_team_id' => $teamB->id,
        'status' => 'pending',
    ]);
    $trade->tradePokemon()->create(['league_pokemon_id' => $pikachuA->id, 'direction' => 'offered']);
    $trade->tradePokemon()->create(['league_pokemon_id' => $blastoiseB->id, 'direction' => 'requested']);

    $response = $this->actingAs($userB)->put("/leagues/{$league->id}/trades/{$trade->id}", [
        'response' => 'accepted',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    expect($trade->fresh()->status)->toBe('accepted');
    expect($pikachuA->fresh()->drafted_by)->toBe($teamB->id);
    expect($blastoiseB->fresh()->drafted_by)->toBe($teamA->id);
    expect($teamA->fresh()->trades)->toBe(2);
    expect($teamB->fresh()->trades)->toBe(2);
});

it('target user can decline a trade', function () {
    [$owner, $league, $teamA, $teamB, $userA, $userB, $pikachuA, $charizardA, $gengarA, $blastoiseB] = createLeagueForTradeTests();

    $trade = Trade::create([
        'league_id' => $league->id,
        'requesting_team_id' => $teamA->id,
        'target_team_id' => $teamB->id,
        'status' => 'pending',
    ]);
    $trade->tradePokemon()->create(['league_pokemon_id' => $pikachuA->id, 'direction' => 'offered']);
    $trade->tradePokemon()->create(['league_pokemon_id' => $blastoiseB->id, 'direction' => 'requested']);

    $response = $this->actingAs($userB)->put("/leagues/{$league->id}/trades/{$trade->id}", [
        'response' => 'declined',
    ]);

    $response->assertRedirect();
    expect($trade->fresh()->status)->toBe('declined');
    expect($pikachuA->fresh()->drafted_by)->toBe($teamA->id);
    expect($blastoiseB->fresh()->drafted_by)->toBe($teamB->id);
});

it('requesting user can cancel their own pending trade', function () {
    [$owner, $league, $teamA, $teamB, $userA, $userB, $pikachuA, $charizardA, $gengarA, $blastoiseB] = createLeagueForTradeTests();

    $trade = Trade::create([
        'league_id' => $league->id,
        'requesting_team_id' => $teamA->id,
        'target_team_id' => $teamB->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($userA)->put("/leagues/{$league->id}/trades/{$trade->id}", [
        'response' => 'cancelled',
    ]);

    $response->assertRedirect();
    expect($trade->fresh()->status)->toBe('cancelled');
});

it('target user cannot cancel a trade (only requester can)', function () {
    [$owner, $league, $teamA, $teamB, $userA, $userB, $pikachuA, $charizardA, $gengarA, $blastoiseB] = createLeagueForTradeTests();

    $trade = Trade::create([
        'league_id' => $league->id,
        'requesting_team_id' => $teamA->id,
        'target_team_id' => $teamB->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($userB)->put("/leagues/{$league->id}/trades/{$trade->id}", [
        'response' => 'cancelled',
    ]);

    $response->assertSessionHasErrors('trade');
    expect($trade->fresh()->status)->toBe('pending');
});

it('rejects accepting a trade if target team does not have enough trades', function () {
    [$owner, $league, $teamA, $teamB, $userA, $userB, $pikachuA, $charizardA, $gengarA, $blastoiseB, $venusaurB, $mewB] = createLeagueForTradeTests();
    $teamB->update(['trades' => 0]);

    $trade = Trade::create([
        'league_id' => $league->id,
        'requesting_team_id' => $teamA->id,
        'target_team_id' => $teamB->id,
        'status' => 'pending',
    ]);
    $trade->tradePokemon()->create(['league_pokemon_id' => $pikachuA->id, 'direction' => 'offered']);
    $trade->tradePokemon()->create(['league_pokemon_id' => $blastoiseB->id, 'direction' => 'requested']);

    $response = $this->actingAs($userB)->put("/leagues/{$league->id}/trades/{$trade->id}", [
        'response' => 'accepted',
    ]);

    $response->assertSessionHasErrors('trade');
    expect($trade->fresh()->status)->toBe('pending');
});

it('rejects accepting a trade if requesting team no longer has enough trades', function () {
    [$owner, $league, $teamA, $teamB, $userA, $userB, $pikachuA, $charizardA, $gengarA, $blastoiseB] = createLeagueForTradeTests();
    $teamA->update(['trades' => 0]);

    $trade = Trade::create([
        'league_id' => $league->id,
        'requesting_team_id' => $teamA->id,
        'target_team_id' => $teamB->id,
        'status' => 'pending',
    ]);
    $trade->tradePokemon()->create(['league_pokemon_id' => $pikachuA->id, 'direction' => 'offered']);
    $trade->tradePokemon()->create(['league_pokemon_id' => $blastoiseB->id, 'direction' => 'requested']);

    $response = $this->actingAs($userB)->put("/leagues/{$league->id}/trades/{$trade->id}", [
        'response' => 'accepted',
    ]);

    $response->assertSessionHasErrors('trade');
    expect($trade->fresh()->status)->toBe('pending');
});

it('cannot respond to an already resolved trade', function () {
    [$owner, $league, $teamA, $teamB, $userA, $userB, $pikachuA, $charizardA, $gengarA, $blastoiseB] = createLeagueForTradeTests();

    $trade = Trade::create([
        'league_id' => $league->id,
        'requesting_team_id' => $teamA->id,
        'target_team_id' => $teamB->id,
        'status' => 'declined',
    ]);

    $response = $this->actingAs($userB)->put("/leagues/{$league->id}/trades/{$trade->id}", [
        'response' => 'accepted',
    ]);

    $response->assertSessionHasErrors('trade');
});

// ── Admin: Set Team Trades ───────────────────────────────────────────────────

it('admin can set trades for all teams in a league', function () {
    [$owner, $league, $teamA, $teamB, $userA] = createLeagueForTradeTests();

    $response = $this->actingAs($owner)->post("/leagues/{$league->id}/trades/set-team-trades", [
        'trades' => 5,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    expect($teamA->fresh()->trades)->toBe(5);
    expect($teamB->fresh()->trades)->toBe(5);
});

// ── Discord requirement for trades ────────────────────────────────────────────

it('rejects a trade request when the requesting user has no discord linked', function () {
    Notification::fake();

    [$owner, $league, $teamA, $teamB, $userA, $userB, $pikachuA, $charizardA, $gengarA, $blastoiseB] = createLeagueForTradeTests();

    $userA->update(['discord_id' => null]);

    $response = $this->actingAs($userA)->postJson("/leagues/{$league->id}/trades", [
        'target_team_id' => $teamB->id,
        'offered_pokemon_ids' => [$pikachuA->id],
        'requested_pokemon_ids' => [$blastoiseB->id],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('discord');
});

it('discord id is not settable via the profile form', function () {
    $user = User::factory()->create(['discord_id' => null]);

    $response = $this->actingAs($user)->patch('/settings/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'discord_id' => '123456789012345678',
    ]);

    $response->assertSessionHasNoErrors();
    expect($user->fresh()->discord_id)->toBeNull();
});

// ── Free agency trades ───────────────────────────────────────────────────────

it('user can complete a free agency trade and creates an accepted audit trade', function () {
    [$owner, $league, $teamA, $teamB, $userA, $userB, $pikachuA, $charizardA, $gengarA, $blastoiseB] = createLeagueForTradeTests();

    $pd = Pokedex::create(['nationaldex_id' => 133, 'name' => 'Eevee', 'type1' => 'Normal']);
    $poolMon = LeaguePokemon::create([
        'league_id' => $league->id,
        'pokedex_id' => $pd->id,
        'name' => 'Eevee',
        'cost' => 5,
        'drafted_by' => null,
        'is_drafted' => false,
        'banned' => false,
    ]);

    $beforeTrades = $teamA->trades;

    $response = $this->actingAs($userA)->post(route('leagues.trades.free-agency', ['league' => $league->id]), [
        'offered_pokemon_ids' => [$pikachuA->id],
        'requested_pokemon_ids' => [$poolMon->id],
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    expect($pikachuA->fresh()->drafted_by)->toBeNull()
        ->and($poolMon->fresh()->drafted_by)->toBe($teamA->id)
        ->and($teamA->fresh()->trades)->toBe($beforeTrades - 1);

    $trade = Trade::query()
        ->where('league_id', $league->id)
        ->where('requesting_team_id', $teamA->id)
        ->where('counterparty', TradeCounterparty::FreeAgency)
        ->first();

    expect($trade)->not->toBeNull()
        ->and($trade->target_team_id)->toBeNull()
        ->and($trade->status)->toBe('accepted')
        ->and($trade->draft_points_delta)->toBe(10 - 5)
        ->and($trade->offeredPokemon)->toHaveCount(1)
        ->and($trade->requestedPokemon)->toHaveCount(1);
});

it('rejects free agency trade when offered cost is lower than pool cost and team lacks draft points', function () {
    [$owner, $league, $teamA, $teamB, $userA, $userB, $pikachuA, $charizardA, $gengarA, $blastoiseB] = createLeagueForTradeTests();

    $teamA->update(['draft_points' => 5]);

    $pd = Pokedex::create(['nationaldex_id' => 249, 'name' => 'Lugia', 'type1' => 'Psychic']);
    $expensivePool = LeaguePokemon::create([
        'league_id' => $league->id,
        'pokedex_id' => $pd->id,
        'name' => 'Lugia',
        'cost' => 50,
        'drafted_by' => null,
        'is_drafted' => false,
        'banned' => false,
    ]);

    $response = $this->actingAs($userA)->post(route('leagues.trades.free-agency', ['league' => $league->id]), [
        'offered_pokemon_ids' => [$pikachuA->id],
        'requested_pokemon_ids' => [$expensivePool->id],
    ]);

    $response->assertSessionHasErrors('requested_pokemon_ids');
    expect($teamA->fresh()->draft_points)->toBe(5);
});

it('deducts draft points from the team when free agency trade requested cost exceeds offered cost', function () {
    [$owner, $league, $teamA, $teamB, $userA, $userB, $pikachuA, $charizardA, $gengarA, $blastoiseB] = createLeagueForTradeTests();

    $startingPoints = $teamA->draft_points;

    $pd = Pokedex::create(['nationaldex_id' => 249, 'name' => 'Lugia', 'type1' => 'Psychic']);
    $expensivePool = LeaguePokemon::create([
        'league_id' => $league->id,
        'pokedex_id' => $pd->id,
        'name' => 'Lugia',
        'cost' => 50,
        'drafted_by' => null,
        'is_drafted' => false,
        'banned' => false,
    ]);

    $response = $this->actingAs($userA)->post(route('leagues.trades.free-agency', ['league' => $league->id]), [
        'offered_pokemon_ids' => [$pikachuA->id],
        'requested_pokemon_ids' => [$expensivePool->id],
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    expect($teamA->fresh()->draft_points)->toBe($startingPoints - (50 - 10));

    $trade = Trade::query()
        ->where('league_id', $league->id)
        ->where('counterparty', TradeCounterparty::FreeAgency)
        ->first();

    expect($trade->draft_points_delta)->toBe(10 - 50);
});

it('refunds draft points to the team when free agency trade offered cost exceeds requested cost', function () {
    [$owner, $league, $teamA, $teamB, $userA, $userB, $pikachuA, $charizardA, $gengarA, $blastoiseB] = createLeagueForTradeTests();

    $startingPoints = $teamA->draft_points;

    $pd = Pokedex::create(['nationaldex_id' => 132, 'name' => 'Ditto', 'type1' => 'Normal']);
    $cheapPool = LeaguePokemon::create([
        'league_id' => $league->id,
        'pokedex_id' => $pd->id,
        'name' => 'Ditto',
        'cost' => 5,
        'drafted_by' => null,
        'is_drafted' => false,
        'banned' => false,
    ]);

    $response = $this->actingAs($userA)->post(route('leagues.trades.free-agency', ['league' => $league->id]), [
        'offered_pokemon_ids' => [$charizardA->id],
        'requested_pokemon_ids' => [$cheapPool->id],
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    expect($teamA->fresh()->draft_points)->toBe($startingPoints + (20 - 5));

    $trade = Trade::query()
        ->where('league_id', $league->id)
        ->where('counterparty', TradeCounterparty::FreeAgency)
        ->first();

    expect($trade->draft_points_delta)->toBe(20 - 5);
});

it('settles draft points during the free trade window as well', function () {
    [$owner, $league, $teamA, $teamB, $userA, $userB, $pikachuA, $charizardA, $gengarA, $blastoiseB] = createLeagueForTradeTests();

    $league->status = \App\Modules\League\Enums\LeagueStatus::Staging;
    $league->staging_sub_status = \App\Modules\League\Enums\LeagueStagingStatus::FreeTradeWindow;
    $league->free_trade_window_hours = 48;
    $league->save();

    $league->draftConfig()->update(['draft_ended_at' => now()->subHour()]);

    $startingPoints = $teamA->draft_points;
    $startingTrades = $teamA->trades;

    $pd = Pokedex::create(['nationaldex_id' => 132, 'name' => 'Ditto', 'type1' => 'Normal']);
    $cheapPool = LeaguePokemon::create([
        'league_id' => $league->id,
        'pokedex_id' => $pd->id,
        'name' => 'Ditto',
        'cost' => 5,
        'drafted_by' => null,
        'is_drafted' => false,
        'banned' => false,
    ]);

    $response = $this->actingAs($userA)->post(route('leagues.trades.free-agency', ['league' => $league->id]), [
        'offered_pokemon_ids' => [$charizardA->id],
        'requested_pokemon_ids' => [$cheapPool->id],
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $teamA->refresh();
    expect($teamA->draft_points)->toBe($startingPoints + (20 - 5));
    expect($teamA->trades)->toBe($startingTrades);
});

it('does not change draft points when free agency trade costs are equal', function () {
    [$owner, $league, $teamA, $teamB, $userA, $userB, $pikachuA, $charizardA, $gengarA, $blastoiseB] = createLeagueForTradeTests();

    $startingPoints = $teamA->draft_points;

    $pd = Pokedex::create(['nationaldex_id' => 133, 'name' => 'Eevee', 'type1' => 'Normal']);
    $poolMon = LeaguePokemon::create([
        'league_id' => $league->id,
        'pokedex_id' => $pd->id,
        'name' => 'Eevee',
        'cost' => 10,
        'drafted_by' => null,
        'is_drafted' => false,
        'banned' => false,
    ]);

    $response = $this->actingAs($userA)->post(route('leagues.trades.free-agency', ['league' => $league->id]), [
        'offered_pokemon_ids' => [$pikachuA->id],
        'requested_pokemon_ids' => [$poolMon->id],
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    expect($teamA->fresh()->draft_points)->toBe($startingPoints);

    $trade = Trade::query()
        ->where('league_id', $league->id)
        ->where('counterparty', TradeCounterparty::FreeAgency)
        ->first();

    expect($trade->draft_points_delta)->toBeNull();
});

it('charges one trade slot per pokemon picked up in a free agency trade', function () {
    [$owner, $league, $teamA, $teamB, $userA, $userB, $pikachuA, $charizardA, $gengarA, $blastoiseB, $venusaurB, $mewB] = createLeagueForTradeTests();

    $beforeTrades = $teamA->trades;

    $pdKommoO = Pokedex::create(['nationaldex_id' => 784, 'name' => 'Kommo-o', 'type1' => 'Dragon']);
    $pdYanma = Pokedex::create(['nationaldex_id' => 193, 'name' => 'Yanma', 'type1' => 'Bug']);

    $kommoO = LeaguePokemon::create([
        'league_id' => $league->id,
        'pokedex_id' => $pdKommoO->id,
        'name' => 'Kommo-o',
        'cost' => 20,
        'drafted_by' => null,
        'is_drafted' => false,
        'banned' => false,
    ]);

    $yanma = LeaguePokemon::create([
        'league_id' => $league->id,
        'pokedex_id' => $pdYanma->id,
        'name' => 'Yanma',
        'cost' => 10,
        'drafted_by' => null,
        'is_drafted' => false,
        'banned' => false,
    ]);

    $response = $this->actingAs($userA)->post(route('leagues.trades.free-agency', ['league' => $league->id]), [
        'offered_pokemon_ids' => [$pikachuA->id, $charizardA->id],
        'requested_pokemon_ids' => [$kommoO->id, $yanma->id],
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    expect($teamA->fresh()->trades)->toBe($beforeTrades - 2);
});

it('user can complete a free agency trade using only draft points', function () {
    [$owner, $league, $teamA, $teamB, $userA, $userB, $pikachuA, $charizardA, $gengarA, $blastoiseB] = createLeagueForTradeTests();

    $startingPoints = $teamA->draft_points;
    $beforeTrades = $teamA->trades;

    $pd = Pokedex::create(['nationaldex_id' => 133, 'name' => 'Eevee', 'type1' => 'Normal']);
    $poolMon = LeaguePokemon::create([
        'league_id' => $league->id,
        'pokedex_id' => $pd->id,
        'name' => 'Eevee',
        'cost' => 15,
        'drafted_by' => null,
        'is_drafted' => false,
        'banned' => false,
    ]);

    $response = $this->actingAs($userA)->post(route('leagues.trades.free-agency', ['league' => $league->id]), [
        'offered_pokemon_ids' => [],
        'requested_pokemon_ids' => [$poolMon->id],
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    expect($poolMon->fresh()->drafted_by)->toBe($teamA->id)
        ->and($teamA->fresh()->draft_points)->toBe($startingPoints - 15)
        ->and($teamA->fresh()->trades)->toBe($beforeTrades - 1);

    $trade = Trade::query()
        ->where('league_id', $league->id)
        ->where('counterparty', TradeCounterparty::FreeAgency)
        ->latest('id')
        ->first();

    expect($trade->draft_points_delta)->toBe(-15)
        ->and($trade->offeredPokemon)->toHaveCount(0)
        ->and($trade->requestedPokemon)->toHaveCount(1);
});

it('includes all league accepted trades in trade history on the trades page', function () {
    [$owner, $league, $teamA, $teamB, $userA, $userB] = createLeagueForTradeTests();

    Trade::create([
        'league_id' => $league->id,
        'requesting_team_id' => $teamA->id,
        'target_team_id' => null,
        'counterparty' => TradeCounterparty::FreeAgency,
        'status' => 'accepted',
    ]);

    Trade::create([
        'league_id' => $league->id,
        'requesting_team_id' => $teamB->id,
        'target_team_id' => null,
        'counterparty' => TradeCounterparty::FreeAgency,
        'status' => 'accepted',
    ]);

    $response = $this->actingAs($userA)->get(route('leagues.trades', ['league' => $league->id]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('league/LeagueDetailTrades')
        ->has('leagueTradeHistory', 2)
        ->where('trades', fn ($trades) => count($trades) === 1)
    );
});
