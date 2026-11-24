<?php

namespace App\Enums;

enum RouteStatus: string
{
    use EnumHelpers;

    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
