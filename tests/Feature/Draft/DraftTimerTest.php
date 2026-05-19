<?php

use App\Models\User;
use App\Modules\Draft\Actions\CreateEditDraftAction;
use App\Modules\Draft\Actions\CreateEditDraftOrderAction;
use App\Modules\Draft\Actions\DraftTimerAction;
use App\Modules\Draft\Actions\SkipCurrentTurnAction;
use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\Draft\Models\DraftOrder;
use App\Modules\League\Models\League;
use App\Modules\Teams\Models\Team;
use App\Notifications\DraftNextTurnNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeTimerLeague(
    int $pickTimerSeconds = 3600,
    bool $quietHoursEnabled = false,
    string $quietHoursStart = '00:00',
    string $quietHoursEnd = '08:00',
    string $quietHoursTimezone = 'UTC',
    int $teamCount = 3,
    int $draftPoints = 100,
    int $minimumDrafts = 0,
    ?string $discordWebhookUrl = null,
    bool $pickTimerEnabled = true,
): array {
    $owner = User::factory()->create();
    $league = League::create([
        'name' => 'Timer League',
        'status' => \App\Modules\League\Enums\LeagueStatus::Staging->value,
        'open' => true,
        'league_owner' => $owner->id,
        'discord_webhook_url' => $discordWebhookUrl,
    ]);

    DraftConfig::create([
        'league_id' => $league->id,
        'draft_points' => $draftPoints,
        'minimum_drafts' => $minimumDrafts,
        'ban_enabled' => false,
        'pick_timer_enabled' => $pickTimerEnabled,
        'pick_timer_seconds' => $pickTimerSeconds,
        'quiet_hours_enabled' => $quietHoursEnabled,
        'quiet_hours_start' => $quietHoursStart,
        'quiet_hours_end' => $quietHoursEnd,
        'quiet_hours_timezone' => $quietHoursTimezone,
    ]);

    $teams = [];
    for ($i = 1; $i <= $teamCount; $i++) {
        $user = User::factory()->create();
        $teams[] = Team::create([
            'name' => "Timer Team {$i}",
            'league_id' => $league->id,
            'user_id' => $user->id,
            'pick_position' => $i,
            'draft_points' => $draftPoints,
            'victory_points' => 0,
            'admin_flag' => $i === 1 ? 1 : 0,
            'set_wins' => 0,
            'set_losses' => 0,
            'game_wins' => 0,
            'game_losses' => 0,
        ]);
    }

    return ['owner' => $owner, 'league' => $league, 'teams' => $teams];
}

it('saves pick timer + quiet hours config via existing endpoint', function () {
    ['owner' => $owner, 'league' => $league] = makeTimerLeague(pickTimerEnabled: false, pickTimerSeconds: 60);

    $this->actingAs($owner)
        ->patch(route('leagues.admin.draft-config.update', ['league' => $league->id]), [
            'draft_points' => 100,
            'minimum_drafts' => 0,
            'ban_enabled' => false,
            'pick_timer_enabled' => true,
            'pick_timer_seconds' => 7200,
            'quiet_hours_enabled' => true,
            'quiet_hours_start' => '00:00',
            'quiet_hours_end' => '08:00',
            'quiet_hours_timezone' => 'America/New_York',
        ])
        ->assertRedirect();

    $config = DraftConfig::query()->where('league_id', $league->id)->first();
    expect($config->pick_timer_enabled)->toBeTrue();
    expect($config->pick_timer_seconds)->toBe(7200);
    expect($config->quiet_hours_enabled)->toBeTrue();
    expect($config->quiet_hours_timezone)->toBe('America/New_York');
});

it('rejects invalid pick timer values', function () {
    ['owner' => $owner, 'league' => $league] = makeTimerLeague();

    $this->actingAs($owner)
        ->patch(route('leagues.admin.draft-config.update', ['league' => $league->id]), [
            'draft_points' => 100,
            'minimum_drafts' => 0,
            'ban_enabled' => false,
            'pick_timer_enabled' => true,
            'pick_timer_seconds' => 30,
        ])
        ->assertSessionHasErrors('pick_timer_seconds');
});

