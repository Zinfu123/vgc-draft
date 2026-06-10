<?php

use App\Models\User;
use App\Modules\Draft\Actions\BanPokemonAction;
use App\Modules\Draft\Actions\CreateEditDraftAction;
use App\Modules\Draft\Actions\CreateEditDraftOrderAction;
use App\Modules\Draft\Actions\SkipCurrentTurnAction;
use App\Modules\Draft\Models\BanOrder;
use App\Modules\Draft\Models\Bans;
use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\Draft\Models\DraftOrder;
use App\Modules\Draft\Models\DraftPick;
use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Teams\Models\Team;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * @return array{league: League, owner: User, teams: array<int, Team>}
 */
function makeRevertActionLeague(int $teamCount = 2, bool $banEnabled = false): array
{
    $owner = User::factory()->create();

    $league = League::create([
        'name' => 'Revert Action League',
        'status' => \App\Modules\League\Enums\LeagueStatus::Staging->value,
        'open' => true,
        'league_owner' => $owner->id,
    ]);

    DraftConfig::create([
        'league_id' => $league->id,
        'draft_points' => 100,
        'minimum_drafts' => 0,
        'ban_enabled' => $banEnabled,
        'bans_per_user' => $banEnabled ? 1 : 0,
        'minimum_cost_to_ban' => 0,
        'pick_timer_enabled' => false,
    ]);

    $teams = [];
    for ($i = 1; $i <= $teamCount; $i++) {
        $user = $i === 1 ? $owner : User::factory()->create();
        $teams[] = Team::create([
            'name' => "Team {$i}",
            'league_id' => $league->id,
            'user_id' => $user->id,
            'pick_position' => $i,
            'draft_points' => 100,
            'victory_points' => 0,
            'admin_flag' => $i === 1 ? 1 : 0,
            'set_wins' => 0,
            'set_losses' => 0,
            'game_wins' => 0,
            'game_losses' => 0,
        ]);
    }

    return ['league' => $league, 'owner' => $owner, 'teams' => $teams];
}

it('records skipped_at on the draft order when a draft turn is skipped', function () {
    ['league' => $league] = makeRevertActionLeague();

    (new CreateEditDraftAction)(['league_id' => $league->id, 'command' => 'create']);
    (new CreateEditDraftOrderAction)(['league_id' => $league->id]);

    app(SkipCurrentTurnAction::class)(['league_id' => $league->id]);

    $skipped = DraftOrder::query()
        ->where('league_id', $league->id)
        ->whereNotNull('skipped_at')
        ->first();

    expect($skipped)->not->toBeNull();
    expect($skipped->status)->toBe(0);
    expect($skipped->skipped_at)->not->toBeNull();
});

it('surfaces lastSkip in DraftDetail props when a draft-phase skip is most recent', function () {
    ['league' => $league, 'owner' => $owner] = makeRevertActionLeague();

    (new CreateEditDraftAction)(['league_id' => $league->id, 'command' => 'create']);
    (new CreateEditDraftOrderAction)(['league_id' => $league->id]);

    app(SkipCurrentTurnAction::class)(['league_id' => $league->id]);

    $response = $this->actingAs($owner)->get(route('draft.detail', ['league_id' => $league->id]));

    $response->assertOk();
    $props = $response->viewData('page')['props'];

    expect($props['lastSkip'])->not->toBeNull();
    expect((int) $props['lastSkip']['round_number'])->toBe(1);
    expect((int) $props['lastSkip']['pick_number'])->toBe(1);
    expect($props['lastSkip']['skipped_at'])->not->toBeNull();
});

it('reverts the most recent skip when the commissioner clicks revert last action', function () {
    ['league' => $league, 'owner' => $owner] = makeRevertActionLeague();

    (new CreateEditDraftAction)(['league_id' => $league->id, 'command' => 'create']);
    (new CreateEditDraftOrderAction)(['league_id' => $league->id]);

    app(SkipCurrentTurnAction::class)(['league_id' => $league->id]);

    $skipped = DraftOrder::query()
        ->where('league_id', $league->id)
        ->whereNotNull('skipped_at')
        ->first();

    expect($skipped)->not->toBeNull();
    $skippedRound = (int) $skipped->round_number;
    $skippedPick = (int) $skipped->pick_number;

    $this->actingAs($owner)
        ->post(route('draft.revert-last-pick'), ['league_id' => $league->id])
        ->assertRedirect(route('draft.detail', ['league_id' => $league->id]));

    $skipped->refresh();
    expect($skipped->status)->toBe(1);
    expect($skipped->skipped_at)->toBeNull();

    $draft = Draft::query()->where('league_id', $league->id)->first();
    expect((int) $draft->round_number)->toBe($skippedRound);
    expect((int) $draft->pick_number)->toBe($skippedPick);
});

