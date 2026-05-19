<?php

use App\Models\User;
use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Models\League;
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Facades\Notification;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * @return array{0: League, 1: User, 2: User, 3: Team}
 */
function makeStartDraftLeague(bool $withTeam = true, bool $banEnabled = false): array
{
    $owner = User::factory()->create();
    $coach = User::factory()->create();

    $league = League::create([
        'name' => 'Start Draft League',
        'status' => \App\Modules\League\Enums\LeagueStatus::Staging->value,
        'league_owner' => $owner->id,
        'maximum_teams' => 10,
    ]);

    DraftConfig::create([
        'league_id' => $league->id,
        'draft_date' => '2026-05-15',
        'draft_points' => 80,
        'minimum_drafts' => 1,
        'ban_enabled' => $banEnabled,
        'bans_per_user' => $banEnabled ? 1 : null,
        'minimum_cost_to_ban' => $banEnabled ? 0 : null,
    ]);

    $team = null;
    if ($withTeam) {
        $team = Team::create([
            'league_id' => $league->id,
            'user_id' => $coach->id,
            'name' => 'Alpha',
            'pick_position' => 1,
            'draft_points' => 80,
            'admin_flag' => 0,
            'set_wins' => 0,
            'set_losses' => 0,
            'game_wins' => 0,
            'game_losses' => 0,
            'victory_points' => 0,
        ]);
    }

    return [$league, $owner, $coach, $team];
}

it('exposes start-draft flags to the commissioner draft admin page', function () {
    Notification::fake();
    [$league, $owner] = makeStartDraftLeague();

    $this->actingAs($owner)
        ->get(route('leagues.admin.draft', ['league' => $league->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('league/admin/DraftSettings')
            ->where('canStartDraft', true)
            ->where('draftExists', false)
            ->where('activeTeamCount', 1));
});

it('marks draft as not startable when no teams exist yet', function () {
    Notification::fake();
    [$league, $owner] = makeStartDraftLeague(withTeam: false);

    $this->actingAs($owner)
        ->get(route('leagues.admin.draft', ['league' => $league->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canStartDraft', false)
            ->where('activeTeamCount', 0));
});

it('marks draft as not startable when a draft already exists', function () {
    Notification::fake();
    [$league, $owner] = makeStartDraftLeague();

    Draft::create([
        'league_id' => $league->id,
        'status' => 1,
        'round_number' => 1,
        'pick_number' => 1,
    ]);

    $this->actingAs($owner)
        ->get(route('leagues.admin.draft', ['league' => $league->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canStartDraft', false)
            ->where('draftExists', true));
});

it('lets the league owner start a draft from the commissioner page', function () {
    Notification::fake();
    [$league, $owner] = makeStartDraftLeague();

    $this->actingAs($owner)
        ->post(route('draft.create'), ['league_id' => $league->id])
        ->assertRedirect(route('draft.detail', ['league_id' => $league->id]));

    expect(Draft::where('league_id', $league->id)->exists())->toBeTrue();
});

it('lets a co-admin team owner start a draft', function () {
    Notification::fake();
    [$league, , $coach, $team] = makeStartDraftLeague();
    $team->admin_flag = 1;
    $team->save();

    $this->actingAs($coach)
        ->post(route('draft.create'), ['league_id' => $league->id])
        ->assertRedirect(route('draft.detail', ['league_id' => $league->id]));

    expect(Draft::where('league_id', $league->id)->exists())->toBeTrue();
});

it('forbids a coach without admin flag from starting a draft', function () {
    Notification::fake();
    [$league, , $coach] = makeStartDraftLeague();

    $this->actingAs($coach)
        ->post(route('draft.create'), ['league_id' => $league->id])
        ->assertForbidden();

    expect(Draft::where('league_id', $league->id)->exists())->toBeFalse();
});

it('forbids a non-member from starting a draft', function () {
    Notification::fake();
    [$league] = makeStartDraftLeague();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->post(route('draft.create'), ['league_id' => $league->id])
        ->assertForbidden();

    expect(Draft::where('league_id', $league->id)->exists())->toBeFalse();
});

it('rejects starting a draft when one already exists', function () {
    Notification::fake();
    [$league, $owner] = makeStartDraftLeague();

    Draft::create([
        'league_id' => $league->id,
        'status' => 1,
        'round_number' => 1,
        'pick_number' => 1,
    ]);

    $this->actingAs($owner)
        ->post(route('draft.create'), ['league_id' => $league->id])
        ->assertSessionHasErrors('league_id');

    expect(Draft::where('league_id', $league->id)->count())->toBe(1);
});

it('rejects starting a draft when the league has no teams', function () {
    Notification::fake();
    [$league, $owner] = makeStartDraftLeague(withTeam: false);

    $this->actingAs($owner)
        ->post(route('draft.create'), ['league_id' => $league->id])
        ->assertSessionHasErrors('league_id');

    expect(Draft::where('league_id', $league->id)->exists())->toBeFalse();
});
