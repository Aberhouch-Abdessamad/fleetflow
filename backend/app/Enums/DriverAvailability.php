<?php

namespace App\Enums;

enum DriverAvailability: string
{
    use EnumHelpers;

    case Available = 'available';
    case OnDuty = 'on_duty';
    case OffDuty = 'off_duty';
    case OnLeave = 'on_leave';
}