it('rewinds the round when reverting a skip that triggered a round advance', function () {
    ['league' => $league] = makeRevertActionLeague(teamCount: 1);

    (new CreateEditDraftAction)(['league_id' => $league->id, 'command' => 'create']);
    (new CreateEditDraftOrderAction)(['league_id' => $league->id]);

    app(SkipCurrentTurnAction::class)(['league_id' => $league->id]);

    $draftBefore = Draft::query()->where('league_id', $league->id)->first();
    expect((int) $draftBefore->round_number)->toBe(2);

    $round2OrderCount = DraftOrder::query()
        ->where('league_id', $league->id)
        ->where('round_number', 2)
        ->count();
    expect($round2OrderCount)->toBeGreaterThan(0);

    (new CreateEditDraftAction)(['league_id' => $league->id, 'command' => 'revert_last_pick']);

    $draftAfter = Draft::query()->where('league_id', $league->id)->first();
    expect((int) $draftAfter->round_number)->toBe(1);
    expect((int) $draftAfter->pick_number)->toBe(1);

    $remainingRound2 = DraftOrder::query()
        ->where('league_id', $league->id)
        ->where('round_number', 2)
        ->count();
    expect($remainingRound2)->toBe(0);

    $restoredOrder = DraftOrder::query()
        ->where('league_id', $league->id)
        ->where('round_number', 1)
        ->where('pick_number', 1)
        ->first();
    expect($restoredOrder)->not->toBeNull();
    expect($restoredOrder->status)->toBe(1);
    expect($restoredOrder->skipped_at)->toBeNull();
});

it('reverts a pick when a pick is more recent than a prior skip', function () {
    ['league' => $league, 'owner' => $owner, 'teams' => $teams] = makeRevertActionLeague();

    (new CreateEditDraftAction)(['league_id' => $league->id, 'command' => 'create']);
    (new CreateEditDraftOrderAction)(['league_id' => $league->id]);

    app(SkipCurrentTurnAction::class)(['league_id' => $league->id]);

    $pokedex = Pokedex::query()->first()
        ?? Pokedex::create(['name' => 'Pikachu', 'type1' => 'electric', 'type2' => null]);

    $leaguePokemon = LeaguePokemon::create([
        'league_id' => $league->id,
        'pokedex_id' => $pokedex->id,
        'name' => $pokedex->name,
        'cost' => 10,
        'is_drafted' => 0,
        'banned' => 0,
    ]);

    $currentOrder = DraftOrder::query()
        ->where('league_id', $league->id)
        ->where('status', 1)
        ->orderBy('round_number')
        ->orderBy('pick_number')
        ->first();

    $draft = Draft::query()->where('league_id', $league->id)->first();

    DraftPick::create([
        'draft_id' => $draft->id,
        'team_id' => $currentOrder->team_id,
        'league_pokemon_id' => $leaguePokemon->id,
        'round_number' => (int) $currentOrder->round_number,
        'pick_number' => (int) $currentOrder->pick_number,
        'league_id' => $league->id,
    ]);
    $leaguePokemon->update(['is_drafted' => 1, 'drafted_by' => $currentOrder->team_id]);
    $pickingTeam = Team::query()->find($currentOrder->team_id);
    $pickingTeam->draft_points -= 10;
    $pickingTeam->save();
    $currentOrder->status = 0;
    $currentOrder->save();

    expect(DraftPick::query()->where('league_id', $league->id)->count())->toBe(1);

    (new CreateEditDraftAction)(['league_id' => $league->id, 'command' => 'revert_last_pick']);

    expect(DraftPick::query()->where('league_id', $league->id)->count())->toBe(0);

    $skipped = DraftOrder::query()
        ->where('league_id', $league->id)
        ->whereNotNull('skipped_at')
        ->first();
    expect($skipped)->not->toBeNull();
    expect($skipped->status)->toBe(0);

    $pickingTeam->refresh();
    expect($pickingTeam->draft_points)->toBe(100);
});

