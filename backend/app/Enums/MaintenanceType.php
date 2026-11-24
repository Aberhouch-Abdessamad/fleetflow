<?php

namespace App\Enums;

enum MaintenanceType: string
{
    use EnumHelpers;

    case RegularService = 'regular_service';
    case OilChange = 'oil_change';
    case TireReplacement = 'tire_replacement';
    case BrakeInspection = 'brake_inspection';
    case EngineRepair = 'engine_repair';
    case ElectricalRepair = 'electrical_repair';
    case GeneralInspection = 'general_inspection';
    case Other = 'other';
}
