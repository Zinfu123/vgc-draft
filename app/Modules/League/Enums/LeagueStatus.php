<?php

namespace App\Modules\League\Enums;

enum LeagueStatus: int
{
    case Cancelled = 0;
    case Completed = 1;
    case Registration = 2;
    case Staging = 3;
    case RegularSeason = 4;
    case Playoffs = 5;

    public function label(): string
    {
        return match ($this) {
            self::Cancelled => 'Cancelled',
            self::Completed => 'Completed',
            self::Registration => 'Registration',
            self::Staging => 'Staging',
            self::RegularSeason => 'Regular Season',
            self::Playoffs => 'Playoffs',
        };
    }

    /**
     * Returns true for statuses that represent a league in progress.
     */
    public function isActive(): bool
    {
        return in_array($this, [
            self::Registration,
            self::Staging,
            self::RegularSeason,
            self::Playoffs,
        ], true);
    }

    /**
     * Returns false for cancelled leagues which are hidden from public listings.
     */
    public function isVisible(): bool
    {
        return $this !== self::Cancelled;
    }

    /**
     * Returns true if the league is in a completed/past state.
     */
    public function isPast(): bool
    {
        return $this === self::Completed;
    }
}
