<?php

use App\Events\EndDraftEvent;
use App\Models\User;
use App\Modules\Draft\Actions\CreateEditDraftOrderAction;
use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Models\League;
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Facades\Event;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function createLeagueWithZeroPointTeams(): array
{
    $owner = User::factory()->create();
    $league = League::create([
        'name' => 'End Draft League',
        'status' => \App\Modules\League\Enums\LeagueStatus::Staging->value,
        'open' => true,
        'league_owner' => $owner->id,
    ]);

    DraftConfig::create([
        'league_id' => $league->id,
        'draft_points' => 0,
        'minimum_drafts' => 0,
        'enforce_round_count' => false,
        'ban_enabled' => false,
        'bans_per_user' => 0,
        'minimum_cost_to_ban' => 0,
    ]);

    $user = User::factory()->create();
    $team = Team::create([
        'name' => 'Team 1',
        'league_id' => $league->id,
        'user_id' => $user->id,
        'pick_position' => 1,
        'draft_points' => 0,
        'victory_points' => 0,
        'admin_flag' => 1,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
    ]);

    $draft = Draft::create([
        'league_id' => $league->id,
        'status' => 1,
        'round_number' => 1,
        'pick_number' => 1,
    ]);

    return [$league, $team, $draft];
}

it('dispatches EndDraftEvent with draft_id when all teams have zero draft points', function () {
    Event::fake([EndDraftEvent::class]);

    [$league, $team, $draft] = createLeagueWithZeroPointTeams();

    (new CreateEditDraftOrderAction)(['league_id' => $league->id]);

    Event::assertDispatched(EndDraftEvent::class, function (EndDraftEvent $event) use ($draft): bool {
        return $event->data['draft_id'] === $draft->id
            && $event->data['end_draft'] === 1
            && $event->data['league_id'] === $draft->league_id;
    });
});

it('sets draft status to 0 when all teams have zero draft points', function () {
    Event::fake([EndDraftEvent::class]);

    [$league, $team, $draft] = createLeagueWithZeroPointTeams();

    (new CreateEditDraftOrderAction)(['league_id' => $league->id]);

    expect($draft->fresh()->status)->toBe(0);
});

it('broadcasts EndDraftEvent on the correct channel using draft_id', function () {
    Event::fake([EndDraftEvent::class]);

    [$league, $team, $draft] = createLeagueWithZeroPointTeams();

    (new CreateEditDraftOrderAction)(['league_id' => $league->id]);

    Event::assertDispatched(EndDraftEvent::class, function (EndDraftEvent $event) use ($draft): bool {
        $channels = $event->broadcastOn();

        return count($channels) === 1
            && $channels[0]->name === 'end.draft.'.$draft->id;
    });
});

it('adjusts set_start_date to today when draft ends and set_start_date is in the past', function () {
    Event::fake([EndDraftEvent::class]);

    [$league, $team, $draft] = createLeagueWithZeroPointTeams();

    $league->set_start_date = now()->subMonth()->toDateString();
    $league->save();

    (new CreateEditDraftOrderAction)(['league_id' => $league->id]);

    expect($league->fresh()->set_start_date)->toBe(now()->toDateString());
});

it('does not change set_start_date when it is in the future', function () {
    Event::fake([EndDraftEvent::class]);

    [$league, $team, $draft] = createLeagueWithZeroPointTeams();

    $futureDate = now()->addMonth()->toDateString();
    $league->set_start_date = $futureDate;
    $league->save();

    (new CreateEditDraftOrderAction)(['league_id' => $league->id]);

    expect($league->fresh()->set_start_date)->toBe($futureDate);
});
