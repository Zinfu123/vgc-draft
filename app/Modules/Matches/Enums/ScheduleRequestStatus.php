<?php

namespace App\Modules\Matches\Enums;

enum ScheduleRequestStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Reschedule = 'reschedule';
}
