<?php

namespace App\Enums\Playoffs;

enum PlayoffFormat: string
{
    case SingleElimination = 'single_elimination';
    case DoubleElimination = 'double_elimination';
}