it('start_turn populates current_deadline_at on draft creation', function () {
    ['league' => $league] = makeTimerLeague(pickTimerSeconds: 600);

    (new CreateEditDraftAction)(['league_id' => $league->id, 'command' => 'create']);
    (new CreateEditDraftOrderAction)(['league_id' => $league->id]);
    Carbon::setTestNow(Carbon::parse('2026-05-19 12:00:00', 'UTC'));
    (new DraftTimerAction)(['league_id' => $league->id, 'command' => DraftTimerAction::COMMAND_START_TURN]);

    $draft = Draft::query()->where('league_id', $league->id)->first();
    expect($draft->current_deadline_at?->toDateTimeString())->toBe('2026-05-19 12:10:00');

    Carbon::setTestNow();
});

it('pause captures remaining seconds and resume restores the deadline', function () {
    ['league' => $league] = makeTimerLeague(pickTimerSeconds: 600);

    Carbon::setTestNow(Carbon::parse('2026-05-19 12:00:00', 'UTC'));
    Draft::create([
        'league_id' => $league->id,
        'round_number' => 1,
        'pick_number' => 1,
        'status' => 1,
        'current_deadline_at' => Carbon::now()->addMinutes(7),
    ]);

    (new DraftTimerAction)(['league_id' => $league->id, 'command' => DraftTimerAction::COMMAND_PAUSE]);

    $draft = Draft::query()->where('league_id', $league->id)->first();
    expect($draft->paused_at)->not->toBeNull();
    expect($draft->paused_remaining_seconds)->toBe(420);

    Carbon::setTestNow(Carbon::parse('2026-05-19 13:00:00', 'UTC'));
    (new DraftTimerAction)(['league_id' => $league->id, 'command' => DraftTimerAction::COMMAND_RESUME]);

    $draft->refresh();
    expect($draft->paused_at)->toBeNull();
    expect($draft->current_deadline_at?->toDateTimeString())->toBe('2026-05-19 13:07:00');

    Carbon::setTestNow();
});

it('adjusts the deadline when running, and the remaining seconds when paused', function () {
    ['league' => $league] = makeTimerLeague();

    Carbon::setTestNow(Carbon::parse('2026-05-19 12:00:00', 'UTC'));
    Draft::create([
        'league_id' => $league->id,
        'round_number' => 1,
        'pick_number' => 1,
        'status' => 1,
        'current_deadline_at' => Carbon::now()->addMinutes(10),
    ]);

    (new DraftTimerAction)(['league_id' => $league->id, 'command' => DraftTimerAction::COMMAND_ADJUST, 'delta_seconds' => 1800]);
    expect(Draft::query()->where('league_id', $league->id)->first()->current_deadline_at?->toDateTimeString())->toBe('2026-05-19 12:40:00');

    (new DraftTimerAction)(['league_id' => $league->id, 'command' => DraftTimerAction::COMMAND_PAUSE]);
    (new DraftTimerAction)(['league_id' => $league->id, 'command' => DraftTimerAction::COMMAND_ADJUST, 'delta_seconds' => -600]);

    $draft = Draft::query()->where('league_id', $league->id)->first();
    expect($draft->paused_remaining_seconds)->toBe(1800);

    Carbon::setTestNow();
});

