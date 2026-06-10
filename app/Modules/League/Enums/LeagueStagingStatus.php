<?php

namespace App\Modules\League\Enums;

enum LeagueStagingStatus: int
{
    case PreDraft = 0;
    case DraftInProgress = 1;
    case FreeTradeWindow = 2;

    public function label(): string
    {
        return match ($this) {
            self::PreDraft => 'Pre-Draft',
            self::DraftInProgress => 'Draft In Progress',
            self::FreeTradeWindow => 'Free Trade Window',
        };
    }
}
