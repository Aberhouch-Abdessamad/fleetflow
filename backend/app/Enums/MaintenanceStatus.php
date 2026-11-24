<?php

namespace App\Enums;

enum MaintenanceStatus: string
{
    use EnumHelpers;

    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
