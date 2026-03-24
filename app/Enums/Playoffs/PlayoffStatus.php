<?php

namespace App\Enums\Playoffs;

enum PlayoffStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Completed = 'completed';
}
