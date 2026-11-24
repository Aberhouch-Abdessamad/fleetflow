<?php

namespace App\Enums;

enum DriverStatus: string
{
    use EnumHelpers;

    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
}
