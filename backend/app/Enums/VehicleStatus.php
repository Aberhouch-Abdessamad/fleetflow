<?php

namespace App\Enums;

enum VehicleStatus: string
{
    use EnumHelpers;

    case Available = 'available';
    case Assigned = 'assigned';
    case OnDelivery = 'on_delivery';
    case UnderMaintenance = 'under_maintenance';
    case OutOfService = 'out_of_service';
    case Inactive = 'inactive';
}