it('reverts a successful ban and rolls draft state back to ban phase', function () {
    ['league' => $league, 'teams' => $teams] = makeRevertActionLeague(banEnabled: true);

    (new CreateEditDraftAction)(['league_id' => $league->id, 'command' => 'create']);
    (new CreateEditDraftAction)(['league_id' => $league->id, 'command' => 'create_ban']);
    (new CreateEditDraftOrderAction)(['league_id' => $league->id, 'command' => 'create_ban_order']);

    expect((int) Draft::query()->where('league_id', $league->id)->first()->status)->toBe(2);

    app(SkipCurrentTurnAction::class)(['league_id' => $league->id]);

    $pokedex = Pokedex::query()->first()
        ?? Pokedex::create(['name' => 'Pikachu', 'type1' => 'electric', 'type2' => null]);

    $leaguePokemon = LeaguePokemon::create([
        'league_id' => $league->id,
        'pokedex_id' => $pokedex->id,
        'name' => $pokedex->name,
        'cost' => 5,
        'is_drafted' => 0,
        'banned' => 0,
    ]);

    $currentBanner = BanOrder::query()
        ->where('league_id', $league->id)
        ->where('status', 1)
        ->orderBy('round_number')
        ->orderBy('ban_number')
        ->first();
    expect($currentBanner)->not->toBeNull();

    app(BanPokemonAction::class)([
        'league_id' => $league->id,
        'team_id' => $currentBanner->team_id,
        'pokemon_id' => $leaguePokemon->id,
    ]);

    $draftAfterBan = Draft::query()->where('league_id', $league->id)->first();
    expect((int) $draftAfterBan->status)->toBe(1);
    expect($leaguePokemon->fresh()->banned)->toBeTrue();

    (new CreateEditDraftAction)(['league_id' => $league->id, 'command' => 'revert_last_pick']);

    $draftReverted = Draft::query()->where('league_id', $league->id)->first();
    expect((int) $draftReverted->status)->toBe(2);

    expect($leaguePokemon->fresh()->banned)->toBeFalse();

    $restoredBan = Bans::query()
        ->where('league_id', $league->id)
        ->where('team_id', $currentBanner->team_id)
        ->where('round_number', $currentBanner->round_number)
        ->first();
    expect($restoredBan->pokedex_id)->toBeNull();

    $restoredBanOrder = BanOrder::query()
        ->where('league_id', $league->id)
        ->where('team_id', $currentBanner->team_id)
        ->where('round_number', $currentBanner->round_number)
        ->first();
    expect($restoredBanOrder->status)->toBe(1);

    expect(DraftOrder::query()->where('league_id', $league->id)->count())->toBe(0);
});

it('reverts a successful mid-phase ban without leaving ban phase when more bans remain', function () {
    ['league' => $league] = makeRevertActionLeague(teamCount: 3, banEnabled: true);

    (new CreateEditDraftAction)(['league_id' => $league->id, 'command' => 'create']);
    (new CreateEditDraftAction)(['league_id' => $league->id, 'command' => 'create_ban']);
    (new CreateEditDraftOrderAction)(['league_id' => $league->id, 'command' => 'create_ban_order']);

    $pokedex = Pokedex::query()->first()
        ?? Pokedex::create(['name' => 'Pikachu', 'type1' => 'electric', 'type2' => null]);

    $leaguePokemon = LeaguePokemon::create([
        'league_id' => $league->id,
        'pokedex_id' => $pokedex->id,
        'name' => $pokedex->name,
        'cost' => 5,
        'is_drafted' => 0,
        'banned' => 0,
    ]);

    $banner = BanOrder::query()
        ->where('league_id', $league->id)
        ->where('status', 1)
        ->orderBy('ban_number')
        ->first();

    app(BanPokemonAction::class)([
        'league_id' => $league->id,
        'team_id' => $banner->team_id,
        'pokemon_id' => $leaguePokemon->id,
    ]);

    $draftStillBanning = Draft::query()->where('league_id', $league->id)->first();
    expect((int) $draftStillBanning->status)->toBe(2);

    (new CreateEditDraftAction)(['league_id' => $league->id, 'command' => 'revert_last_pick']);

    $draftAfterRevert = Draft::query()->where('league_id', $league->id)->first();
    expect((int) $draftAfterRevert->status)->toBe(2);
    expect($leaguePokemon->fresh()->banned)->toBeFalse();

    $restored = BanOrder::query()
        ->where('league_id', $league->id)
        ->where('team_id', $banner->team_id)
        ->where('round_number', $banner->round_number)
        ->first();
    expect($restored->status)->toBe(1);
});

it('reverts a ban-phase skip and rolls draft state back to ban phase', function () {
    ['league' => $league] = makeRevertActionLeague(banEnabled: true);

    (new CreateEditDraftAction)(['league_id' => $league->id, 'command' => 'create']);
    (new CreateEditDraftAction)(['league_id' => $league->id, 'command' => 'create_ban']);
    (new CreateEditDraftOrderAction)(['league_id' => $league->id, 'command' => 'create_ban_order']);

    $draftBefore = Draft::query()->where('league_id', $league->id)->first();
    expect((int) $draftBefore->status)->toBe(2);

    foreach (range(1, 2) as $_) {
        app(SkipCurrentTurnAction::class)(['league_id' => $league->id]);
    }

    $draftAfterSkips = Draft::query()->where('league_id', $league->id)->first();
    expect((int) $draftAfterSkips->status)->toBe(1);

    (new CreateEditDraftAction)(['league_id' => $league->id, 'command' => 'revert_last_pick']);

    $draftReverted = Draft::query()->where('league_id', $league->id)->first();
    expect((int) $draftReverted->status)->toBe(2);

    $restoredBanOrder = BanOrder::query()
        ->where('league_id', $league->id)
        ->where('status', 1)
        ->whereNull('skipped_at')
        ->first();
    expect($restoredBanOrder)->not->toBeNull();
});
