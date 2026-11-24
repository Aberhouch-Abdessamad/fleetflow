<?php

namespace App\Enums;

/**
 * Company-scoped roles. Stored in Spatie's roles table with company_id = NULL
 * (shared role definitions) and assigned per company through model_has_roles.company_id.
 */
enum CompanyRole: string
{
    case CompanyAdmin = 'company_admin';
    case FleetManager = 'fleet_manager';
    case Dispatcher = 'dispatcher';
    case Driver = 'driver';
    case Accountant = 'accountant';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::CompanyAdmin => 'Company Admin',
            self::FleetManager => 'Fleet Manager',
            self::Dispatcher => 'Dispatcher',
            self::Driver => 'Driver',
            self::Accountant => 'Accountant',
            self::Customer => 'Customer',
        };
    }
}
