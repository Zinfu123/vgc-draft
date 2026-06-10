<?php

use App\Enums\Trade\TradeCounterparty;
use App\Events\LeagueTransactionEvent;
use App\Events\TradePendingEvent;
use App\Models\User;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Teams\Models\Team;
use App\Modules\Trade\Models\Trade;
use Illuminate\Support\Facades\Event;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createLeagueForFreeAgencyBroadcastTests(): array
{
    $owner = User::factory()->create();

    $league = League::create([
        'name' => 'FA Broadcast League',
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
        'league_owner' => $owner->id,
    ]);

    DraftConfig::create([
        'league_id' => $league->id,
        'draft_date' => now()->addDay(),
        'draft_points' => 80,
        'ban_enabled' => false,
        'minimum_drafts' => 2,
    ]);

    $user = User::factory()->create(['discord_id' => '111111111111111111']);

    $team = Team::create([
        'name' => 'Team Alpha',
        'league_id' => $league->id,
        'user_id' => $user->id,
        'pick_position' => 1,
        'draft_points' => 80,
        'trades' => 3,
    ]);

    $pdPikachu = Pokedex::create(['nationaldex_id' => 25, 'name' => 'Pikachu', 'type1' => 'Electric']);
    $pdEevee = Pokedex::create(['nationaldex_id' => 133, 'name' => 'Eevee', 'type1' => 'Normal']);
    $pdGengar = Pokedex::create(['nationaldex_id' => 94, 'name' => 'Gengar', 'type1' => 'Ghost']);

    $pikachu = LeaguePokemon::create([
        'league_id' => $league->id,
        'pokedex_id' => $pdPikachu->id,
        'name' => 'Pikachu',
        'cost' => 10,
        'drafted_by' => $team->id,
        'is_drafted' => true,
    ]);

    LeaguePokemon::create([
        'league_id' => $league->id,
        'pokedex_id' => $pdGengar->id,
        'name' => 'Gengar',
        'cost' => 15,
        'drafted_by' => $team->id,
        'is_drafted' => true,
    ]);

    $poolMon = LeaguePokemon::create([
        'league_id' => $league->id,
        'pokedex_id' => $pdEevee->id,
        'name' => 'Eevee',
        'cost' => 5,
        'drafted_by' => null,
        'is_drafted' => false,
        'banned' => false,
    ]);

    return compact('league', 'team', 'user', 'pikachu', 'poolMon');
}

it('dispatches LeagueTransactionEvent when a free agency trade is completed', function () {
    Event::fake([LeagueTransactionEvent::class, TradePendingEvent::class]);

    ['league' => $league, 'team' => $team, 'user' => $user, 'pikachu' => $pikachu, 'poolMon' => $poolMon] = createLeagueForFreeAgencyBroadcastTests();

    $this->actingAs($user)->post(route('leagues.trades.free-agency', ['league' => $league->id]), [
        'offered_pokemon_ids' => [$pikachu->id],
        'requested_pokemon_ids' => [$poolMon->id],
    ])->assertRedirect()->assertSessionHasNoErrors();

    Event::assertDispatched(LeagueTransactionEvent::class, function (LeagueTransactionEvent $event) use ($league) {
        return $event->leagueId === $league->id;
    });

    Event::assertNotDispatched(TradePendingEvent::class);
});

it('completes a free agency trade even when live broadcast dispatch fails', function () {
    ['league' => $league, 'user' => $user, 'pikachu' => $pikachu, 'poolMon' => $poolMon] = createLeagueForFreeAgencyBroadcastTests();

    config(['broadcasting.default' => 'reverb']);

    $response = $this->actingAs($user)->post(route('leagues.trades.free-agency', ['league' => $league->id]), [
        'offered_pokemon_ids' => [$pikachu->id],
        'requested_pokemon_ids' => [$poolMon->id],
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    expect(Trade::query()
        ->where('league_id', $league->id)
        ->where('counterparty', TradeCounterparty::FreeAgency)
        ->where('status', 'accepted')
        ->exists())->toBeTrue();
});
