<?php

use App\Models\User;
use App\Modules\League\Enums\LeagueStatus;
use App\Modules\League\Models\League;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * @return array{User, League}
 */
function makeFreeTradeWindowLeague(int $hours = 24): array
{
    $owner = User::factory()->create();

    $league = League::create([
        'name' => 'Free Window League',
        'status' => LeagueStatus::RegularSeason->value,
        'league_owner' => $owner->id,
        'maximum_teams' => 10,
        'free_trade_window_hours' => $hours,
    ]);

    return [$owner, $league];
}

it('allows the league owner to update the free trade window hours', function () {
    [$owner, $league] = makeFreeTradeWindowLeague(24);

    $this->actingAs($owner)
        ->patch(route('leagues.free-trade-window.update', ['league' => $league->id]), [
            'free_trade_window_hours' => 48,
        ])
        ->assertRedirect();

    expect($league->fresh()->free_trade_window_hours)->toBe(48);
});

it('allows setting the free trade window hours to zero', function () {
    [$owner, $league] = makeFreeTradeWindowLeague(24);

    $this->actingAs($owner)
        ->patch(route('leagues.free-trade-window.update', ['league' => $league->id]), [
            'free_trade_window_hours' => 0,
        ])
        ->assertSessionHasNoErrors();

    expect($league->fresh()->free_trade_window_hours)->toBe(0);
});

it('forbids non-admins from updating the free trade window', function () {
    [, $league] = makeFreeTradeWindowLeague(24);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->patch(route('leagues.free-trade-window.update', ['league' => $league->id]), [
            'free_trade_window_hours' => 12,
        ])
        ->assertForbidden();

    expect($league->fresh()->free_trade_window_hours)->toBe(24);
});

it('rejects negative free trade window hours', function () {
    [$owner, $league] = makeFreeTradeWindowLeague(24);

    $this->actingAs($owner)
        ->patch(route('leagues.free-trade-window.update', ['league' => $league->id]), [
            'free_trade_window_hours' => -1,
        ])
        ->assertSessionHasErrors('free_trade_window_hours');

    expect($league->fresh()->free_trade_window_hours)->toBe(24);
});

it('rejects non-integer free trade window hours', function () {
    [$owner, $league] = makeFreeTradeWindowLeague(24);

    $this->actingAs($owner)
        ->patch(route('leagues.free-trade-window.update', ['league' => $league->id]), [
            'free_trade_window_hours' => 'abc',
        ])
        ->assertSessionHasErrors('free_trade_window_hours');

    expect($league->fresh()->free_trade_window_hours)->toBe(24);
});
