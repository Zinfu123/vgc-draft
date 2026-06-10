<?php

namespace App\Enums\Playoffs;

enum PlayoffStatus: int
{
    case Draft = 0;
    case Active = 1;
    case Completed = 2;
}
