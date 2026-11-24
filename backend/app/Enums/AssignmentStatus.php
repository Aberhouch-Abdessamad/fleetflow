<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Superseded = 'superseded';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}