it('tick command does not expire a turn during quiet hours', function () {
    ['league' => $league, 'teams' => $teams] = makeTimerLeague(
        pickTimerSeconds: 600,
        quietHoursEnabled: true,
        quietHoursStart: '00:00',
        quietHoursEnd: '08:00',
        quietHoursTimezone: 'UTC',
    );

    Carbon::setTestNow(Carbon::parse('2026-05-19 02:00:00', 'UTC'));
    (new CreateEditDraftAction)(['league_id' => $league->id, 'command' => 'create']);
    (new CreateEditDraftOrderAction)(['league_id' => $league->id]);

    Draft::query()->where('league_id', $league->id)->update([
        'current_deadline_at' => Carbon::parse('2026-05-19 01:55:00', 'UTC'),
    ]);

    $orderBefore = DraftOrder::query()
        ->where('league_id', $league->id)
        ->where('status', 1)
        ->orderBy('pick_number')
        ->first();

    $this->artisan('draft:tick-timers')->assertSuccessful();

    $orderAfter = DraftOrder::query()->where('id', $orderBefore->id)->first();
    expect((int) $orderAfter->status)->toBe(1);

    $draft = Draft::query()->where('league_id', $league->id)->first();
    expect($draft->current_deadline_at?->gt(Carbon::now()))->toBeTrue();

    Carbon::setTestNow();
});

it('tick command keeps remaining time constant across quiet hours', function () {
    ['league' => $league] = makeTimerLeague(
        pickTimerSeconds: 1800,
        quietHoursEnabled: true,
        quietHoursStart: '00:00',
        quietHoursEnd: '08:00',
        quietHoursTimezone: 'UTC',
    );

    // Start a turn 15 minutes before quiet hours begin so the team has time on the clock
    // when the window opens; the deadline lands inside quiet hours at 00:15.
    Carbon::setTestNow(Carbon::parse('2026-05-19 23:45:00', 'UTC'));
    (new CreateEditDraftAction)(['league_id' => $league->id, 'command' => 'create']);
    (new CreateEditDraftOrderAction)(['league_id' => $league->id]);
    (new DraftTimerAction)(['league_id' => $league->id, 'command' => DraftTimerAction::COMMAND_START_TURN]);

    Carbon::setTestNow(Carbon::parse('2026-05-20 00:00:00', 'UTC'));
    $draftStart = Draft::query()->where('league_id', $league->id)->first();
    $startingRemaining = (int) round(Carbon::now()->diffInSeconds($draftStart->current_deadline_at, false));

    foreach (range(1, 480) as $minutesElapsed) {
        Carbon::setTestNow(Carbon::parse('2026-05-20 00:00:00', 'UTC')->addMinutes($minutesElapsed));
        $this->artisan('draft:tick-timers')->assertSuccessful();
    }

    Carbon::setTestNow(Carbon::parse('2026-05-20 08:00:00', 'UTC'));
    $draftEnd = Draft::query()->where('league_id', $league->id)->first();
    $endingRemaining = (int) round(Carbon::now()->diffInSeconds($draftEnd->current_deadline_at, false));

    // Remaining time across quiet hours should drift no more than ~2 minutes (clamping + tick slop).
    expect(abs($endingRemaining - $startingRemaining))->toBeLessThanOrEqual(120);

    Carbon::setTestNow();
});

it('tick command auto-skips when deadline passes outside quiet hours', function () {
    ['league' => $league, 'teams' => $teams] = makeTimerLeague();

    Carbon::setTestNow(Carbon::parse('2026-05-19 12:00:00', 'UTC'));
    (new CreateEditDraftAction)(['league_id' => $league->id, 'command' => 'create']);
    (new CreateEditDraftOrderAction)(['league_id' => $league->id]);

    Draft::query()->where('league_id', $league->id)->update([
        'current_deadline_at' => Carbon::parse('2026-05-19 11:50:00', 'UTC'),
    ]);

    $firstOrder = DraftOrder::query()
        ->where('league_id', $league->id)
        ->where('status', 1)
        ->orderBy('pick_number')
        ->first();

    $this->artisan('draft:tick-timers')->assertSuccessful();

    $skipped = DraftOrder::query()->where('id', $firstOrder->id)->first();
    expect((int) $skipped->status)->toBe(0);

    Carbon::setTestNow();
});

