<?php

namespace App\Enums;

enum ExpenseCategory: string
{
    use EnumHelpers;

    case Fuel = 'fuel';
    case Maintenance = 'maintenance';
    case Repairs = 'repairs';
    case Tolls = 'tolls';
    case Parking = 'parking';
    case Insurance = 'insurance';
    case VehicleRegistration = 'vehicle_registration';
    case DriverExpenses = 'driver_expenses';
    case Other = 'other';
}
