<?php

use App\Models\User;
use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\Draft\Models\DraftPick;
use App\Modules\League\Models\League;
use App\Modules\Teams\Models\Team;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * @return array{league: League, owner: User, team: Team}
 */
function makeRevertPickLeague(): array
{
    $owner = User::factory()->create();

    $league = League::create([
        'name' => 'Revert League',
        'status' => \App\Modules\League\Enums\LeagueStatus::Staging->value,
        'league_owner' => $owner->id,
    ]);

    DraftConfig::create([
        'league_id' => $league->id,
        'draft_points' => 100,
        'minimum_drafts' => 1,
        'ban_enabled' => false,
    ]);

    $team = Team::create([
        'league_id' => $league->id,
        'user_id' => $owner->id,
        'name' => 'Team Alpha',
        'pick_position' => 1,
        'draft_points' => 100,
        'admin_flag' => 1,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
        'victory_points' => 0,
    ]);

    Draft::create([
        'league_id' => $league->id,
        'round_number' => 1,
        'pick_number' => 1,
        'status' => 1,
    ]);

    return ['league' => $league, 'owner' => $owner, 'team' => $team];
}

it('returns successfully when there are no picks to revert', function () {
    ['league' => $league, 'owner' => $owner] = makeRevertPickLeague();

    expect(DraftPick::where('league_id', $league->id)->exists())->toBeFalse();

    $this->actingAs($owner)
        ->post(route('draft.revert-last-pick'), ['league_id' => $league->id])
        ->assertRedirect(route('draft.detail', ['league_id' => $league->id]));
});