it('skip dispatches a Discord notification to the next user', function () {
    Notification::fake();

    ['league' => $league, 'teams' => $teams] = makeTimerLeague(discordWebhookUrl: 'https://discord.example/hook');

    (new CreateEditDraftAction)(['league_id' => $league->id, 'command' => 'create']);
    (new CreateEditDraftOrderAction)(['league_id' => $league->id]);

    app(SkipCurrentTurnAction::class)(['league_id' => $league->id]);

    Notification::assertSentTimes(DraftNextTurnNotification::class, 1);
});

it('does not notify when a skip ends the draft', function () {
    Notification::fake();

    ['league' => $league, 'teams' => $teams] = makeTimerLeague(
        teamCount: 1,
        draftPoints: 0,
        discordWebhookUrl: 'https://discord.example/hook',
    );

    (new CreateEditDraftAction)(['league_id' => $league->id, 'command' => 'create']);
    (new CreateEditDraftOrderAction)(['league_id' => $league->id]);

    app(SkipCurrentTurnAction::class)(['league_id' => $league->id]);

    Notification::assertNotSentTo($league, DraftNextTurnNotification::class);
});

it('rejects pause/resume/adjust/skip for non-admin users', function () {
    ['league' => $league] = makeTimerLeague();

    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->post(route('draft.timer.pause'), ['league_id' => $league->id])
        ->assertForbidden();

    $this->actingAs($outsider)
        ->post(route('draft.timer.resume'), ['league_id' => $league->id])
        ->assertForbidden();

    $this->actingAs($outsider)
        ->post(route('draft.timer.adjust'), ['league_id' => $league->id, 'delta_seconds' => 60])
        ->assertForbidden();

    $this->actingAs($outsider)
        ->post(route('draft.timer.skip'), ['league_id' => $league->id])
        ->assertForbidden();
});

it('skip condition gates the schedule based on active timer-enabled drafts', function () {
    $shouldSkip = fn (): bool => ! \App\Modules\Draft\Models\Draft::query()
        ->join('draft_config', 'draft_config.league_id', '=', 'drafts.league_id')
        ->whereIn('drafts.status', [1, 2])
        ->where('draft_config.pick_timer_enabled', true)
        ->exists();

    expect($shouldSkip())->toBeTrue();

    ['league' => $league] = makeTimerLeague(pickTimerEnabled: false);

    expect($shouldSkip())->toBeTrue();

    (new CreateEditDraftAction)(['league_id' => $league->id, 'command' => 'create']);
    (new CreateEditDraftOrderAction)(['league_id' => $league->id]);

    expect($shouldSkip())->toBeTrue();

    DraftConfig::query()->where('league_id', $league->id)->update(['pick_timer_enabled' => true]);

    expect($shouldSkip())->toBeFalse();

    Draft::query()->where('league_id', $league->id)->update(['status' => 0]);

    expect($shouldSkip())->toBeTrue();
});

it('finalizes the draft when an entire round produces no picks', function () {
    ['league' => $league] = makeTimerLeague(pickTimerSeconds: 60, teamCount: 2);

    Carbon::setTestNow(Carbon::parse('2026-05-19 12:00:00', 'UTC'));
    (new CreateEditDraftAction)(['league_id' => $league->id, 'command' => 'create']);
    (new CreateEditDraftOrderAction)(['league_id' => $league->id]);

    $skipAction = app(SkipCurrentTurnAction::class);

    // Skip everyone in round 1 (two teams, two skips = round complete)
    $skipAction(['league_id' => $league->id]);
    $skipAction(['league_id' => $league->id]);

    // Round 2 begins with the same teams; skip them too — zero picks in round 2.
    $skipAction(['league_id' => $league->id]);
    $skipAction(['league_id' => $league->id]);

    $draft = Draft::query()->where('league_id', $league->id)->first();
    expect((int) $draft->status)->toBe(0);
    expect($draft->current_deadline_at)->toBeNull();

    Carbon::setTestNow();
});
