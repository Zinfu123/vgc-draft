<?php

namespace App\Modules\Draft\Actions;

use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\Draft\Models\DraftReminder;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class DraftTimerAction
{
    public const COMMAND_START_TURN = 'start_turn';

    public const COMMAND_PAUSE = 'pause';

    public const COMMAND_RESUME = 'resume';

    public const COMMAND_ADJUST = 'adjust';

    public const COMMAND_SHIELD_QUIET_HOURS = 'shield_during_quiet_hours';

    public const COMMAND_CLEAR = 'clear';

    /**
     * Discord reminder thresholds in seconds. Reminders are only scheduled for thresholds
     * strictly smaller than the configured pick_timer_seconds, so a 20-minute timer
     * never schedules a "30 minutes left" reminder.
     *
     * @var array<int, int>
     */
    public const REMINDER_THRESHOLDS = [1800, 600, 300];

    /**
     * @param  array{league_id:int, command:string, delta_seconds?:int}  $data
     */
    public function __invoke(array $data): void
    {
        $leagueId = (int) $data['league_id'];
        $command = $data['command'];

        $draft = Draft::query()->where('league_id', $leagueId)->first();
        if ($draft === null) {
            return;
        }

        $config = DraftConfig::query()->where('league_id', $leagueId)->first();

        match ($command) {
            self::COMMAND_START_TURN => $this->startTurn($draft, $config),
            self::COMMAND_PAUSE => $this->pause($draft),
            self::COMMAND_RESUME => $this->resume($draft, $config),
            self::COMMAND_ADJUST => $this->adjust($draft, $config, (int) ($data['delta_seconds'] ?? 0)),
            self::COMMAND_SHIELD_QUIET_HOURS => $this->shieldDuringQuietHours($draft, $config),
            self::COMMAND_CLEAR => $this->clear($draft),
            default => null,
        };
    }

    /**
     * Determine whether the configured quiet-hours window is active at the given time.
     */
    public function isInQuietHours(?DraftConfig $config, ?CarbonImmutable $atUtc = null): bool
    {
        if ($config === null || ! $config->quiet_hours_enabled) {
            return false;
        }
        if ($config->quiet_hours_start === null || $config->quiet_hours_end === null) {
            return false;
        }

        $timezone = $config->quiet_hours_timezone ?: config('app.timezone');
        $now = ($atUtc ?? CarbonImmutable::now())->setTimezone($timezone);

        $start = $this->minutesFromTimeString($config->quiet_hours_start);
        $end = $this->minutesFromTimeString($config->quiet_hours_end);
        if ($start === null || $end === null) {
            return false;
        }

        $current = $now->hour * 60 + $now->minute;

        if ($start === $end) {
            return false;
        }

        if ($start < $end) {
            return $current >= $start && $current < $end;
        }

        return $current >= $start || $current < $end;
    }

    private function startTurn(Draft $draft, ?DraftConfig $config): void
    {
        $draft->paused_at = null;
        $draft->paused_remaining_seconds = null;

        if ($config === null || ! $config->pick_timer_enabled || ! $config->pick_timer_seconds) {
            $draft->current_deadline_at = null;
            $draft->save();
            $this->cancelPendingReminders($draft);

            return;
        }

        $seconds = (int) $config->pick_timer_seconds;
        $draft->current_deadline_at = Carbon::now()->addSeconds($seconds);
        $draft->save();

        $this->cancelPendingReminders($draft);
        $this->scheduleReminders($draft, $config);
    }

    private function pause(Draft $draft): void
    {
        if ($draft->paused_at !== null) {
            return;
        }

        $remaining = 0;
        if ($draft->current_deadline_at !== null) {
            $remaining = max(0, Carbon::now()->diffInSeconds($draft->current_deadline_at, false));
        }

        $draft->paused_at = Carbon::now();
        $draft->paused_remaining_seconds = (int) $remaining;
        $draft->save();

        $this->cancelPendingReminders($draft);
    }

    private function resume(Draft $draft, ?DraftConfig $config): void
    {
        if ($draft->paused_at === null) {
            return;
        }

        $remaining = (int) ($draft->paused_remaining_seconds ?? 0);

        if ($config === null || ! $config->pick_timer_enabled) {
            $draft->current_deadline_at = null;
        } else {
            $draft->current_deadline_at = Carbon::now()->addSeconds(max(0, $remaining));
        }

        $draft->paused_at = null;
        $draft->paused_remaining_seconds = null;
        $draft->save();

        if ($config !== null && $config->pick_timer_enabled && $draft->current_deadline_at !== null) {
            $this->scheduleReminders($draft, $config);
        }
    }

    private function adjust(Draft $draft, ?DraftConfig $config, int $deltaSeconds): void
    {
        if ($deltaSeconds === 0) {
            return;
        }

        if ($draft->paused_at !== null) {
            $remaining = (int) ($draft->paused_remaining_seconds ?? 0) + $deltaSeconds;
            $draft->paused_remaining_seconds = max(0, $remaining);
            $draft->save();

            return;
        }

        if ($draft->current_deadline_at === null) {
            return;
        }

        $draft->current_deadline_at = $draft->current_deadline_at->copy()->addSeconds($deltaSeconds);
        $draft->save();

        $this->cancelPendingReminders($draft);
        if ($config !== null && $config->pick_timer_enabled) {
            $this->scheduleReminders($draft, $config);
        }
    }

    /**
     * Advance the deadline (and any unsent reminders) by 60 seconds so the clock effectively
     * pauses while quiet hours are active.
     */
    private function shieldDuringQuietHours(Draft $draft, ?DraftConfig $config): void
    {
        if ($config === null || ! $config->pick_timer_enabled) {
            return;
        }
        if ($draft->paused_at !== null) {
            return;
        }
        if ($draft->current_deadline_at === null) {
            return;
        }

        $advanced = $draft->current_deadline_at->copy()->addSeconds(60);
        $floor = Carbon::now()->addSeconds(60);

        $draft->current_deadline_at = $advanced->lt($floor) ? $floor : $advanced;
        $draft->save();

        DraftReminder::query()
            ->where('draft_id', $draft->id)
            ->whereNull('sent_at')
            ->whereNull('cancelled_at')
            ->get()
            ->each(function (DraftReminder $reminder): void {
                $reminder->fire_at = $reminder->fire_at->copy()->addSeconds(60);
                $reminder->save();
            });
    }

    private function clear(Draft $draft): void
    {
        $draft->current_deadline_at = null;
        $draft->paused_at = null;
        $draft->paused_remaining_seconds = null;
        $draft->save();

        $this->cancelPendingReminders($draft);
    }

    private function cancelPendingReminders(Draft $draft): void
    {
        DraftReminder::query()
            ->where('draft_id', $draft->id)
            ->whereNull('sent_at')
            ->whereNull('cancelled_at')
            ->update(['cancelled_at' => Carbon::now()]);
    }

    private function scheduleReminders(Draft $draft, DraftConfig $config): void
    {
        if ($draft->current_deadline_at === null) {
            return;
        }

        $pickTimerSeconds = (int) ($config->pick_timer_seconds ?? 0);
        if ($pickTimerSeconds <= 0) {
            return;
        }

        $deadline = $draft->current_deadline_at->copy();
        $now = Carbon::now();

        foreach (self::REMINDER_THRESHOLDS as $threshold) {
            if ($threshold >= $pickTimerSeconds) {
                continue;
            }

            $fireAt = $deadline->copy()->subSeconds($threshold);
            if ($fireAt->lte($now)) {
                continue;
            }

            DraftReminder::create([
                'draft_id' => $draft->id,
                'league_id' => $draft->league_id,
                'threshold_seconds' => $threshold,
                'fire_at' => $fireAt,
            ]);
        }
    }

    private function minutesFromTimeString(mixed $value): ?int
    {
        if ($value instanceof \DateTimeInterface) {
            return ((int) $value->format('H')) * 60 + ((int) $value->format('i'));
        }

        if (! is_string($value)) {
            return null;
        }

        if (! preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $value, $matches)) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return null;
        }

        return $hour * 60 + $minute;
    }
}
